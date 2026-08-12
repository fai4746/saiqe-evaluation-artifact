<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-03 Role-based access control.
 * Source: SPEC-functional.md FR-03.1 .. FR-03.5.
 */
final class Mod03AccessControlArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-03.1 */
    public function testPermittedRoleIsAdmitted(): void {
        $this->assertTrue(Auth::hasRole(ROLE_OFFICER, [ROLE_OFFICER, ROLE_SUPERVISOR]));
    }

    /** FR-03.1 */
    public function testUnpermittedRoleIsRefused(): void {
        $this->assertFalse(Auth::hasRole(ROLE_CITIZEN, [ROLE_OFFICER, ROLE_SUPERVISOR]));
    }

    /** FR-03.3 */
    public function testIssuedTokenVerifies(): void {
        $token  = Auth::generateJwt(['user_id' => 7, 'role_id' => ROLE_OFFICER, 'email' => 'o@example.test']);
        $claims = Auth::verifyJwt($token);
        $this->assertIsArray($claims);
        $this->assertSame(7, $claims['user_id']);
    }

    /** FR-03.3 */
    public function testMalformedTokenIsRejected(): void {
        $this->assertNull(Auth::verifyJwt('not.a.token.at.all'));
        $this->assertNull(Auth::verifyJwt('onlyonesegment'));
    }

    /** FR-03.5 */
    public function testCitizenPrincipalIsScopedToOwnApplications(): void {
        $this->assertSame('own', Auth::applicationScope(['role_id' => ROLE_CITIZEN, 'user_id' => 3]));
    }

    /** FR-03.5 */
    public function testStaffPrincipalSeesTheQueue(): void {
        $this->assertSame('all', Auth::applicationScope(['role_id' => ROLE_OFFICER, 'user_id' => 4]));
    }
}
