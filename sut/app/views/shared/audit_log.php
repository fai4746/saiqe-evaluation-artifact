<?php $pageTitle = 'Audit Log'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>Audit Log</h2>
        <div class="page-subtitle">Immutable system activity record</div>
    </div>
    <span class="badge badge-in-review fs-6 px-3 py-2"><?= number_format($total ?? count($logs)) ?> entries</span>
</div>

<!-- Filters -->
<div class="filter-bar mb-3">
    <form method="GET" class="d-flex align-items-end gap-2 flex-wrap">
        <div>
            <label for="af" class="form-label small mb-1">From</label>
            <input type="date" name="date_from" id="af" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
        </div>
        <div>
            <label for="at" class="form-label small mb-1">To</label>
            <input type="date" name="date_to" id="at" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
        </div>
        <div>
            <label for="action_type" class="form-label small mb-1">Action</label>
            <select name="action_type" id="action_type" class="form-select form-select-sm">
                <option value="">All Actions</option>
                <?php foreach (['Login','Logout','Failed Login','Registration','Application Submitted','Case Status Updated','Case Approved','Case Denied','Document Uploaded','User Created','User Updated','User Deactivated','Report Exported'] as $a): ?>
                    <option value="<?= $a ?>" <?= ($_GET['action_type'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-brand">Filter</button>
            <a href="<?= BASE_URL ?>/audit-log" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
            <div class="empty-state-text">No log entries</div>
            <div class="empty-state-sub">Try adjusting your filters.</div>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log):
                $roleCls = strtolower($log['role'] ?? '');
            ?>
                <tr>
                    <td class="text-muted small" style="white-space:nowrap"><?= htmlspecialchars(date('M d, Y g:ia', strtotime($log['created_at']))) ?></td>
                    <td class="fw-medium small"><?= htmlspecialchars($log['username'] ?? '—') ?></td>
                    <td>
                        <?php if ($log['role'] ?? null): ?>
                            <span class="badge badge-<?= $roleCls ?>"><?= htmlspecialchars($log['role']) ?></span>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars($log['action_type']) ?></td>
                    <td class="text-muted small">
                        <?= htmlspecialchars($log['entity_type'] ?? '') ?>
                        <?= $log['entity_id'] ? ' <code>#' . $log['entity_id'] . '</code>' : '' ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pages ?? 1) > 1): ?>
    <div class="card-footer bg-transparent">
        <nav aria-label="Audit log pagination">
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                           <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
