<?php
class ProgramModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(bool $activeOnly = true): array {
        $stmt = $activeOnly
            ? $this->db->query('SELECT * FROM programs WHERE is_active = 1 ORDER BY program_name')
            : $this->db->query('SELECT * FROM programs ORDER BY program_name');
        return $stmt->fetchAll();
    }
}
