<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-04 Case and appeals workflow. Budget: 6.
 * Functional: FR-04.1..04.3. Properties: SP-01, SP-04.
 * n/a: SP-02,03,05,06,07,08,09,10,11,12.
 */
final class Mod04WorkflowArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    // ── Functional ────────────────────────────────────────────

    /** FR-04.1 */
    public function testDocumentedTransitionIsPermitted(): void {
        $this->assertTrue(WorkflowPolicy::canTransition('Officer Review', 'Quality Review', ROLE_OFFICER));
    }

    /** FR-04.3 */
    public function testSupervisorDecisionRequiresAComment(): void {
        $this->assertTrue(WorkflowPolicy::requiresDecisionComment());
    }

    // ── SP-01 The state machine admits nothing outside itself ──

    /**
     * SP-01 / FR-04.1 — enumerate the whole state space. Every (from, to) pair
     * not in VALID_TRANSITIONS must be refused, not just the ones we thought of.
     */
    public function testNoTransitionOutsideTheDeclaredStateMachineIsPermitted(): void {
        $violations = [];
        foreach (APP_STATUSES as $from) {
            $declared = VALID_TRANSITIONS[$from] ?? [];
            foreach (APP_STATUSES as $to) {
                if (in_array($to, $declared, true)) continue;
                if (WorkflowPolicy::canTransition($from, $to, ROLE_OFFICER)) {
                    $violations[] = "$from -> $to";
                }
            }
        }
        $this->assertSame([], $violations,
            'undeclared transitions permitted: ' . implode(', ', $violations));
    }

    /** SP-01 / FR-04.1 — terminal states are closed for every role */
    public function testTerminalStatesAreClosedForEveryRole(): void {
        foreach (TERMINAL_STATUSES as $terminal) {
            foreach ([ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_ADMIN, ROLE_QR] as $role) {
                $this->assertSame([], WorkflowPolicy::allowedTransitions($terminal, $role),
                    "$terminal was open to role $role");
            }
        }
    }

    // ── SP-04 Authority is scoped ─────────────────────────────

    /**
     * SP-04 / FR-04.2 — Approved is reachable only from Supervisor Review. Any
     * other origin reaching it is a review-gate bypass.
     */
    public function testApprovedIsReachableOnlyFromSupervisorReview(): void {
        foreach (APP_STATUSES as $from) {
            $expected = ($from === 'Supervisor Review');
            $this->assertSame($expected, WorkflowPolicy::canTransition($from, 'Approved', ROLE_SUPERVISOR),
                "Approved reachable from '$from'");
        }
    }

    /**
     * SP-04 / FR-04.2 — a high-risk case may not reach Supervisor Review until
     * Quality Review has run.
     */
    public function testHighRiskCaseIsHeldUntilQualityReview(): void {
        $this->assertTrue(WorkflowPolicy::blockedPendingQualityReview('Supervisor Review', 'high'));
        $this->assertTrue(WorkflowPolicy::blockedPendingQualityReview('Supervisor Review', 'critical'));
        $this->assertFalse(WorkflowPolicy::blockedPendingQualityReview('Supervisor Review', 'low'));
    }
}
