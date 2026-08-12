<?php
/**
 * PHPUnit bootstrap for both evaluation arms.
 *
 * Loads the FCCSMS system under test against the isolated evaluation database.
 * Identical for Arm A and Arm B — the arms differ in their tests and their
 * admission gate, never in the environment they run against.
 */

define('SAIQE_ROOT', dirname(__DIR__));
define('APP_ROOT', SAIQE_ROOT . '/sut');

// The defect set this run pins. A run with no SAIQE_DEFECTS set is a clean
// build. A detection run pins exactly one defect. Tests reset to this baseline
// between cases rather than to empty, so a pinned defect survives setUp().
define('SAIQE_BASELINE_DEFECTS', getenv('SAIQE_DEFECTS') ?: '');
putenv('SAIQE_DEFECTS=' . SAIQE_BASELINE_DEFECTS);

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/core/Database.php';
require_once APP_ROOT . '/core/Session.php';
require_once APP_ROOT . '/core/Auth.php';
require_once APP_ROOT . '/core/Response.php';

foreach (glob(APP_ROOT . '/app/models/*.php') as $f) require_once $f;
foreach (glob(APP_ROOT . '/app/core/*.php')   as $f) require_once $f;
require_once APP_ROOT . '/app/ai/ModelAdapter.php';
require_once APP_ROOT . '/app/ai/DeterministicAdapter.php';
require_once APP_ROOT . '/app/ai/ToolBroker.php';
require_once APP_ROOT . '/app/ai/CaseSummaryAssistant.php';
require_once APP_ROOT . '/app/controllers/AuthController.php';

require_once SAIQE_ROOT . '/harness/src/TestSupport.php';

// CLI sessions: give PHP a writable, run-local save path.
$sessDir = sys_get_temp_dir() . '/saiqe_sessions';
if (!is_dir($sessDir)) @mkdir($sessDir, 0777, true);
session_save_path($sessDir);
