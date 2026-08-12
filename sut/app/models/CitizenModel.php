<?php
class CitizenModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByUserId(int $userId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM citizens WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $citizenId): ?array {
        $stmt = $this->db->prepare('SELECT c.*, u.first_name, u.last_name, u.email, u.status FROM citizens c JOIN users u ON c.user_id = u.user_id WHERE c.citizen_id = ?');
        $stmt->execute([$citizenId]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $userId, array $data): int {
        $stmt = $this->db->prepare('INSERT INTO citizens (user_id, date_of_birth, phone, address_street, address_city, address_state, address_zip, ssn_last4) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$userId, $data['dob'], $data['phone'], $data['street'], $data['city'], $data['state'], $data['zip'], $data['ssn_last4']]);
        return (int)$this->db->lastInsertId();
    }

    public function search(string $term): array {
        $like = '%' . $term . '%';
        $stmt = $this->db->prepare('SELECT c.*, u.first_name, u.last_name, u.email, u.status FROM citizens c JOIN users u ON c.user_id = u.user_id WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR c.ssn_last4 = ? ORDER BY u.last_name');
        $stmt->execute([$like, $like, $like, $term]);
        return $stmt->fetchAll();
    }

    public function getAll(): array {
        $stmt = $this->db->query('SELECT c.*, u.first_name, u.last_name, u.email, u.status FROM citizens c JOIN users u ON c.user_id = u.user_id ORDER BY u.last_name');
        return $stmt->fetchAll();
    }
}
