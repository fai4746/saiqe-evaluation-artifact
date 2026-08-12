<?php
class RiskScoreModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getByApplication(int $appId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM risk_scores WHERE application_id=?');
        $stmt->execute([$appId]);
        return $stmt->fetch() ?: null;
    }

    public function getByCase(int $caseId): ?array {
        $stmt = $this->db->prepare(
            'SELECT rs.* FROM risk_scores rs
             JOIN cases c ON c.application_id = rs.application_id
             WHERE c.case_id = ?'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetch() ?: null;
    }

    public function getDistribution(): array {
        $stmt = $this->db->query(
            'SELECT p.program_name, rs.risk_level, COUNT(*) AS cnt
             FROM risk_scores rs
             JOIN applications a ON rs.application_id = a.application_id
             JOIN programs p ON a.program_id = p.program_id
             GROUP BY p.program_name, rs.risk_level
             ORDER BY p.program_name, FIELD(rs.risk_level,"low","medium","high","critical")'
        );
        return $stmt->fetchAll();
    }

    public function getMonthlyDistribution(): array {
        $stmt = $this->db->query(
            "SELECT DATE_FORMAT(rs.computed_at,'%Y-%m') AS month, rs.risk_level, COUNT(*) AS cnt
             FROM risk_scores rs
             WHERE rs.computed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month, rs.risk_level
             ORDER BY month"
        );
        return $stmt->fetchAll();
    }
}
