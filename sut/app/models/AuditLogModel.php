<?php
class AuditLogModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Two calling conventions:
     *   Legacy array:   log(['action_type'=>'...', 'entity_type'=>'...', ...])
     *   New positional: log('Action Name', 'entity_type', 123, $oldArr, $newArr)
     */
    public function log(
        string|array $actionOrData,
        string $entityType = '',
        int    $entityId   = 0,
        mixed  $oldValue   = null,
        mixed  $newValue   = null
    ): void {
        $user = Auth::user();

        if (is_array($actionOrData)) {
            // Legacy array form
            $data = $actionOrData;
        } else {
            // New positional form
            $data = [
                'action_type' => $actionOrData,
                'entity_type' => $entityType ?: null,
                'entity_id'   => $entityId   ?: null,
                'old_value'   => $oldValue,
                'new_value'   => $newValue,
            ];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs
             (user_id, username, role, action_type, entity_type, entity_id,
              old_value, new_value, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['user_id']     ?? ($user['user_id']   ?? null),
            $data['username']    ?? ($user['email']     ?? null),
            $data['role']        ?? ($user['role_name'] ?? null),
            $data['action_type'],
            $data['entity_type'] ?? null,
            $data['entity_id']   ?? null,
            isset($data['old_value']) ? json_encode($data['old_value']) : null,
            isset($data['new_value']) ? json_encode($data['new_value']) : null,
            $data['ip_address']  ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            $data['user_agent']  ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
        ]);
    }

    public function getAll(array $filters = [], int $page = 1, int $perPage = 50): array {
        $sql = 'SELECT * FROM audit_logs WHERE 1=1';
        $params = [];
        if (!empty($filters['date_from'])) { $sql .= ' AND DATE(created_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $sql .= ' AND DATE(created_at) <= ?'; $params[] = $filters['date_to']; }
        if (!empty($filters['user_id']))   { $sql .= ' AND user_id = ?'; $params[] = $filters['user_id']; }
        if (!empty($filters['action_type'])) { $sql .= ' AND action_type = ?'; $params[] = $filters['action_type']; }
        if (!empty($filters['entity_type'])) { $sql .= ' AND entity_type = ?'; $params[] = $filters['entity_type']; }
        $sql .= ' ORDER BY ' . self::sortClause($filters['sort'] ?? null);
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Resolve a caller-supplied sort key to a column. ORDER BY cannot be
     * parameterised, so the value must be allow-listed rather than interpolated.
     */
    public static function sortClause(?string $sort): string {
        $allowed = [
            'created_at' => 'created_at DESC',
            'created_at_asc' => 'created_at ASC',
            'action' => 'action_type ASC',
            'user'   => 'username ASC',
        ];
        // Defect: report_sql_concatenated_sort — the allow-list is bypassed and the
        // raw parameter is concatenated into the statement (CWE-89).
        if (defectActive('report_sql_concatenated_sort') && $sort !== null && $sort !== '') {
            return $sort;
        }
        return $allowed[$sort ?? 'created_at'] ?? $allowed['created_at'];
    }

    /**
     * The audit trail is append-only for the applicable retention period.
     * There is no supported deletion path, so this is always false.
     */
    public static function deletionPermitted(): bool {
        // Defect: audit_log_deletable — the refusal is lifted and records are destroyed.
        return defectActive('audit_log_deletable');
    }

    /**
     * Physical delete. Callers must gate on deletionPermitted(); it exists so the
     * API can express the operation and be observed refusing it.
     */
    public function deleteById(int $id): int {
        $stmt = $this->db->prepare('DELETE FROM audit_logs WHERE log_id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function count(array $filters = []): int {
        $sql = 'SELECT COUNT(*) FROM audit_logs WHERE 1=1';
        $params = [];
        if (!empty($filters['date_from'])) { $sql .= ' AND DATE(created_at) >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $sql .= ' AND DATE(created_at) <= ?'; $params[] = $filters['date_to']; }
        if (!empty($filters['user_id']))   { $sql .= ' AND user_id = ?'; $params[] = $filters['user_id']; }
        if (!empty($filters['action_type'])) { $sql .= ' AND action_type = ?'; $params[] = $filters['action_type']; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
