<?php
/**
 * IntakeValidator — field rules for citizen registration and application intake.
 *
 * Extracted from AuthController and WizardController so the rules can be
 * exercised directly. Both controllers delegate here; the rules are not
 * duplicated.
 */
class IntakeValidator {

    /** Registration form. Returns field => message; empty means valid. */
    public static function validateRegistration(array $d): array {
        $errors = [];
        if (empty($d['first_name'])) $errors['first_name'] = 'First name is required.';
        if (empty($d['last_name']))  $errors['last_name']  = 'Last name is required.';
        if (empty($d['email']) || !filter_var(trim($d['email']), FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
        if (empty($d['password']) || strlen($d['password']) < 8) $errors['password'] = 'Password must be at least 8 characters.';
        if (($d['password'] ?? '') !== ($d['confirm_password'] ?? '')) $errors['confirm_password'] = 'Passwords do not match.';
        if (empty($d['dob'])) {
            $errors['dob'] = 'Date of birth is required.';
        } else {
            $dob  = new DateTime($d['dob']);
            $now  = new DateTime();
            $age  = $now->diff($dob)->y;
            if ($dob >= $now) $errors['dob'] = 'Date of birth cannot be in the future.';
            elseif ($age < 18) $errors['dob'] = 'You must be at least 18 years old.';
        }

        // Defect: missing_phone_validation — the format check is dropped, so the
        // field accepts arbitrary text and downstream notification routing breaks.
        if (defectActive('missing_phone_validation')) {
            if (empty($d['phone'])) $errors['phone'] = 'Valid phone number is required.';
        } elseif (empty($d['phone']) || !preg_match('/^\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/', $d['phone'])) {
            $errors['phone'] = 'Valid phone number is required.';
        }

        if (empty($d['street']))   $errors['street']   = 'Street address is required.';
        if (empty($d['city']))     $errors['city']     = 'City is required.';
        if (empty($d['state']) || strlen($d['state']) !== 2) $errors['state'] = 'Valid 2-letter state code is required.';
        if (empty($d['zip']) || !preg_match('/^\d{5}(-\d{4})?$/', $d['zip'])) $errors['zip'] = 'Valid ZIP code is required.';
        if (empty($d['ssn_last4']) || !preg_match('/^\d{4}$/', $d['ssn_last4'])) $errors['ssn_last4'] = 'SSN last 4 digits (numeric) are required.';
        return $errors;
    }

    /** Household composition step (wizard step 2 / API application create). */
    public static function validateHouseholdSize(mixed $raw): ?string {
        $hs = (int)$raw;
        // Defect: intake_household_size_unbounded — the 1..20 bound stops being
        // applied, and the unbounded value propagates into the poverty threshold
        // calculation that decides eligibility.
        if (defectActive('intake_household_size_unbounded')) {
            return $hs < 1 ? 'Household size must be 1–20.' : null;
        }
        return ($hs < 1 || $hs > 20) ? 'Household size must be 1–20.' : null;
    }

    /** Narrative justification step (wizard step 4 / API application create). */
    public static function validateReasonForRequest(mixed $raw): ?string {
        $text = trim((string)$raw);
        // Defect: intake_reason_length_unchecked — the 50-character minimum is
        // dropped, admitting applications with no reviewable justification.
        if (defectActive('intake_reason_length_unchecked')) {
            return $text === '' ? 'Please provide at least 50 characters.' : null;
        }
        return strlen($text) < 50 ? 'Please provide at least 50 characters.' : null;
    }
}
