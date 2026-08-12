<?php
class NotificationModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create(int $recipientId, string $event, string $message, string $type = 'general'): int {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (recipient_user_id, trigger_event, message, notification_type)
             VALUES (?,?,?,?)'
        );
        $stmt->execute([$recipientId, $event, $message, $type]);
        return (int)$this->db->lastInsertId();
    }

    /** Notify all users of a given role. */
    public function notifyRole(int $roleId, string $event, string $message, string $type = 'general'): void {
        $stmt = $this->db->prepare(
            "SELECT user_id FROM users WHERE role_id=? AND status='active'"
        );
        $stmt->execute([$roleId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $this->create((int)$uid, $event, $message, $type);
        }
    }

    public function getByUser(int $userId, int $limit = 100): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE recipient_user_id=? ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getRecent(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications WHERE recipient_user_id=? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public function countUnread(int $userId): int {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE recipient_user_id=? AND is_read=0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markRead(int $notifId, int $userId): bool {
        // Defect: notification_markread_missing_owner_check — the recipient
        // predicate is dropped, so any authenticated user can mutate any
        // notification by guessing its id (CWE-639).
        if (defectActive('notification_markread_missing_owner_check')) {
            return $this->db->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=?')
                            ->execute([$notifId]);
        }
        return $this->db->prepare(
            'UPDATE notifications SET is_read=1 WHERE notification_id=? AND recipient_user_id=?'
        )->execute([$notifId, $userId]);
    }

    /**
     * Notification bodies embed citizen- and officer-supplied text and are
     * rendered into HTML, so escaping happens here rather than at each view.
     */
    public static function renderMessage(string $message): string {
        // Defect: notification_message_not_escaped — stored markup reaches the
        // browser intact (CWE-79, stored XSS).
        if (defectActive('notification_message_not_escaped')) {
            return $message;
        }
        return htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function markAllRead(int $userId): bool {
        return $this->db->prepare('UPDATE notifications SET is_read=1 WHERE recipient_user_id=?')
                        ->execute([$userId]);
    }
}
