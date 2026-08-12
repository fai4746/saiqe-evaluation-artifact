<?php
class AppealController {
    private AppealModel $appeals;
    private ApplicationModel $apps;
    private CaseModel $cases;
    private NotificationModel $notif;

    public function __construct() {
        $this->appeals = new AppealModel();
        $this->apps    = new ApplicationModel();
        $this->cases   = new CaseModel();
        $this->notif   = new NotificationModel();
    }

    /** GET /appeals/new?app={id} — show appeal form */
    public function showForm(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        $appId = (int)($_GET['app'] ?? 0);
        $app   = $this->apps->findById($appId);
        $citizen = (new CitizenModel())->findByUserId(Session::get('user_id'));

        if (!$app || $app['citizen_id'] != $citizen['citizen_id'] || $app['status'] !== 'Denied') {
            http_response_code(403); include APP_ROOT . '/app/views/shared/403.php'; return;
        }

        if (!$this->appeals->isWithinAppealWindow($appId)) {
            header('Location: ' . BASE_URL . '/dashboard/citizen'); exit;
        }

        $deniedAt = $this->appeals->getDeniedAt($appId);
        $appealWindowExpires = $deniedAt ? (clone $deniedAt)->modify('+30 days') : null;
        $errors = Session::get('appeal_errors', []); Session::delete('appeal_errors');
        $old    = Session::get('appeal_old',   []); Session::delete('appeal_old');
        $pageTitle = 'File an Appeal';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/citizen/appeal_form.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** POST /appeals */
    public function submit(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');

        $appId   = (int)($_POST['application_id'] ?? 0);
        $citizen = (new CitizenModel())->findByUserId(Session::get('user_id'));
        $app     = $this->apps->findById($appId);

        if (!$app || $app['citizen_id'] != $citizen['citizen_id'] || $app['status'] !== 'Denied') {
            http_response_code(403); include APP_ROOT . '/app/views/shared/403.php'; return;
        }
        if (!$this->appeals->isWithinAppealWindow($appId)) {
            header('Location: ' . BASE_URL . '/dashboard/citizen'); exit;
        }

        $reason = trim($_POST['appeal_reason'] ?? '');
        if (strlen($reason) < 100) {
            Session::set('appeal_errors', ['appeal_reason' => 'Appeal reason must be at least 100 characters.']);
            Session::set('appeal_old', ['appeal_reason' => $reason]);
            header('Location: ' . BASE_URL . '/appeals/new?app=' . $appId); exit;
        }

        if (empty($_POST['certification'])) {
            Session::set('appeal_errors', ['certification' => 'You must certify the information.']);
            Session::set('appeal_old', ['appeal_reason' => $reason]);
            header('Location: ' . BASE_URL . '/appeals/new?app=' . $appId); exit;
        }

        $deniedAt = $this->appeals->getDeniedAt($appId);
        $expires  = $deniedAt ? (clone $deniedAt)->modify('+30 days') : new DateTime('+30 days');

        $appealId = $this->appeals->create([
            'application_id'           => $appId,
            'citizen_id'               => $citizen['citizen_id'],
            'appeal_reason'            => $reason,
            'appeal_window_expires_at' => $expires->format('Y-m-d H:i:s'),
        ]);

        // Upload appeal documents
        if (!empty($_FILES['appeal_documents']['name'][0])) {
            $this->handleAppealUploads($appealId, $_FILES['appeal_documents']);
        }

        // Transition application to Appealed
        $case = $this->cases->findByApplicationId($appId);
        if ($case) {
            $this->cases->updateStatus($case['case_id'], 'Appealed', Session::get('user_id'), 'Appeal filed by citizen');
            // Then to Appeal Review
            $this->cases->updateStatus($case['case_id'], 'Appeal Review', Session::get('user_id'), 'Appeal submitted');
        }

        // Notifications
        $this->notif->create(Session::get('user_id'), 'Appeal Submitted',
            "Your appeal for application {$app['application_number']} has been received and is under review.",
            'appeal_submitted');
        $this->notif->notifyRole(ROLE_SUPERVISOR, 'New Appeal',
            "Appeal submitted for application {$app['application_number']}. Please review.",
            'appeal_submitted');

        (new AuditLogModel())->log('Appeal Submitted', 'application', $appId, null, ['appeal_id' => $appealId]);

        header('Location: ' . BASE_URL . '/dashboard/citizen?appeal=1'); exit;
    }

    private function handleAppealUploads(int $appealId, array $files): void {
        $uploadDir = APPEAL_UPLOAD_DIR;
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $count = min(count($files['name']), 3);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > MAX_FILE_SIZE) continue;
            $mime = mime_content_type($files['tmp_name'][$i]);
            if (!isset(ALLOWED_MIME_TYPES[$mime])) continue;
            $stored = 'appeal_' . $appealId . '_' . uniqid() . '.' . ALLOWED_MIME_TYPES[$mime];
            if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $stored)) {
                $this->appeals->addDocument([
                    'appeal_id'          => $appealId,
                    'original_filename'  => $files['name'][$i],
                    'stored_filename'    => $stored,
                    'file_type'          => $mime,
                    'file_size_bytes'    => $files['size'][$i],
                    'uploaded_by_user_id'=> Session::get('user_id'),
                ]);
            }
        }
    }
}
