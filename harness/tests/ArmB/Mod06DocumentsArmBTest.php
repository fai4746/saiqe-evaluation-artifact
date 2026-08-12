<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-06 Document and attachment handling. Budget: 7.
 * Functional: FR-06.1..06.5. Properties: SP-01, SP-06, SP-07.
 * n/a: SP-02,03,04,08,09,10,11,12.
 */
final class Mod06DocumentsArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    private function file(array $over = []): array {
        return array_merge([
            'name' => 'proof.pdf', 'size' => 100_000,
            'mime' => 'application/pdf', 'error' => UPLOAD_ERR_OK,
        ], $over);
    }

    // ── Functional ────────────────────────────────────────────

    /** FR-06.1 */
    public function testAllowedTypeIsAdmitted(): void {
        $v = UploadValidator::validate($this->file());
        $this->assertTrue($v['ok']);
        $this->assertSame('pdf', $v['ext']);
    }

    /** FR-06.3, FR-06.4 */
    public function testCapAndTransportErrorsAreRefused(): void {
        $this->assertSame(UploadValidator::ERR_COUNT,  UploadValidator::validate($this->file(), MAX_FILES)['code']);
        $this->assertSame(UploadValidator::ERR_UPLOAD, UploadValidator::validate($this->file(['error' => UPLOAD_ERR_PARTIAL]))['code']);
    }

    // ── SP-06 Admitted on what it is, not what it claims ──────

    /**
     * SP-06 / FR-06.1 — the decisive input is the sniffed content type. A file
     * whose name and content disagree must be judged on its content.
     */
    public function testFileIsJudgedOnContentNotOnItsName(): void {
        $disguised = $this->file(['name' => 'invoice.pdf', 'mime' => 'application/x-httpd-php']);
        $this->assertFalse(UploadValidator::validate($disguised)['ok'],
            'admitted executable content carrying an allowed extension');

        $doubleExt = $this->file(['name' => 'photo.png.php', 'mime' => 'text/x-php']);
        $this->assertFalse(UploadValidator::validate($doubleExt)['ok'],
            'admitted a double-extension payload');
    }

    /** SP-06 / FR-06.1 — a disallowed content type is refused whatever it is named */
    public function testDisallowedContentIsRefusedUnderEveryName(): void {
        foreach (['x.pdf', 'x.jpg', 'x.docx', 'x'] as $name) {
            $v = UploadValidator::validate($this->file(['name' => $name, 'mime' => 'application/zip']));
            $this->assertFalse($v['ok'], "admitted application/zip as $name");
            $this->assertSame(UploadValidator::ERR_TYPE, $v['code']);
        }
    }

    // ── SP-07 Storage identifiers are not attacker-chosen ─────

    /**
     * SP-07 / FR-06.5 — the storage name must be system-generated. If the
     * client-supplied name survives, the upload directory becomes addressable.
     */
    public function testStorageNameIsNotDerivedFromTheSuppliedName(): void {
        $supplied = 'quarterly-report.pdf';
        $stored   = UploadValidator::storedName($supplied, 'pdf');
        $this->assertNotSame($supplied, $stored);
        $this->assertStringNotContainsString('quarterly-report', $stored);
    }

    /** SP-07 / FR-06.5 — traversal sequences must never reach the storage path */
    public function testStorageNameCarriesNoPathSeparatorsOrTraversal(): void {
        foreach (['../../../../windows/win.ini', '..\\..\\evil.pdf', 'a/b/c.pdf', "nul\0.pdf"] as $hostile) {
            $stored = UploadValidator::storedName($hostile, 'pdf');
            $this->assertStringNotContainsString('..', $stored, "traversal survived: $hostile");
            $this->assertStringNotContainsString('/',  $stored, "separator survived: $hostile");
            $this->assertStringNotContainsString('\\', $stored, "separator survived: $hostile");
        }
    }

    // ── SP-01 Input boundaries hold ───────────────────────────

    /** SP-01 / FR-06.2 — the size cap is a boundary: at, and one byte over */
    public function testSizeCapHoldsAtItsBoundary(): void {
        $this->assertTrue( UploadValidator::validate($this->file(['size' => MAX_FILE_SIZE]))['ok'],     'rejected a file at the cap');
        $this->assertFalse(UploadValidator::validate($this->file(['size' => MAX_FILE_SIZE + 1]))['ok'], 'admitted a file over the cap');
        $this->assertFalse(UploadValidator::validate($this->file(['size' => PHP_INT_MAX]))['ok'],       'admitted an extreme size');
    }
}
