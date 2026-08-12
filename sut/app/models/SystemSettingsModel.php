<?php
class SystemSettingsModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function get(string $key, string $default = ''): string {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    public function set(string $key, string $value): void {
        $this->db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()'
        )->execute([$key, $value, $value]);
    }

    public function getAll(): array {
        return $this->db->query('SELECT * FROM system_settings ORDER BY setting_key')->fetchAll();
    }
}
