<?php
class NotificationController {
    public function index(): void {
        Auth::requireLogin();
        $userId = Session::get('user_id');
        $notif  = new NotificationModel();
        $notifications = $notif->getByUser($userId);
        include APP_ROOT . '/app/views/shared/notifications.php';
    }

    public function markRead(string $id): void {
        Auth::requireLogin();
        (new NotificationModel())->markRead((int)$id, Session::get('user_id'));
        header('Location: ' . BASE_URL . '/notifications'); exit;
    }

    public function markAllRead(): void {
        Auth::requireLogin();
        (new NotificationModel())->markAllRead(Session::get('user_id'));
        header('Location: ' . BASE_URL . '/notifications'); exit;
    }

    // AJAX endpoint: returns unread count
    public function count(): void {
        Auth::requireLogin();
        $count = (new NotificationModel())->countUnread(Session::get('user_id'));
        header('Content-Type: application/json');
        echo json_encode(['unread' => $count]);
        exit;
    }
}
