<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-01 Case intake and registration.
 * Source: SPEC-functional.md FR-01.1 .. FR-01.8.
 */
final class Mod01IntakeArmATest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    private function validRegistration(array $over = []): array {
        return array_merge([
            'first_name' => 'Ada', 'last_name' => 'Byron',
            'email' => 'ada@example.test',
            'password' => 'Str0ngPass!', 'confirm_password' => 'Str0ngPass!',
            'dob' => '1985-06-01', 'phone' => '(555) 555-1234',
            'street' => '1 Main St', 'city' => 'Arlington', 'state' => 'VA',
            'zip' => '22201', 'ssn_last4' => '0451',
        ], $over);
    }

    /** FR-01.1 */
    public function testCompleteRegistrationIsAccepted(): void {
        $this->assertSame([], IntakeValidator::validateRegistration($this->validRegistration()));
    }

    /** FR-01.1 */
    public function testShortPasswordIsRejected(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['password' => 'short', 'confirm_password' => 'short']));
        $this->assertArrayHasKey('password', $e);
    }

    /** FR-01.1 */
    public function testMismatchedConfirmationIsRejected(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['confirm_password' => 'Different1!']));
        $this->assertArrayHasKey('confirm_password', $e);
    }

    /** FR-01.1 */
    public function testMalformedEmailIsRejected(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['email' => 'not-an-email']));
        $this->assertArrayHasKey('email', $e);
    }

    /** FR-01.2 */
    public function testApplicantUnderEighteenIsRejected(): void {
        $dob = (new DateTime('-15 years'))->format('Y-m-d');
        $e = IntakeValidator::validateRegistration($this->validRegistration(['dob' => $dob]));
        $this->assertArrayHasKey('dob', $e);
    }

    /** FR-01.3 */
    public function testWellFormedPhoneIsAccepted(): void {
        foreach (['(555) 555-1234', '555-555-1234', '5555551234', '555.555.1234'] as $phone) {
            $e = IntakeValidator::validateRegistration($this->validRegistration(['phone' => $phone]));
            $this->assertArrayNotHasKey('phone', $e, "rejected valid format: $phone");
        }
    }

    /** FR-01.3 */
    public function testPhoneMustBeAPhoneNumber(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['phone' => 'call me maybe']));
        $this->assertArrayHasKey('phone', $e);
    }

    /** FR-01.4 */
    public function testStateMustBeTwoLetters(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['state' => 'Virginia']));
        $this->assertArrayHasKey('state', $e);
    }

    /** FR-01.5 */
    public function testSsnLastFourMustBeFourDigits(): void {
        $e = IntakeValidator::validateRegistration($this->validRegistration(['ssn_last4' => '12']));
        $this->assertArrayHasKey('ssn_last4', $e);
    }

    /** FR-01.6 */
    public function testHouseholdSizeWithinRangeIsAccepted(): void {
        $this->assertNull(IntakeValidator::validateHouseholdSize(4));
    }

    /** FR-01.6 */
    public function testHouseholdSizeBelowRangeIsRejected(): void {
        $this->assertNotNull(IntakeValidator::validateHouseholdSize(0));
    }

    /** FR-01.7 */
    public function testSufficientlyLongNarrativeIsAccepted(): void {
        $this->assertNull(IntakeValidator::validateReasonForRequest(str_repeat('a', 60)));
    }

    /** FR-01.7 */
    public function testEmptyNarrativeIsRejected(): void {
        $this->assertNotNull(IntakeValidator::validateReasonForRequest(''));
    }

    /** FR-01.8 */
    public function testRenderedFieldCarriesItsLabelText(): void {
        $html = FormRenderer::field('annual_income', 'Annual income', 'text');
        $this->assertStringContainsString('Annual income', $html);
    }
}
