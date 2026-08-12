<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-08 Reporting, search and audit trail.
 * Source: SPEC-functional.md FR-08.1 .. FR-08.3.
 */
final class Mod08ReportingArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-08.1 */
    public function testRecognisedSortKeysResolve(): void {
        $this->assertSame('created_at DESC', AuditLogModel::sortClause('created_at'));
        $this->assertSame('created_at ASC',  AuditLogModel::sortClause('created_at_asc'));
        $this->assertSame('action_type ASC', AuditLogModel::sortClause('action'));
        $this->assertSame('username ASC',    AuditLogModel::sortClause('user'));
    }

    /** FR-08.2 */
    public function testUnrecognisedSortKeyFallsBackToDefault(): void {
        $this->assertSame('created_at DESC', AuditLogModel::sortClause('nonsense_column'));
    }

    /** FR-08.2 */
    public function testAbsentSortKeyFallsBackToDefault(): void {
        $this->assertSame('created_at DESC', AuditLogModel::sortClause(null));
    }

    /** FR-08.1 */
    public function testAuditLogCanBeListedAndCounted(): void {
        $m = new AuditLogModel();
        $this->assertIsArray($m->getAll([], 1, 5));
        $this->assertGreaterThanOrEqual(0, $m->count([]));
    }
}
