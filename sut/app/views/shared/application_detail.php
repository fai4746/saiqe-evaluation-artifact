<?php
$pageTitle = 'Application ' . htmlspecialchars($app['application_number']);
include APP_ROOT . '/app/views/shared/layout_header.php';
$statusSlug = strtolower(str_replace([' ','/'], ['-','-'], $app['status']));
?>

<div class="page-header">
    <div>
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <h2>Application — <code><?= htmlspecialchars($app['application_number']) ?></code></h2>
        <div class="page-subtitle"><?= htmlspecialchars($app['program_name']) ?></div>
    </div>
    <span class="badge badge-<?= $statusSlug ?> fs-6 px-3 py-2"><?= htmlspecialchars($app['status']) ?></span>
</div>

<div class="row g-3">
    <!-- Details -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><span class="card-title-icon bi bi-file-earmark-text"></span>Application Details</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">App #</dt>
                    <dd class="col-7"><code><?= htmlspecialchars($app['application_number']) ?></code></dd>
                    <dt class="col-5 text-muted">Program</dt>
                    <dd class="col-7 fw-medium"><?= htmlspecialchars($app['program_name']) ?></dd>
                    <dt class="col-5 text-muted">Income</dt>
                    <dd class="col-7"><?= htmlspecialchars($app['income_range']) ?></dd>
                    <dt class="col-5 text-muted">Household</dt>
                    <dd class="col-7"><?= $app['household_size'] ?></dd>
                    <dt class="col-5 text-muted">Urgency</dt>
                    <dd class="col-7">
                        <?php $urgency = $app['urgency_level'] ?? 'normal'; ?>
                        <span class="badge badge-<?= $urgency ?>"><?= ucfirst($urgency) ?></span>
                    </dd>
                    <dt class="col-5 text-muted">Contact</dt>
                    <dd class="col-7"><?= ucfirst(htmlspecialchars($app['preferred_contact'] ?? 'email')) ?></dd>
                    <dt class="col-5 text-muted">Submitted</dt>
                    <dd class="col-7"><?= date('M d, Y g:ia', strtotime($app['submitted_at'])) ?></dd>
                </dl>
                <div class="mt-3">
                    <div class="section-heading">Reason for Request</div>
                    <p class="small mb-0"><?= nl2br(htmlspecialchars($app['reason_for_request'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <span class="card-title-icon bi bi-paperclip"></span>Documents (<?= count($docs) ?>)
            </div>
            <?php if (empty($docs)): ?>
                <div class="card-body text-muted small">No documents uploaded yet.</div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($docs as $doc): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <div>
                        <div class="small fw-medium"><?= htmlspecialchars($doc['original_filename']) ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= round($doc['file_size_bytes']/1024) ?> KB</div>
                    </div>
                    <a href="<?= BASE_URL ?>/documents/<?= $doc['document_id'] ?>/download"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download"></i>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if (Session::get('role_id') == ROLE_CITIZEN): ?>
            <div class="card-footer bg-transparent">
                <a href="<?= BASE_URL ?>/documents/upload" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-upload me-1"></i>Upload More Documents
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
