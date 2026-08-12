<?php
class QualityReviewerController {
    private CaseModel $cases;
    private QualityReviewModel $qr;
    private NotificationModel $notif;

    public function __construct() {
        $this->cases = new CaseModel();
        $this->qr    = new QualityReviewModel();
        $this->notif = new NotificationModel();
    }

    /** GET /quality-review/queue — alias for the QR dashboard */
    public function queue(): void {
        $this->dashboard();
    }

    /** GET /dashboard/quality-reviewer */
    public function dashboard(): void {
        Auth::requireRole([ROLE_QR, ROLE_ADMIN]);
        $stats   = $this->qr->getDashboardStats();
        $queue   = $this->qr->getQueue();
        $pageTitle = 'Quality Reviewer Dashboard';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/quality_reviewer/dashboard.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** GET /quality-review/cases/{id} */
    public function reviewCase(int $caseId): void {
        Auth::requireRole([ROLE_QR, ROLE_ADMIN]);
        $case    = $this->cases->findById($caseId);
        if (!$case) { http_response_code(404); include APP_ROOT . '/app/views/shared/404.php'; return; }
        $notes   = $this->cases->getNotes($caseId);
        $history = $this->cases->getStatusHistory($caseId);
        $qrActions = $this->qr->getForCase($caseId);
        $docs    = (new DocumentModel())->getByApplication($case['application_id']);
        $risk    = (new RiskScoreModel())->getByCase($caseId);
        $sla     = (new SLAModel())->getForCase($caseId);
        $eligibility = $this->getEligibilityResults($case['application_id']);
        $errors  = Session::get('qr_errors', []); Session::delete('qr_errors');
        $pageTitle = 'Quality Review';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/quality_reviewer/case_review.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** POST /quality-review/cases/{id}/action */
    public function submitAction(int $caseId): void {
        Auth::requireRole([ROLE_QR, ROLE_ADMIN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        // View posts qr_action (pass|return|flag) and qr_notes
        $rawAction = $_POST['qr_action'] ?? '';
        $notes     = trim($_POST['qr_notes'] ?? '');
        $userId    = Session::get('user_id');
        $case      = $this->cases->findById($caseId);

        $allowed = ['pass', 'return', 'flag'];
        if (!in_array($rawAction, $allowed) || !$case) {
            Session::set('qr_errors', ['qr_action' => 'Invalid action.']);
            header('Location: ' . BASE_URL . "/quality-review/cases/$caseId"); exit;
        }

        if (strlen($notes) < 10) {
            Session::set('qr_errors', ['qr_notes' => 'Notes must be at least 10 characters.']);
            header('Location: ' . BASE_URL . "/quality-review/cases/$caseId"); exit;
        }

        // Map view value → internal action name for DB
        $actionMap = ['pass' => 'passed_to_supervisor', 'return' => 'returned_to_officer', 'flag' => 'flagged_with_issues'];
        $action = $actionMap[$rawAction];

        // Store checklist alongside notes
        $checklist = $_POST['checklist'] ?? [];
        $fullNotes = $notes;
        if (!empty($checklist)) {
            $checked = implode(', ', array_keys($checklist));
            $fullNotes .= " [Checklist: $checked]";
        }

        // Log the QR action
        $this->qr->logAction($caseId, $userId, $action, $fullNotes);
        (new AuditLogModel())->log('Quality Review Action', 'case', $caseId, ['status' => $case['status']], ['action' => $action]);

        // Transition case status
        if ($rawAction === 'pass') {
            $this->cases->updateStatus($caseId, 'Supervisor Review', $userId, $fullNotes);
            $this->notif->notifyRole(ROLE_SUPERVISOR, 'Quality Review Passed',
                "Case {$case['application_number']} has passed QR and awaits your decision.",
                'case_assigned');
        } elseif ($rawAction === 'return' || $rawAction === 'flag') {
            $prefix = $rawAction === 'flag' ? 'Returned (Flagged) from Quality Review' : 'Returned from Quality Review';
            $this->cases->updateStatus($caseId, 'Officer Review', $userId, "$prefix: $fullNotes");
            if ($case['assigned_officer_id']) {
                $this->notif->create((int)$case['assigned_officer_id'], 'Case Returned from QR',
                    "Case {$case['application_number']} was returned: $fullNotes",
                    'case_assigned');
            }
        }

        header('Location: ' . BASE_URL . '/dashboard/quality-reviewer'); exit;
    }

    private function getEligibilityResults(int $appId): array {
        $stmt = Database::getInstance()->prepare('SELECT * FROM eligibility_results WHERE application_id=? ORDER BY result_id');
        $stmt->execute([$appId]);
        return $stmt->fetchAll();
    }
}
