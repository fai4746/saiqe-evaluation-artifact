<?php
class ApplicationController {
    private ApplicationModel $apps;
    private AuditLogModel $audit;

    public function __construct() {
        $this->apps  = new ApplicationModel();
        $this->audit = new AuditLogModel();
    }

    public function showSubmitForm(): void {
        Auth::requireRole(ROLE_CITIZEN);
        $programs = (new ProgramModel())->getAll();
        $errors   = Session::get('app_errors', []);
        $old      = Session::get('app_old', []);
        Session::delete('app_errors'); Session::delete('app_old');
        include APP_ROOT . '/app/views/citizen/submit_application.php';
    }

    public function submitApplication(): void {
        Auth::requireRole(ROLE_CITIZEN);
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/applications/submit'); exit;
        }

        $userId  = Session::get('user_id');
        $citizen = (new CitizenModel())->findByUserId($userId);

        if ($this->apps->countActive($citizen['citizen_id']) >= 3) {
            Session::set('app_errors', ['limit' => 'You may not have more than 3 active applications.']);
            header('Location: ' . BASE_URL . '/applications/submit'); exit;
        }

        $errors = $this->validateApplication($_POST);
        if ($errors) {
            Session::set('app_errors', $errors);
            Session::set('app_old', $_POST);
            header('Location: ' . BASE_URL . '/applications/submit'); exit;
        }

        $appNumber = $this->apps->generateNumber();
        $appId = $this->apps->create([
            'application_number' => $appNumber,
            'citizen_id'         => $citizen['citizen_id'],
            'program_id'         => (int)$_POST['program_id'],
            'income_range'       => $_POST['income_range'],
            'household_size'     => (int)$_POST['household_size'],
            'reason_for_request' => trim($_POST['reason_for_request']),
            'urgency_level'      => $_POST['urgency_level'],
            'preferred_contact'  => $_POST['preferred_contact'],
            'ip_address'         => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        // Create linked case
        $caseModel = new CaseModel();
        $caseId = $caseModel->createFromApplication($appId);
        $caseModel->updateStatus($caseId, 'Submitted', $userId, 'Application submitted by citizen');

        // Handle file uploads
        if (!empty($_FILES['documents']['name'][0])) {
            $this->handleUploads($appId, $userId);
        }

        // Notification
        (new NotificationModel())->create($userId, 'Application Submitted', "Your application $appNumber has been successfully submitted. Track status on your dashboard.");

        $this->audit->log(['action_type'=>'Application Submitted','entity_type'=>'application','entity_id'=>$appId,'new_value'=>['application_number'=>$appNumber]]);

        Session::set('app_success', $appNumber);
        header('Location: ' . BASE_URL . '/applications/success'); exit;
    }

    public function success(): void {
        Auth::requireRole(ROLE_CITIZEN);
        $appNumber = Session::get('app_success');
        Session::delete('app_success');
        // Wizard submits with ?app=<id> instead of session var
        if (!$appNumber && isset($_GET['app'])) {
            $app = $this->apps->findById((int)$_GET['app']);
            $appNumber = $app['application_number'] ?? null;
        }
        include APP_ROOT . '/app/views/citizen/application_success.php';
    }

    public function listAll(): void {
        Auth::requireRole(ROLE_ADMIN, ROLE_OFFICER, ROLE_AUDITOR);
        $filters = $_GET;
        $apps    = $this->apps->getAll($filters);
        $programs = (new ProgramModel())->getAll();
        include APP_ROOT . '/app/views/shared/application_list.php';
    }

    public function detail(string $id): void {
        Auth::requireLogin();
        $app = $this->apps->findById((int)$id);
        if (!$app) { http_response_code(404); include APP_ROOT . '/app/views/shared/404.php'; return; }

        $roleId = Session::get('role_id');
        $userId = Session::get('user_id');

        // Citizens can only view their own
        if ($roleId === ROLE_CITIZEN) {
            $citizen = (new CitizenModel())->findByUserId($userId);
            if (!$citizen || $citizen['citizen_id'] !== (int)$app['citizen_id']) {
                http_response_code(403); include APP_ROOT . '/app/views/shared/403.php'; return;
            }
        }

        $case = (new CaseModel())->findByApplicationId((int)$id);
        $docs = (new DocumentModel())->getByApplication((int)$id);
        include APP_ROOT . '/app/views/shared/application_detail.php';
    }

    private function validateApplication(array $d): array {
        $errors = [];
        if (empty($d['program_id'])) $errors['program_id'] = 'Program type is required.';
        if (empty($d['income_range'])) $errors['income_range'] = 'Income range is required.';
        $hs = (int)($d['household_size'] ?? 0);
        if ($hs < 1 || $hs > 20) $errors['household_size'] = 'Household size must be between 1 and 20.';
        $reason = trim($d['reason_for_request'] ?? '');
        if (strlen($reason) < 50) $errors['reason_for_request'] = 'Reason must be at least 50 characters.';
        if (empty($d['urgency_level']) || !in_array($d['urgency_level'], ['normal','high','emergency'])) $errors['urgency_level'] = 'Urgency level is required.';
        if (empty($d['preferred_contact']) || !in_array($d['preferred_contact'], ['email','phone','mail'])) $errors['preferred_contact'] = 'Preferred contact method is required.';
        return $errors;
    }

    private function handleUploads(int $appId, int $userId): void {
        $docModel = new DocumentModel();
        $existing = $docModel->countByApplication($appId);
        $files    = $_FILES['documents'];
        $count    = count($files['name']);

        for ($i = 0; $i < $count && $existing < MAX_FILES; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($files['size'][$i] > MAX_FILE_SIZE) continue;

            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($files['tmp_name'][$i]);
            if (!isset(ALLOWED_MIME_TYPES[$mimeType])) continue;

            $ext      = ALLOWED_MIME_TYPES[$mimeType];
            $stored   = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . $stored)) continue;

            $docModel->create([
                'application_id'   => $appId,
                'original_filename' => basename($files['name'][$i]),
                'stored_filename'  => $stored,
                'file_type'        => $mimeType,
                'file_size_bytes'  => $files['size'][$i],
                'uploaded_by'      => $userId,
            ]);
            $existing++;
        }
    }
}
