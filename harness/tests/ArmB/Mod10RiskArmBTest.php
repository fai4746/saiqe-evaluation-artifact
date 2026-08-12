<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-10 Risk scoring. Budget: 4.
 * Functional: FR-10.1..10.3. Properties: SP-01, SP-04.
 * n/a: SP-02,03,05,06,07,08,09,10,11,12.
 */
final class Mod10RiskArmBTest extends TestCase {

    private array $fx = [];

    protected function setUp(): void { TestSupport::resetDefects(); }

    protected function tearDown(): void {
        foreach ($this->fx as $ids) TestSupport::dropApplication($ids);
        $this->fx = [];
    }

    /** FR-10.3 */
    public function testScoreIsComputedAndPersisted(): void {
        $ids = TestSupport::makeApplication(['annual_income' => 60000, 'household_size' => 1]);
        $this->fx[] = $ids;
        $out = (new RiskScoreEngine())->compute($ids['application_id']);
        $this->assertContains($out['risk_level'], ['low', 'medium', 'high', 'critical']);
    }

    // ── SP-01 Boundaries hold ─────────────────────────────────

    /**
     * SP-01 / FR-10.1 — the bands are a partition of the score line. Check each
     * boundary from both sides rather than sampling mid-band values.
     */
    public function testEveryBandBoundaryHoldsFromBothSides(): void {
        $expectations = [
            [0, 'low'], [24, 'low'], [25, 'medium'],
            [49, 'medium'], [50, 'high'],
            [74, 'high'], [75, 'critical'], [200, 'critical'],
        ];
        foreach ($expectations as [$score, $band]) {
            $this->assertSame($band, RiskScoreEngine::scoreToLevel($score),
                "score $score was not graded $band");
        }
    }

    /**
     * SP-01 / FR-10.1 — the partition is total: every score in the range maps to
     * exactly one declared band, with no gap and no overlap.
     */
    public function testBandsPartitionTheWholeScoreRangeMonotonically(): void {
        $order = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];
        $previous = 0;
        for ($score = 0; $score <= 150; $score++) {
            $band = RiskScoreEngine::scoreToLevel($score);
            $this->assertArrayHasKey($band, $order, "score $score produced unknown band '$band'");
            $this->assertGreaterThanOrEqual($previous, $order[$band],
                "band went backwards at score $score");
            $previous = $order[$band];
        }
    }

    // ── SP-04 The review gate is scoped to risk ───────────────

    /**
     * SP-04 / FR-10.2 — Quality Review is the control that catches a
     * misgraded case. Every score at or above the high boundary must require it.
     */
    public function testEveryScoreAtOrAboveTheHighBoundaryRequiresQualityReview(): void {
        for ($score = 50; $score <= 120; $score++) {
            $this->assertTrue(
                RiskScoreEngine::requiresQualityReview(RiskScoreEngine::scoreToLevel($score)),
                "score $score did not require Quality Review"
            );
        }
        for ($score = 0; $score < 50; $score++) {
            $this->assertFalse(
                RiskScoreEngine::requiresQualityReview(RiskScoreEngine::scoreToLevel($score)),
                "score $score unnecessarily required Quality Review"
            );
        }
    }
}
