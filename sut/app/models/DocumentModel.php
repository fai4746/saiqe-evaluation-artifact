<?php
class DocumentModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function countByApplication(int $appId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM documents WHERE application_id = ?');
        $stmt->execute([$appId]);
        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO documents (application_id, original_filename, stored_filename, file_type, file_size_bytes, uploaded_by_user_id, document_category) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$data['application_id'], $data['original_filename'], $data['stored_filename'], $data['file_type'], $data['file_size_bytes'], $data['uploaded_by'], $data['document_category'] ?? null]);
        return (int)$this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE document_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getByApplication(int $appId): array {
        $stmt = $this->db->prepare('SELECT d.*, u.first_name, u.last_name FROM documents d JOIN users u ON d.uploaded_by_user_id = u.user_id WHERE d.application_id = ? ORDER BY d.uploaded_at DESC');
        $stmt->execute([$appId]);
        return $stmt->fetchAll();
    }
}
