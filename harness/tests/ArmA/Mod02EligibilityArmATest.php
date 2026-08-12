<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-02 Eligibility engine.
 * Source: SPEC-functional.md FR-02.1 .. FR-02.9.
 */
final class Mod02EligibilityArmATest extends TestCase {

    private array $fx = [];

    protected function setUp(): void { TestSupport::resetDefects(); }

    protected function tearDown(): void {
        foreach ($this->fx as $ids) { TestSupport::dropApplication($ids); }
        $this->fx = [];
    }

    private function make(array $over = []): array {
        $ids = TestSupport::makeApplication($over);
        $this->fx[] = $ids;
        return $ids;
    }

    /** FR-02.1 */
    public function testEvaluationReturnsResultAndRuleBreakdown(): void {
        $ids = $this->make();
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertContains($out['result'], ['passed', 'flagged', 'failed']);
        $this->assertNotEmpty($out['rules']);
    }

    /** FR-02.1 */
    public function testStraightforwardApplicationPasses(): void {
        $ids = $this->make(['annual_income' => 9000, 'household_size' => 2, 'monthly_expenses' => 100,
                            'documents' => ['identity', 'income', 'residence']]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('passed', $out['result']);
    }

    /** FR-02.4, FR-02.2 */
    public function testApplicantUnderEighteenFails(): void {
        $dob = (new DateTime('-16 years'))->format('Y-m-d');
        $ids = $this->make(['date_of_birth' => $dob]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('failed', $out['result']);
        $this->assertNotNull($out['stop_reason']);
    }

    /** FR-02.4 */
    public function testApplicantOverEighteenPassesTheAgeRule(): void {
        $ids = $this->make(['date_of_birth' => (new DateTime('-30 years'))->format('Y-m-d')]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('passed', $this->ruleResult($out, 'age_rule'));
    }

    /** FR-02.7, FR-02.3 */
    public function testIncomeWellAboveThresholdIsFlagged(): void {
        // Household of 1: threshold 15060, flag boundary 1.85x = 27861.
        $ids = $this->make(['household_size' => 1, 'annual_income' => 40000, 'monthly_expenses' => 100]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('flagged', $this->ruleResult($out, 'poverty_threshold_rule'));
    }

    /** FR-02.7 */
    public function testIncomeBelowThresholdIsNotFlagged(): void {
        $ids = $this->make(['household_size' => 1, 'annual_income' => 12000, 'monthly_expenses' => 100]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('passed', $this->ruleResult($out, 'poverty_threshold_rule'));
    }

    /** FR-02.9 */
    public function testExpensesFarAboveIncomeAreFlagged(): void {
        $ids = $this->make(['household_size' => 1, 'annual_income' => 12000, 'monthly_expenses' => 3000]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('flagged', $this->ruleResult($out, 'income_inconsistency_rule'));
    }

    /** FR-02.1 */
    public function testUnknownApplicationFails(): void {
        $out = (new EligibilityEngine())->evaluate(999999, false);
        $this->assertSame('failed', $out['result']);
    }

    private function ruleResult(array $out, string $rule): ?string {
        foreach ($out['rules'] as $r) {
            if ($r['rule'] === $rule) return $r['result'];
        }
        return null;
    }
}
