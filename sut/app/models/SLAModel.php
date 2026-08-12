<?php
class SLAModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** Start SLA tracking when a case enters a new status. */
    public function startTracking(int $caseId, string $statusName, string $enteredAt): void {
        $rule = $this->getRule($statusName);
        if (!$rule) return;

        $entered  = new DateTime($enteredAt);
        $deadline = $this->addBusinessDays(clone $entered, $rule['business_days_allowed']);
        $warning  = $this->addBusinessDays(clone $entered, $rule['warning_threshold_days']);

        // Close any prior open tracking for this case + status
        $this->db->prepare(
            'UPDATE sla_tracking SET is_breached=1, breached_at=NOW()
             WHERE case_id=? AND status_name=? AND is_breached=0'
        )->execute([$caseId, $statusName]);

        $this->db->prepare(
            'INSERT INTO sla_tracking (case_id, status_name, entered_at, sla_deadline, warning_date)
             VALUES (?,?,?,?,?)'
        )->execute([
            $caseId, $statusName,
            $entered->format('Y-m-d H:i:s'),
            $deadline->format('Y-m-d H:i:s'),
            $warning->format('Y-m-d H:i:s'),
        ]);
    }

    /** Get current (most recent) SLA row for a case. */
    public function getForCase(int $caseId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM sla_tracking WHERE case_id=? ORDER BY tracking_id DESC LIMIT 1'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetch() ?: null;
    }

    /** Check and mark any SLA breaches. Rate-limited by system_settings sla_last_check. */
    public function checkBreaches(): int {
        $settings = new SystemSettingsModel();
        $lastCheck = $settings->get('sla_last_check', '2000-01-01 00:00:00');
        $fiveMinAgo = (new DateTime())->modify('-5 minutes')->format('Y-m-d H:i:s');
        if ($lastCheck > $fiveMinAgo) return 0;

        // Defect: sla_breach_check_grace_window — a silent 48-hour grace period is
        // applied to the deadline, so genuine breaches are never marked.
        $breachPredicate = defectActive('sla_breach_check_grace_window')
            ? 't.sla_deadline < DATE_SUB(NOW(), INTERVAL 48 HOUR)'
            : 't.sla_deadline < NOW()';
        $stmt = $this->db->prepare(
            "SELECT t.tracking_id, t.case_id, t.status_name FROM sla_tracking t
             WHERE t.is_breached=0 AND $breachPredicate"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $count = 0;
        foreach ($rows as $r) {
            $this->db->prepare(
                'UPDATE sla_tracking SET is_breached=1, breached_at=NOW() WHERE tracking_id=?'
            )->execute([$r['tracking_id']]);
            // Audit log
            try {
                (new AuditLogModel())->log('SLA Breached', 'case', $r['case_id'], null,
                    ['status_name' => $r['status_name']]);
            } catch (Exception $e) {}
            $count++;
        }
        $settings->set('sla_last_check', date('Y-m-d H:i:s'));
        return $count;
    }

    public function getSummary(): array {
        $stmt = $this->db->query(
            "SELECT
                SUM(CASE WHEN is_breached=0 AND warning_date > NOW() THEN 1 ELSE 0 END) AS on_track,
                SUM(CASE WHEN is_breached=0 AND warning_date <= NOW() AND sla_deadline > NOW() THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN is_breached=1 THEN 1 ELSE 0 END) AS breached
             FROM sla_tracking t
             JOIN cases c ON t.case_id = c.case_id
             WHERE c.status NOT IN ('Approved','Denied','Closed','Auto-Denied','Final Disposition')"
        );
        return $stmt->fetch() ?: ['on_track' => 0, 'warning' => 0, 'breached' => 0];
    }

    /** Alias used by DashboardController. */
    public function getSummaryCounts(): array {
        return $this->getSummary();
    }

    /** SLA counts scoped to a single officer's cases. */
    public function getSummaryCountsByOfficer(int $officerId): array {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN t.is_breached=0 AND t.warning_date > NOW() THEN 1 ELSE 0 END) AS on_track,
                SUM(CASE WHEN t.is_breached=0 AND t.warning_date <= NOW() AND t.sla_deadline > NOW() THEN 1 ELSE 0 END) AS warning,
                SUM(CASE WHEN t.is_breached=1 THEN 1 ELSE 0 END) AS breached
             FROM sla_tracking t
             JOIN cases c ON t.case_id = c.case_id
             WHERE c.assigned_officer_id = ?
               AND c.status NOT IN ('Approved','Denied','Closed','Auto-Denied','Final Disposition')"
        );
        $stmt->execute([$officerId]);
        return $stmt->fetch() ?: ['on_track' => 0, 'warning' => 0, 'breached' => 0];
    }

    /** Per-case SLA status string for display: on_track | warning | breached */
    public function getStatusForCase(int $caseId): string {
        $row = $this->getForCase($caseId);
        if (!$row) return 'on_track';
        if ($row['is_breached']) return 'breached';
        if ($row['warning_date'] <= date('Y-m-d H:i:s')) return 'warning';
        return 'on_track';
    }

    /** Add N business days (skip Sat/Sun). */
    public function addBusinessDays(DateTime $date, int $days): DateTime {
        // Defect: sla_calculation_ignores_weekends — calendar days are counted as
        // business days, so every deadline lands early and breaches under-report.
        $ignoreWeekends = defectActive('sla_calculation_ignores_weekends');
        $added = 0;
        $d = clone $date;
        while ($added < $days) {
            $d->modify('+1 day');
            $dow = (int)$d->format('N'); // 1=Mon … 7=Sun
            if ($ignoreWeekends || $dow < 6) $added++;
        }
        return $d;
    }

    private function getRule(string $statusName): ?array {
        $stmt = $this->db->prepare('SELECT * FROM sla_rules WHERE status_name=?');
        $stmt->execute([$statusName]);
        return $stmt->fetch() ?: null;
    }
}
