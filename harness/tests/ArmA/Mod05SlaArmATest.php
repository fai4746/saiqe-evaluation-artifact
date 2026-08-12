<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-05 SLA tracking.
 * Source: SPEC-functional.md FR-05.1 .. FR-05.4.
 */
final class Mod05SlaArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-05.1 */
    public function testFiveBusinessDaysFromMondayIsTheFollowingMonday(): void {
        $sla = new SLAModel();
        $out = $sla->addBusinessDays(new DateTime('2026-08-03 09:00:00'), 5); // Monday
        $this->assertSame('2026-08-10', $out->format('Y-m-d'));
    }

    /** FR-05.1 */
    public function testOneBusinessDayFromWednesdayIsThursday(): void {
        $sla = new SLAModel();
        $out = $sla->addBusinessDays(new DateTime('2026-08-05 09:00:00'), 1);
        $this->assertSame('2026-08-06', $out->format('Y-m-d'));
    }

    /** FR-05.2 */
    public function testDeadlineNeverLandsOnAWeekend(): void {
        $sla = new SLAModel();
        foreach ([1, 2, 3, 4, 5, 8, 10] as $days) {
            $out = $sla->addBusinessDays(new DateTime('2026-08-03 09:00:00'), $days);
            $this->assertLessThan(6, (int)$out->format('N'), "landed on a weekend for $days days");
        }
    }

    /** FR-05.1 */
    public function testZeroBusinessDaysDoesNotAdvance(): void {
        $sla = new SLAModel();
        $out = $sla->addBusinessDays(new DateTime('2026-08-05 09:00:00'), 0);
        $this->assertSame('2026-08-05', $out->format('Y-m-d'));
    }
}
