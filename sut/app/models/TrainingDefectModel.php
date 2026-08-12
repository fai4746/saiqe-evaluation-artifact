<?php
class TrainingDefectModel {
    private PDO $db;
    private static ?array $activeDefectsCache = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll(): array {
        return $this->db->query('SELECT * FROM training_defects ORDER BY defect_id')->fetchAll();
    }

    public function toggle(int $defectId, int $isActive, int $activatedBy): void {
        $this->db->prepare(
            'UPDATE training_defects SET is_active=?, activated_at=?, activated_by_user_id=?
             WHERE defect_id=?'
        )->execute([
            $isActive,
            $isActive ? date('Y-m-d H:i:s') : null,
            $isActive ? $activatedBy : null,
            $defectId,
        ]);
        // Clear cache
        self::$activeDefectsCache = null;
        Session::delete('active_defects');
    }

    public function findByKey(string $key): ?array {
        $stmt = $this->db->prepare('SELECT * FROM training_defects WHERE defect_key=?');
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    /** Load active defect keys into session (called once per page load). */
    public static function loadActive(): array {
        // SAIQE evaluation harness: SAIQE_DEFECTS pins the active set for a run
        // without a session or a DB round-trip, so a measurement run is
        // deterministic and reproducible. Empty string means "none active".
        $pinned = getenv('SAIQE_DEFECTS');
        if ($pinned !== false) {
            return array_values(array_filter(array_map('trim', explode(',', $pinned))));
        }

        if (self::$activeDefectsCache !== null) return self::$activeDefectsCache;
        $cached = Session::get('active_defects');
        if ($cached !== null) {
            self::$activeDefectsCache = $cached;
            return $cached;
        }
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT defect_key FROM training_defects WHERE is_active=1");
            $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $keys = [];
        }
        self::$activeDefectsCache = $keys;
        Session::set('active_defects', $keys);
        return $keys;
    }

    /** Check if a specific defect is currently active. */
    public static function isActive(string $key): bool {
        return in_array($key, self::loadActive());
    }

    public function getActiveCount(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM training_defects WHERE is_active=1")->fetchColumn();
    }
}
