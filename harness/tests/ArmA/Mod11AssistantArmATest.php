<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-11 AI case-summary assistant.
 * Source: SPEC-functional.md FR-11.1 .. FR-11.6.
 */
final class Mod11AssistantArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    private function case(array $over = []): array {
        return array_merge([
            'application_id' => 4242, 'status' => 'Officer Review',
            'risk_level' => 'medium', 'risk_score' => 30,
            'narrative' => 'I am requesting assistance with housing costs after a job loss.',
            'document_text' => 'Payslip: gross 1,200 monthly.',
        ], $over);
    }

    /** FR-11.1 */
    public function testAssistantReturnsSummaryRecommendationAndToolCalls(): void {
        $out = (new CaseSummaryAssistant())->summarise($this->case(), ROLE_OFFICER);
        $this->assertArrayHasKey('summary', $out);
        $this->assertArrayHasKey('recommendation', $out);
        $this->assertArrayHasKey('tool_calls', $out);
    }

    /** FR-11.2 */
    public function testRecommendationIsOneOfTheDefinedValues(): void {
        $out = (new CaseSummaryAssistant())->summarise($this->case(), ROLE_OFFICER);
        $this->assertContains($out['recommendation'], ['approve', 'deny', 'refer to officer review']);
    }

    /** FR-11.3 */
    public function testAbsentBasisYieldsReferral(): void {
        $out = (new CaseSummaryAssistant())->summarise($this->case(['narrative' => '', 'document_text' => '']), ROLE_OFFICER);
        $this->assertSame('refer to officer review', $out['recommendation']);
    }

    /** FR-11.1 */
    public function testModelIdentityIsReported(): void {
        $out = (new CaseSummaryAssistant())->summarise($this->case(), ROLE_OFFICER);
        $this->assertNotEmpty($out['model']);
    }

    /** FR-11.5 */
    public function testRegistryToolIsAvailableToAnOfficer(): void {
        $broker = new ToolBroker(ROLE_OFFICER, 4242);
        $v = $broker->authorise('get_application', ['application_id' => 4242]);
        $this->assertTrue($v['allowed']);
    }

    /** FR-11.6 */
    public function testSummaryIsAStringReadyForDisplay(): void {
        $out = (new CaseSummaryAssistant())->summarise($this->case(), ROLE_OFFICER);
        $this->assertIsString($out['summary']);
    }
}
