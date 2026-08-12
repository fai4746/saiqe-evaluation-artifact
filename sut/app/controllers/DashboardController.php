<?php
class DashboardController {
    public function admin(): void {
        Auth::requireRole([ROLE_ADMIN]);
        $users    = new UserModel();
        $apps     = new ApplicationModel();
        $audit    = new AuditLogModel();
        $sla      = new SLAModel();
        $sla->checkBreaches(); // rate-limited — safe to call on every page load
        $data = [
            'userCounts'   => $users->getCounts(),
            'statusCounts' => $apps->getStatusCounts(),
            'recentLogs'   => $audit->getAll([], 1, 10),
            'slaCounts'    => $sla->getSummaryCounts(),
        ];
        $pageTitle = 'Admin Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/admin/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function officer(): void {
        Auth::requireRole([ROLE_OFFICER]);
        $userId = Session::get('user_id');
        $cases  = new CaseModel();
        $notif  = new NotificationModel();
        $sla    = new SLAModel();
        $data = [
            'cases'        => $cases->getByOfficer($userId),
            'overdueCount' => $cases->getOverdueCount($userId),
            'unreadCount'  => $notif->countUnread($userId),
            'slaCounts'    => $sla->getSummaryCountsByOfficer($userId),
        ];
        $pageTitle = 'Officer Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/officer/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function supervisor(): void {
        Auth::requireRole([ROLE_SUPERVISOR]);
        $cases  = new CaseModel();
        $notif  = new NotificationModel();
        $userId = Session::get('user_id');
        $data = [
            'queue'       => $cases->getForSupervisor(),
            'summary'     => $cases->getSupervisorSummary(),
            'unreadCount' => $notif->countUnread($userId),
        ];
        $pageTitle = 'Supervisor Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/supervisor/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function auditor(): void {
        Auth::requireRole([ROLE_AUDITOR]);
        $audit = new AuditLogModel();
        $apps  = new ApplicationModel();
        $data = [
            'recentLogs'   => $audit->getAll([], 1, 10),
            'statusCounts' => $apps->getStatusCounts(),
        ];
        $pageTitle = 'Auditor Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/auditor/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function qualityReviewer(): void {
        Auth::requireRole([ROLE_QR, ROLE_ADMIN]);
        $qr    = new QualityReviewModel();
        $queue = $qr->getQueue();
        $stats = $qr->getDashboardStats();
        $data  = ['stats' => $stats];
        $pageTitle = 'Quality Reviewer Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/quality_reviewer/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function citizen(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        $userId  = Session::get('user_id');
        $citizen = new CitizenModel();
        $apps    = new ApplicationModel();
        $notif   = new NotificationModel();
        $cit = $citizen->findByUserId($userId);
        $data = [
            'applications' => $cit ? $apps->getByCitizen($cit['citizen_id']) : [],
            'unreadCount'  => $notif->countUnread($userId),
        ];
        $pageTitle = 'My Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/citizen/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    // AJAX: refresh dashboard stats
    public function stats(): void {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $roleId = Session::get('role_id');
        $userId = Session::get('user_id');
        $out = [];

        if ($roleId === ROLE_ADMIN) {
            $apps = new ApplicationModel();
            $out  = $apps->getStatusCounts();
        } elseif ($roleId === ROLE_OFFICER) {
            $cases = new CaseModel();
            $list  = $cases->getByOfficer($userId);
            $out   = ['total' => count($list), 'overdue' => $cases->getOverdueCount($userId)];
        } elseif ($roleId === ROLE_CITIZEN) {
            $citizen = new CitizenModel();
            $notif   = new NotificationModel();
            $cit = $citizen->findByUserId($userId);
            $out = ['unread' => $notif->countUnread($userId), 'apps' => $cit ? count((new ApplicationModel())->getByCitizen($cit['citizen_id'])) : 0];
        }

        echo json_encode($out);
        exit;
    }
}
