<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-02 Eligibility engine. Budget: 8.
 * Functional: FR-02.1..02.9. Properties: SP-01, SP-04.
 * n/a: SP-02,03,05,06,07,08,09,10,11,12 — no identity comparison, token,
 * interpreter sink, upload, session, or delegated authority in this module.
 */
final class Mod02EligibilityArmBTest extends TestCase {

    private array $fx = [];

    protected function setUp(): void { TestSupport::resetDefects(); }

    protected function tearDown(): void {
        foreach ($this->fx as $ids) TestSupport::dropApplication($ids);
        $this->fx = [];
    }

    private function make(array $over = []): array {
        $ids = TestSupport::makeApplication($over);
        $this->fx[] = $ids;
        return $ids;
    }

    private function ruleResult(array $out, string $rule): ?string {
        foreach ($out['rules'] as $r) if ($r['rule'] === $rule) return $r['result'];
        return null;
    }

    // ── Functional ────────────────────────────────────────────

    /** FR-02.1 */
    public function testEvaluationReturnsResultAndRuleBreakdown(): void {
        $ids = $this->make();
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertContains($out['result'], ['passed', 'flagged', 'failed']);
        $this->assertNotEmpty($out['rules']);
    }

    /** FR-02.1 */
    public function testStraightforwardApplicationPasses(): void {
        $ids = $this->make(['annual_income' => 9000, 'household_size' => 2,
                            'documents' => ['identity', 'income', 'residence']]);
        $this->assertSame('passed', (new EligibilityEngine())->evaluate($ids['application_id'], false)['result']);
    }

    /** FR-02.9 */
    public function testExpensesFarAboveIncomeAreFlagged(): void {
        $ids = $this->make(['household_size' => 1, 'annual_income' => 12000, 'monthly_expenses' => 3000]);
        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('flagged', $this->ruleResult($out, 'income_inconsistency_rule'));
    }

    /** FR-02.3 — a flag rule firing with no hard stop yields 'flagged' overall */
    public function testFlagWithoutHardStopYieldsFlagged(): void {
        $ids = $this->make(['household_size' => 1, 'annual_income' => 12000,
                            'monthly_expenses' => 3000,
                            'documents' => ['identity', 'income', 'residence']]);
        $this->assertSame('flagged', (new EligibilityEngine())->evaluate($ids['application_id'], false)['result']);
    }

    // ── SP-01 Input boundaries hold ───────────────────────────

    /**
     * SP-01 / FR-02.4 — the age hard stop is a boundary. Probe min-1, min, min+1
     * rather than a single value well inside the range.
     */
    public function testAgeHardStopHoldsAtItsBoundary(): void {
        $cases = [
            ['-17 years -6 months', 'failed'],   // 17 — below the stated minimum
            ['-18 years -1 day',    'passed'],   // 18 — at the stated minimum
            ['-40 years',           'passed'],
        ];
        foreach ($cases as [$offset, $expected]) {
            $ids = $this->make(['date_of_birth' => (new DateTime($offset))->format('Y-m-d')]);
            $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
            $this->assertSame($expected, $this->ruleResult($out, 'age_rule'),
                "age rule wrong at $offset");
        }
    }

    /**
     * SP-01 / FR-02.7 — the poverty flag is a boundary at 1.85x the threshold for
     * the household. Probe just below and just above the computed limit.
     */
    public function testPovertyFlagHoldsAtItsBoundary(): void {
        $limit = POVERTY_THRESHOLDS[1] * 1.85;   // household of 1

        $below = $this->make(['household_size' => 1, 'annual_income' => (int)floor($limit) - 100,
                              'monthly_expenses' => 1, 'documents' => ['identity', 'income', 'residence']]);
        $this->assertSame('passed',
            $this->ruleResult((new EligibilityEngine())->evaluate($below['application_id'], false), 'poverty_threshold_rule'),
            'flagged an income below the limit');

        $above = $this->make(['household_size' => 1, 'annual_income' => (int)ceil($limit) + 100,
                              'monthly_expenses' => 1, 'documents' => ['identity', 'income', 'residence']]);
        $this->assertSame('flagged',
            $this->ruleResult((new EligibilityEngine())->evaluate($above['application_id'], false), 'poverty_threshold_rule'),
            'did not flag an income above the limit');
    }

    /**
     * SP-01 / FR-02.6 — the active-application cap is a boundary. Two other
     * active applications must pass; three must fail.
     */
    public function testActiveApplicationCapHoldsAtItsBoundary(): void {
        $base = $this->make(['documents' => ['identity', 'income', 'residence']]);
        $db = TestSupport::db();

        $addActive = function (int $n) use ($base, $db) {
            for ($i = 0; $i < $n; $i++) {
                $db->prepare('INSERT INTO applications (citizen_id, program_id, application_number, status, household_size)
                              VALUES (?,?,?,?,1)')
                   ->execute([$base['citizen_id'], $base['program_id'],
                              'APP-X-' . bin2hex(random_bytes(5)), 'Officer Review']);
            }
        };

        $addActive(2);
        $this->assertNotSame('failed',
            $this->ruleResult((new EligibilityEngine())->evaluate($base['application_id'], false), 'application_limit_rule'),
            'failed with only 2 other active applications');

        $addActive(1);
        $this->assertSame('failed',
            $this->ruleResult((new EligibilityEngine())->evaluate($base['application_id'], false), 'application_limit_rule'),
            'did not fail with 3 other active applications');

        $db->prepare('DELETE FROM applications WHERE citizen_id=? AND application_id<>?')
           ->execute([$base['citizen_id'], $base['application_id']]);
    }

    // ── SP-04 Object access is scoped to the principal ────────

    /**
     * SP-04 / FR-02.5 — an approved benefit already held by this citizen is a
     * hard stop. The rule must key on the citizen, and must actually fire.
     */
    public function testExistingApprovedBenefitIsAHardStop(): void {
        $ids = $this->make(['documents' => ['identity', 'income', 'residence']]);
        $db = TestSupport::db();
        $db->prepare('INSERT INTO applications (citizen_id, program_id, application_number, status, household_size)
                      VALUES (?,?,?,?,1)')
           ->execute([$ids['citizen_id'], $ids['program_id'], 'APP-A-' . bin2hex(random_bytes(5)), 'Approved']);

        $out = (new EligibilityEngine())->evaluate($ids['application_id'], false);
        $this->assertSame('failed', $this->ruleResult($out, 'active_benefit_rule'));
        $this->assertSame('failed', $out['result']);

        $db->prepare('DELETE FROM applications WHERE citizen_id=? AND application_id<>?')
           ->execute([$ids['citizen_id'], $ids['application_id']]);
    }
}
