<?php
class CitizenController {
    public function list(): void {
        Auth::requireRole(ROLE_ADMIN);
        $model    = new CitizenModel();
        $q        = trim($_GET['q'] ?? '');
        $citizens = $q ? $model->search($q) : $model->getAll();
        include APP_ROOT . '/app/views/admin/citizens.php';
    }
}
