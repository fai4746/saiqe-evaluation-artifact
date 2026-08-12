<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Load config and core
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/Database.php';
require_once APP_ROOT . '/core/Session.php';
require_once APP_ROOT . '/core/Auth.php';
require_once APP_ROOT . '/core/Response.php';
require_once APP_ROOT . '/core/Router.php';

// Load models
require_once APP_ROOT . '/app/models/UserModel.php';
require_once APP_ROOT . '/app/models/CitizenModel.php';
require_once APP_ROOT . '/app/models/ApplicationModel.php';
require_once APP_ROOT . '/app/models/CaseModel.php';
require_once APP_ROOT . '/app/models/DocumentModel.php';
require_once APP_ROOT . '/app/models/NotificationModel.php';
require_once APP_ROOT . '/app/models/AuditLogModel.php';
require_once APP_ROOT . '/app/models/ProgramModel.php';
require_once APP_ROOT . '/app/models/SLAModel.php';
require_once APP_ROOT . '/app/models/RiskScoreModel.php';
require_once APP_ROOT . '/app/models/AppealModel.php';
require_once APP_ROOT . '/app/models/QualityReviewModel.php';
require_once APP_ROOT . '/app/models/SystemSettingsModel.php';
require_once APP_ROOT . '/app/models/TrainingDefectModel.php';

// Load core engines
require_once APP_ROOT . '/app/core/UploadValidator.php';
require_once APP_ROOT . '/app/core/IntakeValidator.php';
require_once APP_ROOT . '/app/core/FormRenderer.php';
require_once APP_ROOT . '/app/core/WorkflowPolicy.php';
require_once APP_ROOT . '/app/ai/ModelAdapter.php';
require_once APP_ROOT . '/app/ai/DeterministicAdapter.php';
require_once APP_ROOT . '/app/ai/ToolBroker.php';
require_once APP_ROOT . '/app/ai/CaseSummaryAssistant.php';
require_once APP_ROOT . '/app/core/EligibilityEngine.php';
require_once APP_ROOT . '/app/core/RiskScoreEngine.php';
require_once APP_ROOT . '/app/core/CaseAssignmentEngine.php';

// Load controllers
require_once APP_ROOT . '/app/controllers/AuthController.php';
require_once APP_ROOT . '/app/controllers/DashboardController.php';
require_once APP_ROOT . '/app/controllers/ApplicationController.php';
require_once APP_ROOT . '/app/controllers/WizardController.php';
require_once APP_ROOT . '/app/controllers/CaseController.php';
require_once APP_ROOT . '/app/controllers/SupervisorController.php';
require_once APP_ROOT . '/app/controllers/QualityReviewerController.php';
require_once APP_ROOT . '/app/controllers/AppealController.php';
require_once APP_ROOT . '/app/controllers/TrainingDefectController.php';
require_once APP_ROOT . '/app/controllers/DocumentController.php';
require_once APP_ROOT . '/app/controllers/UserController.php';
require_once APP_ROOT . '/app/controllers/NotificationController.php';
require_once APP_ROOT . '/app/controllers/ReportController.php';
require_once APP_ROOT . '/app/controllers/AuditLogController.php';
require_once APP_ROOT . '/app/controllers/CitizenController.php';

Session::start();

$router = new Router();

// ── Auth ────────────────────────────────────────────────
$router->get('/login',    [AuthController::class, 'showLogin']);
$router->post('/login',   [AuthController::class, 'processLogin']);
$router->get('/logout',   [AuthController::class, 'logout']);
$router->post('/logout',  [AuthController::class, 'logout']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register',[AuthController::class, 'processRegister']);

// ── Dashboards ──────────────────────────────────────────
$router->get('/dashboard/admin',            [DashboardController::class, 'admin']);
$router->get('/dashboard/officer',          [DashboardController::class, 'officer']);
$router->get('/dashboard/supervisor',       [DashboardController::class, 'supervisor']);
$router->get('/dashboard/auditor',          [DashboardController::class, 'auditor']);
$router->get('/dashboard/citizen',          [DashboardController::class, 'citizen']);
$router->get('/dashboard/quality-reviewer', [DashboardController::class, 'qualityReviewer']);
$router->get('/dashboard/stats',            [DashboardController::class, 'stats']);

// ── Application Wizard ──────────────────────────────────
$router->get('/applications/wizard',         [WizardController::class, 'start']);
$router->post('/applications/wizard/submit', [WizardController::class, 'submit']);    // must be before {step}
$router->get('/applications/wizard/{step}',  [WizardController::class, 'showStep']);
$router->post('/applications/wizard/{step}', [WizardController::class, 'saveStep']);

// ── Applications ────────────────────────────────────────
$router->get('/applications/submit',  [ApplicationController::class, 'showSubmitForm']);
$router->post('/applications/submit', [ApplicationController::class, 'submitApplication']);
$router->get('/applications/success', [ApplicationController::class, 'success']);
$router->get('/applications',         [ApplicationController::class, 'listAll']);
$router->get('/applications/{id}',    [ApplicationController::class, 'detail']);

// ── Cases ───────────────────────────────────────────────
$router->get('/cases',                [CaseController::class, 'list']);
$router->get('/cases/{id}',           [CaseController::class, 'detail']);
$router->post('/cases/{id}/status',   [CaseController::class, 'updateStatus']);
$router->post('/cases/{id}/notes',    [CaseController::class, 'addNote']);
$router->post('/cases/{id}/assign',   [CaseController::class, 'assign']);

// ── Supervisor ──────────────────────────────────────────
$router->get('/supervisor/queue',                    [SupervisorController::class, 'queue']);
$router->get('/supervisor/cases/{id}',               [SupervisorController::class, 'reviewCase']);
$router->post('/supervisor/cases/{id}/decision',     [SupervisorController::class, 'submitDecision']);
$router->get('/supervisor/appeals',                         [SupervisorController::class, 'appealQueue']);
$router->get('/supervisor/appeals/document/{id}',           [SupervisorController::class, 'serveAppealDocument']);
$router->get('/supervisor/appeals/{id}',                    [SupervisorController::class, 'reviewAppeal']);
$router->post('/supervisor/appeals/{id}/decision',          [SupervisorController::class, 'decideAppeal']);

// ── Quality Reviewer ────────────────────────────────────
$router->get('/quality-review/queue',                [QualityReviewerController::class, 'queue']);
$router->get('/quality-review/cases/{id}',           [QualityReviewerController::class, 'reviewCase']);
$router->post('/quality-review/cases/{id}/action',   [QualityReviewerController::class, 'submitAction']);

// ── Appeals (citizen) ───────────────────────────────────
$router->get('/appeals/new',          [AppealController::class, 'showForm']);
$router->post('/appeals',             [AppealController::class, 'submit']);

// ── Training Defects (admin) ────────────────────────────
$router->get('/admin/training-defects',              [TrainingDefectController::class, 'index']);
$router->post('/admin/training-defects/{id}/toggle', [TrainingDefectController::class, 'toggle']);

// ── Documents ───────────────────────────────────────────
$router->get('/documents/upload',              [DocumentController::class, 'showUpload']);
$router->post('/documents/upload',             [DocumentController::class, 'upload']);
$router->get('/documents/{id}/download',       [DocumentController::class, 'download']);

// ── Users ───────────────────────────────────────────────
$router->get('/users',                         [UserController::class, 'list']);
$router->post('/users',                        [UserController::class, 'create']);
$router->post('/users/{id}',                   [UserController::class, 'update']);
$router->post('/users/{id}/deactivate',        [UserController::class, 'deactivate']);
$router->get('/profile',                       [UserController::class, 'profile']);
$router->post('/profile',                      [UserController::class, 'updateProfile']);

// ── Notifications ───────────────────────────────────────
$router->get('/notifications',                         [NotificationController::class, 'index']);
$router->post('/notifications/{id}/read',              [NotificationController::class, 'markRead']);
$router->post('/notifications/mark-all-read',          [NotificationController::class, 'markAllRead']);
$router->get('/notifications/count',                   [NotificationController::class, 'count']);

// ── Reports ─────────────────────────────────────────────
$router->get('/reports',        [ReportController::class, 'index']);
$router->get('/reports/export', [ReportController::class, 'exportCsv']);

// ── Citizens ────────────────────────────────────────────
$router->get('/citizens', [CitizenController::class, 'list']);

// ── Audit Log ───────────────────────────────────────────
$router->get('/audit-log', [AuditLogController::class, 'index']);

// ── Root redirect ───────────────────────────────────────
$router->get('/', function() {
    if (Auth::isLoggedIn()) {
        $path = ROLE_DASHBOARDS[Session::get('role_id')] ?? '/dashboard/citizen';
        header('Location: ' . BASE_URL . $path); exit;
    }
    header('Location: ' . BASE_URL . '/login'); exit;
});

// ── Dispatch ────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = parse_url(BASE_URL, PHP_URL_PATH); // '/fccsms'
$uri    = '/' . ltrim(substr($uri, strlen($base)), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Allow POST to act as PUT/DELETE via _method override
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

$router->dispatch($method, $uri);
