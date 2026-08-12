<?php $pageTitle = '403 Forbidden'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>
<div class="text-center py-5 mt-4">
    <div style="font-size:5rem;color:var(--brand-red);font-weight:900;line-height:1">403</div>
    <h2 class="mt-2 mb-1">Access Denied</h2>
    <p class="text-muted">You do not have permission to access this resource.</p>
    <a href="<?= BASE_URL ?>/dashboard/<?= htmlspecialchars(Session::get('role_name','citizen')) ?>" class="btn btn-brand mt-2">
        <i class="bi bi-house me-1"></i>Go to Dashboard
    </a>
</div>
<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
