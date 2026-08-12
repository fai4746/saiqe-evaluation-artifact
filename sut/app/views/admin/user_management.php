<?php $pageTitle = 'User Management'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>User Management</h2>
        <div class="page-subtitle">Internal staff accounts</div>
    </div>
    <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus me-1"></i>Add User
    </button>
</div>

<?php $success = Session::get('user_success'); Session::delete('user_success'); ?>
<?php if ($success): ?>
    <div class="alert alert-success rounded-3 mb-3" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Filter bar -->
<div class="filter-bar mb-3">
    <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
        <div>
            <label for="role_id" class="form-label small mb-1">Role</label>
            <select name="role_id" id="role_id" class="form-select form-select-sm">
                <option value="">All Roles</option>
                <?php foreach ([1=>'Admin',2=>'Officer',3=>'Supervisor',4=>'Auditor',5=>'Citizen'] as $id => $name): ?>
                    <option value="<?= $id ?>" <?= ($_GET['role_id']??'') == $id ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status_filter" class="form-label small mb-1">Status</label>
            <select name="status" id="status_filter" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="active" <?= ($_GET['status']??'') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($_GET['status']??'') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="locked" <?= ($_GET['status']??'') === 'locked' ? 'selected' : '' ?>>Locked</option>
            </select>
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-brand">Filter</button>
            <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-people"></i></div>
            <div class="empty-state-text">No users found</div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $roleCls = strtolower($u['role_name']);
                $statusCls = match($u['status']) { 'active'=>'success','locked'=>'danger', default=>'secondary' };
            ?>
            <tr>
                <td class="fw-medium"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge badge-<?= $roleCls ?>"><?= htmlspecialchars($u['role_name']) ?></span></td>
                <td><span class="badge bg-<?= $statusCls ?>"><?= ucfirst(htmlspecialchars($u['status'])) ?></span></td>
                <td class="text-muted small"><?= $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) : '—' ?></td>
                <td class="text-muted small"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['status'] !== 'inactive' && $u['user_id'] !== Session::get('user_id')): ?>
                    <form method="POST" action="<?= BASE_URL ?>/users/<?= $u['user_id'] ?>/deactivate" class="d-inline" data-confirm="Deactivate <?= htmlspecialchars($u['first_name']) ?>?">
                        <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/users">
                <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                <div class="modal-header" style="background:var(--brand-dark);color:#fff">
                    <h3 class="modal-title h5" id="addUserModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Add Internal User
                    </h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger rounded-3">
                            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="m_first" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="m_first" name="first_name" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label for="m_last" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="m_last" name="last_name" required autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label for="m_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="m_email" name="email" required autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label for="m_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="m_role" name="role_id" required>
                                <option value="">Select role…</option>
                                <option value="2">Officer</option>
                                <option value="3">Supervisor</option>
                                <option value="4">Auditor</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="m_pwd" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="m_pwd" name="password" minlength="8" required autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new bootstrap.Modal(document.getElementById('addUserModal')).show();
});
</script>
<?php endif; ?>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
