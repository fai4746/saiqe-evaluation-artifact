<?php $pageTitle = 'Applications'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>Applications</h2>
        <div class="page-subtitle">All submitted service applications</div>
    </div>
</div>

<!-- Filters -->
<div class="filter-bar mb-3">
    <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
        <div>
            <label for="status" class="form-label small mb-1">Status</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (APP_STATUSES as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="program_id" class="form-label small mb-1">Program</label>
            <select name="program_id" id="program_id" class="form-select form-select-sm">
                <option value="">All Programs</option>
                <?php foreach ($programs as $p): ?>
                    <option value="<?= $p['program_id'] ?>" <?= ($_GET['program_id'] ?? '') == $p['program_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['program_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="date_from" class="form-label small mb-1">From</label>
            <input type="date" name="date_from" id="date_from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>
        <div>
            <label for="date_to" class="form-label small mb-1">To</label>
            <input type="date" name="date_to" id="date_to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-brand">Filter</button>
            <a href="<?= BASE_URL ?>/applications" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($apps)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
            <div class="empty-state-text">No applications found</div>
            <div class="empty-state-sub">Try adjusting your filters.</div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover table-clickable mb-0">
            <thead>
                <tr>
                    <th>App #</th>
                    <th>Program</th>
                    <th>Citizen</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($apps as $app):
                $statusSlug = strtolower(str_replace([' ','/'], ['-','-'], $app['status']));
            ?>
            <tr onclick="location.href='<?= BASE_URL ?>/applications/<?= $app['application_id'] ?>'">
                <td><code><?= htmlspecialchars($app['application_number']) ?></code></td>
                <td><?= htmlspecialchars($app['program_name']) ?></td>
                <td class="fw-medium"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                <td>
                    <?php $urgency = $app['urgency_level'] ?? 'normal'; ?>
                    <span class="badge badge-<?= $urgency ?>"><?= ucfirst($urgency) ?></span>
                </td>
                <td><span class="badge badge-<?= $statusSlug ?>"><?= htmlspecialchars($app['status']) ?></span></td>
                <td class="text-muted small"><?= date('M d, Y', strtotime($app['submitted_at'])) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/applications/<?= $app['application_id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">
                        View
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
