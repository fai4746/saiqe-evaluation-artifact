<?php
class CaseModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function createFromApplication(int $applicationId): int {
        $stmt = $this->db->prepare('INSERT INTO cases (application_id) VALUES (?)');
        $stmt->execute([$applicationId]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $caseId): ?array {
        $stmt = $this->db->prepare(
            'SELECT c.*, a.application_number, a.income_range, a.household_size, a.reason_for_request, a.urgency_level, a.submitted_at, a.program_id, a.ip_address,
                    p.program_name, u.first_name AS citizen_first, u.last_name AS citizen_last, u.email AS citizen_email, cit.phone, cit.citizen_id,
                    o.first_name AS officer_first, o.last_name AS officer_last
             FROM cases c
             JOIN applications a ON c.application_id = a.application_id
             JOIN programs p ON a.program_id = p.program_id
             JOIN citizens cit ON a.citizen_id = cit.citizen_id
             JOIN users u ON cit.user_id = u.user_id
             LEFT JOIN users o ON c.assigned_officer_id = o.user_id
             WHERE c.case_id = ?'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetch() ?: null;
    }

    public function findByApplicationId(int $appId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM cases WHERE application_id = ?');
        $stmt->execute([$appId]);
        return $stmt->fetch() ?: null;
    }

    public function getByOfficer(int $officerId, array $filters = []): array {
        $sql = 'SELECT c.*, a.application_number, a.urgency_level, a.submitted_at, p.program_name, u.first_name, u.last_name FROM cases c JOIN applications a ON c.application_id = a.application_id JOIN programs p ON a.program_id = p.program_id JOIN citizens cit ON a.citizen_id = cit.citizen_id JOIN users u ON cit.user_id = u.user_id WHERE c.assigned_officer_id = ?';
        $params = [$officerId];
        if (!empty($filters['status'])) { $sql .= ' AND c.status = ?'; $params[] = $filters['status']; }
        $sql .= ' ORDER BY a.submitted_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getForSupervisor(): array {
        $stmt = $this->db->query("SELECT c.*, a.application_number, a.urgency_level, a.submitted_at, p.program_name, u.first_name, u.last_name, o.first_name AS officer_first, o.last_name AS officer_last FROM cases c JOIN applications a ON c.application_id = a.application_id JOIN programs p ON a.program_id = p.program_id JOIN citizens cit ON a.citizen_id = cit.citizen_id JOIN users u ON cit.user_id = u.user_id LEFT JOIN users o ON c.assigned_officer_id = o.user_id WHERE c.status = 'Supervisor Review' ORDER BY a.submitted_at ASC");
        return $stmt->fetchAll();
    }

    public function getAll(array $filters = []): array {
        $sql = 'SELECT c.*, a.application_number, a.urgency_level, p.program_name, u.first_name, u.last_name, o.first_name AS officer_first, o.last_name AS officer_last FROM cases c JOIN applications a ON c.application_id = a.application_id JOIN programs p ON a.program_id = p.program_id JOIN citizens cit ON a.citizen_id = cit.citizen_id JOIN users u ON cit.user_id = u.user_id LEFT JOIN users o ON c.assigned_officer_id = o.user_id WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) { $sql .= ' AND c.status = ?'; $params[] = $filters['status']; }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Validate transition against VALID_TRANSITIONS map. */
    public function isValidTransition(string $from, string $to): bool {
        $allowed = VALID_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed);
    }

    public function updateStatus(int $caseId, string $status, int $userId, string $comments = '', string $reason = ''): bool {
        $old = $this->findById($caseId);
        if (!$old) return false;
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE cases SET status=?, updated_at=NOW() WHERE case_id=?')
                     ->execute([$status, $caseId]);
            $this->db->prepare('UPDATE applications SET status=? WHERE application_id=?')
                     ->execute([$status, $old['application_id']]);
            $this->db->prepare(
                'INSERT INTO case_status_history (case_id, old_status, new_status, changed_by_user_id, comments) VALUES (?,?,?,?,?)'
            )->execute([$caseId, $old['status'], $status, $userId, $comments]);
            $this->db->commit();
            // Start SLA tracking for the new status
            try {
                (new SLAModel())->startTracking($caseId, $status, date('Y-m-d H:i:s'));
            } catch (Exception $e) {}
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function assign(int $caseId, int $officerId): bool {
        $stmt = $this->db->prepare('UPDATE cases SET assigned_officer_id = ?, updated_at = NOW() WHERE case_id = ?');
        return $stmt->execute([$officerId, $caseId]);
    }

    public function makeDecision(int $caseId, string $status, int $supervisorId, string $comments): bool {
        $old = $this->findById($caseId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('UPDATE cases SET status = ?, decision_comments = ?, decided_by = ?, decided_at = NOW(), updated_at = NOW() WHERE case_id = ?');
            $stmt->execute([$status, $comments, $supervisorId, $caseId]);
            $hist = $this->db->prepare('INSERT INTO case_status_history (case_id, old_status, new_status, changed_by_user_id, comments) VALUES (?,?,?,?,?)');
            $hist->execute([$caseId, $old['status'], $status, $supervisorId, $comments]);
            // Update application status too
            $appStmt = $this->db->prepare('UPDATE applications SET status = ? WHERE application_id = ?');
            $appStmt->execute([$status, $old['application_id']]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getStatusHistory(int $caseId): array {
        $stmt = $this->db->prepare('SELECT h.*, u.first_name, u.last_name, r.role_name FROM case_status_history h JOIN users u ON h.changed_by_user_id = u.user_id JOIN roles r ON u.role_id = r.role_id WHERE h.case_id = ? ORDER BY h.changed_at ASC');
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public function addNote(int $caseId, int $officerId, string $text): int {
        $stmt = $this->db->prepare('INSERT INTO case_notes (case_id, officer_user_id, note_text) VALUES (?,?,?)');
        $stmt->execute([$caseId, $officerId, $text]);
        return (int)$this->db->lastInsertId();
    }

    public function getNotes(int $caseId): array {
        $stmt = $this->db->prepare('SELECT n.*, u.first_name, u.last_name FROM case_notes n JOIN users u ON n.officer_user_id = u.user_id WHERE n.case_id = ? ORDER BY n.created_at ASC');
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    public function getOfficerPerformance(): array {
        $stmt = $this->db->query("SELECT u.user_id, u.first_name, u.last_name, COUNT(c.case_id) AS total_cases, SUM(c.status IN ('Approved','Denied')) AS resolved, AVG(TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at)) AS avg_hours FROM users u LEFT JOIN cases c ON c.assigned_officer_id = u.user_id WHERE u.role_id = " . ROLE_OFFICER . " GROUP BY u.user_id ORDER BY total_cases DESC");
        return $stmt->fetchAll();
    }

    public function getSupervisorSummary(): array {
        $stmt = $this->db->query("SELECT u.user_id, u.first_name, u.last_name, SUM(c.status='Approved') AS approved, SUM(c.status='Denied') AS denied FROM users u LEFT JOIN cases c ON c.decided_by = u.user_id AND MONTH(c.decided_at) = MONTH(NOW()) WHERE u.role_id = " . ROLE_SUPERVISOR . " GROUP BY u.user_id");
        return $stmt->fetchAll();
    }

    public function getOverdueCount(int $officerId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM cases c JOIN applications a ON c.application_id = a.application_id WHERE c.assigned_officer_id = ? AND c.status NOT IN ('Approved','Denied') AND TIMESTAMPDIFF(DAY, a.submitted_at, NOW()) > 10");
        $stmt->execute([$officerId]);
        return (int)$stmt->fetchColumn();
    }
}
