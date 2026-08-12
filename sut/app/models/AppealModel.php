<?php
class AppealModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appeals (application_id, citizen_id, appeal_reason, appeal_window_expires_at)
             VALUES (?,?,?,?)'
        );
        $stmt->execute([
            $data['application_id'],
            $data['citizen_id'],
            $data['appeal_reason'],
            $data['appeal_window_expires_at'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT ap.*, a.application_number, a.status AS app_status, p.program_name,
                    u.first_name AS citizen_first, u.last_name AS citizen_last
             FROM appeals ap
             JOIN applications a ON ap.application_id = a.application_id
             JOIN programs p     ON a.program_id       = p.program_id
             JOIN citizens c     ON ap.citizen_id      = c.citizen_id
             JOIN users u        ON c.user_id           = u.user_id
             WHERE ap.appeal_id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByApplication(int $appId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM appeals WHERE application_id=? ORDER BY submitted_at DESC LIMIT 1');
        $stmt->execute([$appId]);
        return $stmt->fetch() ?: null;
    }

    public function getForSupervisor(): array {
        $stmt = $this->db->query(
            "SELECT ap.*, a.application_number, p.program_name,
                    u.first_name AS citizen_first, u.last_name AS citizen_last,
                    o.first_name AS officer_first, o.last_name AS officer_last
             FROM appeals ap
             JOIN applications a ON ap.application_id = a.application_id
             JOIN programs p     ON a.program_id       = p.program_id
             JOIN citizens c     ON ap.citizen_id      = c.citizen_id
             JOIN users u        ON c.user_id           = u.user_id
             LEFT JOIN cases cs  ON cs.application_id  = a.application_id
             LEFT JOIN users o   ON cs.assigned_officer_id = o.user_id
             WHERE ap.decision = 'pending'
             ORDER BY ap.submitted_at ASC"
        );
        return $stmt->fetchAll();
    }

    public function decide(int $appealId, string $decision, string $notes, int $decidedBy): void {
        $this->db->prepare(
            'UPDATE appeals SET decision=?, decision_notes=?, decided_by_user_id=?, decided_at=NOW()
             WHERE appeal_id=?'
        )->execute([$decision, $notes, $decidedBy, $appealId]);
    }

    public function addDocument(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appeal_documents (appeal_id, original_filename, stored_filename, file_type, file_size_bytes, uploaded_by_user_id)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['appeal_id'],
            $data['original_filename'],
            $data['stored_filename'],
            $data['file_type'],
            $data['file_size_bytes'],
            $data['uploaded_by_user_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getDocuments(int $appealId): array {
        $stmt = $this->db->prepare('SELECT * FROM appeal_documents WHERE appeal_id=?');
        $stmt->execute([$appealId]);
        return $stmt->fetchAll();
    }

    public function findDocumentById(int $docId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM appeal_documents WHERE doc_id=?');
        $stmt->execute([$docId]);
        return $stmt->fetch() ?: null;
    }

    public function isWithinAppealWindow(int $appId): bool {
        $stmt = $this->db->prepare(
            "SELECT csh.changed_at FROM case_status_history csh
             JOIN cases cs ON csh.case_id = cs.case_id
             WHERE cs.application_id=? AND csh.new_status='Denied'
             ORDER BY csh.changed_at DESC LIMIT 1"
        );
        $stmt->execute([$appId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $thirtyDaysLater = (new DateTime($row['changed_at']))->modify('+30 days');
        return new DateTime() <= $thirtyDaysLater;
    }

    public function getDeniedAt(int $appId): ?DateTime {
        $stmt = $this->db->prepare(
            "SELECT csh.changed_at FROM case_status_history csh
             JOIN cases cs ON csh.case_id = cs.case_id
             WHERE cs.application_id=? AND csh.new_status='Denied'
             ORDER BY csh.changed_at DESC LIMIT 1"
        );
        $stmt->execute([$appId]);
        $row = $stmt->fetch();
        return $row ? new DateTime($row['changed_at']) : null;
    }
}
