<?php // pageTitle + layout set by CaseController ?>

<div class="page-header">
    <div>
        <h2>Cases</h2>
        <div class="page-subtitle">All service cases in the system</div>
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
        <div class="d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-brand">Filter</button>
            <a href="<?= BASE_URL ?>/cases" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <?php if (empty($cases)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-folder2"></i></div>
            <div class="empty-state-text">No cases found</div>
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
                    <th>Officer</th>
                    <th>Status</th>
                    <th>Urgency</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cases as $c):
                $statusSlug = strtolower(str_replace([' ','/'], ['-','-'], $c['status']));
            ?>
            <tr onclick="location.href='<?= BASE_URL ?>/cases/<?= $c['case_id'] ?>'">
                <td><code><?= htmlspecialchars($c['application_number']) ?></code></td>
                <td><?= htmlspecialchars($c['program_name']) ?></td>
                <td class="fw-medium"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                <td class="text-muted small">
                    <?= htmlspecialchars(trim(($c['officer_first']??'') . ' ' . ($c['officer_last']??''))) ?: '<span class="text-muted fst-italic">Unassigned</span>' ?>
                </td>
                <td><span class="badge badge-<?= $statusSlug ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                <td>
                    <?php $urgency = $c['urgency_level'] ?? 'normal'; ?>
                    <span class="badge badge-<?= $urgency ?>"><?= ucfirst($urgency) ?></span>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/cases/<?= $c['case_id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">
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

