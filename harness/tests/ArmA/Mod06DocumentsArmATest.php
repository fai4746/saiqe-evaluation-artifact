<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-06 Document and attachment handling.
 * Source: SPEC-functional.md FR-06.1 .. FR-06.5.
 */
final class Mod06DocumentsArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    private function file(array $over = []): array {
        return array_merge([
            'name' => 'proof.pdf', 'size' => 100_000,
            'mime' => 'application/pdf', 'error' => UPLOAD_ERR_OK,
        ], $over);
    }

    /** FR-06.1 */
    public function testAllowedTypeIsAdmitted(): void {
        $v = UploadValidator::validate($this->file());
        $this->assertTrue($v['ok']);
        $this->assertSame('pdf', $v['ext']);
    }

    /** FR-06.1 */
    public function testDisallowedTypeIsRefused(): void {
        $v = UploadValidator::validate($this->file(['name' => 'x.zip', 'mime' => 'application/zip']));
        $this->assertFalse($v['ok']);
        $this->assertSame(UploadValidator::ERR_TYPE, $v['code']);
    }

    /** FR-06.1 */
    public function testEachAllowedMimeTypeMapsToAnExtension(): void {
        foreach (ALLOWED_MIME_TYPES as $mime => $ext) {
            $v = UploadValidator::validate($this->file(['mime' => $mime, 'name' => "f.$ext"]));
            $this->assertTrue($v['ok'], "rejected allowed type $mime");
        }
    }

    /** FR-06.2 */
    public function testOversizeFileIsRefused(): void {
        $v = UploadValidator::validate($this->file(['size' => MAX_FILE_SIZE + 1]));
        $this->assertFalse($v['ok']);
        $this->assertSame(UploadValidator::ERR_SIZE, $v['code']);
    }

    /** FR-06.3 */
    public function testAttachmentCapIsEnforced(): void {
        $v = UploadValidator::validate($this->file(), MAX_FILES);
        $this->assertFalse($v['ok']);
        $this->assertSame(UploadValidator::ERR_COUNT, $v['code']);
    }

    /** FR-06.4 */
    public function testTransportErrorIsRefused(): void {
        $v = UploadValidator::validate($this->file(['error' => UPLOAD_ERR_PARTIAL]));
        $this->assertFalse($v['ok']);
        $this->assertSame(UploadValidator::ERR_UPLOAD, $v['code']);
    }

    /** FR-06.5 */
    public function testStoredNameKeepsTheResolvedExtension(): void {
        $this->assertStringEndsWith('.pdf', UploadValidator::storedName('proof.pdf', 'pdf'));
    }
}
