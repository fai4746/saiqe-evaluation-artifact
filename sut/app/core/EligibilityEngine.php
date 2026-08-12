<?php
/**
 * EligibilityEngine — evaluates applicant eligibility against defined rules.
 * Returns result: 'passed' | 'flagged' | 'failed', plus per-rule breakdown.
 * Persists results to eligibility_results table.
 */
class EligibilityEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Evaluate an application and return:
     * [
     *   'result'  => 'passed'|'flagged'|'failed',
     *   'rules'   => [['rule'=>..., 'result'=>..., 'reason'=>...], ...],
     *   'stop_reason' => 'reason text' | null
     * ]
     */
    public function evaluate(int $applicationId, bool $persist = true): array {
        $app = $this->loadApplication($applicationId);
        if (!$app) {
            return ['result' => 'failed', 'rules' => [], 'stop_reason' => 'Application not found.'];
        }

        $rules = [];
        $failed = false;
        $flagged = false;
        $stopReason = null;

        // ── HARD-STOP RULES ──────────────────────────────────────

        // Rule 1: Age Rule
        $age = $this->calcAge($app['date_of_birth'] ?? '');
        // Defect: eligibility_age_off_by_one — minimum age drops to 17
        $minAge = TrainingDefectModel::isActive('eligibility_age_off_by_one') ? 17 : 18;
        if ($age !== null && $age < $minAge) {
            $rules[] = ['rule' => 'age_rule', 'result' => 'failed',
                'reason' => 'Applicant must be at least 18 years of age.'];
            $failed = true; $stopReason = 'Applicant must be at least 18 years of age.';
        } else {
            $rules[] = ['rule' => 'age_rule', 'result' => 'passed', 'reason' => 'Age requirement met.'];
        }

        // Rule 2: Active Benefit Rule
        // Defect: eligibility_active_benefit_skipped — hard stop never evaluates
        $activeBenefitHit = !TrainingDefectModel::isActive('eligibility_active_benefit_skipped')
            && $this->hasActiveBenefit($app['citizen_id'], $app['program_id'], $applicationId);
        if ($activeBenefitHit) {
            $rules[] = ['rule' => 'active_benefit_rule', 'result' => 'failed',
                'reason' => 'You are already receiving this benefit.'];
            $failed = true; $stopReason = $stopReason ?? 'You are already receiving this benefit.';
        } else {
            $rules[] = ['rule' => 'active_benefit_rule', 'result' => 'passed', 'reason' => 'No active benefit of this type.'];
        }

        // Rule 3: Recent Benefit Rule
        // Wizard saves this in step4.received_benefit_12mo ('yes'/'no')
        $step4Data = is_array($app['step4'] ?? null) ? $app['step4'] : [];
        $recentBenefit = ($step4Data['received_benefit_12mo'] ?? 'no') === 'yes';
        $programProhibitsRecent = $this->programProhibitsRecent($app['program_id']);
        if ($recentBenefit && $programProhibitsRecent) {
            $rules[] = ['rule' => 'recent_benefit_rule', 'result' => 'failed',
                'reason' => 'This program cannot be reapplied within 12 months.'];
            $failed = true; $stopReason = $stopReason ?? 'This program cannot be reapplied within 12 months.';
        } else {
            $rules[] = ['rule' => 'recent_benefit_rule', 'result' => 'passed', 'reason' => 'Recent benefit restriction not triggered.'];
        }

        // Rule 4: Active Application Limit
        // Defect: duplicate_app_limit_bypass — the cap stops being enforced
        $activeCount = $this->countActiveApplications($app['citizen_id'], $applicationId);
        if ($activeCount >= 3 && !TrainingDefectModel::isActive('duplicate_app_limit_bypass')) {
            $rules[] = ['rule' => 'application_limit_rule', 'result' => 'failed',
                'reason' => 'Maximum of 3 active applications allowed.'];
            $failed = true; $stopReason = $stopReason ?? 'Maximum of 3 active applications allowed.';
        } else {
            $rules[] = ['rule' => 'application_limit_rule', 'result' => 'passed', 'reason' => 'Active application count within limit.'];
        }

        // ── FLAG RULES ───────────────────────────────────────────

        // Rule 5: Poverty Threshold
        $income       = (float)($app['annual_income'] ?? 0);
        $householdSz  = (int)($app['household_size'] ?? 1);
        $povertyBase  = $this->getPovertyThreshold($householdSz);
        // Defect: eligibility_poverty_multiplier_wrong — threshold multiplier inflated,
        // so over-threshold incomes are never flagged for officer verification
        $povertyMultiplier = TrainingDefectModel::isActive('eligibility_poverty_multiplier_wrong') ? 2.85 : 1.85;
        $povertyLimit = $povertyBase * $povertyMultiplier;
        if ($income > $povertyLimit) {
            $rules[] = ['rule' => 'poverty_threshold_rule', 'result' => 'flagged',
                'reason' => 'Income may exceed program threshold. Requires officer verification.'];
            $flagged = true;
        } else {
            $rules[] = ['rule' => 'poverty_threshold_rule', 'result' => 'passed', 'reason' => 'Income within threshold.'];
        }

        // Rule 6: Duplicate Detection
        if ($this->isDuplicate($app, $applicationId)) {
            $rules[] = ['rule' => 'duplicate_detection_rule', 'result' => 'flagged',
                'reason' => 'Potential duplicate application detected.'];
            $flagged = true;
        } else {
            $rules[] = ['rule' => 'duplicate_detection_rule', 'result' => 'passed', 'reason' => 'No duplicate detected.'];
        }

        // Rule 7: Income Inconsistency
        $monthly = (float)($app['monthly_expenses'] ?? 0);
        if ($income > 0 && ($monthly * 12) > ($income * 1.5)) {
            $rules[] = ['rule' => 'income_inconsistency_rule', 'result' => 'flagged',
                'reason' => 'Reported expenses appear inconsistent with reported income.'];
            $flagged = true;
        } else {
            $rules[] = ['rule' => 'income_inconsistency_rule', 'result' => 'passed', 'reason' => 'Income and expenses are consistent.'];
        }

        // Rule 8: Missing Required Documents
        if ($this->hasMissingDocs($app, $applicationId)) {
            $rules[] = ['rule' => 'missing_documents_rule', 'result' => 'flagged',
                'reason' => 'Required documents incomplete.'];
            $flagged = true;
        } else {
            $rules[] = ['rule' => 'missing_documents_rule', 'result' => 'passed', 'reason' => 'All required documents uploaded.'];
        }

        // Determine overall result
        if ($failed) {
            $overallResult = 'failed';
        } elseif ($flagged) {
            $overallResult = 'flagged';
        } else {
            $overallResult = 'passed';
        }

        // Persist to DB
        if ($persist) {
            $this->persistResults($applicationId, $rules);
        }

        return [
            'result'      => $overallResult,
            'rules'       => $rules,
            'stop_reason' => $stopReason,
        ];
    }

    // ── Private helpers ──────────────────────────────────────────

    private function loadApplication(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.*, c.date_of_birth, c.ssn_last4, u.email,
                    (SELECT COUNT(*) FROM appeals ap WHERE ap.application_id = a.application_id AND ap.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)) AS recent_appeals
             FROM applications a
             JOIN citizens c  ON a.citizen_id = c.citizen_id
             JOIN users u     ON c.user_id    = u.user_id
             WHERE a.application_id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // Decode wizard data
        $wizardData = [];
        if (!empty($row['application_data_json'])) {
            $wizardData = json_decode($row['application_data_json'], true) ?? [];
        }
        return array_merge($row, $wizardData);
    }

    private function calcAge(string $dob): ?int {
        if (!$dob) return null;
        try {
            $birth = new DateTime($dob);
            $today = new DateTime();
            return (int)$today->diff($birth)->y;
        } catch (Exception $e) { return null; }
    }

    private function hasActiveBenefit(int $citizenId, int $programId, int $excludeAppId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM applications
             WHERE citizen_id = ? AND program_id = ? AND status = ? AND application_id != ?'
        );
        $stmt->execute([$citizenId, $programId, 'Approved', $excludeAppId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function programProhibitsRecent(int $programId): bool {
        $stmt = $this->db->prepare(
            'SELECT eligibility_questions FROM programs WHERE program_id = ?'
        );
        $stmt->execute([$programId]);
        $row = $stmt->fetch();
        if (!$row || !$row['eligibility_questions']) return false;
        $q = json_decode($row['eligibility_questions'], true) ?? [];
        return (bool)($q['prohibit_recent_reapply'] ?? false);
    }

    private function countActiveApplications(int $citizenId, int $excludeAppId): int {
        $placeholders = implode(',', array_fill(0, count(ACTIVE_STATUSES), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM applications
             WHERE citizen_id = ? AND application_id != ? AND status IN ($placeholders)"
        );
        $stmt->execute(array_merge([$citizenId, $excludeAppId], ACTIVE_STATUSES));
        return (int)$stmt->fetchColumn();
    }

    private function getPovertyThreshold(int $household): float {
        $table = POVERTY_THRESHOLDS;
        if ($household <= 8) return (float)($table[$household] ?? $table[8]);
        return (float)($table[8] + ($household - 8) * POVERTY_ADDITIONAL_PER_PERSON);
    }

    private function isDuplicate(array $app, int $excludeAppId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM applications a
             JOIN citizens c ON a.citizen_id = c.citizen_id
             JOIN users u    ON c.user_id    = u.user_id
             WHERE c.ssn_last4 = ? AND c.date_of_birth = ? AND u.email = ?
               AND a.application_id != ?'
        );
        $stmt->execute([
            $app['ssn_last4'],
            $app['date_of_birth'],
            $app['email'],
            $excludeAppId,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasMissingDocs(array $app, int $applicationId): bool {
        // Categories match wizard step 5 keys: identity, income, residence
        $required = ['identity', 'income', 'residence'];
        $stmt = $this->db->prepare(
            'SELECT document_category FROM documents WHERE application_id = ?'
        );
        $stmt->execute([$applicationId]);
        $uploaded = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // If any doc was uploaded without category (NULL), treat as partial coverage
        if (in_array(null, $uploaded, true) || in_array('', $uploaded, true)) return false;
        foreach ($required as $cat) {
            if (!in_array($cat, $uploaded)) return true;
        }
        return false;
    }

    private function persistResults(int $applicationId, array $rules): void {
        // Remove existing results for this application
        $this->db->prepare('DELETE FROM eligibility_results WHERE application_id = ?')
                 ->execute([$applicationId]);

        $stmt = $this->db->prepare(
            'INSERT INTO eligibility_results (application_id, rule_name, rule_result, reason_text)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($rules as $r) {
            $stmt->execute([$applicationId, $r['rule'], $r['result'], $r['reason']]);
        }
    }
}
