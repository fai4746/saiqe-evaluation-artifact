<?php
class CaseController {
    private CaseModel $cases;
    private AuditLogModel $audit;

    public function __construct() {
        $this->cases = new CaseModel();
        $this->audit = new AuditLogModel();
    }

    public function detail(string $caseId): void {
        Auth::requireRole([ROLE_ADMIN, ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_AUDITOR, ROLE_QR]);
        $case = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(404); include APP_ROOT . '/app/views/shared/404.php'; return; }

        $roleId = Session::get('role_id');
        $userId = Session::get('user_id');
        if ($roleId === ROLE_OFFICER && (int)$case['assigned_officer_id'] !== $userId) {
            http_response_code(403); include APP_ROOT . '/app/views/shared/403.php'; return;
        }

        $history = $this->cases->getStatusHistory((int)$caseId);
        $notes   = $this->cases->getNotes((int)$caseId);
        $docs    = (new DocumentModel())->getByApplication((int)$case['application_id']);
        $risk    = (new RiskScoreModel())->getByCase((int)$caseId);
        $sla     = (new SLAModel())->getForCase((int)$caseId);
        $officers= ($roleId === ROLE_ADMIN) ? (new UserModel())->getOfficers() : [];
        $errors  = Session::get('case_errors', []); Session::delete('case_errors');
        $pageTitle = 'Case Detail';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/officer/case_detail.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function updateStatus(string $caseId): void {
        Auth::requireRole([ROLE_OFFICER]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $userId = Session::get('user_id');
        $case   = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(404); return; }
        if ((int)$case['assigned_officer_id'] !== $userId) { http_response_code(403); return; }

        if (in_array($case['status'], TERMINAL_STATUSES)) {
            Session::set('case_errors', ['status' => 'This case is locked.']);
            header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
        }

        $newStatus = $_POST['status'] ?? '';

        if (!WorkflowPolicy::canTransition($case['status'], $newStatus, ROLE_OFFICER)) {
            Session::set('case_errors', ['status' => "Cannot transition from '{$case['status']}' to '$newStatus'."]);
            header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
        }

        // High/Critical risk cases must go through Quality Review before Supervisor
        $risk = (new RiskScoreModel())->getByCase((int)$caseId);
        if (WorkflowPolicy::blockedPendingQualityReview($newStatus, $risk['risk_level'] ?? null)) {
            Session::set('case_errors', ['status' => 'This case is High/Critical risk and must go through Quality Review first.']);
            header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
        }

        $comments = trim($_POST['comments'] ?? '');
        $this->cases->updateStatus((int)$caseId, $newStatus, $userId, $comments);
        $this->audit->log('Case Status Updated', 'case', (int)$caseId,
            ['status' => $case['status']], ['status' => $newStatus]);

        // Notify citizen
        $app = (new ApplicationModel())->findById((int)$case['application_id']);
        if ($app) {
            $cit = (new CitizenModel())->findById((int)$app['citizen_id']);
            if ($cit) {
                $explanation = $this->statusExplanation($newStatus);
                (new NotificationModel())->create($cit['user_id'], 'Status Changed',
                    "Your application {$app['application_number']} is now: $newStatus. $explanation", 'general');
            }
        }

        // Notify QR if transitioning to Quality Review
        if ($newStatus === 'Quality Review') {
            (new NotificationModel())->notifyRole(ROLE_QR, 'New QR Case',
                "Case {$case['application_number']} is ready for quality review.", 'case_assigned');
        }

        header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
    }

    public function addNote(string $caseId): void {
        Auth::requireRole([ROLE_OFFICER, ROLE_ADMIN, ROLE_QR]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $userId = Session::get('user_id');
        $case   = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(403); return; }

        $text = trim($_POST['note_text'] ?? '');
        if (strlen($text) < 3) {
            Session::set('case_errors', ['note' => 'Note is too short.']);
            header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
        }
        $this->cases->addNote((int)$caseId, $userId, $text);
        $this->audit->log('Case Note Added', 'case', (int)$caseId, null, ['note_length' => strlen($text)]);
        header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
    }

    public function list(): void {
        Auth::requireRole([ROLE_ADMIN, ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_AUDITOR, ROLE_QR]);
        $roleId  = Session::get('role_id');
        $userId  = Session::get('user_id');
        $filters = $_GET;

        $cases = ($roleId === ROLE_OFFICER)
            ? $this->cases->getByOfficer($userId, $filters)
            : $this->cases->getAll($filters);

        $pageTitle = 'Cases';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/shared/case_list.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function assign(string $caseId): void {
        Auth::requireRole([ROLE_ADMIN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $officerId = (int)($_POST['officer_id'] ?? 0);
        $notes     = trim($_POST['assign_notes'] ?? '');
        $case      = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(404); return; }

        (new CaseAssignmentEngine())->reassign((int)$caseId, $officerId, Session::get('user_id'), $notes);

        $officer = (new UserModel())->findById($officerId);
        if ($officer) {
            (new NotificationModel())->create($officerId, 'Case Assigned',
                "Case for application {$case['application_number']} has been assigned to you.", 'case_assigned');
        }
        $this->audit->log('Case Manually Reassigned', 'case', (int)$caseId, null, ['officer_id' => $officerId]);
        header('Location: ' . BASE_URL . '/cases/' . $caseId); exit;
    }

    private function statusExplanation(string $status): string {
        return match($status) {
            'Eligibility Review'    => 'Your application is being reviewed for eligibility.',
            'Pending Verification'  => 'We are verifying your information.',
            'Document Requested'    => 'Additional documents have been requested. Please check your notifications.',
            'Officer Review'        => 'A case officer is reviewing your application.',
            'Quality Review'        => 'Your case is undergoing a quality check.',
            'Supervisor Review'     => 'Your case is awaiting a final decision from a supervisor.',
            'Approved'              => 'Congratulations — your application has been approved!',
            'Denied'                => 'Your application has been denied. You may appeal within 30 days.',
            default                 => '',
        };
    }
}
