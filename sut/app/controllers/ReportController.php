<?php
class ReportController {

    private array $reportTypes = [
        'status_summary'        => 'Application Status Summary',
        'by_program'            => 'Applications by Program',
        'by_date_range'         => 'Applications by Date Range',
        'officer_performance'   => 'Officer Performance',
        'supervisor_summary'    => 'Supervisor Summary',
        'sla_compliance'        => 'SLA Compliance',
        'risk_distribution'     => 'Risk Score Distribution',
        'eligibility_outcomes'  => 'Eligibility Outcomes',
        'appeal_outcomes'       => 'Appeal Outcomes',
        'processing_times'      => 'Average Processing Times',
        'denial_reasons'        => 'Denial Reasons Analysis',
        'monthly_volume'        => 'Monthly Application Volume',
        'qr_review_summary'     => 'Quality Review Summary',
    ];

    public function index(): void {
        Auth::requireRole([ROLE_ADMIN, ROLE_AUDITOR]);
        $type = $_GET['type']      ?? 'status_summary';
        $from = $_GET['date_from'] ?? date('Y-01-01');
        $to   = $_GET['date_to']   ?? date('Y-m-d');

        $data  = $this->getData($type, $from, $to);
        $types = $this->reportTypes;

        $pageTitle = 'Reports';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/shared/reports.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function exportCsv(): void {
        Auth::requireRole([ROLE_ADMIN, ROLE_AUDITOR]);
        $type = $_GET['type']      ?? 'status_summary';
        $from = $_GET['date_from'] ?? date('Y-01-01');
        $to   = $_GET['date_to']   ?? date('Y-m-d');

        $rows = $this->getData($type, $from, $to);

        (new AuditLogModel())->log('Report Exported', 'report', 0, null, ['type' => $type]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fccsms_report_' . $type . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            $firstRow = reset($rows);
            if (is_array($firstRow)) {
                // Array-of-arrays: write headers from first row keys
                fputcsv($out, array_keys($firstRow));
                foreach ($rows as $row) fputcsv($out, array_values($row));
            } else {
                // Associative [key => scalar] (e.g. status_summary)
                fputcsv($out, ['Status', 'Count']);
                foreach ($rows as $key => $val) fputcsv($out, [$key, $val]);
            }
        }
        fclose($out);
        exit;
    }

    private function getData(string $type, string $from, string $to): array {
        $apps  = new ApplicationModel();
        $cases = new CaseModel();
        $db    = Database::getInstance();

        // Helper: prepare + execute with date-range params
        $query = function(string $sql, array $params = []) use ($db): array {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        };

        return match($type) {
            // Returns associative ['Status' => count] — view and chart both iterate by key/value
            'status_summary' => $apps->getStatusCounts(),
            'by_program'            => $apps->getByProgram(),
            'by_date_range'         => $apps->getByDateRange($from, $to),
            'officer_performance'   => $cases->getOfficerPerformance(),
            'supervisor_summary'    => $cases->getSupervisorSummary(),

            'sla_compliance' => $query(
                "SELECT
                    st.status_name,
                    COUNT(*) AS total,
                    SUM(st.is_breached = 1) AS breached,
                    SUM(st.is_breached = 0 AND st.warning_date <= NOW()) AS warning,
                    SUM(st.is_breached = 0 AND st.warning_date > NOW()) AS on_track,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, st.entered_at, st.sla_deadline)), 1) AS avg_hours_allowed
                 FROM sla_tracking st
                 GROUP BY st.status_name
                 ORDER BY st.status_name"
            ),

            'risk_distribution' => $query(
                "SELECT risk_level, COUNT(*) AS count,
                        ROUND(AVG(total_score),1) AS avg_score,
                        MIN(total_score) AS min_score,
                        MAX(total_score) AS max_score
                 FROM risk_scores
                 GROUP BY risk_level ORDER BY avg_score"
            ),

            'eligibility_outcomes' => $query(
                "SELECT rule_name, rule_result, COUNT(*) AS count
                 FROM eligibility_results
                 WHERE evaluated_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY rule_name, rule_result ORDER BY rule_name, rule_result",
                [$from, $to]
            ),

            'appeal_outcomes' => $query(
                "SELECT
                    COUNT(*) AS total_appeals,
                    SUM(decision='upheld') AS upheld,
                    SUM(decision='denied') AS denied,
                    SUM(decision='pending') AS pending,
                    ROUND(AVG(CASE WHEN decision IN ('upheld','denied')
                        THEN TIMESTAMPDIFF(DAY, submitted_at, decided_at) END),1) AS avg_days_to_decision
                 FROM appeals
                 WHERE submitted_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)",
                [$from, $to]
            ),

            'processing_times' => $query(
                "SELECT
                    p.program_name,
                    COUNT(a.application_id) AS total,
                    ROUND(AVG(TIMESTAMPDIFF(DAY, a.submitted_at, c.updated_at)),1) AS avg_days,
                    MIN(TIMESTAMPDIFF(DAY, a.submitted_at, c.updated_at)) AS min_days,
                    MAX(TIMESTAMPDIFF(DAY, a.submitted_at, c.updated_at)) AS max_days
                 FROM applications a
                 JOIN programs p ON a.program_id = p.program_id
                 JOIN cases c ON c.application_id = a.application_id
                 WHERE a.status IN ('Approved','Denied')
                   AND a.submitted_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY p.program_id ORDER BY avg_days",
                [$from, $to]
            ),

            'denial_reasons' => $query(
                "SELECT
                    csh.comments AS reason,
                    COUNT(*) AS count,
                    p.program_name
                 FROM case_status_history csh
                 JOIN cases c ON csh.case_id = c.case_id
                 JOIN applications a ON c.application_id = a.application_id
                 JOIN programs p ON a.program_id = p.program_id
                 WHERE csh.new_status = 'Denied'
                   AND csh.changed_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY p.program_id, csh.comments
                 ORDER BY count DESC LIMIT 50",
                [$from, $to]
            ),

            'monthly_volume' => $query(
                "SELECT
                    DATE_FORMAT(submitted_at,'%Y-%m') AS month,
                    COUNT(*) AS total,
                    SUM(status='Approved') AS approved,
                    SUM(status='Denied') AS denied,
                    SUM(status NOT IN ('Approved','Denied','Auto-Denied','Closed','Final Disposition')) AS in_progress
                 FROM applications
                 WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY month ORDER BY month"
            ),

            'qr_review_summary' => $query(
                "SELECT
                    qra.action_type,
                    COUNT(*) AS count,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, csh.changed_at, qra.action_timestamp)),1) AS avg_hours_in_qr,
                    u.first_name, u.last_name
                 FROM quality_review_actions qra
                 JOIN users u ON qra.reviewer_user_id = u.user_id
                 JOIN cases c ON qra.case_id = c.case_id
                 LEFT JOIN case_status_history csh ON csh.case_id = c.case_id AND csh.new_status = 'Quality Review'
                 WHERE qra.action_timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
                 GROUP BY qra.reviewer_user_id, qra.action_type
                 ORDER BY count DESC",
                [$from, $to]
            ),

            default => [],
        };
    }
}
