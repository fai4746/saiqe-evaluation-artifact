<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-09 Authentication and session. Budget: 7.
 * Functional: FR-09.1..09.5. Properties: SP-05, SP-08, SP-09, SP-10.
 * n/a: SP-01,02,03,04,06,07,11,12.
 */
final class Mod09AuthSessionArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-09.1 */
    public function testMatchingCsrfTokenIsAccepted(): void {
        TestSupport::session();
        $this->assertTrue(Session::verifyCsrf(Session::csrfToken()));
    }

    /** FR-09.5 */
    public function testIdleSessionIsNoLongerAuthenticated(): void {
        TestSupport::session(['user_id' => 1, 'last_activity' => time() - (SESSION_TIMEOUT + 60)]);
        $this->assertFalse(Session::checkTimeout());
    }

    // ── SP-05 A forged request must not be accepted ───────────

    /**
     * SP-05 / FR-09.1 — CSRF verification exists to refuse a token the caller did
     * not receive from us. It must refuse wrong, empty and absent tokens.
     */
    public function testCsrfVerificationRefusesTokensItDidNotIssue(): void {
        TestSupport::session();
        Session::csrfToken();

        $this->assertFalse(Session::verifyCsrf(''),                       'accepted an empty token');
        $this->assertFalse(Session::verifyCsrf('0'),                      'accepted a falsy token');
        $this->assertFalse(Session::verifyCsrf(str_repeat('a', 64)),      'accepted an arbitrary token');
        $this->assertFalse(Session::verifyCsrf(bin2hex(random_bytes(32))),'accepted a well-formed foreign token');
    }

    /** SP-05 / FR-09.1 — a session that never issued a token accepts nothing */
    public function testSessionWithoutAnIssuedTokenAcceptsNothing(): void {
        TestSupport::session();
        $this->assertFalse(Session::verifyCsrf(''));
        $this->assertFalse(Session::verifyCsrf('anything'));
    }

    // ── SP-09 Session identity is re-established at auth ──────

    /**
     * SP-09 / FR-09.2 — an identifier held before authentication must not still
     * authenticate after it, or an attacker who plants one inherits the session.
     */
    public function testSessionIdentifierChangesAtAuthentication(): void {
        TestSupport::session();
        $before = session_id();
        $this->assertNotEmpty($before);

        Auth::login(['user_id' => 5, 'role_id' => ROLE_CITIZEN, 'first_name' => 'A',
                     'last_name' => 'B', 'email' => 'a@example.test']);

        $this->assertNotSame($before, session_id(),
            'the pre-authentication session identifier survived login');
    }

    // ── SP-08 Secrets never enter logs or telemetry ───────────

    /**
     * SP-08 / FR-09.3 — a failed login is recorded. The record identifies the
     * attempt; it must not contain the secret that was submitted.
     */
    public function testFailedLoginRecordCarriesNoCredential(): void {
        $secret = 'Sup3rSecret!Passw0rd';
        $record = Auth::failedLoginRecord('victim@example.test',
            ['user_id' => 12, 'role_name' => 'citizen'], $secret);

        $this->assertStringNotContainsString($secret, json_encode($record),
            'the submitted password reached the audit record');
    }

    // ── SP-10 Repeated failure is rate-limited ────────────────

    /**
     * SP-10 / FR-09.4 — the lockout threshold is a boundary. Below it the account
     * stays open; at it the account must close.
     */
    public function testLockoutThresholdHoldsAtItsBoundary(): void {
        for ($before = 0; $before < MAX_FAILED_LOGINS - 1; $before++) {
            $this->assertFalse(Auth::shouldLockAccount($before), "locked early at $before prior failures");
        }
        $this->assertTrue(Auth::shouldLockAccount(MAX_FAILED_LOGINS - 1),
            'the account did not lock at the threshold');
        $this->assertTrue(Auth::shouldLockAccount(MAX_FAILED_LOGINS + 20),
            'the account did not lock beyond the threshold');
    }
}
