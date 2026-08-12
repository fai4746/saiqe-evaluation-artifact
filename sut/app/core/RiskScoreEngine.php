<?php
/**
 * RiskScoreEngine — computes a numeric risk score for an application.
 * Risk levels: 0-24=low, 25-49=medium, 50-74=high, 75+=critical.
 * High/Critical cases MUST go through Quality Review before Supervisor.
 */
class RiskScoreEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** Compute and persist risk score. Returns stored row. */
    public function compute(int $applicationId, ?DateTime $wizardStartedAt = null): array {
        $app = $this->loadApplication($applicationId);
        if (!$app) return ['total_score' => 0, 'risk_level' => 'low', 'score_breakdown_json' => '{}'];

        $breakdown = [];
        $score = 0;

        // Factor 1: Missing required documents (+25 per missing)
        $missingDocs = $this->countMissingDocs($app, $applicationId);
        if ($missingDocs > 0) {
            $pts = $missingDocs * 25;
            $score += $pts;
            $breakdown['missing_documents'] = ['points' => $pts, 'detail' => "$missingDocs required doc(s) missing"];
        }

        // Factor 2: Income inconsistency flag (+20)
        $income  = (float)($app['annual_income'] ?? 0);
        $monthly = (float)($app['monthly_expenses'] ?? 0);
        if ($income > 0 && ($monthly * 12) > ($income * 1.5)) {
            $score += 20;
            $breakdown['income_inconsistency'] = ['points' => 20, 'detail' => 'Monthly expenses exceed 150% of annual income'];
        }

        // Factor 3: Duplicate detection (+30)
        if ($this->isDuplicate($app, $applicationId)) {
            $score += 30;
            $breakdown['duplicate_detected'] = ['points' => 30, 'detail' => 'Matching SSN+DOB+email found in another application'];
        }

        // Factor 4: Multiple active applications
        $otherActive = $this->countOtherActive($app['citizen_id'], $applicationId);
        if ($otherActive === 1) {
            $score += 10;
            $breakdown['multiple_active_1'] = ['points' => 10, 'detail' => '1 other active application'];
        } elseif ($otherActive >= 2) {
            $score += 25;
            $breakdown['multiple_active_2'] = ['points' => 25, 'detail' => "$otherActive other active applications"];
        }

        // Factor 5: Income > 150% poverty threshold (+15)
        $hh = (int)($app['household_size'] ?? 1);
        $poverty = $this->getPovertyThreshold($hh);
        if ($income > $poverty * 1.50) {
            $score += 15;
            $breakdown['income_over_150pct'] = ['points' => 15, 'detail' => 'Income exceeds 150% of poverty threshold'];
        }

        // Factor 6: Previously denied same program in 24 months (+20)
        if ($this->wasRecentlyDenied($app['citizen_id'], $app['program_id'], $applicationId)) {
            $score += 20;
            $breakdown['recent_denial'] = ['points' => 20, 'detail' => 'Denied for same program within last 24 months'];
        }

        // Factor 7: Veteran/disability claimed but no doc uploaded (+20)
        $claimsVet = in_array($app['veteran_status']    ?? '', ['Veteran', 'Active Duty', 'Reserve/National Guard', 'Surviving Spouse']);
        $claimsDis = in_array($app['disability_status'] ?? '', ['Physical Disability', 'Cognitive Disability', 'Sensory Disability', 'Multiple Disabilities']);
        if ($claimsVet || $claimsDis) {
            $hasDoc = $this->hasVetDisabilityDoc($applicationId);
            if (!$hasDoc) {
                $score += 20;
                $breakdown['missing_vet_disability_doc'] = ['points' => 20, 'detail' => 'Veteran/disability status claimed but documentation not uploaded'];
            }
        }

        // Factor 8: Wizard completed in under 3 minutes (+15)
        if ($wizardStartedAt !== null) {
            $now = new DateTime();
            $elapsed = ($now->getTimestamp() - $wizardStartedAt->getTimestamp());
            if ($elapsed < 180) {
                $score += 15;
                $breakdown['rapid_submission'] = ['points' => 15, 'detail' => 'All steps completed in under 3 minutes (possible automated submission)'];
            }
        } elseif (!empty($app['wizard_started_at'])) {
            $started = new DateTime($app['wizard_started_at']);
            $submitted = !empty($app['submitted_at']) ? new DateTime($app['submitted_at']) : new DateTime();
            $elapsed = $submitted->getTimestamp() - $started->getTimestamp();
            if ($elapsed < 180) {
                $score += 15;
                $breakdown['rapid_submission'] = ['points' => 15, 'detail' => 'All steps completed in under 3 minutes'];
            }
        }

        $level = $this->scoreToLevel($score);

        // Upsert risk_scores
        $existing = $this->getByApplication($applicationId);
        if ($existing) {
            $stmt = $this->db->prepare(
                'UPDATE risk_scores SET total_score=?, risk_level=?, score_breakdown_json=?, recomputed_at=NOW()
                 WHERE application_id=?'
            );
            $stmt->execute([$score, $level, json_encode($breakdown), $applicationId]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO risk_scores (application_id, total_score, risk_level, score_breakdown_json)
                 VALUES (?,?,?,?)'
            );
            $stmt->execute([$applicationId, $score, $level, json_encode($breakdown)]);
        }

        return ['total_score' => $score, 'risk_level' => $level, 'score_breakdown_json' => json_encode($breakdown)];
    }

    public function getByApplication(int $appId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM risk_scores WHERE application_id = ?');
        $stmt->execute([$appId]);
        return $stmt->fetch() ?: null;
    }

    public static function scoreToLevel(int $score): string {
        // Defect: risk_level_boundary_shifted — every band boundary moves up ten
        // points, so genuinely high-risk applications are graded medium and skip
        // the mandatory Quality Review step.
        if (defectActive('risk_level_boundary_shifted')) {
            if ($score >= 85) return 'critical';
            if ($score >= 60) return 'high';
            if ($score >= 35) return 'medium';
            return 'low';
        }
        if ($score >= 75) return 'critical';
        if ($score >= 50) return 'high';
        if ($score >= 25) return 'medium';
        return 'low';
    }

    /** High/Critical cases require QR step before Supervisor */
    public static function requiresQualityReview(string $level): bool {
        return in_array($level, ['high', 'critical']);
    }

    // ── Private helpers ──────────────────────────────────────────

    private function loadApplication(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.*, c.date_of_birth, c.ssn_last4, u.email
             FROM applications a
             JOIN citizens c ON a.citizen_id = c.citizen_id
             JOIN users u    ON c.user_id    = u.user_id
             WHERE a.application_id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if (!empty($row['application_data_json'])) {
            $extra = json_decode($row['application_data_json'], true) ?? [];
            $row = array_merge($row, $extra);
        }
        return $row;
    }

    private function countMissingDocs(array $app, int $appId): int {
        // Categories match wizard step 5 keys: identity, income, residence
        $required = ['identity', 'income', 'residence'];
        $stmt = $this->db->prepare('SELECT document_category FROM documents WHERE application_id = ?');
        $stmt->execute([$appId]);
        $uploaded = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // If any doc has no category (uploaded via old route), don't penalise
        if (in_array(null, $uploaded, true) || in_array('', $uploaded, true)) return 0;
        return count(array_diff($required, $uploaded));
    }

    private function isDuplicate(array $app, int $excludeId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM applications a
             JOIN citizens c ON a.citizen_id = c.citizen_id
             JOIN users u    ON c.user_id    = u.user_id
             WHERE c.ssn_last4=? AND c.date_of_birth=? AND u.email=? AND a.application_id != ?'
        );
        $stmt->execute([$app['ssn_last4'], $app['date_of_birth'], $app['email'], $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function countOtherActive(int $citizenId, int $excludeId): int {
        $p = implode(',', array_fill(0, count(ACTIVE_STATUSES), '?'));
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM applications WHERE citizen_id=? AND application_id!=? AND status IN ($p)");
        $stmt->execute(array_merge([$citizenId, $excludeId], ACTIVE_STATUSES));
        return (int)$stmt->fetchColumn();
    }

    private function getPovertyThreshold(int $hh): float {
        $t = POVERTY_THRESHOLDS;
        if ($hh <= 8) return (float)($t[$hh] ?? $t[8]);
        return (float)($t[8] + ($hh - 8) * POVERTY_ADDITIONAL_PER_PERSON);
    }

    private function wasRecentlyDenied(int $citizenId, int $programId, int $excludeId): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM applications
             WHERE citizen_id=? AND program_id=? AND status='Denied'
               AND submitted_at >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
               AND application_id != ?"
        );
        $stmt->execute([$citizenId, $programId, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function hasVetDisabilityDoc(int $appId): bool {
        // 'other' category is where vet/disability docs are uploaded
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM documents WHERE application_id=? AND document_category IN ('other','Veteran/Disability Documentation')"
        );
        $stmt->execute([$appId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
