<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-04 Case and appeals workflow.
 * Source: SPEC-functional.md FR-04.1 .. FR-04.3.
 */
final class Mod04WorkflowArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-04.1 */
    public function testDocumentedTransitionIsPermitted(): void {
        $this->assertTrue(WorkflowPolicy::canTransition('Officer Review', 'Quality Review', ROLE_OFFICER));
    }

    /** FR-04.1 */
    public function testUndocumentedTransitionIsRefused(): void {
        $this->assertFalse(WorkflowPolicy::canTransition('Draft', 'Closed', ROLE_OFFICER));
    }

    /** FR-04.1 */
    public function testTerminalCaseCannotMove(): void {
        $this->assertSame([], WorkflowPolicy::allowedTransitions('Closed', ROLE_OFFICER));
    }

    /** FR-04.2 */
    public function testApprovedFollowsSupervisorReview(): void {
        $this->assertTrue(WorkflowPolicy::canTransition('Supervisor Review', 'Approved', ROLE_SUPERVISOR));
    }

    /** FR-04.3 */
    public function testSupervisorDecisionRequiresAComment(): void {
        $this->assertTrue(WorkflowPolicy::requiresDecisionComment());
    }

    /** FR-04.1 */
    public function testAllowedSetForSubmittedIsEligibilityReview(): void {
        $this->assertSame(['Eligibility Review'], WorkflowPolicy::allowedTransitions('Submitted', ROLE_OFFICER));
    }
}
