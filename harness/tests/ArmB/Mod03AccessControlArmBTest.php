<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-03 Role-based access control. Budget: 6.
 * Functional: FR-03.1..03.5. Properties: SP-02, SP-03, SP-04.
 * n/a: SP-01,05,06,07,08,09,10,11,12.
 */
final class Mod03AccessControlArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    // ── Functional ────────────────────────────────────────────

    /** FR-03.1 */
    public function testPermittedRoleIsAdmittedAndOthersRefused(): void {
        $this->assertTrue(Auth::hasRole(ROLE_OFFICER, [ROLE_OFFICER, ROLE_SUPERVISOR]));
        $this->assertFalse(Auth::hasRole(ROLE_CITIZEN, [ROLE_OFFICER, ROLE_SUPERVISOR]));
    }

    /** FR-03.3 */
    public function testIssuedTokenVerifies(): void {
        $claims = Auth::verifyJwt(Auth::generateJwt(['user_id' => 7, 'role_id' => ROLE_OFFICER]));
        $this->assertSame(7, $claims['user_id']);
    }

    // ── SP-02 Type confusion does not grant access ────────────

    /**
     * SP-02 / FR-03.2 — a role identifier is an integer. Values of another type
     * that loosely compare equal are not that role.
     */
    public function testLooselyEqualRoleValuesAreNotAdmitted(): void {
        $admins = [ROLE_ADMIN];
        $this->assertFalse(Auth::hasRole('1',      $admins), 'numeric string admitted');
        $this->assertFalse(Auth::hasRole('1abc',   $admins), 'leading-numeric string admitted');
        $this->assertFalse(Auth::hasRole(true,     $admins), 'boolean admitted');
        $this->assertFalse(Auth::hasRole(1.0,      $admins), 'float admitted');

        // Absent identity is likewise not a role.
        $this->assertFalse(Auth::hasRole(null, [ROLE_ADMIN, ROLE_OFFICER]), 'null admitted');
        $this->assertFalse(Auth::hasRole(0,    [ROLE_ADMIN, ROLE_OFFICER]), 'zero admitted');
    }

    // ── SP-03 Tokens are verified, not parsed ─────────────────

    /**
     * SP-03 / FR-03.3 — a token can be reassembled with elevated claims by anyone
     * who can read one. Without the secret, it must not verify.
     */
    public function testTokenWithForgedClaimsIsRejected(): void {
        $issued = Auth::generateJwt(['user_id' => 9, 'role_id' => ROLE_CITIZEN]);
        [$header, $payload, $sig] = explode('.', $issued);

        $claims = json_decode(base64url_decode($payload), true);
        $claims['role_id'] = ROLE_ADMIN;
        $forged = $header . '.' . base64url_encode(json_encode($claims)) . '.' . $sig;

        $this->assertNull(Auth::verifyJwt($forged), 'accepted a token with rewritten claims');
    }

    /** SP-03 / FR-03.3 — an absent, empty or attacker-chosen signature is rejected */
    public function testTokenWithoutAValidSignatureIsRejected(): void {
        $issued = Auth::generateJwt(['user_id' => 9, 'role_id' => ROLE_CITIZEN]);
        [$header, $payload] = explode('.', $issued);

        $this->assertNull(Auth::verifyJwt("$header.$payload."), 'accepted an empty signature');
        $this->assertNull(Auth::verifyJwt("$header.$payload.deadbeef"), 'accepted an arbitrary signature');
        $this->assertNull(Auth::verifyJwt('not.a.token'), 'accepted a malformed token');
    }

    // ── SP-04 Object access is scoped to the principal ────────

    /**
     * SP-04 / FR-03.5 — a citizen principal reads only their own applications,
     * whatever else the token carries.
     */
    public function testCitizenPrincipalIsAlwaysScopedToOwnRecords(): void {
        $this->assertSame('own', Auth::applicationScope(['role_id' => ROLE_CITIZEN, 'user_id' => 3]));
        $this->assertSame('own', Auth::applicationScope(['role_id' => ROLE_CITIZEN, 'user_id' => 3, 'admin' => true]));
        $this->assertSame('all', Auth::applicationScope(['role_id' => ROLE_OFFICER, 'user_id' => 4]));
    }
}
