<?php // pageTitle + layout set by ReportController ?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-bar-chart me-2" style="color:var(--brand-red)"></i>Reports</h2>
        <div class="page-subtitle">Generate and export system reports</div>
    </div>
    <a href="<?= BASE_URL ?>/reports/export?type=<?= urlencode($type) ?>&date_from=<?= urlencode($from) ?>&date_to=<?= urlencode($to) ?>"
       class="btn btn-brand"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>

<!-- Filters -->
<div class="filter-bar mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label for="report_type" class="form-label">Report Type</label>
            <select class="form-select" name="type" id="report_type">
                <option value="status_summary"      <?= $type==='status_summary'      ?'selected':''?>>Application Status Summary</option>
                <option value="by_program"           <?= $type==='by_program'          ?'selected':''?>>By Program Type</option>
                <option value="by_date_range"        <?= $type==='by_date_range'       ?'selected':''?>>By Date Range</option>
                <option value="officer_performance"  <?= $type==='officer_performance' ?'selected':''?>>Officer Performance</option>
                <option value="supervisor_summary"   <?= $type==='supervisor_summary'  ?'selected':''?>>Supervisor Summary</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="date_from" class="form-label">From</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($from) ?>">
        </div>
        <div class="col-md-2">
            <label for="date_to" class="form-label">To</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($to) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-brand w-100">Generate</button>
        </div>
    </form>
</div>

<?php if (!empty($data)): ?>
<!-- Chart (status_summary and by_program) -->
<?php if (in_array($type, ['status_summary','by_program'])): ?>
<div class="card mb-4">
    <div class="card-header"><span class="card-title-icon bi bi-bar-chart"></span>Chart View</div>
    <div class="card-body"><div class="chart-wrap" style="height:260px"><canvas id="reportChart"></canvas></div></div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-table"></span>Results</span>
        <?php if (!empty($data)): ?><span class="badge bg-secondary"><?= is_array(reset($data)) ? count($data) : count($data) ?> rows</span><?php endif; ?>
    </div>
    <div class="table-responsive">
    <?php if (empty($data)): ?>
        <div class="empty-state"><i class="bi bi-inbox"></i>No data for selected criteria.</div>
    <?php elseif ($type === 'status_summary'): ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Status</th><th>Count</th><th>% of Total</th></tr></thead>
            <tbody>
            <?php $total = array_sum($data); ?>
            <?php foreach ($data as $status => $cnt): ?>
            <tr>
                <td><span class="badge badge-<?= strtolower(str_replace([' ','/','.'],'-',$status)) ?>"><?= htmlspecialchars($status) ?></span></td>
                <td class="fw-bold"><?= $cnt ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:6px">
                            <div class="progress-bar" style="width:<?= $total ? round($cnt/$total*100) : 0 ?>%;background:var(--brand-red)"></div>
                        </div>
                        <span class="text-muted small"><?= $total ? round($cnt/$total*100,1) : 0 ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'by_program'): ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Program</th><th>Applications</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row): ?>
                <tr><td><?= htmlspecialchars($row['program_name']) ?></td><td class="fw-bold"><?= $row['cnt'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'officer_performance'): ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Officer</th><th>Total Cases</th><th>Resolved</th><th>Pending</th><th>Avg Resolution (hrs)</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <td class="fw-medium"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                <td><?= $row['total_cases'] ?></td>
                <td><span class="text-success fw-bold"><?= $row['resolved'] ?></span></td>
                <td><?= $row['total_cases'] - $row['resolved'] ?></td>
                <td><?= round($row['avg_hours'] ?? 0, 1) ?>h</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'supervisor_summary'): ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Supervisor</th><th>Approved (this month)</th><th>Denied (this month)</th></tr></thead>
            <tbody>
            <?php foreach ($data as $row): ?>
            <tr>
                <td class="fw-medium"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                <td><span class="text-success fw-bold"><?= $row['approved'] ?></span></td>
                <td><span class="text-danger fw-bold"><?= $row['denied'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="table table-hover mb-0">
            <?php if (!empty($data)): ?>
            <thead><tr><?php foreach (array_keys($data[0]) as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($data as $row): ?><tr><?php foreach ($row as $val): ?><td><?= htmlspecialchars((string)$val) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
            <?php endif; ?>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php if (!empty($data) && in_array($type, ['status_summary','by_program'])): ?>
<script>
const chartLabels = <?= $type === 'status_summary' ? json_encode(array_keys($data)) : json_encode(array_column($data,'program_name')) ?>;
const chartValues = <?= $type === 'status_summary' ? json_encode(array_values($data)) : json_encode(array_column($data,'cnt')) ?>;
new Chart(document.getElementById('reportChart'), {
    type: '<?= $type === "status_summary" ? "doughnut" : "bar" ?>',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Count',
            data: chartValues,
            backgroundColor: <?= $type === "status_summary"
                ? "['#6366f1','#f59e0b','#ef4444','#10b981','#f87171','#16a34a','#dc2626','#d97706']"
                : "'#C8102E'" ?>,
            borderRadius: <?= $type === "by_program" ? 5 : 0 ?>,
            borderWidth: <?= $type === "status_summary" ? 2 : 0 ?>,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: '<?= $type === "status_summary" ? "right" : "none" ?>' } },
        <?= $type === "by_program" ? "scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }," : "" ?>
    }
});
</script>
<?php endif; ?>

