<?php
class SupervisorController {
    private CaseModel $cases;
    private AuditLogModel $audit;
    private AppealModel $appeals;
    private NotificationModel $notif;

    public function __construct() {
        $this->cases   = new CaseModel();
        $this->audit   = new AuditLogModel();
        $this->appeals = new AppealModel();
        $this->notif   = new NotificationModel();
    }

    public function queue(): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        $queue = $this->cases->getForSupervisor();
        $pageTitle = 'Review Queue';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/supervisor/queue.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function reviewCase(string $caseId): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        $case = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(404); include APP_ROOT . '/app/views/shared/404.php'; return; }
        $history  = $this->cases->getStatusHistory((int)$caseId);
        $notes    = $this->cases->getNotes((int)$caseId);
        $docs     = (new DocumentModel())->getByApplication((int)$case['application_id']);
        $risk     = (new RiskScoreModel())->getByCase((int)$caseId);
        $sla      = (new SLAModel())->getForCase((int)$caseId);
        $errors   = Session::get('sup_errors', []); Session::delete('sup_errors');
        $pageTitle = 'Final Decision';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/supervisor/case_review.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function submitDecision(string $caseId): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $userId  = Session::get('user_id');
        $case    = $this->cases->findById((int)$caseId);
        if (!$case) { http_response_code(404); return; }

        $decision = $_POST['decision'] ?? '';
        $comments = trim($_POST['decision_comments'] ?? '');

        if (WorkflowPolicy::requiresDecisionComment() && strlen($comments) < 1) {
            Session::set('sup_errors', ['decision_comments' => 'Decision comments are required.']);
            header('Location: ' . BASE_URL . '/supervisor/cases/' . $caseId); exit;
        }

        $allowed = WorkflowPolicy::allowedTransitions($case['status'], ROLE_SUPERVISOR);

        if (!in_array($decision, $allowed, true)) {
            Session::set('sup_errors', ['decision' => "Cannot transition from '{$case['status']}' to '$decision'.'"]);
            header('Location: ' . BASE_URL . '/supervisor/cases/' . $caseId); exit;
        }

        $this->cases->updateStatus((int)$caseId, $decision, $userId, $comments);

        // Notify citizen
        $app = (new ApplicationModel())->findById((int)$case['application_id']);
        if ($app) {
            $cit = (new CitizenModel())->findById((int)$app['citizen_id']);
            if ($cit) {
                [$event, $msg, $type] = match ($decision) {
                    'Approved' => [
                        'Case Approved',
                        "Congratulations! Your application {$app['application_number']} has been Approved. A representative will contact you with next steps.",
                        'case_approved',
                    ],
                    'Denied' => [
                        'Case Denied',
                        "Your application {$app['application_number']} has been Denied. Reason: $comments. You may appeal within 30 days.",
                        'case_denied',
                    ],
                    default => [
                        'Case Status Changed',
                        "Your application {$app['application_number']} status has changed to: $decision.",
                        'general',
                    ],
                };
                $this->notif->create($cit['user_id'], $event, $msg, $type);
            }
            if ($decision === 'Quality Review' && $case['assigned_officer_id']) {
                $this->notif->create($case['assigned_officer_id'], 'Case Returned from Supervisor',
                    "Case {$app['application_number']} returned from Supervisor: $comments", 'general');
            }
        }

        $this->audit->log('Supervisor Decision', 'case', (int)$caseId,
            ['status' => $case['status']], ['status' => $decision, 'comments' => $comments]);

        header('Location: ' . BASE_URL . '/supervisor/queue'); exit;
    }

    /** GET /supervisor/appeals */
    public function appealQueue(): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        $appeals   = $this->appeals->getForSupervisor();
        $pageTitle = 'Appeal Queue';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/supervisor/appeals.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** GET /supervisor/appeals/document/{id} — serve an appeal document file */
    public function serveAppealDocument(int $docId): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        $doc = $this->appeals->findDocumentById($docId);
        if (!$doc) { http_response_code(404); echo 'Document not found.'; return; }
        $path = APPEAL_UPLOAD_DIR . $doc['stored_filename'];
        if (!file_exists($path)) { http_response_code(404); echo 'File not found on server.'; return; }
        header('Content-Type: ' . $doc['file_type']);
        header('Content-Disposition: inline; filename="' . rawurlencode($doc['original_filename']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** GET /supervisor/appeals/{id} */
    public function reviewAppeal(int $appealId): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        $appeal  = $this->appeals->findById($appealId);
        if (!$appeal) { http_response_code(404); include APP_ROOT . '/app/views/shared/404.php'; return; }
        $app     = (new ApplicationModel())->findById($appeal['application_id']);
        $case    = (new CaseModel())->findByApplicationId($appeal['application_id']);
        $history = $case ? $this->cases->getStatusHistory($case['case_id']) : [];
        $notes   = $case ? $this->cases->getNotes($case['case_id']) : [];
        $docs    = $this->appeals->getDocuments($appealId);
        $errors  = Session::get('appeal_dec_errors', []); Session::delete('appeal_dec_errors');
        $pageTitle = 'Review Appeal';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/supervisor/appeal_review.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** POST /supervisor/appeals/{id}/decision */
    public function decideAppeal(int $appealId): void {
        Auth::requireRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $appeal   = $this->appeals->findById($appealId);
        if (!$appeal) { http_response_code(404); return; }

        $decision = $_POST['decision'] ?? '';
        $notes    = trim($_POST['decision_notes'] ?? '');

        if (!in_array($decision, ['upheld','denied'])) {
            Session::set('appeal_dec_errors', ['decision' => 'Invalid decision.']);
            header('Location: ' . BASE_URL . '/supervisor/appeals/' . $appealId); exit;
        }
        if (strlen($notes) < 50) {
            Session::set('appeal_dec_errors', ['decision_notes' => 'Notes must be at least 50 characters.']);
            header('Location: ' . BASE_URL . '/supervisor/appeals/' . $appealId); exit;
        }

        $userId = Session::get('user_id');
        $this->appeals->decide($appealId, $decision, $notes, $userId);

        $case = (new CaseModel())->findByApplicationId($appeal['application_id']);
        if ($case) {
            $newStatus = ($decision === 'upheld') ? 'Approved' : 'Final Disposition';
            $this->cases->updateStatus($case['case_id'], $newStatus, $userId, "Appeal $decision: $notes");
        }

        // Notify citizen
        $cit = (new CitizenModel())->findById($appeal['citizen_id']);
        if ($cit) {
            [$type, $msg] = $decision === 'upheld'
                ? ['appeal_upheld', "Your appeal for {$appeal['application_number']} was upheld! Your case has been Approved."]
                : ['appeal_denied', "Your appeal for {$appeal['application_number']} was denied. $notes"];
            $this->notif->create($cit['user_id'], 'Appeal Decision', $msg, $type);
        }

        $this->audit->log('Appeal Decided', 'appeal', $appealId, null, ['decision' => $decision]);
        header('Location: ' . BASE_URL . '/supervisor/appeals'); exit;
    }
}
