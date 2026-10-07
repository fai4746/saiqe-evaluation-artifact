<?php
/**
 * Score the blind replication against the two existing arms.
 *
 * Run after the second author hands back `your-tests/`, copied into
 * harness/tests/Replication/ and measured with:
 *
 *   php harness/bin/check_blinding.php
 *   php harness/bin/run_detection.php R
 *   php harness/replication/score_replication.php MOD-09
 *
 * WHAT A RESULT HERE MEANS. If the replicating author's suite detects the
 * module's seeded defects at a rate close to Arm B's, the detection claim
 * survives removal of the author's knowledge of where the faults were placed,
 * and §9's "upper bound" caveat can be narrowed. If it detects substantially
 * fewer, the caveat was right and the paper must say so. Both outcomes are
 * reportable; only one of them is comfortable.
 *
 * This script does not decide which. It reports the three rates and the
 * per-defect agreement, and refuses to run if the blinding check has not
 * passed.
 */

$root   = dirname(__DIR__, 2);
$module = strtoupper($argv[1] ?? 'MOD-09');

$load = function (string $rel) use ($root) {
    $p = "$root/$rel";
    return is_file($p) ? json_decode(file_get_contents($p), true) : null;
};

$register = $load('register/seeded_defects.json');
$detA = $load('results/detection_arm_A.json');
$detB = $load('results/detection_arm_B.json');
$detR = $load('results/detection_arm_R.json');

if (!$detR) {
    fwrite(STDERR, "No results/detection_arm_R.json.\n"
        . "The replication has not been run yet. Steps:\n"
        . "  1. python harness/replication/build_kit.py MOD-09\n"
        . "  2. hand results/replication_kit/ to the second author\n"
        . "  3. copy their tests into harness/tests/Replication/\n"
        . "  4. php harness/bin/check_blinding.php\n"
        . "  5. php harness/bin/run_detection.php R\n"
        . "  6. re-run this script\n");
    exit(2);
}

$inModule = array_values(array_filter($register['defects'], fn($d) => $d['module'] === $module));
if (!$inModule) { fwrite(STDERR, "no seeded defects in $module\n"); exit(2); }

$byId = function (array $det): array {
    $out = [];
    foreach ($det['results'] as $r) $out[$r['defect_id']] = $r;
    return $out;
};
$A = $byId($detA); $B = $byId($detB); $R = $byId($detR);

printf("Blind replication — %s\n", $module);
echo str_repeat('=', 78) . "\n";
printf("%-7s %-40s %7s %7s %7s\n", 'ID', 'DEFECT', 'ARM A', 'ARM B', 'REPL');
echo str_repeat('-', 78) . "\n";

$ca = $cb = $cr = 0;
$agreeB = 0;
foreach ($inModule as $d) {
    $a = (bool)($A[$d['id']]['detected'] ?? false);
    $b = (bool)($B[$d['id']]['detected'] ?? false);
    $r = (bool)($R[$d['id']]['detected'] ?? false);
    $ca += $a; $cb += $b; $cr += $r;
    if ($b === $r) $agreeB++;
    printf("%-7s %-40s %7s %7s %7s\n", $d['id'], substr($d['key'], 0, 39),
        $a ? 'yes' : '-', $b ? 'yes' : '-', $r ? 'yes' : '-');
}

$n = count($inModule);
echo str_repeat('-', 78) . "\n";
printf("%-48s %6.1f%% %6.1f%% %6.1f%%\n", 'detection rate',
    $ca / $n * 100, $cb / $n * 100, $cr / $n * 100);
printf("%-48s %6d  %6d  %6d\n", 'defects detected (of ' . $n . ')', $ca, $cb, $cr);

echo "\n";
printf("Replication vs Arm B: agreement on %d of %d defects (%.1f%%)\n",
    $agreeB, $n, $agreeB / $n * 100);

// Tests authored, and whether the budget was respected.
$budget = ['MOD-01'=>14,'MOD-02'=>8,'MOD-03'=>6,'MOD-04'=>6,'MOD-05'=>4,
           'MOD-06'=>7,'MOD-07'=>4,'MOD-08'=>4,'MOD-09'=>7,'MOD-10'=>4,'MOD-11'=>6][$module] ?? null;
$authored = 0;
foreach (glob("$root/harness/tests/Replication/*.php") as $f) {
    $authored += preg_match_all('/public function test/', file_get_contents($f));
}
printf("Tests authored: %d%s\n", $authored,
    $budget !== null
        ? sprintf(" (budget %d — %s)", $budget,
            $authored === $budget ? 'respected' : 'BUDGET VIOLATED, comparison is not like-for-like')
        : '');

$payload = [
    'module'            => $module,
    'generated_at'      => date('c'),
    'kind'              => 'blind replication by the second author',
    'defects_in_module' => $n,
    'tests_authored'    => $authored,
    'budget'            => $budget,
    'budget_respected'  => $budget === null ? null : ($authored === $budget),
    'detection'         => [
        'arm_a'       => round($ca / $n, 4),
        'arm_b'       => round($cb / $n, 4),
        'replication' => round($cr / $n, 4),
    ],
    'agreement_with_arm_b' => round($agreeB / $n, 4),
];
file_put_contents("$root/results/replication_$module.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\nwritten results/replication_$module.json\n";
echo "Report this in §7 and narrow or confirm the §9 upper-bound caveat accordingly.\n";
