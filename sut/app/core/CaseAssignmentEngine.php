<?php
/**
 * CaseAssignmentEngine — auto-assigns cases to officers based on configured method.
 * Methods: round_robin | least_loaded | program_based (→ falls back to round_robin)
 * Config stored in system_settings: assignment_method, rr_last_officer_id.
 */
class CaseAssignmentEngine {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Assign the given case to an officer.
     * Returns assigned user_id, or null if no eligible officer found.
     */
    public function assign(int $caseId, int $applicationId): ?int {
        $method = $this->getSetting('assignment_method') ?: 'least_loaded';
        $programId = $this->getProgramId($applicationId);

        $officerId = match ($method) {
            'round_robin'   => $this->roundRobin(),
            'program_based' => $this->programBased($programId),
            default         => $this->leastLoaded(),
        };

        if (!$officerId) return null;

        // Update cases table
        $this->db->prepare('UPDATE cases SET assigned_officer_id=? WHERE case_id=?')
                 ->execute([$officerId, $caseId]);

        // Log assignment record (close any open prior assignment first)
        $this->db->prepare('UPDATE case_assignments SET unassigned_at=NOW() WHERE case_id=? AND unassigned_at IS NULL')
                 ->execute([$caseId]);

        $this->db->prepare(
            'INSERT INTO case_assignments (case_id, assigned_to_user_id, assigned_by_user_id, assignment_method)
             VALUES (?, ?, NULL, ?)'
        )->execute([$caseId, $officerId, $method]);

        return $officerId;
    }

    /**
     * Manual reassignment by an admin.
     */
    public function reassign(int $caseId, int $newOfficerId, int $adminUserId, string $notes = ''): void {
        // Close current
        $this->db->prepare('UPDATE case_assignments SET unassigned_at=NOW() WHERE case_id=? AND unassigned_at IS NULL')
                 ->execute([$caseId]);

        // Update case
        $this->db->prepare('UPDATE cases SET assigned_officer_id=? WHERE case_id=?')
                 ->execute([$newOfficerId, $caseId]);

        // New assignment record
        $this->db->prepare(
            'INSERT INTO case_assignments (case_id, assigned_to_user_id, assigned_by_user_id, assignment_method, notes)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$caseId, $newOfficerId, $adminUserId, 'manual', $notes]);
    }

    // ── Assignment strategies ────────────────────────────────────

    private function roundRobin(): ?int {
        $lastId = (int)$this->getSetting('rr_last_officer_id');
        $officers = $this->getActiveOfficers();
        if (!$officers) return null;

        // Find the next officer after lastId
        $nextId = null;
        $foundCurrent = false;
        foreach ($officers as $o) {
            if ($foundCurrent) { $nextId = $o; break; }
            if ($o == $lastId) $foundCurrent = true;
        }
        if (!$nextId) $nextId = $officers[0]; // wrap

        $this->setSetting('rr_last_officer_id', (string)$nextId);
        return $nextId;
    }

    private function leastLoaded(): ?int {
        $p = implode(',', array_fill(0, count(ACTIVE_STATUSES), '?'));
        $stmt = $this->db->prepare(
            "SELECT u.user_id, COUNT(c.case_id) AS open_cases
             FROM users u
             LEFT JOIN cases c ON c.assigned_officer_id = u.user_id AND c.status IN ($p)
             WHERE u.role_id = ? AND u.status = 'active'
             GROUP BY u.user_id
             ORDER BY open_cases ASC
             LIMIT 1"
        );
        $stmt->execute(array_merge(ACTIVE_STATUSES, [ROLE_OFFICER]));
        $row = $stmt->fetch();
        return $row ? (int)$row['user_id'] : null;
    }

    private function programBased(int $programId): ?int {
        $officers = $this->getActiveOfficers();
        foreach ($officers as $uid) {
            $spec = $this->getSpecialization($uid);
            $ids = array_map('intval', explode(',', $spec));
            if (in_array($programId, $ids)) return $uid;
        }
        return $this->roundRobin(); // fallback
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function getActiveOfficers(): array {
        $stmt = $this->db->prepare(
            "SELECT user_id FROM users WHERE role_id=? AND status='active' ORDER BY user_id ASC"
        );
        $stmt->execute([ROLE_OFFICER]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getSpecialization(int $uid): string {
        $stmt = $this->db->prepare('SELECT program_specialization FROM users WHERE user_id=?');
        $stmt->execute([$uid]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function getProgramId(int $appId): int {
        $stmt = $this->db->prepare('SELECT program_id FROM applications WHERE application_id=?');
        $stmt->execute([$appId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function getSetting(string $key): string {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key=?');
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    private function setSetting(string $key, string $value): void {
        $this->db->prepare(
            'INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value=?, updated_at=NOW()'
        )->execute([$key, $value, $value]);
    }
}
