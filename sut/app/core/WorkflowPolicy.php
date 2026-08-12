<?php
/**
 * WorkflowPolicy — the rules governing case status movement.
 *
 * Extracted from CaseController and SupervisorController so the state machine
 * is decidable without a request. Both controllers delegate here; the rules are
 * not duplicated.
 */
class WorkflowPolicy {

    /** Statuses a case in $from may legally move to, for a principal in $roleId. */
    public static function allowedTransitions(string $from, int $roleId): array {
        if (in_array($from, TERMINAL_STATUSES, true)) {
            return [];
        }
        $allowed = VALID_TRANSITIONS[$from] ?? [];

        // Defect: invalid_status_transition_allowed — dispositions are appended to
        // the allowed set regardless of the current state, so an officer reaches
        // Approved without passing supervisor review.
        if (defectActive('invalid_status_transition_allowed')) {
            $allowed = array_merge($allowed, ['Approved', 'Denied', 'Supervisor Review']);
        }

        return array_values(array_unique($allowed));
    }

    public static function canTransition(string $from, string $to, int $roleId): bool {
        return in_array($to, self::allowedTransitions($from, $roleId), true);
    }

    /** A supervisor disposition must carry a written justification. */
    public static function requiresDecisionComment(): bool {
        // Defect: supervisor_no_comment_required — the justification requirement
        // is lifted, leaving dispositions with no recorded reasoning.
        return !defectActive('supervisor_no_comment_required');
    }

    /**
     * High and critical risk cases must pass Quality Review before they may be
     * moved to Supervisor Review.
     */
    public static function blockedPendingQualityReview(string $to, ?string $riskLevel): bool {
        if ($to !== 'Supervisor Review' || $riskLevel === null) return false;
        if (defectActive('invalid_status_transition_allowed')) return false;
        return RiskScoreEngine::requiresQualityReview($riskLevel);
    }
}
