<?php $pageTitle = '404 Not Found'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>
<div class="text-center py-5 mt-4">
    <div style="font-size:5rem;color:#9ca3af;font-weight:900;line-height:1">404</div>
    <h2 class="mt-2 mb-1">Page Not Found</h2>
    <p class="text-muted">The page you are looking for doesn't exist or has been moved.</p>
    <a href="<?= BASE_URL ?>/dashboard/<?= htmlspecialchars(Session::get('role_name','citizen')) ?>" class="btn btn-brand mt-2">
        <i class="bi bi-house me-1"></i>Go to Dashboard
    </a>
</div>
<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
