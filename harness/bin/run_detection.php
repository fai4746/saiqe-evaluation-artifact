<?php
/**
 * M01 seeded-defect detection rate, M10 seeded vulnerability escapes.
 *
 * For each seeded defect: activate that defect and no other, run the arm's full
 * suite, and record whether any test failed. Running the whole suite rather than
 * the owning module's tests means a defect caught by another module's tests
 * still counts as detected — which is what "detected" means in practice.
 *
 * Usage: php harness/bin/run_detection.php <A|B>
 */

$arm = strtoupper($argv[1] ?? '');
if (!in_array($arm, ['A', 'B'], true)) {
    fwrite(STDERR, "usage: run_detection.php <A|B>\n");
    exit(2);
}

$root     = dirname(__DIR__, 2);
$register = json_decode(file_get_contents("$root/register/seeded_defects.json"), true);
$suite    = $arm === 'A' ? 'arm-a' : 'arm-b';
$php      = PHP_BINARY;
$junit    = sys_get_temp_dir() . "/saiqe_junit_$arm.xml";

$results   = [];
$startedAt = microtime(true);

echo "SAIQE detection run — Arm $arm\n";
echo str_repeat('=', 72) . "\n";
printf("%-6s %-8s %-44s %s\n", 'ID', 'MODULE', 'DEFECT', 'RESULT');
echo str_repeat('-', 72) . "\n";

foreach ($register['defects'] as $defect) {
    @unlink($junit);

    $cmd = sprintf(
        'set SAIQE_DEFECTS=%s&& %s %s --testsuite %s --no-coverage --log-junit %s',
        $defect['key'],
        escapeshellarg($php),
        escapeshellarg("$root/vendor/phpunit/phpunit/phpunit"),
        $suite,
        escapeshellarg($junit)
    );

    $t0 = microtime(true);
    exec($cmd . ' 2>&1', $out, $exitCode);
    $elapsed = microtime(true) - $t0;

    [$failing, $total] = readJunit($junit);
    $detected = $failing > 0;

    $results[] = [
        'defect_id'       => $defect['id'],
        'defect_key'      => $defect['key'],
        'module'          => $defect['module'],
        'class'           => $defect['class'],
        'severity'        => $defect['severity'],
        'cwe'             => $defect['cwe'] ?? null,
        'detected'        => $detected,
        'failing_tests'   => $failing,
        'total_tests'     => $total,
        'detecting_tests' => detectingTests($junit),
        'seconds'         => round($elapsed, 3),
    ];

    printf("%-6s %-8s %-44s %s\n",
        $defect['id'], $defect['module'],
        substr($defect['key'], 0, 43),
        $detected ? "DETECTED ($failing)" : 'ESCAPED');
}

$summary = summarise($results);
$payload = [
    'arm'          => $arm,
    'generated_at' => date('c'),
    'php'          => PHP_VERSION,
    'wall_seconds' => round(microtime(true) - $startedAt, 2),
    'summary'      => $summary,
    'results'      => $results,
];

@mkdir("$root/results", 0777, true);
file_put_contents("$root/results/detection_arm_$arm.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo str_repeat('-', 72) . "\n";
printf("overall            %d/%d detected (%.1f%%)\n",
    $summary['detected'], $summary['total'], $summary['detection_rate'] * 100);
printf("  security defects %d/%d (%.1f%%)\n",
    $summary['security_detected'], $summary['security_total'],
    $summary['security_total'] ? $summary['security_detected'] / $summary['security_total'] * 100 : 0);
printf("  functional       %d/%d (%.1f%%)\n",
    $summary['functional_detected'], $summary['functional_total'],
    $summary['functional_total'] ? $summary['functional_detected'] / $summary['functional_total'] * 100 : 0);
echo "written            results/detection_arm_$arm.json\n";

// ── helpers ────────────────────────────────────────────────────

function readJunit(string $path): array {
    if (!is_file($path)) return [0, 0];
    $xml = @simplexml_load_file($path);
    if (!$xml) return [0, 0];
    $tests = 0; $bad = 0;
    foreach ($xml->xpath('//testsuite[@tests]') ?: [] as $ts) {
        // Only leaf suites carry per-class counts; the root repeats them.
        if ($ts->xpath('testsuite')) continue;
        $tests += (int)$ts['tests'];
        $bad   += (int)$ts['failures'] + (int)$ts['errors'];
    }
    return [$bad, $tests];
}

function detectingTests(string $path): array {
    if (!is_file($path)) return [];
    $xml = @simplexml_load_file($path);
    if (!$xml) return [];
    $names = [];
    foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $tc) {
        $names[] = (string)$tc['class'] . '::' . (string)$tc['name'];
    }
    return $names;
}

function summarise(array $results): array {
    $byModule = [];
    foreach ($results as $r) {
        $m = $r['module'];
        $byModule[$m] ??= ['present' => 0, 'detected' => 0];
        $byModule[$m]['present']++;
        if ($r['detected']) $byModule[$m]['detected']++;
    }
    foreach ($byModule as $m => $v) {
        $byModule[$m]['rate'] = round($v['detected'] / $v['present'], 4);
    }
    ksort($byModule);

    $count = fn(callable $f) => count(array_filter($results, $f));

    $total = count($results);
    $detected = $count(fn($r) => $r['detected']);

    return [
        'total'               => $total,
        'detected'            => $detected,
        'escaped'             => $total - $detected,
        'detection_rate'      => $total ? round($detected / $total, 4) : 0,
        'security_total'      => $count(fn($r) => $r['class'] === 'security'),
        'security_detected'   => $count(fn($r) => $r['class'] === 'security' && $r['detected']),
        'security_escapes'    => $count(fn($r) => $r['class'] === 'security' && !$r['detected']),
        'functional_total'    => $count(fn($r) => $r['class'] === 'functional'),
        'functional_detected' => $count(fn($r) => $r['class'] === 'functional' && $r['detected']),
        'by_module'           => $byModule,
    ];
}
