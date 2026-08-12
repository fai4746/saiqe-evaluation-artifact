<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-08 Reporting, search and audit trail. Budget: 4.
 * Functional: FR-08.1..08.3. Properties: SP-04, SP-05.
 * n/a: SP-01,02,03,06,07,08,09,10,11,12.
 */
final class Mod08ReportingArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-08.1 */
    public function testRecognisedSortKeysResolve(): void {
        $this->assertSame('created_at DESC', AuditLogModel::sortClause('created_at'));
        $this->assertSame('action_type ASC', AuditLogModel::sortClause('action'));
    }

    // ── SP-05 Untrusted text does not reach an interpreter ────

    /**
     * SP-05 / FR-08.1, FR-08.2 — ORDER BY cannot be parameterised, so the sort
     * key must be resolved through an allow-list. Anything not on that list must
     * come back as the default clause, never as the caller's text.
     */
    public function testSortKeyIsResolvedThroughAnAllowListNotEchoed(): void {
        $hostile = [
            "created_at; DROP TABLE audit_logs--",
            "1 UNION SELECT password_hash FROM users",
            "(SELECT 1 FROM users WHERE SLEEP(5))",
            "username`--",
            "created_at DESC, (SELECT 1)",
        ];
        foreach ($hostile as $input) {
            $clause = AuditLogModel::sortClause($input);
            $this->assertSame('created_at DESC', $clause,
                "sort key was not allow-listed; produced: $clause");
        }
    }

    /**
     * SP-05 / FR-08.1 — the resolved clause is drawn from a closed set. Whatever
     * is passed, the output must be one of the four known clauses.
     */
    public function testResolvedClauseIsAlwaysFromTheClosedSet(): void {
        $permitted = ['created_at DESC', 'created_at ASC', 'action_type ASC', 'username ASC'];
        foreach ([null, '', 'action', 'user', 'created_at_asc', 'nonsense', '../../etc/passwd', '0x41'] as $input) {
            $this->assertContains(AuditLogModel::sortClause($input), $permitted,
                'clause escaped the closed set for input: ' . var_export($input, true));
        }
    }

    // ── SP-04 Authority is scoped ─────────────────────────────

    /**
     * SP-04 / FR-08.3 — the audit trail is the record of who did what. If it can
     * be pruned, every other control loses its evidence. No principal, including
     * an administrator, has a deletion path.
     */
    public function testAuditTrailOffersNoDeletionPath(): void {
        $this->assertFalse(AuditLogModel::deletionPermitted(),
            'the audit trail permitted deletion');
    }
}
