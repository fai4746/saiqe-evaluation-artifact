<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/Database.php';
require_once APP_ROOT . '/core/Session.php';
require_once APP_ROOT . '/core/Auth.php';
require_once APP_ROOT . '/core/Response.php';
require_once APP_ROOT . '/app/models/UserModel.php';
require_once APP_ROOT . '/app/models/CitizenModel.php';
require_once APP_ROOT . '/app/models/ApplicationModel.php';
require_once APP_ROOT . '/app/models/CaseModel.php';
require_once APP_ROOT . '/app/models/DocumentModel.php';
require_once APP_ROOT . '/app/models/NotificationModel.php';
require_once APP_ROOT . '/app/models/AuditLogModel.php';
require_once APP_ROOT . '/app/models/ProgramModel.php';
require_once APP_ROOT . '/app/models/RiskScoreModel.php';
require_once APP_ROOT . '/app/models/SLAModel.php';
require_once APP_ROOT . '/app/models/AppealModel.php';
require_once APP_ROOT . '/app/models/SystemSettingsModel.php';
require_once APP_ROOT . '/app/models/TrainingDefectModel.php';
require_once APP_ROOT . '/app/models/QualityReviewModel.php';
require_once APP_ROOT . '/app/core/UploadValidator.php';
require_once APP_ROOT . '/app/core/IntakeValidator.php';
require_once APP_ROOT . '/app/core/FormRenderer.php';
require_once APP_ROOT . '/app/core/WorkflowPolicy.php';
require_once APP_ROOT . '/app/ai/ModelAdapter.php';
require_once APP_ROOT . '/app/ai/DeterministicAdapter.php';
require_once APP_ROOT . '/app/ai/ToolBroker.php';
require_once APP_ROOT . '/app/ai/CaseSummaryAssistant.php';
require_once APP_ROOT . '/app/core/RiskScoreEngine.php';

Session::start();

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = parse_url(BASE_URL, PHP_URL_PATH) . '/api/v1';
$path   = '/' . ltrim(substr($uri, strlen($base)), '/');

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Auth helper ─────────────────────────────────────────
function requireJwt(): array {
    $claims = Auth::jwtFromRequest();
    if (!$claims) Response::unauthorized('Valid Bearer token required');
    return $claims;
}
function requireJwtRole(int|array ...$roles): array {
    $claims = requireJwt();
    $flat = [];
    foreach ($roles as $r) {
        if (is_array($r)) { foreach ($r as $v) $flat[] = (int)$v; }
        else $flat[] = (int)$r;
    }
    if (!in_array($claims['role_id'], $flat, true)) Response::forbidden('Insufficient permissions');
    return $claims;
}

// ── Route matching ──────────────────────────────────────
function matchRoute(string $pattern, string $path, array &$params): bool {
    $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';
    if (preg_match($regex, $path, $m)) {
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        return true;
    }
    return false;
}

$params = [];

// ─────────────────────────────────────────────────────────
// POST /auth/login
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/auth/login') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) Response::error('Email and password are required', ['email'=>'required','password'=>'required'], 422);

    $users = new UserModel();
    $audit = new AuditLogModel();
    $user  = $users->findByEmail($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        if ($user) $users->incrementFailedLogins($user['user_id']);
        $audit->log(['action_type'=>'Failed Login','entity_type'=>'user','user_id'=>null,'username'=>$email,'role'=>null]);
        Response::error('Invalid credentials', [], 401);
    }
    if ($user['status'] !== 'active') Response::error('Account is not active', [], 403);

    $users->resetFailedLogins($user['user_id']);
    $token = Auth::generateJwt(['user_id'=>$user['user_id'],'role_id'=>$user['role_id'],'email'=>$user['email']]);
    $audit->log(['action_type'=>'Login','entity_type'=>'user','entity_id'=>$user['user_id'],'user_id'=>$user['user_id'],'username'=>$user['email'],'role'=>$user['role_name']]);
    Response::success('Login successful', ['token'=>$token,'role'=>$user['role_name'],'user_id'=>$user['user_id']]);
}

// ─────────────────────────────────────────────────────────
// POST /auth/register
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && $path === '/auth/register') {
    $email = trim($body['email'] ?? '');
    $errors = [];
    if (empty($body['first_name'])) $errors['first_name'] = 'Required';
    if (empty($body['last_name']))  $errors['last_name']  = 'Required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email required';
    if (empty($body['password']) || strlen($body['password']) < 8) $errors['password'] = 'Min 8 characters';
    if ($errors) Response::error('Validation failed', $errors, 422);

    $users = new UserModel();
    if ($users->findByEmail($email)) Response::error('Email already registered', ['email'=>'duplicate'], 409);

    $userId = $users->create(['role_id'=>ROLE_CITIZEN,'first_name'=>$body['first_name'],'last_name'=>$body['last_name'],'email'=>$email,'password'=>$body['password']]);
    (new AuditLogModel())->log(['action_type'=>'Registration','entity_type'=>'user','entity_id'=>$userId,'user_id'=>$userId,'username'=>$email,'role'=>'citizen']);
    Response::success('Registration successful', ['user_id'=>$userId], 201);
}

// ─────────────────────────────────────────────────────────
// GET /users
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/users') {
    requireJwtRole([ROLE_ADMIN]);
    Response::success('Users retrieved', (new UserModel())->getAll());
}

// POST /users
if ($method === 'POST' && $path === '/users') {
    $c = requireJwtRole([ROLE_ADMIN]);
    $errors = [];
    if (empty($body['email'])) $errors['email'] = 'Required';
    if (!in_array((int)($body['role_id']??0), [ROLE_OFFICER,ROLE_SUPERVISOR,ROLE_AUDITOR])) $errors['role_id'] = 'Invalid role';
    if (empty($body['password'])) $errors['password'] = 'Required';
    if ($errors) Response::error('Validation failed', $errors, 422);
    $users = new UserModel();
    if ($users->findByEmail($body['email'])) Response::error('Email already exists', [], 409);
    $id = $users->create(['role_id'=>(int)$body['role_id'],'first_name'=>$body['first_name']??'','last_name'=>$body['last_name']??'','email'=>$body['email'],'password'=>$body['password']]);
    (new AuditLogModel())->log(['action_type'=>'User Created','entity_type'=>'user','entity_id'=>$id,'user_id'=>$c['user_id'],'username'=>$c['email'],'role'=>ROLE_NAMES[$c['role_id']]]);
    Response::success('User created', ['user_id'=>$id], 201);
}

// GET /users/{id}
if ($method === 'GET' && matchRoute('/users/{id}', $path, $params)) {
    requireJwtRole([ROLE_ADMIN]);
    $user = (new UserModel())->findById((int)$params['id']);
    if (!$user) Response::notFound('User not found');
    Response::success('User found', $user);
}

// PUT /users/{id}
if ($method === 'PUT' && matchRoute('/users/{id}', $path, $params)) {
    $c = requireJwtRole([ROLE_ADMIN]);
    (new UserModel())->update((int)$params['id'], array_filter($body));
    (new AuditLogModel())->log(['action_type'=>'User Updated','entity_type'=>'user','entity_id'=>$params['id'],'user_id'=>$c['user_id'],'username'=>$c['email'],'role'=>ROLE_NAMES[$c['role_id']]]);
    Response::success('User updated');
}

// ─────────────────────────────────────────────────────────
// GET /applications
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/applications') {
    $c = requireJwt();
    $apps = new ApplicationModel();
    if (Auth::applicationScope($c) === 'own') {
        $citizen = (new CitizenModel())->findByUserId($c['user_id']);
        $data = $citizen ? $apps->getByCitizen($citizen['citizen_id']) : [];
    } else {
        $data = $apps->getAll($_GET);
    }
    Response::success('Applications retrieved', $data);
}

// POST /applications
if ($method === 'POST' && $path === '/applications') {
    $c = requireJwtRole(ROLE_CITIZEN);
    $citizen = (new CitizenModel())->findByUserId($c['user_id']);
    if (!$citizen) Response::error('Citizen profile not found', [], 404);

    $apps = new ApplicationModel();
    if ($apps->countActive($citizen['citizen_id']) >= 3) Response::error('Maximum 3 active applications allowed', [], 422);

    $errors = [];
    if (empty($body['program_id'])) $errors['program_id'] = 'Required';
    if (empty($body['income_range'])) $errors['income_range'] = 'Required';
    $hs = (int)($body['household_size']??0);
    if ($hs < 1 || $hs > 20) $errors['household_size'] = 'Must be 1-20';
    if (strlen($body['reason_for_request']??'') < 50) $errors['reason_for_request'] = 'Min 50 characters';
    if ($errors) Response::error('Validation failed', $errors, 422);

    $appNumber = $apps->generateNumber();
    $appId = $apps->create([
        'application_number' => $appNumber,
        'citizen_id'         => $citizen['citizen_id'],
        'program_id'         => (int)$body['program_id'],
        'income_range'       => $body['income_range'],
        'household_size'     => $hs,
        'reason_for_request' => $body['reason_for_request'],
        'urgency_level'      => $body['urgency_level'] ?? 'normal',
        'preferred_contact'  => $body['preferred_contact'] ?? 'email',
        'ip_address'         => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $caseModel = new CaseModel();
    $caseId = $caseModel->createFromApplication($appId);
    $caseModel->updateStatus($caseId, 'Submitted', $c['user_id'], 'Application submitted');
    (new NotificationModel())->create($c['user_id'], 'Application Submitted', "Your application $appNumber has been submitted.");
    (new AuditLogModel())->log(['action_type'=>'Application Submitted','entity_type'=>'application','entity_id'=>$appId,'user_id'=>$c['user_id'],'username'=>$c['email'],'role'=>'citizen','new_value'=>['application_number'=>$appNumber]]);
    Response::success('Application submitted', ['application_id'=>$appId,'application_number'=>$appNumber], 201);
}

// GET /applications/{id}
if ($method === 'GET' && matchRoute('/applications/{id}', $path, $params)) {
    $c = requireJwt();
    $app = (new ApplicationModel())->findById((int)$params['id']);
    if (!$app) Response::notFound('Application not found');
    if ($c['role_id'] === ROLE_CITIZEN) {
        $citizen = (new CitizenModel())->findByUserId($c['user_id']);
        if (!$citizen || (int)$app['citizen_id'] !== $citizen['citizen_id']) Response::forbidden();
    }
    Response::success('Application found', $app);
}

// ─────────────────────────────────────────────────────────
// PUT /cases/{id}/status
// ─────────────────────────────────────────────────────────
if ($method === 'PUT' && matchRoute('/cases/{id}/status', $path, $params)) {
    $c = requireJwtRole([ROLE_OFFICER]);
    $cases = new CaseModel();
    $case  = $cases->findById((int)$params['id']);
    if (!$case) Response::notFound('Case not found');
    if ((int)$case['assigned_officer_id'] !== $c['user_id']) Response::forbidden('Not your assigned case');
    if (in_array($case['status'], TERMINAL_STATUSES)) Response::forbidden('Case is locked');

    $newStatus = $body['status'] ?? '';
    $allowed   = VALID_TRANSITIONS[$case['status']] ?? [];
    if (!in_array($newStatus, $allowed)) Response::error('Invalid status transition', ['status'=>'not allowed'], 422);

    $cases->updateStatus((int)$params['id'], $newStatus, $c['user_id'], $body['comments'] ?? '');
    (new AuditLogModel())->log('Case Status Updated', 'case', (int)$params['id'],
        ['status' => $case['status']], ['status' => $newStatus]);
    Response::success('Status updated');
}

// POST /cases/{id}/notes
if ($method === 'POST' && matchRoute('/cases/{id}/notes', $path, $params)) {
    $c = requireJwtRole([ROLE_OFFICER, ROLE_QR, ROLE_ADMIN]);
    $cases = new CaseModel();
    $case  = $cases->findById((int)$params['id']);
    if (!$case) Response::notFound('Case not found');
    if ($c['role_id'] === ROLE_OFFICER && (int)$case['assigned_officer_id'] !== $c['user_id']) Response::forbidden();
    if (strlen($body['note_text'] ?? '') < 3) Response::error('Note is too short', [], 422);
    $id = $cases->addNote((int)$params['id'], $c['user_id'], $body['note_text']);
    Response::success('Note added', ['note_id'=>$id], 201);
}

// GET /cases/{id}/history
if ($method === 'GET' && matchRoute('/cases/{id}/history', $path, $params)) {
    requireJwt();
    $history = (new CaseModel())->getStatusHistory((int)$params['id']);
    Response::success('History retrieved', $history);
}

// GET /cases/{id}/risk
if ($method === 'GET' && matchRoute('/cases/{id}/risk', $path, $params)) {
    requireJwtRole([ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_ADMIN, ROLE_QR]);
    $risk = (new RiskScoreModel())->getByCase((int)$params['id']);
    if (!$risk) Response::notFound('Risk score not found');
    Response::success('Risk score retrieved', $risk);
}

// GET /cases/{id}/sla
if ($method === 'GET' && matchRoute('/cases/{id}/sla', $path, $params)) {
    requireJwtRole([ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_ADMIN, ROLE_QR]);
    $sla = (new SLAModel())->getForCase((int)$params['id']);
    if (!$sla) Response::notFound('SLA not found');
    Response::success('SLA retrieved', $sla);
}

// PUT /cases/{id}/decision
if ($method === 'PUT' && matchRoute('/cases/{id}/decision', $path, $params)) {
    $c = requireJwtRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
    $cases = new CaseModel();
    $case  = $cases->findById((int)$params['id']);
    if (!$case) Response::notFound('Case not found');

    $decision  = $body['decision'] ?? '';
    $comments  = trim($body['decision_comments'] ?? '');
    $allowed   = VALID_TRANSITIONS[$case['status']] ?? [];
    if (!in_array($decision, $allowed)) Response::error('Invalid status transition', ['decision'=>'not allowed'], 422);
    if (empty($comments)) Response::error('Decision comments are required', ['decision_comments'=>'required'], 422);

    $cases->updateStatus((int)$params['id'], $decision, $c['user_id'], $comments);
    (new AuditLogModel())->log('Supervisor Decision', 'case', (int)$params['id'],
        ['status' => $case['status']], ['status' => $decision]);
    Response::success('Decision submitted', ['decision' => $decision]);
}

// ─────────────────────────────────────────────────────────
// Appeals API
// ─────────────────────────────────────────────────────────
// GET /appeals
if ($method === 'GET' && $path === '/appeals') {
    $c = requireJwtRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
    Response::success('Appeals retrieved', (new AppealModel())->getForSupervisor());
}

// GET /appeals/{id}
if ($method === 'GET' && matchRoute('/appeals/{id}', $path, $params)) {
    requireJwtRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
    $appeal = (new AppealModel())->findById((int)$params['id']);
    if (!$appeal) Response::notFound('Appeal not found');
    Response::success('Appeal retrieved', $appeal);
}

// PUT /appeals/{id}/decision
if ($method === 'PUT' && matchRoute('/appeals/{id}/decision', $path, $params)) {
    $c = requireJwtRole([ROLE_SUPERVISOR, ROLE_ADMIN]);
    $appeals = new AppealModel();
    $appeal  = $appeals->findById((int)$params['id']);
    if (!$appeal) Response::notFound('Appeal not found');

    $decision = $body['decision'] ?? '';
    $notes    = trim($body['decision_notes'] ?? '');
    if (!in_array($decision, ['upheld','denied'])) Response::error('Invalid decision', [], 422);
    if (strlen($notes) < 50) Response::error('Notes must be at least 50 characters', [], 422);

    $appeals->decide((int)$params['id'], $decision, $notes, $c['user_id']);
    $case = (new CaseModel())->findByApplicationId($appeal['application_id']);
    if ($case) {
        $newStatus = $decision === 'upheld' ? 'Approved' : 'Final Disposition';
        (new CaseModel())->updateStatus($case['case_id'], $newStatus, $c['user_id'], "Appeal $decision");
    }
    (new AuditLogModel())->log('Appeal Decided', 'appeal', (int)$params['id'], null, ['decision' => $decision]);
    Response::success('Appeal decision recorded', ['decision' => $decision]);
}

// ─────────────────────────────────────────────────────────
// POST /applications/{id}/wizard-step
// ─────────────────────────────────────────────────────────
if ($method === 'POST' && matchRoute('/applications/{id}/wizard-step', $path, $params)) {
    $c = requireJwtRole([ROLE_CITIZEN]);
    $appId = (int)$params['id'];
    $app = (new ApplicationModel())->findById($appId);
    if (!$app) Response::notFound('Application not found');

    $citizen = (new CitizenModel())->findByUserId($c['user_id']);
    if (!$citizen || (int)$app['citizen_id'] !== (int)$citizen['citizen_id']) Response::forbidden('Not your application');

    $step = (int)($body['step'] ?? 0);
    if ($step < 1 || $step > 6) Response::error('Invalid step number (1–6)', [], 422);

    $stepData = $body['data'] ?? [];
    if (empty($stepData)) Response::error('Step data is required', ['data' => 'required'], 422);

    (new ApplicationModel())->saveWizardStep($appId, $step, $stepData);
    (new AuditLogModel())->log('Wizard Step Saved', 'application', $appId, null, ['step' => $step]);
    Response::success('Wizard step saved', ['step' => $step, 'application_id' => $appId]);
}

// ─────────────────────────────────────────────────────────
// GET /applications/{id}/eligibility
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && matchRoute('/applications/{id}/eligibility', $path, $params)) {
    $c = requireJwt();
    $appId = (int)$params['id'];
    $app = (new ApplicationModel())->findById($appId);
    if (!$app) Response::notFound('Application not found');

    if ($c['role_id'] === ROLE_CITIZEN) {
        $citizen = (new CitizenModel())->findByUserId($c['user_id']);
        if (!$citizen || (int)$app['citizen_id'] !== (int)$citizen['citizen_id']) Response::forbidden();
    }

    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM eligibility_results WHERE application_id = ? ORDER BY result_id');
    $stmt->execute([$appId]);
    $results = $stmt->fetchAll();
    Response::success('Eligibility results retrieved', [
        'application_id'               => $appId,
        'preliminary_eligibility_result' => $app['preliminary_eligibility_result'] ?? null,
        'results'                      => $results,
    ]);
}

// ─────────────────────────────────────────────────────────
// PUT /cases/{id}/quality-action
// ─────────────────────────────────────────────────────────
if ($method === 'PUT' && matchRoute('/cases/{id}/quality-action', $path, $params)) {
    $c = requireJwtRole([ROLE_QR, ROLE_ADMIN]);
    $caseId = (int)$params['id'];
    $cases = new CaseModel();
    $case  = $cases->findById($caseId);
    if (!$case) Response::notFound('Case not found');
    if ($case['status'] !== 'Quality Review') Response::error('Case is not in Quality Review', [], 422);

    $action = $body['action'] ?? '';  // pass | return | flag
    $notes  = trim($body['notes'] ?? '');
    if (!in_array($action, ['pass', 'return', 'flag'])) Response::error('Invalid action', ['action' => 'must be pass, return, or flag'], 422);
    if (strlen($notes) < 10) Response::error('Notes must be at least 10 characters', [], 422);

    $actionMap = ['pass' => 'passed_to_supervisor', 'return' => 'returned_to_officer', 'flag' => 'flagged_with_issues'];
    (new QualityReviewModel())->logAction($caseId, $c['user_id'], $actionMap[$action], $notes);
    (new AuditLogModel())->log('Quality Review Action', 'case', $caseId, ['status' => $case['status']], ['action' => $actionMap[$action]]);

    if ($action === 'pass') {
        $cases->updateStatus($caseId, 'Supervisor Review', $c['user_id'], $notes);
    } else {
        $cases->updateStatus($caseId, 'Officer Review', $c['user_id'],
            ($action === 'flag' ? 'Flagged: ' : 'Returned: ') . $notes);
    }
    Response::success('Quality review action recorded', ['action' => $actionMap[$action]]);
}

// ─────────────────────────────────────────────────────────
// Risk & SLA API
// ─────────────────────────────────────────────────────────
// GET /applications/{id}/risk
if ($method === 'GET' && matchRoute('/applications/{id}/risk', $path, $params)) {
    requireJwtRole([ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_ADMIN, ROLE_QR]);
    $risk = (new RiskScoreModel())->getByApplication((int)$params['id']);
    if (!$risk) Response::notFound('Risk score not found');
    Response::success('Risk score retrieved', $risk);
}

// GET /reports/sla-compliance
if ($method === 'GET' && $path === '/reports/sla-compliance') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    Response::success('SLA compliance', (new SLAModel())->getSummaryCounts());
}

// GET /reports/risk-distribution
if ($method === 'GET' && $path === '/reports/risk-distribution') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    Response::success('Risk distribution', (new RiskScoreModel())->getDistribution());
}

// ─────────────────────────────────────────────────────────
// Training Defects API (admin only)
// ─────────────────────────────────────────────────────────
// GET /training-defects
if ($method === 'GET' && $path === '/training-defects') {
    requireJwtRole([ROLE_ADMIN]);
    Response::success('Defects retrieved', (new TrainingDefectModel())->getAll());
}

// PUT /training-defects/{id}
if ($method === 'PUT' && matchRoute('/training-defects/{id}', $path, $params)) {
    $c = requireJwtRole([ROLE_ADMIN]);
    $td = new TrainingDefectModel();
    $active = (int)($body['is_active'] ?? 0);
    $td->toggle((int)$params['id'], $active, $c['user_id']);
    (new AuditLogModel())->log(
        $active ? 'training_defect_enabled' : 'training_defect_disabled',
        'training_defect', (int)$params['id'], null, ['is_active' => $active]
    );
    Response::success('Defect updated');
}

// ─────────────────────────────────────────────────────────
// GET /programs
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/programs') {
    requireJwt();
    $progs = Database::getInstance()->query('SELECT program_id, program_name, program_code, description FROM programs WHERE is_active=1')->fetchAll();
    Response::success('Programs retrieved', $progs);
}

// ─────────────────────────────────────────────────────────
// GET /notifications
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/notifications') {
    $c = requireJwt();
    $notifs = (new NotificationModel())->getByUser($c['user_id']);
    Response::success('Notifications retrieved', $notifs);
}

// PUT /notifications/{id}/read
if ($method === 'PUT' && matchRoute('/notifications/{id}/read', $path, $params)) {
    $c = requireJwt();
    (new NotificationModel())->markRead((int)$params['id'], $c['user_id']);
    Response::success('Marked as read');
}

// ─────────────────────────────────────────────────────────
// GET /reports/*
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/reports/summary') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    Response::success('Report', (new ApplicationModel())->getStatusCounts());
}

if ($method === 'GET' && $path === '/reports/officer-performance') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    Response::success('Report', (new CaseModel())->getOfficerPerformance());
}

// GET /reports/processing-time
if ($method === 'GET' && $path === '/reports/processing-time') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    $db = Database::getInstance();
    $rows = $db->query("
        SELECT
            p.program_name,
            COUNT(c.case_id)                                            AS total_cases,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, NOW())), 1)    AS avg_hours_open,
            ROUND(AVG(CASE WHEN c.status IN ('Approved','Denied','Closed','Auto-Denied','Final Disposition')
                          THEN TIMESTAMPDIFF(HOUR, c.created_at, NOW()) END), 1) AS avg_hours_to_close,
            MIN(TIMESTAMPDIFF(HOUR, c.created_at, NOW()))               AS min_hours,
            MAX(TIMESTAMPDIFF(HOUR, c.created_at, NOW()))               AS max_hours
        FROM cases c
        JOIN applications a ON a.application_id = c.application_id
        JOIN programs p     ON p.program_id      = a.program_id
        GROUP BY p.program_id, p.program_name
        ORDER BY avg_hours_to_close DESC
    ")->fetchAll();
    Response::success('Processing time report', $rows);
}

// GET /reports/officer-productivity
if ($method === 'GET' && $path === '/reports/officer-productivity') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    $db   = Database::getInstance();
    $stmt = $db->prepare("
        SELECT
            u.user_id,
            CONCAT(u.first_name, ' ', u.last_name)                     AS officer_name,
            COUNT(c.case_id)                                            AS total_assigned,
            SUM(c.status IN ('Approved','Denied'))                      AS decisions_made,
            SUM(c.status NOT IN ('Approved','Denied','Closed','Auto-Denied','Final Disposition')) AS active_cases,
            ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, NOW())), 1)    AS avg_case_age_hours,
            COALESCE(SUM(st.is_breached), 0)                           AS sla_breaches
        FROM users u
        JOIN cases c  ON c.assigned_officer_id = u.user_id
        LEFT JOIN sla_tracking st ON st.case_id = c.case_id
        WHERE u.role_id = ?
        GROUP BY u.user_id, officer_name
        ORDER BY decisions_made DESC
    ");
    $stmt->execute([ROLE_OFFICER]);
    $rows = $stmt->fetchAll();
    Response::success('Officer productivity report', $rows);
}

// ─────────────────────────────────────────────────────────
// GET /audit-logs
// ─────────────────────────────────────────────────────────
if ($method === 'GET' && $path === '/audit-logs') {
    requireJwtRole([ROLE_ADMIN, ROLE_AUDITOR]);
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $audit = new AuditLogModel();
    $logs  = $audit->getAll($_GET, $page);
    $total = $audit->count($_GET);
    Response::success('Audit logs retrieved', ['logs'=>$logs,'total'=>$total,'page'=>$page]);
}

// DELETE /audit-logs/{id}
// The audit trail is append-only by design: this endpoint must always refuse.
// Defect: audit_log_deletable — the refusal is lifted and records are destroyed.
if ($method === 'DELETE' && matchRoute('/audit-logs/{id}', $path, $params)) {
    requireJwtRole([ROLE_ADMIN]);
    if (!AuditLogModel::deletionPermitted()) {
        Response::error('Audit records are immutable and cannot be deleted', [], 405);
    }
    (new AuditLogModel())->deleteById((int)$params['id']);
    Response::success('Audit log deleted');
}

// POST /auth/logout
if ($method === 'POST' && $path === '/auth/logout') {
    $c = requireJwt();
    (new AuditLogModel())->log(['action_type'=>'Logout','entity_type'=>'user','entity_id'=>$c['user_id'],'user_id'=>$c['user_id'],'username'=>$c['email'],'role'=>ROLE_NAMES[$c['role_id']]]);
    Response::success('Logged out');
}

// ── Fallback ────────────────────────────────────────────
Response::notFound('Endpoint not found');
