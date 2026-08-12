<?php
class QualityReviewModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function logAction(int $caseId, int $reviewerUserId, string $actionType, string $notes = ''): int {
        $stmt = $this->db->prepare(
            'INSERT INTO quality_review_actions (case_id, reviewer_user_id, action_type, notes)
             VALUES (?,?,?,?)'
        );
        $stmt->execute([$caseId, $reviewerUserId, $actionType, $notes]);
        return (int)$this->db->lastInsertId();
    }

    public function getForCase(int $caseId): array {
        $stmt = $this->db->prepare(
            'SELECT q.*, u.first_name, u.last_name FROM quality_review_actions q
             JOIN users u ON q.reviewer_user_id = u.user_id
             WHERE q.case_id = ? ORDER BY q.action_timestamp DESC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    /** Returns the queue for the QR dashboard view.
     *  Columns: case_id, case_number, application_number, applicant_name,
     *           risk_level, sla_status, status_entered_at */
    public function getQueue(): array {
        $stmt = $this->db->query(
            "SELECT
                c.case_id,
                CONCAT('CASE-', LPAD(c.case_id, 5, '0'))              AS case_number,
                a.application_number,
                CONCAT(u.first_name, ' ', u.last_name)                AS applicant_name,
                COALESCE(rs.risk_level, 'unknown')                    AS risk_level,
                CASE
                    WHEN st.is_breached = 1                           THEN 'breached'
                    WHEN st.warning_date IS NOT NULL
                         AND NOW() >= st.warning_date                 THEN 'warning'
                    ELSE 'on_track'
                END                                                   AS sla_status,
                sh.changed_at                                         AS status_entered_at
             FROM cases c
             JOIN applications a   ON c.application_id     = a.application_id
             JOIN programs p       ON a.program_id          = p.program_id
             JOIN citizens cit     ON a.citizen_id          = cit.citizen_id
             JOIN users u          ON cit.user_id           = u.user_id
             LEFT JOIN risk_scores rs  ON rs.application_id = a.application_id
             LEFT JOIN sla_tracking st ON st.case_id = c.case_id
                                        AND st.status_name = 'Quality Review'
             LEFT JOIN (
                 SELECT case_id, MAX(history_id) AS max_id
                 FROM case_status_history
                 GROUP BY case_id
             ) lh ON lh.case_id = c.case_id
             LEFT JOIN case_status_history sh ON sh.history_id = lh.max_id
             WHERE c.status = 'Quality Review'
             ORDER BY a.urgency_level DESC, a.submitted_at ASC"
        );
        return $stmt->fetchAll();
    }

    /** Returns stats array with keys matching quality_reviewer/dashboard.php view:
     *  pending, sla_warning, approved_today, total_reviewed */
    public function getDashboardStats(): array {
        $today = date('Y-m-d');

        $pending = (int)$this->db->query(
            "SELECT COUNT(*) FROM cases WHERE status='Quality Review'"
        )->fetchColumn();

        $slaWarning = (int)$this->db->query(
            "SELECT COUNT(*) FROM sla_tracking st
             JOIN cases c ON c.case_id = st.case_id
             WHERE c.status = 'Quality Review'
               AND st.is_breached = 0
               AND NOW() >= st.warning_date"
        )->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM quality_review_actions
             WHERE action_type = 'passed_to_supervisor' AND DATE(action_timestamp) = ?"
        );
        $stmt->execute([$today]);
        $approvedToday = (int)$stmt->fetchColumn();

        $totalReviewed = (int)$this->db->query(
            "SELECT COUNT(*) FROM quality_review_actions WHERE action_type = 'passed_to_supervisor'"
        )->fetchColumn();

        return [
            'pending'        => $pending,
            'sla_warning'    => $slaWarning,
            'approved_today' => $approvedToday,
            'total_reviewed' => $totalReviewed,
        ];
    }
}
