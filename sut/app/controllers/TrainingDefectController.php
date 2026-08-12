<?php
class TrainingDefectController {
    private TrainingDefectModel $defects;

    public function __construct() {
        $this->defects = new TrainingDefectModel();
    }

    /** GET /admin/training-defects */
    public function index(): void {
        Auth::requireRole([ROLE_ADMIN]);
        $defects    = $this->defects->getAll();
        $activeCount = $this->defects->getActiveCount();
        $pageTitle  = 'Training Defect Mode';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/admin/training_defects.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** POST /admin/training-defects/{id}/toggle */
    public function toggle(int $defectId): void {
        Auth::requireRole([ROLE_ADMIN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $defect   = $this->getById($defectId);
        if (!$defect) { http_response_code(404); return; }

        $newState = $defect['is_active'] ? 0 : 1;
        $userId   = Session::get('user_id');
        $this->defects->toggle($defectId, $newState, $userId);

        $action = $newState ? 'training_defect_enabled' : 'training_defect_disabled';
        (new AuditLogModel())->log($action, 'training_defect', $defectId, null,
            ['defect_key' => $defect['defect_key'], 'is_active' => $newState]);

        // Clear cached defects from session so next page load refreshes
        Session::delete('active_defects');

        header('Location: ' . BASE_URL . '/admin/training-defects'); exit;
    }

    private function getById(int $id): ?array {
        $stmt = Database::getInstance()->prepare('SELECT * FROM training_defects WHERE defect_id=?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
