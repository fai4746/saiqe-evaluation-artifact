<?php
// Central application configuration

define('APP_NAME',    'Agile1Tech Federal Case & Citizen Service Management System');
define('APP_SHORT',   'Agile1Tech FCCSMS');
define('APP_BRAND',   'Agile1Tech');
define('APP_VERSION', '2.0');

// Per-environment overrides (BASE_URL, DB credentials) live in an untracked
// config.local.php created on the server, so production secrets never sit in
// the repository. Local XAMPP falls back to the defaults below.
$localConfig = __DIR__ . '/config.local.php';
$overrides   = is_file($localConfig) ? (require $localConfig) : [];

define('BASE_URL',   $overrides['base_url'] ?? 'http://localhost/fccsms');

// Database
define('DB_HOST',    $overrides['db_host'] ?? 'localhost');
define('DB_PORT',    $overrides['db_port'] ?? 3306);
define('DB_NAME',    $overrides['db_name'] ?? 'fccsms');
define('DB_USER',    $overrides['db_user'] ?? 'root');
define('DB_PASS',    $overrides['db_pass'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_TIMEOUT',   1800); // 30 minutes
define('MAX_FAILED_LOGINS', 5);

// JWT
define('JWT_SECRET', 'FCCSMS_JWT_SECRET_CHANGE_IN_PRODUCTION_2026');
define('JWT_EXPIRY',  3600); // 60 minutes

// File uploads
define('UPLOAD_DIR',    dirname(__DIR__) . '/uploads/');
define('APPEAL_UPLOAD_DIR', dirname(__DIR__) . '/uploads/appeals/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('MAX_FILES',     10);
define('ALLOWED_MIME_TYPES', [
    'application/pdf'                                                            => 'pdf',
    'application/msword'                                                         => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'    => 'docx',
    'image/jpeg'                                                                 => 'jpg',
    'image/png'                                                                  => 'png',
]);

// ── Application statuses (v2 complete list) ──────────────────
define('APP_STATUSES', [
    'Draft', 'Submitted', 'Eligibility Review', 'Pending Verification',
    'Document Requested', 'Document Received', 'Officer Review', 'Quality Review',
    'Supervisor Review', 'Approved', 'Denied', 'Closed', 'Appealed',
    'Appeal Review', 'Final Disposition', 'Auto-Denied',
]);

// Valid status transitions (enforced in PHP + API)
define('VALID_TRANSITIONS', [
    'Draft'               => ['Submitted'],
    'Submitted'           => ['Eligibility Review'],
    'Eligibility Review'  => ['Pending Verification', 'Auto-Denied'],
    'Pending Verification'=> ['Document Requested', 'Officer Review'],
    'Document Requested'  => ['Document Received'],
    'Document Received'   => ['Officer Review'],
    'Officer Review'      => ['Quality Review'],
    'Quality Review'      => ['Supervisor Review', 'Officer Review'],
    'Supervisor Review'   => ['Approved', 'Denied', 'Quality Review'],
    'Approved'            => ['Closed'],
    'Denied'              => ['Appealed', 'Closed'],
    'Appealed'            => ['Appeal Review'],
    'Appeal Review'       => ['Final Disposition'],
    'Final Disposition'   => ['Closed'],
    'Auto-Denied'         => [],
    'Closed'              => [],
]);

// Statuses that count toward the active-application limit
define('ACTIVE_STATUSES', [
    'Submitted', 'Eligibility Review', 'Pending Verification',
    'Document Requested', 'Document Received', 'Officer Review',
    'Quality Review', 'Supervisor Review',
]);

// Statuses that are "closed" / terminal
define('TERMINAL_STATUSES', ['Approved', 'Denied', 'Closed', 'Auto-Denied', 'Final Disposition']);

// ── Roles ─────────────────────────────────────────────────────
define('ROLE_ADMIN',    1);
define('ROLE_OFFICER',  2);
define('ROLE_SUPERVISOR', 3);
define('ROLE_AUDITOR',  4);
define('ROLE_CITIZEN',  5);
define('ROLE_QR',       6); // Quality Reviewer

define('ROLE_NAMES', [
    ROLE_ADMIN      => 'admin',
    ROLE_OFFICER    => 'officer',
    ROLE_SUPERVISOR => 'supervisor',
    ROLE_AUDITOR    => 'auditor',
    ROLE_CITIZEN    => 'citizen',
    ROLE_QR         => 'quality_reviewer',
]);

define('ROLE_DASHBOARDS', [
    ROLE_ADMIN      => '/dashboard/admin',
    ROLE_OFFICER    => '/dashboard/officer',
    ROLE_SUPERVISOR => '/dashboard/supervisor',
    ROLE_AUDITOR    => '/dashboard/auditor',
    ROLE_CITIZEN    => '/dashboard/citizen',
    ROLE_QR         => '/dashboard/quality-reviewer',
]);

// ── Poverty threshold table (2025 federal, 48 contiguous states) ──
define('POVERTY_THRESHOLDS', [
    1 => 15060, 2 => 20440, 3 => 25820, 4 => 31200,
    5 => 36580, 6 => 41960, 7 => 47340, 8 => 52720,
]);
define('POVERTY_ADDITIONAL_PER_PERSON', 5380);

// ── Training-defect switch ───────────────────────────────────
/**
 * Null-safe wrapper around TrainingDefectModel::isActive().
 *
 * Injection points inside core/ run in contexts where the model class may not
 * have been required yet (the API front controller, CLI harness bootstraps).
 * This keeps a defect check from turning into a fatal error there.
 */
function defectActive(string $key): bool {
    if (!class_exists('TrainingDefectModel', false)) return false;
    return TrainingDefectModel::isActive($key);
}

// ── Status → badge CSS class helper ──────────────────────────
function statusBadgeClass(string $status): string {
    return match($status) {
        'Draft'                => 'badge-draft',
        'Submitted'            => 'badge-submitted',
        'Eligibility Review'   => 'badge-eligibility-review',
        'Pending Verification' => 'badge-pending-verification',
        'Document Requested'   => 'badge-document-requested',
        'Document Received'    => 'badge-document-received',
        'Officer Review'       => 'badge-officer-review',
        'Quality Review'       => 'badge-quality-review',
        'Supervisor Review'    => 'badge-supervisor-review',
        'Approved'             => 'badge-approved',
        'Denied'               => 'badge-denied',
        'Closed'               => 'badge-closed',
        'Appealed'             => 'badge-appealed',
        'Appeal Review'        => 'badge-appeal-review',
        'Final Disposition'    => 'badge-final-disposition',
        'Auto-Denied'          => 'badge-auto-denied',
        default                => 'badge-secondary',
    };
}

// ── Risk level → badge CSS class ─────────────────────────────
function riskBadgeClass(string $level): string {
    return match($level) {
        'low'      => 'badge-risk-low',
        'medium'   => 'badge-risk-medium',
        'high'     => 'badge-risk-high',
        'critical' => 'badge-risk-critical',
        default    => 'bg-secondary',
    };
}

// ── SLA badge helper ──────────────────────────────────────────
/** Returns full badge HTML from an SLA row array (with sla_deadline, warning_date, is_breached). */
function slaBadge(?array $sla): string {
    if (!$sla) return '';
    $now = new DateTime();
    if (!empty($sla['is_breached'])) {
        return '<span class="badge badge-sla-breached">Breached</span>';
    }
    try {
        $deadline = new DateTime($sla['sla_deadline'] ?? $sla['deadline_at'] ?? 'now');
        $warning  = new DateTime($sla['warning_date'] ?? 'now');
        $diff = (int)$now->diff($deadline)->format('%r%a');
        if ($diff < 0) {
            return '<span class="badge badge-sla-breached">Overdue ' . abs($diff) . 'd</span>';
        }
        if ($now >= $warning) {
            return '<span class="badge badge-sla-warning">' . $diff . 'd left</span>';
        }
        return '<span class="badge badge-sla-ok">' . $diff . 'd left</span>';
    } catch (Exception $e) {
        return '';
    }
}

/** Returns a CSS badge class from an SLA status string: on_track | warning | breached */
function slaBadgeClass(string $status): string {
    return match(strtolower($status)) {
        'breached'    => 'badge-sla-breached',
        'warning'     => 'badge-sla-warning',
        'on_track', 'ok' => 'badge-sla-ok',
        default       => 'badge-sla-ok',
    };
}
