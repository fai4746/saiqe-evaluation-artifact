<?php $pageTitle = 'My Profile'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>My Profile</h2>
        <div class="page-subtitle">Manage your account information</div>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success rounded-3 mb-3" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-6">

    <!-- Account Overview -->
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-person-badge"></span>Account Overview</div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:56px;height:56px;border-radius:50%;background:var(--brand-red);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;flex-shrink:0">
                    <?= strtoupper(substr($user['first_name'],0,1)) ?>
                </div>
                <div>
                    <div class="fw-bold fs-5"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </div>
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted">Role</dt>
                <dd class="col-7">
                    <span class="badge badge-<?= strtolower($user['role_name']) ?>"><?= htmlspecialchars($user['role_name']) ?></span>
                </dd>
                <dt class="col-5 text-muted">Last Login</dt>
                <dd class="col-7"><?= $user['last_login'] ? date('M d, Y g:ia', strtotime($user['last_login'])) : 'N/A' ?></dd>
                <dt class="col-5 text-muted">Account Status</dt>
                <dd class="col-7">
                    <?php $sc = match($user['status'] ?? 'active') { 'active'=>'success','locked'=>'danger', default=>'secondary' }; ?>
                    <span class="badge bg-<?= $sc ?>"><?= ucfirst($user['status'] ?? 'active') ?></span>
                </dd>
            </dl>
        </div>
    </div>

    <!-- Update Profile -->
    <div class="card">
        <div class="card-header"><span class="card-title-icon bi bi-pencil-square"></span>Update Profile</div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/profile">
                <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

                <div class="section-heading mt-0 mb-3">Personal Information</div>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name"
                               value="<?= htmlspecialchars($user['first_name']) ?>" autocomplete="given-name">
                    </div>
                    <div class="col-6">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name"
                               value="<?= htmlspecialchars($user['last_name']) ?>" autocomplete="family-name">
                    </div>
                </div>

                <div class="section-heading mb-3">Change Password</div>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               autocomplete="current-password">
                    </div>
                    <div class="col-6">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="new-password" aria-describedby="pwd_help">
                        <div id="pwd_help" class="form-text">Leave blank to keep current.</div>
                    </div>
                    <div class="col-6">
                        <label for="confirm_password" class="form-label">Confirm New</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                               autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-brand w-100">
                    <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
            </form>
        </div>
    </div>

</div>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
