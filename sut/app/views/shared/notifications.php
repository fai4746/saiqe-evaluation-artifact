<?php $pageTitle = 'Notifications'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>Notifications</h2>
        <div class="page-subtitle">
            <?php $unread = count(array_filter($notifications, fn($n) => !$n['is_read'])); ?>
            <?= $unread ?> unread of <?= count($notifications) ?> total
        </div>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/notifications/mark-all-read">
        <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
        <button type="submit" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-check2-all me-1"></i>Mark All Read
        </button>
    </form>
</div>

<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
            <div class="empty-state-text">No notifications</div>
            <div class="empty-state-sub">You're all caught up!</div>
        </div>
    <?php else: ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
        <li class="list-group-item py-3 <?= !$n['is_read'] ? 'border-start border-4' : '' ?>"
            style="<?= !$n['is_read'] ? 'border-left-color:var(--brand-red)!important' : '' ?>">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php if (!$n['is_read']): ?>
                            <span class="badge badge-in-review">New</span>
                        <?php endif; ?>
                        <span class="text-muted small"><?= htmlspecialchars($n['trigger_event']) ?></span>
                        <span class="text-muted small">&bull; <?= date('M d, Y g:ia', strtotime($n['created_at'])) ?></span>
                    </div>
                    <div class="<?= !$n['is_read'] ? 'fw-semibold' : '' ?>"><?= htmlspecialchars($n['message']) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                <form method="POST" action="<?= BASE_URL ?>/notifications/<?= $n['notification_id'] ?>/read" class="flex-shrink-0">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-check2"></i>
                    </button>
                </form>
                <?php else: ?>
                <i class="bi bi-check2-all text-success flex-shrink-0 mt-1"></i>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
