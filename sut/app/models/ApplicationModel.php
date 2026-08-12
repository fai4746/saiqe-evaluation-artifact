<?php
class ApplicationModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function generateNumber(): string {
        $year = date('Y');
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE YEAR(submitted_at) = ?");
        $stmt->execute([$year]);
        $count = (int)$stmt->fetchColumn() + 1;
        return 'APP-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    public function countActive(int $citizenId): int {
        $placeholders = implode(',', array_fill(0, count(ACTIVE_STATUSES), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE citizen_id = ? AND status IN ($placeholders)");
        $stmt->execute(array_merge([$citizenId], ACTIVE_STATUSES));
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO applications (application_number, citizen_id, program_id, income_range, household_size, reason_for_request, urgency_level, preferred_contact, ip_address) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['application_number'],
            $data['citizen_id'],
            $data['program_id'],
            $data['income_range'],
            $data['household_size'],
            $data['reason_for_request'],
            $data['urgency_level'],
            $data['preferred_contact'],
            $data['ip_address'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Create a Draft application for the wizard. */
    public function createDraft(int $citizenId): int {
        $number = $this->generateNumber();
        $stmt = $this->db->prepare(
            "INSERT INTO applications
             (application_number, citizen_id, program_id, income_range, household_size,
              reason_for_request, urgency_level, preferred_contact, status, wizard_started_at)
             VALUES (?,?,NULL,NULL,NULL,NULL,'normal','email','Draft',NOW())"
        );
        $stmt->execute([$number, $citizenId]);
        return (int)$this->db->lastInsertId();
    }

    public function saveWizardStep(int $appId, int $step, array $stepData): void {
        // Load existing data, merge, save
        $stmt = $this->db->prepare('SELECT application_data_json FROM applications WHERE application_id=?');
        $stmt->execute([$appId]);
        $existing = json_decode((string)$stmt->fetchColumn(), true) ?? [];
        $existing["step$step"] = $stepData;
        $this->db->prepare(
            'UPDATE applications SET application_data_json=?, wizard_step_completed=? WHERE application_id=?'
        )->execute([json_encode($existing), $step, $appId]);
    }

    public function getWizardData(int $appId): array {
        $stmt = $this->db->prepare('SELECT application_data_json, wizard_step_completed FROM applications WHERE application_id=?');
        $stmt->execute([$appId]);
        $row = $stmt->fetch();
        return [
            'data' => json_decode($row['application_data_json'] ?? '{}', true) ?? [],
            'step' => (int)($row['wizard_step_completed'] ?? 0),
        ];
    }

    public function submitWizard(int $appId, array $allData, int $citizenId): void {
        $s1 = $allData['step1'] ?? [];
        $s2 = $allData['step2'] ?? [];
        $s3 = $allData['step3'] ?? [];
        $s4 = $allData['step4'] ?? [];

        $incomeRange = $this->mapIncomeRange((float)($s3['annual_income'] ?? 0));

        $this->db->prepare(
            'UPDATE applications SET
                status="Submitted", program_id=?, income_range=?, household_size=?,
                reason_for_request=?, urgency_level=?, preferred_contact=?,
                annual_income=?, employment_status=?, monthly_expenses=?,
                housing_cost_monthly=?, num_dependents=?, marital_status=?,
                veteran_status=?, disability_status=?,
                certification_checked=1, certification_timestamp=NOW(),
                submitted_at=NOW()
             WHERE application_id=?'
        )->execute([
            $s4['program_id'] ?? 0,
            $incomeRange,
            $s2['household_size'] ?? 1,
            $s4['reason_for_request'] ?? '',
            $s4['urgency_level'] ?? 'normal',
            $s4['preferred_contact'] ?? 'email',
            $s3['annual_income'] ?? null,
            $s3['employment_status'] ?? null,
            $s2['monthly_expenses'] ?? null,     // housing/expense fields collected in step 2
            $s2['housing_cost_monthly'] ?? null,
            $s2['num_dependents'] ?? 0,
            $s2['marital_status'] ?? null,
            $s2['veteran_status'] ?? null,
            $s2['disability_status'] ?? null,
            $appId,
        ]);
    }

    private function mapIncomeRange(float $income): string {
        if ($income <= 15000)  return '$0-$15,000';
        if ($income <= 30000)  return '$15,001-$30,000';
        if ($income <= 45000)  return '$30,001-$45,000';
        if ($income <= 60000)  return '$45,001-$60,000';
        return '$60,001+';
    }

    public function findDraftByCitizen(int $citizenId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM applications WHERE citizen_id=? AND status='Draft' ORDER BY wizard_started_at DESC LIMIT 1"
        );
        $stmt->execute([$citizenId]);
        return $stmt->fetch() ?: null;
    }

    public function updatePreliminaryEligibility(int $appId, string $result): void {
        $this->db->prepare(
            'UPDATE applications SET preliminary_eligibility_result=? WHERE application_id=?'
        )->execute([$result, $appId]);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT a.*, p.program_name, c.citizen_id, u.first_name, u.last_name, u.email FROM applications a JOIN programs p ON a.program_id = p.program_id JOIN citizens c ON a.citizen_id = c.citizen_id JOIN users u ON c.user_id = u.user_id WHERE a.application_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByNumber(string $number): ?array {
        $stmt = $this->db->prepare('SELECT * FROM applications WHERE application_number = ?');
        $stmt->execute([$number]);
        return $stmt->fetch() ?: null;
    }

    public function getByCitizen(int $citizenId): array {
        $stmt = $this->db->prepare('SELECT a.*, p.program_name FROM applications a JOIN programs p ON a.program_id = p.program_id WHERE a.citizen_id = ? ORDER BY a.submitted_at DESC');
        $stmt->execute([$citizenId]);
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = []): array {
        $sql = 'SELECT a.*, p.program_name, u.first_name, u.last_name FROM applications a JOIN programs p ON a.program_id = p.program_id JOIN citizens c ON a.citizen_id = c.citizen_id JOIN users u ON c.user_id = u.user_id WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) { $sql .= ' AND a.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['program_id'])) { $sql .= ' AND a.program_id = ?'; $params[] = $filters['program_id']; }
        if (!empty($filters['urgency'])) { $sql .= ' AND a.urgency_level = ?'; $params[] = $filters['urgency']; }
        if (!empty($filters['date_from'])) { $sql .= ' AND DATE(a.submitted_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $sql .= ' AND DATE(a.submitted_at) <= ?'; $params[] = $filters['date_to']; }
        $sql .= ' ORDER BY a.submitted_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStatusCounts(): array {
        $stmt = $this->db->query('SELECT status, COUNT(*) AS cnt FROM applications GROUP BY status');
        $result = [];
        foreach ($stmt->fetchAll() as $row) $result[$row['status']] = $row['cnt'];
        return $result;
    }

    public function getByProgram(): array {
        $stmt = $this->db->query('SELECT p.program_name, COUNT(*) AS cnt FROM applications a JOIN programs p ON a.program_id = p.program_id GROUP BY a.program_id ORDER BY cnt DESC');
        return $stmt->fetchAll();
    }

    public function getByDateRange(string $from, string $to): array {
        $stmt = $this->db->prepare('SELECT a.*, p.program_name, u.first_name, u.last_name FROM applications a JOIN programs p ON a.program_id = p.program_id JOIN citizens c ON a.citizen_id = c.citizen_id JOIN users u ON c.user_id = u.user_id WHERE DATE(a.submitted_at) BETWEEN ? AND ? ORDER BY a.submitted_at DESC');
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }
}
