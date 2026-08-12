<div class="page-header">
    <div>
        <h2>Training Defect Mode</h2>
        <div class="page-subtitle">Toggle intentional process defects for QA training exercises</div>
    </div>
    <?php if ($activeCount > 0): ?>
    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-exclamation-triangle me-1"></i><?= $activeCount ?> Active</span>
    <?php else: ?>
    <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>All Off</span>
    <?php endif; ?>
</div>

<div class="alert alert-warning rounded-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Training Mode Only.</strong> Enabling defects intentionally breaks process rules for training purposes.
    A yellow banner is displayed to all non-citizen users when any defect is active.
    <strong>Never enable these on a production system.</strong>
</div>

<?php
// DB defect_category ENUM: validation, business_rule, status_transition, api_authorization, accessibility, database_integrity
$categoryLabels = [
    'validation'          => 'Validation',
    'business_rule'       => 'Business Rule',
    'status_transition'   => 'Status Transition',
    'api_authorization'   => 'API / Authorization',
    'accessibility'       => 'Accessibility',
    'database_integrity'  => 'Database Integrity',
];
$categories = array_fill_keys(array_keys($categoryLabels), []);
foreach ($defects as $d) {
    $cat = $d['defect_category'] ?? 'business_rule';
    if (!isset($categories[$cat])) $categories[$cat] = [];
    $categories[$cat][] = $d;
}
?>

<?php foreach ($categories as $catKey => $catDefects): ?>
<?php if (empty($catDefects)) continue; ?>
<?php $catName = $categoryLabels[$catKey] ?? ucwords(str_replace('_',' ',$catKey)); ?>
<div class="card mb-3">
    <div class="card-header"><span class="card-title-icon bi bi-bug"></span><?= htmlspecialchars($catName) ?> Defects</div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
        <?php foreach ($catDefects as $defect): ?>
        <li class="list-group-item py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong><?= htmlspecialchars($defect['defect_name']) ?></strong>
                        <code class="small text-muted"><?= htmlspecialchars($defect['defect_key']) ?></code>
                        <?php if ($defect['is_active']): ?>
                        <span class="badge bg-warning text-dark">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted"><?= htmlspecialchars($defect['defect_description'] ?? '') ?></div>
                    <?php if ($defect['is_active'] && $defect['activated_at']): ?>
                    <div class="small text-warning mt-1">
                        <i class="bi bi-clock me-1"></i>Enabled <?= htmlspecialchars($defect['activated_at']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= BASE_URL ?>/admin/training-defects/<?= (int)$defect['defect_id'] ?>/toggle">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                    <button type="submit"
                            class="btn <?= $defect['is_active'] ? 'btn-warning' : 'btn-outline-secondary' ?> btn-sm"
                            onclick="return confirm('<?= $defect['is_active'] ? 'Disable' : 'Enable' ?> defect: <?= addslashes($defect['defect_name']) ?>?')">
                        <?php if ($defect['is_active']): ?>
                            <i class="bi bi-toggle-on me-1"></i>Disable
                        <?php else: ?>
                            <i class="bi bi-toggle-off me-1"></i>Enable
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endforeach; ?>
