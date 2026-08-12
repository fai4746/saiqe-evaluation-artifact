<?php
class UserModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(?int $roleId = null): array {
        if ($roleId) {
            $stmt = $this->db->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE u.role_id = ? ORDER BY u.created_at DESC');
            $stmt->execute([$roleId]);
        } else {
            $stmt = $this->db->query('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.created_at DESC');
        }
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash) VALUES (?,?,?,?,?)');
        $stmt->execute([$data['role_id'], $data['first_name'], $data['last_name'], $data['email'], password_hash($data['password'], PASSWORD_BCRYPT, ['cost'=>12])]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        foreach (['first_name','last_name','email','role_id','status'] as $f) {
            if (isset($data[$f])) { $fields[] = "$f = ?"; $params[] = $data[$f]; }
        }
        if (isset($data['password'])) {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost'=>12]);
        }
        if (!$fields) return false;
        $params[] = $id;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(',', $fields) . ' WHERE user_id = ?');
        return $stmt->execute($params);
    }

    public function incrementFailedLogins(int $id): void {
        $this->db->prepare('UPDATE users SET failed_logins = failed_logins + 1 WHERE user_id = ?')->execute([$id]);
    }

    public function lockAccount(int $id): void {
        $this->db->prepare("UPDATE users SET status = 'locked' WHERE user_id = ?")->execute([$id]);
    }

    public function resetFailedLogins(int $id): void {
        $this->db->prepare('UPDATE users SET failed_logins = 0, last_login = NOW() WHERE user_id = ?')->execute([$id]);
    }

    public function getOfficers(): array {
        $stmt = $this->db->prepare('SELECT user_id, first_name, last_name, email FROM users WHERE role_id = ? AND status = ?');
        $stmt->execute([ROLE_OFFICER, 'active']);
        return $stmt->fetchAll();
    }

    public function getCounts(): array {
        $row = $this->db->query('SELECT COUNT(*) AS total, SUM(role_id='.ROLE_CITIZEN.') AS citizens, SUM(role_id!='.ROLE_CITIZEN.') AS internal FROM users')->fetch();
        return $row;
    }
}
