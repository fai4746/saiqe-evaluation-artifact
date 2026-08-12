<?php
/**
 * Blinding control B2 (see docs/PRE-REGISTRATION.md).
 *
 * No test in either arm may name a seeded defect, reference the register, or be
 * justified by knowledge of an injected fault. This fails the run if any does.
 */

$root = dirname(__DIR__, 2);
$register = json_decode(file_get_contents("$root/register/seeded_defects.json"), true);

$keys = array_column($register['defects'], 'key');
$ids  = array_column($register['defects'], 'id');
$banned = array_merge($keys, $ids, ['seeded_defects', 'register/', 'SAIQE_DEFECTS', 'TrainingDefect']);

$violations = [];
$files = 0;

foreach (['ArmA', 'ArmB'] as $arm) {
    foreach (glob("$root/harness/tests/$arm/*.php") as $file) {
        $files++;
        $body = file_get_contents($file);
        foreach ($banned as $term) {
            if (stripos($body, $term) !== false) {
                $violations[] = basename($file) . ' contains "' . $term . '"';
            }
        }
    }
}

echo "Blinding control B2\n";
echo str_repeat('-', 52) . "\n";
echo "test files scanned   $files\n";
echo "banned terms         " . count($banned) . "\n";

if ($violations) {
    echo "RESULT               FAIL\n\n";
    foreach ($violations as $v) echo "  $v\n";
    exit(1);
}

echo "RESULT               PASS — no test references the register\n";
exit(0);
