<?php $pageTitle = 'Create Account'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= APP_SHORT ?> — Create Account</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/app.css">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width:700px">
    <div class="auth-card-header">
        <img src="<?= BASE_URL ?>/public/img/agile1tech-logo.svg" alt="Agile1Tech" height="40" class="mb-3">
        <div class="text-white-50 small">Citizen Self-Service Portal</div>
    </div>
    <div class="p-4">

        <h2 class="h5 fw-bold mb-1">Create Your Account</h2>
        <p class="text-muted small mb-4">Register to access federal citizen services</p>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger rounded-3 mb-3" role="alert">
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/register" novalidate id="regForm">
            <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

            <div class="section-heading mt-0 mb-3">Account Information</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>"
                           id="first_name" name="first_name"
                           value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                           required aria-required="true" autocomplete="given-name">
                    <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['first_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>"
                           id="last_name" name="last_name"
                           value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                           required aria-required="true" autocomplete="family-name">
                    <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['last_name']) ?></div><?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email" name="email"
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                               required aria-required="true" autocomplete="email">
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
                        <input type="date" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>"
                               id="dob" name="dob"
                               value="<?= htmlspecialchars($old['dob'] ?? '') ?>"
                               required aria-required="true">
                        <?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['dob']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               id="password" name="password"
                               required aria-required="true" autocomplete="new-password"
                               aria-describedby="pwd_help">
                        <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Show password">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                        <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div id="pwd_help" class="form-text">Minimum 8 characters.</div>
                </div>
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                               id="confirm_password" name="confirm_password"
                               required aria-required="true" autocomplete="new-password">
                        <?php if (isset($errors['confirm_password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section-heading mb-3">Contact Information</div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               id="phone" name="phone"
                               value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                               placeholder="(555) 555-5555" required aria-required="true" autocomplete="tel">
                        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['phone']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="ssn_last4" class="form-label">SSN Last 4 Digits <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                        <input type="text" class="form-control <?= isset($errors['ssn_last4']) ? 'is-invalid' : '' ?>"
                               id="ssn_last4" name="ssn_last4"
                               value="<?= htmlspecialchars($old['ssn_last4'] ?? '') ?>"
                               maxlength="4" pattern="\d{4}" placeholder="••••"
                               required aria-required="true">
                        <?php if (isset($errors['ssn_last4'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['ssn_last4']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="street" class="form-label">Street Address <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['street']) ? 'is-invalid' : '' ?>"
                       id="street" name="street"
                       value="<?= htmlspecialchars($old['street'] ?? '') ?>"
                       required aria-required="true" autocomplete="street-address">
                <?php if (isset($errors['street'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['street']) ?></div><?php endif; ?>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
                           id="city" name="city"
                           value="<?= htmlspecialchars($old['city'] ?? '') ?>"
                           required aria-required="true" autocomplete="address-level2">
                    <?php if (isset($errors['city'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['city']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['state']) ? 'is-invalid' : '' ?>"
                           id="state" name="state"
                           value="<?= htmlspecialchars($old['state'] ?? '') ?>"
                           maxlength="2" placeholder="IL"
                           required aria-required="true" autocomplete="address-level1">
                    <?php if (isset($errors['state'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['state']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="zip" class="form-label">ZIP Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['zip']) ? 'is-invalid' : '' ?>"
                           id="zip" name="zip"
                           value="<?= htmlspecialchars($old['zip'] ?? '') ?>"
                           placeholder="62701"
                           required aria-required="true" autocomplete="postal-code">
                    <?php if (isset($errors['zip'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['zip']) ?></div><?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-brand w-100 py-2 mb-3">
                <i class="bi bi-person-plus me-1"></i>Create Account
            </button>
        </form>

        <p class="text-center text-muted small mb-0">
            Already have an account? <a href="<?= BASE_URL ?>/login" class="text-decoration-none fw-semibold">Sign in</a>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const togglePwd = document.getElementById('togglePwd');
const pwdField = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');
if (togglePwd) {
    togglePwd.addEventListener('click', function() {
        const show = pwdField.type === 'password';
        pwdField.type = show ? 'text' : 'password';
        eyeIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
}
</script>
</body>
</html>
