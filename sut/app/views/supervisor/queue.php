<?php // pageTitle + layout set by SupervisorController ?>

<div class="page-header">
    <div>
        <h2>Review Queue</h2>
        <div class="page-subtitle">Cases awaiting your final decision</div>
    </div>
    <?php if (!empty($queue)): ?>
        <span class="badge badge-supervisor-review fs-6 px-3 py-2"><?= count($queue) ?> Pending</span>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($queue)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-check2-all"></i></div>
            <div class="empty-state-text">Queue is clear</div>
            <div class="empty-state-sub">All cases have been decided.</div>
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
                    <th>Recommendation</th>
                    <th>Urgency</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($queue as $c): ?>
            <tr onclick="location.href='<?= BASE_URL ?>/supervisor/cases/<?= $c['case_id'] ?>'">
                <td><code><?= htmlspecialchars($c['application_number']) ?></code></td>
                <td><?= htmlspecialchars($c['program_name']) ?></td>
                <td class="fw-medium"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars(trim(($c['officer_first']??'') . ' ' . ($c['officer_last']??''))) ?: '<span class="text-muted">Unassigned</span>' ?></td>
                <td><span class="badge <?= statusBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                <td>
                    <?php $urgency = $c['urgency_level'] ?? 'normal'; ?>
                    <span class="badge badge-<?= $urgency ?>"><?= ucfirst($urgency) ?></span>
                </td>
                <td class="text-muted small"><?= date('M d, Y', strtotime($c['submitted_at'])) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/supervisor/cases/<?= $c['case_id'] ?>" class="btn btn-sm btn-brand" onclick="event.stopPropagation()">
                        <i class="bi bi-gavel me-1"></i>Decide
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

