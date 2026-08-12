<?php

$statusGroups = [];
foreach ($data['cases'] as $c) $statusGroups[$c['status']] = ($statusGroups[$c['status']] ?? 0) + 1;
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-person-badge me-2" style="color:var(--brand-red)"></i>Officer Dashboard</h2>
        <div class="page-subtitle">Welcome, <strong><?= htmlspecialchars(Session::get('first_name')) ?></strong> — Your assigned caseload</div>
    </div>
    <?php if ($data['overdueCount'] > 0): ?>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-exclamation-triangle me-1"></i><?= $data['overdueCount'] ?> Overdue
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-folder2-open"></i></div>
            <div><div class="stat-value"><?= count($data['cases']) ?></div><div class="stat-label">Total Assigned</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-search"></i></div>
            <div><div class="stat-value"><?= $statusGroups['Officer Review'] ?? 0 ?></div><div class="stat-label">Officer Review</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-shield-check"></i></div>
            <div><div class="stat-value"><?= $statusGroups['Quality Review'] ?? 0 ?></div><div class="stat-label">In Quality Review</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-clock-history"></i></div>
            <div><div class="stat-value"><?= $data['overdueCount'] ?></div><div class="stat-label">Overdue (&gt;10 days)</div></div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="filter-bar d-flex flex-wrap gap-2 align-items-end">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end w-100">
        <div>
            <label for="status" class="form-label mb-1 small">Status</label>
            <select name="status" id="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (APP_STATUSES as $s): ?>
                    <option value="<?= $s ?>" <?= ($_GET['status']??'') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="search-input-wrap">
            <i class="bi bi-search"></i>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search app #..." value="<?= htmlspecialchars($_GET['q']??'') ?>" style="width:180px">
        </div>
        <button type="submit" class="btn btn-brand btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/dashboard/officer" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>
</div>

<!-- Cases table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-folder2-open"></span>My Cases</span>
        <span class="badge bg-secondary"><?= count($data['cases']) ?> cases</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-clickable mb-0">
            <thead><tr><th>App #</th><th>Program</th><th>Citizen</th><th>Urgency</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($data['cases'])): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i>No cases assigned to you.</div></td></tr>
            <?php else: ?>
                <?php foreach ($data['cases'] as $c):
                    $urg = $c['urgency_level'];
                    $overdue = (strtotime('now') - strtotime($c['submitted_at'])) > 10*86400 && !in_array($c['status'],['Approved','Denied']);
                ?>
                <tr onclick="window.location='<?= BASE_URL ?>/cases/<?= $c['case_id'] ?>'" <?= $overdue ? 'class="table-warning"' : '' ?>>
                    <td><code><?= htmlspecialchars($c['application_number']) ?></code><?= $overdue ? ' <span class="badge bg-danger ms-1">Overdue</span>' : '' ?></td>
                    <td><?= htmlspecialchars($c['program_name']) ?></td>
                    <td><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
                    <td><span class="priority-dot <?= $urg ?>"></span><span class="badge badge-<?= $urg ?>"><?= ucfirst($urg) ?></span></td>
                    <td><span class="badge badge-<?= strtolower(str_replace([' ','.','/'],'-',$c['status'])) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                    <td class="text-muted small"><?= date('M d, Y', strtotime($c['submitted_at'])) ?></td>
                    <td><a href="<?= BASE_URL ?>/cases/<?= $c['case_id'] ?>" class="btn btn-sm btn-brand" onclick="event.stopPropagation()">Review</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

