<?php
class WizardController {
    private ApplicationModel $apps;
    private CitizenModel $citizens;
    private ProgramModel $programs;

    public function __construct() {
        $this->apps     = new ApplicationModel();
        $this->citizens = new CitizenModel();
        $this->programs = new ProgramModel();
    }

    /** GET /applications/wizard — start or resume */
    public function start(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        $citizenId = $this->getCitizenId();

        // Check for existing draft
        $draft = $this->apps->findDraftByCitizen($citizenId);
        if ($draft) {
            $nextStep = min($draft['wizard_step_completed'] + 1, 6);
            header('Location: ' . BASE_URL . '/applications/wizard/' . $nextStep); exit;
        }

        // Create new draft
        $appId = $this->apps->createDraft($citizenId);
        Session::set('wizard_app_id', $appId);
        Session::set('wizard_started_at', time());
        header('Location: ' . BASE_URL . '/applications/wizard/1'); exit;
    }

    /** GET /applications/wizard/{step} */
    public function showStep(int $step): void {
        Auth::requireRole([ROLE_CITIZEN]);
        $appId = $this->ensureWizardSession();

        $wizard   = $this->apps->getWizardData($appId);
        $data     = $wizard['data'];
        $citizen  = $this->citizens->findByUserId(Session::get('user_id'));
        $user     = ['first_name' => Session::get('first_name'), 'last_name' => Session::get('last_name'), 'email' => Session::get('email')];
        $programs = $this->programs->getAll();
        $errors   = Session::get('wizard_errors', []); Session::delete('wizard_errors');
        $old      = Session::get('wizard_old', []);    Session::delete('wizard_old');

        // Load uploaded docs for the document (5) and review (6) steps
        if ($step >= 5) {
            $data['existing_docs'] = (new DocumentModel())->getByApplication($appId);
        }

        $pageTitle = "Application — Step $step of 6";
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . "/app/views/wizard/step{$step}.php";
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    /** POST /applications/wizard/{step} */
    public function saveStep(int $step): void {
        Auth::requireRole([ROLE_CITIZEN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');
        $appId = $this->ensureWizardSession();

        $errors = $this->validateStep($step, $_POST);
        if ($errors) {
            Session::set('wizard_errors', $errors);
            Session::set('wizard_old', $_POST);
            header('Location: ' . BASE_URL . '/applications/wizard/' . $step); exit;
        }

        $this->apps->saveWizardStep($appId, $step, $_POST);

        // Step 5: process file uploads (categories: identity, income, residence, household, other)
        if ($step === 5) {
            $this->handleWizardUploads($appId, Session::get('user_id'));
        }

        if ($step < 6) {
            header('Location: ' . BASE_URL . '/applications/wizard/' . ($step + 1)); exit;
        }
        // Step 6 = review page
        header('Location: ' . BASE_URL . '/applications/wizard/6'); exit;
    }

    /** POST /applications/wizard/submit */
    public function submit(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        Session::verifyCsrf($_POST['csrf_token'] ?? '');
        $appId = $this->ensureWizardSession();

        if (empty($_POST['certification'])) {
            Session::set('wizard_errors', ['certification' => 'You must certify the information before submitting.']);
            header('Location: ' . BASE_URL . '/applications/wizard/6'); exit;
        }

        $wizard = $this->apps->getWizardData($appId);
        $allData = $wizard['data'];
        $citizenId = $this->getCitizenId();

        // Finalize the application record
        $this->apps->submitWizard($appId, $allData, $citizenId);

        // Create case record
        $caseId = (new CaseModel())->createFromApplication($appId);

        // Transition: Submitted → Eligibility Review
        (new CaseModel())->updateStatus($caseId, 'Eligibility Review', Session::get('user_id'), 'Application submitted via wizard');

        // Run eligibility engine
        $eligibility = (new EligibilityEngine())->evaluate($appId);
        $this->apps->updatePreliminaryEligibility($appId, $eligibility['result']);

        // Run risk score engine
        $startedAt = Session::get('wizard_started_at') ? new DateTime('@' . Session::get('wizard_started_at')) : null;
        (new RiskScoreEngine())->compute($appId, $startedAt);

        if ($eligibility['result'] === 'failed') {
            // Auto-deny
            (new CaseModel())->updateStatus($caseId, 'Auto-Denied', Session::get('user_id'),
                'Auto-Denied by eligibility engine: ' . ($eligibility['stop_reason'] ?? ''));
            (new ApplicationModel())->updatePreliminaryEligibility($appId, 'failed');

            $notif = new NotificationModel();
            $notif->create(Session::get('user_id'), 'Eligibility Review',
                'Your application has been automatically denied. Reason: ' . ($eligibility['stop_reason'] ?? 'Eligibility failed.'),
                'eligibility_denied');
        } else {
            // Auto-assign
            (new CaseAssignmentEngine())->assign($caseId, $appId);

            // Notify citizen
            $appNum = (new ApplicationModel())->findById($appId)['application_number'] ?? '';
            (new NotificationModel())->create(Session::get('user_id'), 'Application Submitted',
                "Your application $appNum has been submitted and is under review.",
                'application_submitted');
        }

        // Audit log
        (new AuditLogModel())->log('Application Submitted', 'application', $appId, null,
            ['result' => $eligibility['result']]);

        // Clear wizard session
        Session::delete('wizard_app_id');
        Session::delete('wizard_started_at');

        header('Location: ' . BASE_URL . '/applications/success?app=' . $appId); exit;
    }

    // ── Private helpers ──────────────────────────────────────────

    private function ensureWizardSession(): int {
        $appId = Session::get('wizard_app_id');
        if (!$appId) {
            // Try to find an existing draft
            $citizenId = $this->getCitizenId();
            $draft = $this->apps->findDraftByCitizen($citizenId);
            if ($draft) {
                Session::set('wizard_app_id', $draft['application_id']);
                return (int)$draft['application_id'];
            }
            header('Location: ' . BASE_URL . '/applications/wizard'); exit;
        }
        return (int)$appId;
    }

    private function getCitizenId(): int {
        $citizen = $this->citizens->findByUserId(Session::get('user_id'));
        if (!$citizen) {
            header('Location: ' . BASE_URL . '/dashboard/citizen'); exit;
        }
        return (int)$citizen['citizen_id'];
    }

    /** Handle categorised file uploads for wizard step 5.
     *  Form field name format: documents[{category}][] */
    private function handleWizardUploads(int $appId, int $userId): void {
        if (empty($_FILES['documents']['name'])) return;
        $docModel = new DocumentModel();
        $existing = $docModel->countByApplication($appId);
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        foreach ($_FILES['documents']['name'] as $category => $names) {
            foreach ($names as $idx => $origName) {
                if ($existing >= MAX_FILES) break 2;
                if ($_FILES['documents']['error'][$category][$idx] !== UPLOAD_ERR_OK) continue;
                $size = (int)$_FILES['documents']['size'][$category][$idx];
                if ($size === 0 || $size > MAX_FILE_SIZE) continue;
                $tmpName = $_FILES['documents']['tmp_name'][$category][$idx];
                $finfo   = new finfo(FILEINFO_MIME_TYPE);
                $mime    = $finfo->file($tmpName);
                if (!isset(ALLOWED_MIME_TYPES[$mime])) continue;
                $stored  = bin2hex(random_bytes(16)) . '.' . ALLOWED_MIME_TYPES[$mime];
                if (!move_uploaded_file($tmpName, UPLOAD_DIR . $stored)) continue;
                $docModel->create([
                    'application_id'    => $appId,
                    'original_filename' => basename($origName),
                    'stored_filename'   => $stored,
                    'file_type'         => $mime,
                    'file_size_bytes'   => $size,
                    'uploaded_by'       => $userId,
                    'document_category' => $category,
                ]);
                $existing++;
            }
        }
    }

    private function validateStep(int $step, array $post): array {
        $errors = [];
        switch ($step) {
            case 1:
                if (empty($post['dob'])) {
                    $errors['dob'] = 'Date of birth is required.';
                } else {
                    try {
                        $age = (new DateTime())->diff(new DateTime($post['dob']))->y;
                        if ($age < 18) $errors['dob'] = 'You must be at least 18 years old.';
                    } catch (Exception $e) { $errors['dob'] = 'Invalid date.'; }
                }
                if (!empty($post['ssn_last4']) && !preg_match('/^\d{4}$/', $post['ssn_last4'])) {
                    $errors['ssn_last4'] = 'SSN last 4 must be exactly 4 digits.';
                }
                if (!empty($post['email']) && !filter_var($post['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Invalid email format.';
                }
                break;
            case 2:
                $hs = (int)($post['household_size'] ?? 0);
                $dep = (int)($post['num_dependents'] ?? 0);
                if ($e = IntakeValidator::validateHouseholdSize($post['household_size'] ?? 0)) $errors['household_size'] = $e;
                if ($dep < 0 || $dep > 19) $errors['num_dependents'] = 'Dependents must be 0–19.';
                if ($dep >= $hs) $errors['num_dependents'] = 'Dependents cannot equal or exceed household size.';
                break;
            case 3:
                $income = (float)($post['annual_income'] ?? -1);
                if ($income < 0) $errors['annual_income'] = 'Annual income cannot be negative.';
                if (empty($post['employment_status'])) $errors['employment_status'] = 'Employment status is required.';
                if (empty($post['income_source']))     $errors['income_source']     = 'Income source is required.';
                // employer_name is optional context; no hard validation required
                break;
            case 4:
                if (empty($post['program_id'])) $errors['program_id'] = 'Please select a program.';
                if ($e = IntakeValidator::validateReasonForRequest($post['reason_for_request'] ?? ''))
                    $errors['reason_for_request'] = $e;
                if (!in_array($post['currently_receiving_federal'] ?? '', ['yes','no']))
                    $errors['currently_receiving_federal'] = 'Please answer this question.';
                if (!in_array($post['received_benefit_12mo'] ?? '', ['yes','no']))
                    $errors['received_benefit_12mo'] = 'Please answer this question.';
                if (!in_array($post['has_dependents_under_18'] ?? '', ['yes','no']))
                    $errors['has_dependents_under_18'] = 'Please answer this question.';
                break;
            case 5:
                // Documents — handled by upload controller; skip hard validation here
                break;
            case 6:
                // Review step — no new field validation
                break;
        }
        return $errors;
    }
}
