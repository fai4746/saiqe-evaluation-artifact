<div class="page-header">
    <div>
        <h2>Appeal Queue</h2>
        <div class="page-subtitle">Pending citizen appeals awaiting supervisor review</div>
    </div>
    <span class="badge bg-danger fs-6"><?= count($appeals ?? []) ?> pending</span>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($appeals)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
            <div class="empty-state-text">No pending appeals</div>
            <div class="empty-state-sub">All appeals have been resolved.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Appeal #</th>
                        <th>Application</th>
                        <th>Citizen</th>
                        <th>Filed</th>
                        <th>Window Expires</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appeals as $appeal): ?>
                <tr>
                    <td><code>#<?= (int)$appeal['appeal_id'] ?></code></td>
                    <td><span class="font-monospace small"><?= htmlspecialchars($appeal['application_number'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars(($appeal['citizen_first']??'').' '.($appeal['citizen_last']??'')) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars($appeal['submitted_at'] ?? '—') ?></td>
                    <td class="small <?= (!empty($appeal['appeal_window_expires_at']) && strtotime($appeal['appeal_window_expires_at']) < time() + 86400*3) ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= htmlspecialchars($appeal['appeal_window_expires_at'] ?? '—') ?>
                    </td>
                    <td>
                        <?php $dec = $appeal['decision'] ?? 'pending'; ?>
                        <span class="badge <?= $dec === 'pending' ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                            <?= ucfirst(htmlspecialchars($dec)) ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/supervisor/appeals/<?= (int)$appeal['appeal_id'] ?>" class="btn btn-sm btn-outline-brand">
                            Review <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
