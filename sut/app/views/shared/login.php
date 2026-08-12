<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title>Sign In — <?= APP_SHORT ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/app.css">
</head>
<body class="auth-page d-flex align-items-center justify-content-center py-4">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
            <div class="auth-card">
                <!-- Header with logo -->
                <div class="auth-card-header">
                    <img src="<?= BASE_URL ?>/public/img/agile1tech-logo.svg" alt="Agile1Tech FCCSMS" height="42">
                    <p class="text-white-50 small mt-2 mb-0">Federal Case &amp; Citizen Service Management</p>
                </div>

                <div class="p-4">
                    <h1 class="h5 fw-bold mb-4 text-center" style="color:var(--brand-dark)">Sign In to Your Account</h1>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert" aria-live="assertive">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php $success = Session::get('reg_success'); Session::delete('reg_success'); ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2 py-2" role="alert">
                            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                            <span><?= htmlspecialchars($success) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= BASE_URL ?>/login" novalidate id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" class="form-control border-start-0 ps-0" id="email" name="email"
                                       autocomplete="email" required aria-required="true" placeholder="you@agency.gov">
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label">Password</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" class="form-control border-start-0 border-end-0 ps-0" id="password"
                                       name="password" autocomplete="current-password" required aria-required="true">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePwd" aria-label="Show/hide password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </form>

                    <hr class="my-3">
                    <p class="text-center text-muted small mb-0">
                        New citizen? <a href="<?= BASE_URL ?>/register" class="text-decoration-none fw-semibold" style="color:var(--brand-red)">Create Account</a>
                    </p>
                </div>
            </div>

            <!-- Quick-access credentials for training -->
            <div class="card mt-3 border-0 shadow-sm" style="border-radius:12px;background:rgba(255,255,255,0.08)">
                <div class="card-body p-3">
                    <p class="text-white-50 small fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i>Training Accounts (password: <code class="text-warning">Test@1234</code>)</p>
                    <div class="row g-1 text-center">
                        <?php foreach ([
                            ['admin@fccsms.gov',      'Admin',      'badge-admin'],
                            ['officer1@fccsms.gov',   'Officer',    'badge-officer'],
                            ['supervisor@fccsms.gov', 'Supervisor', 'badge-supervisor'],
                            ['auditor@fccsms.gov',    'Auditor',    'badge-auditor'],
                            ['john@example.com',      'Citizen',    'badge-citizen'],
                        ] as [$email, $label, $cls]): ?>
                        <div class="col">
                            <button type="button" class="btn btn-sm w-100 py-1 quick-login badge <?= $cls ?>"
                                    data-email="<?= $email ?>" style="font-size:0.68rem;cursor:pointer;border:none">
                                <?= $label ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show/hide password
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwd = document.getElementById('password');
    const icon = this.querySelector('i');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
    icon.className = pwd.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

// Quick-fill training credentials
document.querySelectorAll('.quick-login').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('email').value    = this.dataset.email;
        document.getElementById('password').value = 'Test@1234';
        document.getElementById('loginForm').submit();
    });
});

// Basic client validation
document.getElementById('loginForm').addEventListener('submit', function (e) {
    if (!document.getElementById('email').value || !document.getElementById('password').value) {
        e.preventDefault();
        alert('Please enter both email and password.');
    }
});
</script>
</body>
</html>
