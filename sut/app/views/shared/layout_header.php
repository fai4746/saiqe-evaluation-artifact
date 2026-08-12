<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= APP_SHORT ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/app.css">
</head>
<body>

<?php
$role     = Session::get('role_id');
$roleName = Session::get('role_name', 'citizen');
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
function navActive(string $prefix): string {
    global $currentPath;
    return str_starts_with($currentPath, parse_url(BASE_URL, PHP_URL_PATH) . $prefix) ? ' active' : '';
}

// Training defect banner — show to any non-citizen logged-in user when any defect is active
$showDefectBanner = Auth::isLoggedIn()
    && $role !== ROLE_CITIZEN
    && !empty(TrainingDefectModel::loadActive());
?>

<?php if ($showDefectBanner): ?>
<div class="training-defect-banner" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>TRAINING MODE</strong> — One or more process defects are currently active. Do not use in production.
    <?php if ($role === ROLE_ADMIN): ?>
    <a href="<?= BASE_URL ?>/admin/training-defects" class="ms-2 text-warning fw-bold">Manage Defects</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<nav class="navbar navbar-agile navbar-expand-lg" role="navigation" aria-label="Main navigation">
    <div class="container-fluid">
        <!-- Logo -->
        <a class="navbar-brand me-3" href="<?= BASE_URL ?>/dashboard/<?= $roleName ?>">
            <img src="<?= BASE_URL ?>/public/img/agile1tech-logo.svg"
                 alt="Agile1Tech FCCSMS Home" height="36">
        </a>

        <button class="navbar-toggler border-secondary" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list text-white fs-5"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto align-items-lg-center gap-1">
                <?php if ($role === ROLE_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/admin"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/users') ?>" href="<?= BASE_URL ?>/users"><i class="bi bi-people me-1"></i>Users</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/citizens') ?>" href="<?= BASE_URL ?>/citizens"><i class="bi bi-person-vcard me-1"></i>Citizens</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/cases') ?>" href="<?= BASE_URL ?>/cases"><i class="bi bi-folder2-open me-1"></i>Cases</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/reports') ?>" href="<?= BASE_URL ?>/reports"><i class="bi bi-bar-chart me-1"></i>Reports</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/audit-log') ?>" href="<?= BASE_URL ?>/audit-log"><i class="bi bi-journal-text me-1"></i>Audit Log</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/admin/training-defects') ?>" href="<?= BASE_URL ?>/admin/training-defects"><i class="bi bi-bug me-1"></i>Training</a></li>

                <?php elseif ($role === ROLE_OFFICER): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/officer"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/cases') ?>" href="<?= BASE_URL ?>/cases"><i class="bi bi-folder2-open me-1"></i>My Cases</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/applications') ?>" href="<?= BASE_URL ?>/applications"><i class="bi bi-list-ul me-1"></i>Applications</a></li>

                <?php elseif ($role === ROLE_SUPERVISOR): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/supervisor"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/supervisor/queue') ?>" href="<?= BASE_URL ?>/supervisor/queue"><i class="bi bi-clipboard-check me-1"></i>Review Queue</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/supervisor/appeals') ?>" href="<?= BASE_URL ?>/supervisor/appeals"><i class="bi bi-megaphone me-1"></i>Appeals</a></li>

                <?php elseif ($role === ROLE_AUDITOR): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/auditor"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/reports') ?>" href="<?= BASE_URL ?>/reports"><i class="bi bi-bar-chart me-1"></i>Reports</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/audit-log') ?>" href="<?= BASE_URL ?>/audit-log"><i class="bi bi-journal-text me-1"></i>Audit Log</a></li>

                <?php elseif ($role === ROLE_QR): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/quality-reviewer"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/quality-review') ?>" href="<?= BASE_URL ?>/quality-review/queue"><i class="bi bi-shield-check me-1"></i>QR Queue</a></li>

                <?php elseif ($role === ROLE_CITIZEN): ?>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/dashboard') ?>" href="<?= BASE_URL ?>/dashboard/citizen"><i class="bi bi-house me-1"></i>My Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/applications/wizard') ?>" href="<?= BASE_URL ?>/applications/wizard"><i class="bi bi-plus-circle me-1"></i>New Application</a></li>
                    <li class="nav-item"><a class="nav-link<?= navActive('/fccsms/documents') ?>" href="<?= BASE_URL ?>/documents/upload"><i class="bi bi-upload me-1"></i>Upload Docs</a></li>
                <?php endif; ?>
            </ul>

            <!-- Right side -->
            <ul class="navbar-nav align-items-lg-center gap-1 ms-auto">
                <?php if (Auth::isLoggedIn()):
                    $unread = (new NotificationModel())->countUnread(Session::get('user_id'));
                ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="<?= BASE_URL ?>/notifications" aria-label="Notifications (<?= $unread ?> unread)">
                        <i class="bi bi-bell<?= $unread ? '-fill text-warning' : '' ?> fs-5"></i>
                        <?php if ($unread > 0): ?>
                        <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-live="polite"><?= $unread ?></span>
                        <?php else: ?>
                        <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-live="polite" style="display:none"></span>
                        <?php endif; ?>
                    </a>
                </li>

                <li class="nav-item dropdown ms-1">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button"
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex align-items-center justify-content-center rounded-circle bg-secondary text-white"
                              style="width:30px;height:30px;font-size:0.8rem;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr(Session::get('first_name','?'), 0, 1)) ?>
                        </span>
                        <span class="d-none d-lg-inline">
                            <?= htmlspecialchars(Session::get('first_name')) ?>
                            <span class="badge-role ms-1"><?= $roleName ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                        <li class="dropdown-header small text-muted px-3 pt-2"><?= htmlspecialchars(Session::get('email')) ?></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/notifications"><i class="bi bi-bell me-2"></i>Notifications <?php if($unread>0): ?><span class="badge bg-danger ms-1"><?=$unread?></span><?php endif;?></a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout">
                                <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="page-wrapper">
<div class="page-content">
