<?php
/**
 * TestSupport — fixtures shared by both arms.
 *
 * Deliberately arm-neutral: if a helper existed for only one arm, the arms
 * would no longer differ solely in their tests.
 */
class TestSupport {

    private static ?PDO $pdo = null;

    public static function db(): PDO {
        if (self::$pdo === null) {
            self::$pdo = Database::getInstance();
        }
        return self::$pdo;
    }

    /** Activate exactly this set of seeded defects for the current process. */
    public static function activate(array $keys): void {
        putenv('SAIQE_DEFECTS=' . implode(',', $keys));
    }

    /**
     * Restore the defect set this run pins — empty on a clean build, one defect
     * during a detection run. Called from setUp() so a test never silently
     * disarms the condition the run is measuring.
     */
    public static function resetDefects(): void {
        putenv('SAIQE_DEFECTS=' . SAIQE_BASELINE_DEFECTS);
    }

    /** A citizen + application + case fixture, returned as ids. */
    public static function makeApplication(array $overrides = []): array {
        $db = self::db();
        $suffix = bin2hex(random_bytes(4));

        $db->prepare('INSERT INTO users (role_id, first_name, last_name, email, password_hash, status)
                      VALUES (?,?,?,?,?,?)')
           ->execute([ROLE_CITIZEN, 'Test', 'Citizen', "t{$suffix}@example.test",
                      password_hash('Test@1234', PASSWORD_DEFAULT), 'active']);
        $userId = (int)$db->lastInsertId();

        $db->prepare('INSERT INTO citizens (user_id, date_of_birth, ssn_last4, phone,
                      address_street, address_city, address_state, address_zip)
                      VALUES (?,?,?,?,?,?,?,?)')
           ->execute([$userId, $overrides['date_of_birth'] ?? '1990-01-01',
                      $overrides['ssn_last4'] ?? substr($suffix, 0, 4),
                      '(555) 555-5555', '1 Test St', 'Testville', 'VA', '20001']);
        $citizenId = (int)$db->lastInsertId();

        $programId = (int)$db->query('SELECT program_id FROM programs LIMIT 1')->fetchColumn();

        $payload = json_encode(array_merge([
            'received_benefit_12mo' => 'no',
        ], $overrides['wizard'] ?? []));

        $db->prepare('INSERT INTO applications
                      (citizen_id, program_id, application_number, status, household_size,
                       annual_income, monthly_expenses, application_data_json, submitted_at)
                      VALUES (?,?,?,?,?,?,?,?,NOW())')
           ->execute([
               $citizenId, $programId, 'APP-T-' . strtoupper($suffix),
               $overrides['status'] ?? 'Submitted',
               $overrides['household_size'] ?? 1,
               $overrides['annual_income'] ?? 10000,
               $overrides['monthly_expenses'] ?? 100,
               $payload,
           ]);
        $appId = (int)$db->lastInsertId();

        // The engine flags an application whose required document categories are
        // incomplete, so a fixture meant to be otherwise clean supplies them.
        foreach (($overrides['documents'] ?? []) as $category) {
            $db->prepare('INSERT INTO documents (application_id, original_filename, stored_filename,
                          file_type, file_size_bytes, uploaded_by_user_id, document_category)
                          VALUES (?,?,?,?,?,?,?)')
               ->execute([$appId, "$category.pdf", bin2hex(random_bytes(8)) . '.pdf',
                          'application/pdf', 1024, $userId, $category]);
        }

        return ['user_id' => $userId, 'citizen_id' => $citizenId,
                'application_id' => $appId, 'program_id' => $programId];
    }

    /** Remove a fixture created by makeApplication(). */
    public static function dropApplication(array $ids): void {
        $db = self::db();
        $db->prepare('DELETE FROM eligibility_results WHERE application_id=?')->execute([$ids['application_id']]);
        $db->prepare('DELETE FROM risk_scores WHERE application_id=?')->execute([$ids['application_id']]);
        $db->prepare('DELETE FROM documents WHERE application_id=?')->execute([$ids['application_id']]);
        $db->prepare('DELETE FROM applications WHERE application_id=?')->execute([$ids['application_id']]);
        $db->prepare('DELETE FROM citizens WHERE citizen_id=?')->execute([$ids['citizen_id']]);
        $db->prepare('DELETE FROM notifications WHERE recipient_user_id=?')->execute([$ids['user_id']]);
        $db->prepare('DELETE FROM users WHERE user_id=?')->execute([$ids['user_id']]);
    }

    /** Fresh CLI session. */
    public static function session(array $values = []): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        @session_start();
        $_SESSION = $values;
    }
}
