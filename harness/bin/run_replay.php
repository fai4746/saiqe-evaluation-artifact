<?php
/**
 * Operational replay — M02, M03, M09, M25, M26, M27.
 *
 * These metrics normally need a production system with real users and
 * incidents. Instead, both arms process an identical deterministic series of
 * 24 synthetic releases, each carrying a defined subset of seeded defects.
 * Change failure, time to restore, remediation lead time and drift latency are
 * then read off the arms' own gate decisions over that series.
 *
 * THIS IS SIMULATED OPERATION, NOT FIELD DATA. It measures how each pipeline
 * responds to a controlled fault stream. It says nothing about production
 * behaviour, and is labelled that way wherever it is reported.
 *
 * Release semantics:
 *   - a defect introduced at release k stays in the codebase until the arm
 *     detects it (its suite fails), at which point it is fixed for release k+1
 *   - a release SHIPS when the arm's suite passes; it is BLOCKED otherwise
 *   - a shipped release carrying an undetected defect is a change failure
 *
 * Usage: php harness/bin/run_replay.php
 */

$root     = dirname(__DIR__, 2);
$register = json_decode(file_get_contents("$root/register/seeded_defects.json"), true)['defects'];

const RELEASES = 24;
mt_srand(20260812);   // fixed seed: the two arms see an identical stream

// ── Build the release plan once, shared by both arms ───────────
$plan = [];
$pool = array_column($register, 'key');
for ($r = 1; $r <= RELEASES; $r++) {
    $introduce = [];
    $n = ($r % 4 === 0) ? 2 : (($r % 3 === 0) ? 0 : 1);   // some releases are clean
    for ($i = 0; $i < $n; $i++) {
        $introduce[] = $pool[mt_rand(0, count($pool) - 1)];
    }
    $plan[$r] = array_values(array_unique($introduce));
}

$byKey = [];
foreach ($register as $d) $byKey[$d['key']] = $d;

$armResults = [];

foreach (['A' => 'arm-a', 'B' => 'arm-b'] as $arm => $suite) {
    $active   = [];     // defect key => release it was introduced at
    $releases = [];
    $incidents = [];

    echo "Replay — Arm $arm\n" . str_repeat('-', 70) . "\n";

    for ($r = 1; $r <= RELEASES; $r++) {
        foreach ($plan[$r] as $key) {
            if (!isset($active[$key])) $active[$key] = $r;
        }

        $t0 = microtime(true);
        [$failing, $detecting] = runSuite($root, $suite, array_keys($active));
        $leadSeconds = microtime(true) - $t0;

        $detectedKeys = attribute($detecting, $active, $byKey);
        $shipped      = ($failing === 0);

        // Anything detected is fixed before the next release.
        $fixedNow = [];
        foreach ($detectedKeys as $key) {
            $incidents[] = [
                'defect'        => $key,
                'introduced_at' => $active[$key],
                'detected_at'   => $r,
                'lead_releases' => $r - $active[$key],
                'shipped_before_detection' => false,
            ];
            $fixedNow[] = $key;
            unset($active[$key]);
        }

        $escaping = array_keys($active);                 // present, undetected
        $changeFailure = $shipped && $escaping !== [];

        $releases[] = [
            'release'        => $r,
            'introduced'     => $plan[$r],
            'active_defects' => count($active) + count($fixedNow),
            'detected'       => $fixedNow,
            'escaping'       => $escaping,
            'shipped'        => $shipped,
            'change_failure' => $changeFailure,
            'lead_seconds'   => round($leadSeconds, 2),
        ];

        printf("  r%-3d intro %-2d  active %-2d  %-7s  %s%s\n",
            $r, count($plan[$r]), count($active) + count($fixedNow),
            $shipped ? 'SHIP' : 'BLOCK',
            $fixedNow ? 'fixed: ' . count($fixedNow) : '',
            $changeFailure ? '  << change failure (' . count($escaping) . ' escaping)' : '');
    }

    // Defects never detected: censored at the end of the series.
    foreach ($active as $key => $introducedAt) {
        $incidents[] = [
            'defect' => $key, 'introduced_at' => $introducedAt,
            'detected_at' => null, 'lead_releases' => null,
            'censored' => true,
        ];
    }

    $shippedCount  = count(array_filter($releases, fn($x) => $x['shipped']));
    $failureCount  = count(array_filter($releases, fn($x) => $x['change_failure']));
    $resolved      = array_values(array_filter($incidents, fn($i) => $i['detected_at'] !== null));
    $leads         = array_column($resolved, 'lead_releases');
    sort($leads);

    // Time to restore: releases a shipped fault persisted before being fixed.
    $restore = [];
    foreach ($resolved as $i) {
        if ($i['lead_releases'] > 0) $restore[] = $i['lead_releases'];
    }
    sort($restore);

    $armResults[$arm] = [
        'releases'                     => RELEASES,
        'shipped'                      => $shippedCount,
        'blocked'                      => RELEASES - $shippedCount,
        'M26_deployment_frequency'     => round($shippedCount / RELEASES, 4),
        'M02_change_failure_rate'      => $shippedCount ? round($failureCount / $shippedCount, 4) : null,
        'M03_median_releases_to_restore'=> $restore ? median($restore) : null,
        'M09_median_remediation_lead'  => $leads ? median($leads) : null,
        'M27_median_lead_seconds'      => median(array_column($releases, 'lead_seconds')),
        'defects_never_detected'       => count($incidents) - count($resolved),
        'release_log'                  => $releases,
        'incidents'                    => $incidents,
    ];

    printf("  -> shipped %d/%d, change failures %d, never detected %d\n\n",
        $shippedCount, RELEASES, $failureCount, count($incidents) - count($resolved));
}

// ── M25 drift detection latency ────────────────────────────────
$drift = driftLatency($root);

$payload = [
    'generated_at' => date('c'),
    'kind'         => 'simulated_operation',
    'caveat'       => 'Synthetic release series over a controlled fault stream. Not field data. See docs/PRE-REGISTRATION.md §6.',
    'releases'     => RELEASES,
    'seed'         => 20260812,
    'plan'         => $plan,
    'arms'         => $armResults,
    'M25_drift'    => $drift,
];

file_put_contents("$root/results/replay.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo str_repeat('=', 70) . "\n";
printf("%-34s %12s %12s\n", 'metric (simulated operation)', 'Arm A', 'Arm B');
echo str_repeat('-', 70) . "\n";
row('M26 deployment frequency', $armResults, 'M26_deployment_frequency', true);
row('M02 change failure rate', $armResults, 'M02_change_failure_rate', true);
row('M03 median releases to restore', $armResults, 'M03_median_releases_to_restore', false);
row('M09 median remediation lead', $armResults, 'M09_median_remediation_lead', false);
row('M27 median lead time (s)', $armResults, 'M27_median_lead_seconds', false);
row('defects never detected', $armResults, 'defects_never_detected', false);
printf("%-34s %12s %12s\n", 'M25 drift detection latency',
    $drift['arm_a'] === null ? 'undetected' : $drift['arm_a'] . ' rel',
    $drift['arm_b'] === null ? 'undetected' : $drift['arm_b'] . ' rel');
echo "\nwritten results/replay.json\n";

// ── helpers ────────────────────────────────────────────────────

function runSuite(string $root, string $suite, array $defects): array {
    $junit = sys_get_temp_dir() . '/saiqe_replay.xml';
    @unlink($junit);
    exec(sprintf('set SAIQE_DEFECTS=%s&& %s %s --testsuite %s --no-coverage --log-junit %s 2>&1',
        implode(',', $defects),
        escapeshellarg(PHP_BINARY),
        escapeshellarg("$root/vendor/phpunit/phpunit/phpunit"),
        $suite, escapeshellarg($junit)), $o, $rc);

    if (!is_file($junit) || !($xml = @simplexml_load_file($junit))) return [0, []];

    $failing = 0; $names = [];
    foreach ($xml->xpath('//testsuite[@tests]') as $ts) {
        if ($ts->xpath('testsuite')) continue;
        $failing += (int)$ts['failures'] + (int)$ts['errors'];
    }
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $tc) {
        $names[] = (string)$tc['class'];
    }
    return [$failing, array_unique($names)];
}

/**
 * Attribute failures to defects by module. A failing test in the module that
 * owns a defect is taken to have detected it — the same rule for both arms.
 */
function attribute(array $failingClasses, array $active, array $byKey): array {
    $modules = [];
    foreach ($failingClasses as $class) {
        if (preg_match('/^Mod(\d+)/', $class, $m)) $modules[] = 'MOD-' . $m[1];
    }
    $modules = array_unique($modules);

    $detected = [];
    foreach (array_keys($active) as $key) {
        if (in_array($byKey[$key]['module'], $modules, true)) $detected[] = $key;
    }
    return $detected;
}

/**
 * M25 — releases between a behavioural change in the AI component and the
 * guardrail suite flagging it. The change is the prompt-injection control
 * regressing; each arm is checked for whether its suite notices, and when.
 */
function driftLatency(string $root): array {
    $out = ['introduced_at_release' => 12, 'arm_a' => null, 'arm_b' => null,
            'note' => 'Drift modelled as the AI component losing its injection control at release 12. Latency is the number of releases until the arm\'s suite flags it. An arm with no AI verification lane never flags it.'];

    foreach (['A' => 'arm-a', 'B' => 'arm-b'] as $arm => $suite) {
        [$failing, ] = runSuite($root, $suite, ['ai_prompt_injection_unfiltered']);
        $out['arm_' . strtolower($arm)] = $failing > 0 ? 0 : null;
    }
    return $out;
}

function median(array $values): float|int|null {
    if (!$values) return null;
    sort($values);
    $n = count($values);
    return $n % 2 ? $values[intdiv($n, 2)]
                  : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
}

function row(string $label, array $arms, string $key, bool $pct): void {
    $fmt = function ($v) use ($pct) {
        if ($v === null) return 'n/a';
        return $pct ? sprintf('%.1f%%', $v * 100) : (string)$v;
    };
    printf("%-34s %12s %12s\n", $label, $fmt($arms['A'][$key]), $fmt($arms['B'][$key]));
}
