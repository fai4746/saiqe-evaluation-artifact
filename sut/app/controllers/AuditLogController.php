<?php
class AuditLogController {
    public function index(): void {
        Auth::requireRole(ROLE_ADMIN, ROLE_AUDITOR);
        $filters = array_filter($_GET);
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $audit   = new AuditLogModel();
        $logs    = $audit->getAll($filters, $page);
        $total   = $audit->count($filters);
        $pages   = ceil($total / 50);
        include APP_ROOT . '/app/views/shared/audit_log.php';
    }
}
