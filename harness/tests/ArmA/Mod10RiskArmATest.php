<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-10 Risk scoring.
 * Source: SPEC-functional.md FR-10.1 .. FR-10.3.
 */
final class Mod10RiskArmATest extends TestCase {

    private array $fx = [];

    protected function setUp(): void { TestSupport::resetDefects(); }

    protected function tearDown(): void {
        foreach ($this->fx as $ids) TestSupport::dropApplication($ids);
        $this->fx = [];
    }

    /** FR-10.1 */
    public function testRepresentativeScoresMapToBands(): void {
        $this->assertSame('low',      RiskScoreEngine::scoreToLevel(10));
        $this->assertSame('medium',   RiskScoreEngine::scoreToLevel(30));
        $this->assertSame('high',     RiskScoreEngine::scoreToLevel(60));
        $this->assertSame('critical', RiskScoreEngine::scoreToLevel(90));
    }

    /** FR-10.2 */
    public function testHighAndCriticalRequireQualityReview(): void {
        $this->assertTrue(RiskScoreEngine::requiresQualityReview('high'));
        $this->assertTrue(RiskScoreEngine::requiresQualityReview('critical'));
    }

    /** FR-10.2 */
    public function testLowAndMediumDoNotRequireQualityReview(): void {
        $this->assertFalse(RiskScoreEngine::requiresQualityReview('low'));
        $this->assertFalse(RiskScoreEngine::requiresQualityReview('medium'));
    }

    /** FR-10.3 */
    public function testScoreIsComputedAndPersisted(): void {
        $ids = TestSupport::makeApplication(['annual_income' => 60000, 'household_size' => 1]);
        $this->fx[] = $ids;
        $out = (new RiskScoreEngine())->compute($ids['application_id']);
        $this->assertArrayHasKey('total_score', $out);
        $this->assertContains($out['risk_level'], ['low', 'medium', 'high', 'critical']);
    }
}
