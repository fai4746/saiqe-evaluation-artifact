<?php $pageTitle = 'Citizens'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-person-vcard me-2" style="color:var(--brand-red)"></i>Citizen Registry</h2>
        <div class="page-subtitle">All registered citizens</div>
    </div>
</div>

<div class="filter-bar d-flex flex-wrap gap-2 align-items-end">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div class="search-input-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Name, email, SSN last 4…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="width:240px">
        </div>
        <button type="submit" class="btn btn-brand btn-sm">Search</button>
        <a href="<?= BASE_URL ?>/citizens" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>
    <span class="ms-auto text-muted small"><?= count($citizens) ?> citizen(s)</span>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Phone</th><th>City/State</th><th>DOB</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($citizens)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-people"></i>No citizens found.</div></td></tr>
            <?php else: ?>
                <?php foreach ($citizens as $c): ?>
                <tr>
                    <td class="fw-medium"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['phone']) ?></td>
                    <td><?= htmlspecialchars($c['address_city'].', '.$c['address_state']) ?></td>
                    <td class="text-muted small"><?= date('M d, Y', strtotime($c['date_of_birth'])) ?></td>
                    <td>
                        <span class="badge <?= $c['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                            <?= htmlspecialchars($c['status']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/applications?citizen_id=<?= $c['citizen_id'] ?>" class="btn btn-sm btn-outline-primary">Applications</a>
                        <?php if ($c['status'] === 'active'): ?>
                        <form method="POST" action="<?= BASE_URL ?>/users/<?= $c['user_id'] ?>/deactivate" class="d-inline"
                              onsubmit="return confirm('Deactivate this citizen?')">
                            <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
