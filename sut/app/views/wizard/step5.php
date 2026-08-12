<?php
// Wizard step 5 — Document Upload
$s5 = $data['step5'] ?? [];
$categories = [
    'identity'    => ['label' => 'Identity Documents',    'icon' => 'bi-person-badge', 'hint' => 'Government-issued ID (driver\'s license, passport, state ID)'],
    'income'      => ['label' => 'Proof of Income',       'icon' => 'bi-cash',         'hint' => 'Pay stubs, W-2, tax returns, benefit award letters'],
    'residence'   => ['label' => 'Proof of Residence',    'icon' => 'bi-house',        'hint' => 'Utility bill, lease, bank statement with address (last 90 days)'],
    'household'   => ['label' => 'Household Documents',   'icon' => 'bi-people',       'hint' => 'Birth certificates, school records, dependency verification'],
    'other'       => ['label' => 'Supporting Documents',  'icon' => 'bi-file-earmark', 'hint' => 'Any additional documents supporting your application'],
];
$existingDocs = $data['existing_docs'] ?? [];
$docsByCategory = [];
foreach ($existingDocs as $doc) { $docsByCategory[$doc['document_category']][] = $doc; }
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 5 — Documents</h2><div class="page-subtitle">Upload supporting documents for your application</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="alert alert-info rounded-3 mb-3">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Accepted formats:</strong> PDF, JPG, PNG &mdash; Max 10 MB per file. At least one <strong>Identity</strong> document is required.
</div>

<form method="POST" action="<?= BASE_URL ?>/applications/wizard/5" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

    <?php foreach ($categories as $catKey => $cat): ?>
    <div class="card mb-3">
        <div class="card-header">
            <span class="card-title-icon bi <?= $cat['icon'] ?>"></span>
            <?= htmlspecialchars($cat['label']) ?>
            <?php if ($catKey === 'identity'): ?><span class="badge bg-danger ms-2">Required</span><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($docsByCategory[$catKey])): ?>
            <div class="mb-2">
                <?php foreach ($docsByCategory[$catKey] as $doc): ?>
                <div class="d-flex align-items-center gap-2 mb-1 p-2 bg-light rounded">
                    <i class="bi bi-file-earmark-check text-success"></i>
                    <span class="small flex-grow-1"><?= htmlspecialchars($doc['original_filename']) ?></span>
                    <span class="badge bg-success-subtle text-success">Uploaded</span>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="small text-muted mb-2">Upload additional documents (optional):</p>
            <?php else: ?>
            <p class="small text-muted"><?= htmlspecialchars($cat['hint']) ?></p>
            <?php endif; ?>
            <input type="file" name="documents[<?= $catKey ?>][]" class="form-control"
                   accept=".pdf,.jpg,.jpeg,.png" multiple
                   <?= $catKey === 'identity' && empty($docsByCategory[$catKey]) ? 'required' : '' ?>>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/applications/wizard/4" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2"><i class="bi bi-arrow-right me-1"></i>Continue to Step 6</button>
    </div>
</form>
</div>
</div>
