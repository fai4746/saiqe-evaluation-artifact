<?php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Arm A — MOD-09 Authentication and session.
 * Source: SPEC-functional.md FR-09.1 .. FR-09.5.
 */
final class Mod09AuthSessionArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-09.1 */
    public function testMatchingCsrfTokenIsAccepted(): void {
        TestSupport::session();
        $token = Session::csrfToken();
        $this->assertTrue(Session::verifyCsrf($token));
    }

    /** FR-09.1 */
    public function testCsrfTokenIsIssuedAndStable(): void {
        TestSupport::session();
        $this->assertSame(Session::csrfToken(), Session::csrfToken());
    }

    /** FR-09.4 */
    public function testAccountLocksOnTheFifthConsecutiveFailure(): void {
        $this->assertTrue(Auth::shouldLockAccount(MAX_FAILED_LOGINS - 1));
    }

    /** FR-09.4 */
    public function testAccountDoesNotLockBeforeTheThreshold(): void {
        $this->assertFalse(Auth::shouldLockAccount(0));
        $this->assertFalse(Auth::shouldLockAccount(MAX_FAILED_LOGINS - 3));
    }

    /** FR-09.3 */
    public function testFailedLoginIsRecorded(): void {
        $rec = Auth::failedLoginRecord('someone@example.test',
            ['user_id' => 12, 'role_name' => 'citizen'], 'hunter2');
        $this->assertSame('Failed Login', $rec['action_type']);
        $this->assertSame('someone@example.test', $rec['username']);
    }

    /** FR-09.5 */
    public function testIdleSessionIsNoLongerAuthenticated(): void {
        TestSupport::session(['user_id' => 1, 'last_activity' => time() - (SESSION_TIMEOUT + 60)]);
        $this->assertFalse(Session::checkTimeout());
    }

    /** FR-09.5 */
    public function testActiveSessionRemainsAuthenticated(): void {
        TestSupport::session(['user_id' => 1, 'last_activity' => time()]);
        $this->assertTrue(Session::checkTimeout());
    }
}
