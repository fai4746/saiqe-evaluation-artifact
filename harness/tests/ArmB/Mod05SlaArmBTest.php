<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-05 SLA tracking. Budget: 4.
 * Functional: FR-05.1..05.4. Properties: SP-01.
 * n/a: SP-02..SP-12 — no identity, token, sink, upload, session or delegation here.
 */
final class Mod05SlaArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-05.1 */
    public function testFiveBusinessDaysFromMondayIsTheFollowingMonday(): void {
        $out = (new SLAModel())->addBusinessDays(new DateTime('2026-08-03 09:00:00'), 5);
        $this->assertSame('2026-08-10', $out->format('Y-m-d'));
    }

    /**
     * SP-01 / FR-05.1 — the deadline calculation is a boundary function over the
     * week. Walk every start day against every horizon and require that the
     * elapsed calendar span always exceeds the business-day count once a weekend
     * is crossed. A calendar-day calculation cannot satisfy this.
     */
    public function testWeekendsAreExcludedFromEveryHorizon(): void {
        $sla = new SLAModel();
        foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'] as $start) {
            foreach ([1, 3, 5, 7, 10] as $days) {
                $from = new DateTime("$start 09:00:00");
                $to   = $sla->addBusinessDays(clone $from, $days);
                $calendarSpan = (int)$from->diff($to)->days;
                $weekendsCrossed = intdiv($days + (int)$from->format('N') - 1, 5) * 2;
                $this->assertSame($days + $weekendsCrossed, $calendarSpan,
                    "start $start, $days business days: spanned $calendarSpan calendar days");
            }
        }
    }

    /** SP-01 / FR-05.2 — a deadline may never land on a weekend, at any horizon */
    public function testDeadlineNeverLandsOnAWeekend(): void {
        $sla = new SLAModel();
        foreach (['2026-08-03', '2026-08-06', '2026-08-07'] as $start) {
            for ($days = 1; $days <= 15; $days++) {
                $out = $sla->addBusinessDays(new DateTime("$start 09:00:00"), $days);
                $this->assertLessThan(6, (int)$out->format('N'),
                    "start $start + $days landed on " . $out->format('l'));
            }
        }
    }

    /**
     * SP-01 / FR-05.3, FR-05.4 — breach detection is a boundary in time. A row
     * past its deadline must be marked; a row inside it must not.
     */
    public function testBreachDetectionHoldsOnBothSidesOfTheDeadline(): void {
        $db = TestSupport::db();
        $caseId = (int)$db->query('SELECT case_id FROM cases LIMIT 1')->fetchColumn();
        $this->assertNotEmpty($caseId, 'fixture requires at least one case');

        $mk = function (string $deadlineExpr) use ($db, $caseId): int {
            $db->prepare('INSERT INTO sla_tracking (case_id, status_name, entered_at, sla_deadline, warning_date, is_breached)
                          VALUES (?,?,DATE_SUB(NOW(), INTERVAL 30 DAY), ' . $deadlineExpr . ', NOW(), 0)')
               ->execute([$caseId, 'Officer Review']);
            return (int)$db->lastInsertId();
        };

        $overdue = $mk('DATE_SUB(NOW(), INTERVAL 6 HOUR)');
        $inside  = $mk('DATE_ADD(NOW(), INTERVAL 3 DAY)');

        (new SystemSettingsModel())->set('sla_last_check', '2000-01-01 00:00:00');
        (new SLAModel())->checkBreaches();

        $flag = fn(int $id) => (int)$db->query("SELECT is_breached FROM sla_tracking WHERE tracking_id=$id")->fetchColumn();

        $overdueFlag = $flag($overdue);
        $insideFlag  = $flag($inside);
        $db->exec("DELETE FROM sla_tracking WHERE tracking_id IN ($overdue,$inside)");

        $this->assertSame(1, $overdueFlag, 'a row past its deadline was not marked breached');
        $this->assertSame(0, $insideFlag,  'a row inside its deadline was marked breached');
    }
}
