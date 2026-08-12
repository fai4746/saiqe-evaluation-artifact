<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-01 Case intake and registration. Budget: 14.
 * Functional: FR-01.1..01.8. Properties: SP-01, SP-05, SP-08.
 * n/a here: SP-02,03,04,06,07,09,10,11,12 — this module holds no identity
 * comparison, token, object reference, upload, session, or delegated authority.
 */
final class Mod01IntakeArmBTest extends TestCase {

    protected function setUp(): void { TestSupport::resetDefects(); }

    private function reg(array $over = []): array {
        return array_merge([
            'first_name' => 'Ada', 'last_name' => 'Byron', 'email' => 'ada@example.test',
            'password' => 'Str0ngPass!', 'confirm_password' => 'Str0ngPass!',
            'dob' => '1985-06-01', 'phone' => '(555) 555-1234',
            'street' => '1 Main St', 'city' => 'Arlington', 'state' => 'VA',
            'zip' => '22201', 'ssn_last4' => '0451',
        ], $over);
    }

    // ── Functional ────────────────────────────────────────────

    /** FR-01.1 */
    public function testCompleteRegistrationIsAccepted(): void {
        $this->assertSame([], IntakeValidator::validateRegistration($this->reg()));
    }

    /** FR-01.1 */
    public function testShortPasswordIsRejected(): void {
        $this->assertArrayHasKey('password', IntakeValidator::validateRegistration(
            $this->reg(['password' => 'short', 'confirm_password' => 'short'])));
    }

    /** FR-01.2 */
    public function testApplicantUnderEighteenIsRejected(): void {
        $this->assertArrayHasKey('dob', IntakeValidator::validateRegistration(
            $this->reg(['dob' => (new DateTime('-15 years'))->format('Y-m-d')])));
    }

    /** FR-01.4, FR-01.5 */
    public function testAddressAndIdentifierFormatsAreEnforced(): void {
        $e = IntakeValidator::validateRegistration($this->reg(['state' => 'Virginia', 'ssn_last4' => '12']));
        $this->assertArrayHasKey('state', $e);
        $this->assertArrayHasKey('ssn_last4', $e);
    }

    /** FR-01.8 */
    public function testRenderedFieldCarriesItsLabelText(): void {
        $this->assertStringContainsString('Annual income',
            FormRenderer::field('annual_income', 'Annual income'));
    }

    // ── SP-01 Input boundaries hold ───────────────────────────

    /** SP-01 / FR-01.6 — household size at min-1, min, max, max+1, extreme */
    public function testHouseholdSizeRejectsValuesOutsideItsRange(): void {
        $this->assertNotNull(IntakeValidator::validateHouseholdSize(0),   'accepted 0');
        $this->assertNull(   IntakeValidator::validateHouseholdSize(1),   'rejected 1');
        $this->assertNull(   IntakeValidator::validateHouseholdSize(20),  'rejected 20');
        $this->assertNotNull(IntakeValidator::validateHouseholdSize(21),  'accepted 21');
    }

    /** SP-01 / FR-01.6 — an extreme value must not reach the eligibility calculation */
    public function testHouseholdSizeRejectsAnExtremeValue(): void {
        $this->assertNotNull(IntakeValidator::validateHouseholdSize(100000));
        $this->assertNotNull(IntakeValidator::validateHouseholdSize(PHP_INT_MAX));
    }

    /** SP-01 / FR-01.7 — narrative length at the stated boundary */
    public function testNarrativeLengthBoundaryHolds(): void {
        $this->assertNotNull(IntakeValidator::validateReasonForRequest(str_repeat('a', 49)));
        $this->assertNull(   IntakeValidator::validateReasonForRequest(str_repeat('a', 50)));
    }

    /** SP-01 / FR-01.7 — whitespace is not content */
    public function testWhitespacePaddedNarrativeDoesNotSatisfyTheMinimum(): void {
        $this->assertNotNull(IntakeValidator::validateReasonForRequest(str_repeat(' ', 80) . 'help'));
    }

    /** SP-01 / FR-01.3 — the phone field is a constrained format, not free text */
    public function testPhoneFieldRejectsFreeText(): void {
        foreach (['call me maybe', 'DROP TABLE citizens', '123', str_repeat('9', 40)] as $bad) {
            $this->assertArrayHasKey('phone', IntakeValidator::validateRegistration($this->reg(['phone' => $bad])),
                "accepted: $bad");
        }
    }

    /** SP-01 / FR-01.3 — every documented valid format is still accepted */
    public function testPhoneFieldAcceptsEveryDocumentedFormat(): void {
        foreach (['(555) 555-1234', '555-555-1234', '5555551234', '555.555.1234'] as $ok) {
            $this->assertArrayNotHasKey('phone', IntakeValidator::validateRegistration($this->reg(['phone' => $ok])),
                "rejected: $ok");
        }
    }

    // ── SP-05 Untrusted text does not reach an interpreter ────

    /** SP-05 / FR-01.8 — a field value carrying markup must not become markup */
    public function testFieldValueCannotInjectMarkup(): void {
        $html = FormRenderer::field('nickname', 'Nickname', 'text', '"><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
    }

    /** SP-05 / FR-01.8 — a label carrying markup must not become markup */
    public function testLabelTextCannotInjectMarkup(): void {
        $html = FormRenderer::field('x', '<img src=x onerror=alert(1)>');
        $this->assertStringNotContainsString('<img', $html);
    }

    // ── SP-08 Secrets never enter logs or telemetry ───────────

    /** SP-08 / FR-01.1 — validation output must not echo the submitted secret */
    public function testValidationErrorsDoNotCarryTheSubmittedPassword(): void {
        $errors = IntakeValidator::validateRegistration($this->reg(['password' => 'sh', 'confirm_password' => 'other']));
        $this->assertStringNotContainsString('sh', implode(' ', array_values($errors)));
    }
}
