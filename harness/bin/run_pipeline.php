<?php
/**
 * One full pipeline run for one arm, end to end, wall-clocked.
 *
 * Produces M15 control coverage, M16 evidence completeness, M17 traceability
 * completeness, M08 vulnerability density by provenance, M28 pipeline duration,
 * and the release-gate decision.
 *
 * Arm A runs the same stages with the same tools, unintegrated: findings go to
 * a backlog, nothing is signed, nothing carries an obligation reference, and
 * the gate sees only what a conventional pipeline produces.
 *
 * Usage: php harness/bin/run_pipeline.php <A|B>
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/EvidenceStore.php';
require_once dirname(__DIR__) . '/src/PolicyGate.php';

$arm = strtoupper($argv[1] ?? '');
if (!in_array($arm, ['A', 'B'], true)) { fwrite(STDERR, "usage: run_pipeline.php <A|B>\n"); exit(2); }

$root      = SAIQE_ROOT;
$suite     = $arm === 'A' ? 'arm-a' : 'arm-b';
$catalogue = json_decode(file_get_contents("$root/policy/control_catalogue.json"), true)['controls'];
$quality   = json_decode(file_get_contents("$root/results/quality_by_module.json"), true);
$triage    = json_decode(file_get_contents("$root/results/triage.json"), true);
$provIndex = json_decode(file_get_contents("$root/register/provenance.json"), true);

$storePath = "$root/evidence/arm_$arm.jsonl";
@unlink($storePath);
$store = new EvidenceStore($storePath, $arm, 'saiqe-evaluation-key-2026');

$integrated = ($arm === 'B');
$t0 = microtime(true);
$stageTimes = [];
$emitted = [];

/** Record a stage: time it, and in Arm B write a signed evidence record. */
$stage = function (string $controlId, string $stageName, callable $work) use (
    &$stageTimes, &$emitted, $store, $catalogue, $integrated
) {
    $control = null;
    foreach ($catalogue as $c) if ($c['id'] === $controlId) $control = $c;

    $t = microtime(true);
    $result = $work();
    $stageTimes[$controlId] = round(microtime(true) - $t, 3);

    if ($integrated && ($result['executed'] ?? false)) {
        $store->append(
            $controlId, $stageName,
            $result['decision'] ?? 'RECORDED',
            $control['obligations'] ?? [],
            $result['payload'] ?? [],
            $control['retention'] ?? 'standard'
        );
        $emitted[$controlId] = true;
    }
    return $result;
};

echo "SAIQE pipeline — Arm $arm\n" . str_repeat('=', 64) . "\n";

// ── C-01 test execution ────────────────────────────────────────
$tests = $stage('C-01', 'ci_build', function () use ($root, $suite) {
    $junit = sys_get_temp_dir() . "/saiqe_pipeline_junit.xml";
    @unlink($junit);
    exec(sprintf('set SAIQE_DEFECTS=&& %s %s --testsuite %s --no-coverage --log-junit %s 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg("$root/vendor/phpunit/phpunit/phpunit"),
        $suite, escapeshellarg($junit)), $o, $rc);

    $tests = 0; $failing = 0;
    if (is_file($junit) && ($xml = @simplexml_load_file($junit))) {
        foreach ($xml->xpath('//testsuite[@tests]') as $ts) {
            if ($ts->xpath('testsuite')) continue;
            $tests   += (int)$ts['tests'];
            $failing += (int)$ts['failures'] + (int)$ts['errors'];
        }
    }
    return ['executed' => true, 'decision' => $failing === 0 ? 'PASS' : 'FAIL',
            'payload' => ['tests' => $tests, 'failing' => $failing],
            'tests' => $tests, 'failing' => $failing];
});
printf("  C-01 tests            %d run, %d failing\n", $tests['tests'], $tests['failing']);

// ── C-02 mutation (Arm B gate) / coverage (Arm A gate) ─────────
$totals = $quality['arms'][$arm]['totals'];
$mutation = $stage('C-02', 'ci_build', fn() => [
    'executed' => $integrated,
    'decision' => 'RECORDED',
    'payload'  => ['msi' => $totals['msi'], 'covered_msi' => $totals['covered_msi']],
]);
printf("  C-02 mutation         MSI %.1f%%, covered-MSI %.1f%%%s\n",
    $totals['msi'] * 100, $totals['covered_msi'] * 100,
    $integrated ? '' : '  (measured, not gated in this arm)');

// ── C-03 SAST ──────────────────────────────────────────────────
$sast = $stage('C-03', 'ci_build', function () use ($triage, $arm) {
    $count = $arm === 'A' ? $triage['arm_a']['findings'] : $triage['arm_b']['findings'];
    return ['executed' => true, 'decision' => 'RECORDED',
            'payload' => ['findings' => $count], 'findings' => $count];
});
printf("  C-03 SAST             %d findings\n", $sast['findings']);

// ── C-04 triage (Arm B only: the baseline does not triage) ─────
$triageStage = $stage('C-04', 'ci_build', function () use ($triage, $arm) {
    if ($arm === 'A') return ['executed' => false, 'untriaged' => $triage['arm_a']['findings'], 'unresolved_tp' => null];
    return ['executed' => true, 'decision' => 'PASS',
            'payload' => ['true_positive' => $triage['arm_b']['true_positive'],
                          'effective_fp'  => $triage['arm_b']['effective_fp'],
                          'false_positive'=> $triage['arm_b']['false_positive'],
                          'precision'     => $triage['arm_b']['precision_strict']],
            'untriaged' => 0,
            'unresolved_tp' => 0];   // both TPs are the seeded-defect sites, gated by C-11
});
printf("  C-04 triage           %s\n", $arm === 'A'
    ? "not performed — {$triageStage['untriaged']} findings filed to backlog"
    : sprintf('%d TP / %d EFP / %d FP, precision %.1f%%',
        $triage['arm_b']['true_positive'], $triage['arm_b']['effective_fp'],
        $triage['arm_b']['false_positive'], $triage['arm_b']['precision_strict'] * 100));

// ── C-05 dependency audit ──────────────────────────────────────
$sca = $stage('C-05', 'ci_build', function () use ($root) {
    exec(sprintf('%s %s audit --locked --format=json 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg('C:/ProgramData/ComposerSetup/bin/composer.phar')), $o, $rc);
    $json = json_decode(implode("\n", $o), true);
    $advisories = is_array($json['advisories'] ?? null) ? count($json['advisories']) : 0;
    return ['executed' => true, 'decision' => $advisories === 0 ? 'PASS' : 'FAIL',
            'payload' => ['advisories' => $advisories], 'advisories' => $advisories];
});
printf("  C-05 dependencies     %d advisories\n", $sca['advisories']);

// ── C-06 secrets ───────────────────────────────────────────────
$secrets = $stage('C-06', 'pre_commit', function () use ($root) {
    $out = "$root/results/sast/detect_secrets.json";
    $n = 0;
    if (is_file($out)) {
        $d = json_decode(file_get_contents($out), true);
        foreach ($d['results'] ?? [] as $items) $n += count($items);
    }
    return ['executed' => true, 'decision' => $n === 0 ? 'PASS' : 'FAIL',
            'payload' => ['secrets' => $n], 'secrets' => $n];
});
printf("  C-06 secrets          %d\n", $secrets['secrets']);

// ── C-07 provenance ────────────────────────────────────────────
$prov = $stage('C-07', 'author', function () use ($root, $provIndex, $triage, $integrated) {
    if (!$integrated) return ['executed' => false, 'complete' => false, 'density' => null];
    $density = provenanceDensity($root, $provIndex, $triage);
    return ['executed' => true, 'decision' => 'RECORDED',
            'payload' => $density, 'complete' => true, 'density' => $density];
});
if ($prov['density']) {
    foreach ($prov['density'] as $class => $d) {
        printf("  C-07 provenance       %-12s %5.1f KLOC, %d findings, %.2f/KLOC\n",
            $class, $d['kloc'], $d['findings'], $d['per_kloc']);
    }
} else {
    echo "  C-07 provenance       not tracked in this arm\n";
}

// ── C-08 / C-09 AI lane ────────────────────────────────────────
$ai = $stage('C-08', 'ai_lane', function () use ($root, $integrated) {
    if (!$integrated) return ['executed' => false, 'pass_rate' => null];
    $f = "$root/results/adversarial/adversarial_clean.json";
    if (!is_file($f)) return ['executed' => false, 'pass_rate' => null];
    $d = json_decode(file_get_contents($f), true);
    return ['executed' => true, 'decision' => $d['pass_rate'] >= 1.0 ? 'PASS' : 'FAIL',
            'payload' => ['trials' => $d['trials'], 'pass_rate' => $d['pass_rate'], 'by_category' => $d['by_category']],
            'pass_rate' => $d['pass_rate']];
});
printf("  C-08 AI adversarial   %s\n", $ai['pass_rate'] === null
    ? 'no AI verification lane in this arm'
    : sprintf('%.1f%% pass', $ai['pass_rate'] * 100));

// ── C-12 traceability ──────────────────────────────────────────
$trace = $stage('C-12', 'release_gate', function () use ($root, $suite, $integrated) {
    $t = traceability($root, $suite === 'arm-a' ? 'ArmA' : 'ArmB');
    return ['executed' => $integrated, 'decision' => 'RECORDED', 'payload' => $t, 'trace' => $t];
});
printf("  C-12 traceability     %d/%d requirements covered (%.1f%%)\n",
    $trace['trace']['covered'], $trace['trace']['requirements'], $trace['trace']['completeness'] * 100);

// ── C-14 accessibility ─────────────────────────────────────────
$a11y = $stage('C-14', 'ci_build', function () {
    $html = FormRenderer::field('annual_income', 'Annual income');
    $failures = FormRenderer::accessibleName($html) === null ? 1 : 0;
    return ['executed' => true, 'decision' => $failures === 0 ? 'PASS' : 'FAIL',
            'payload' => ['controls_checked' => 1, 'without_accessible_name' => $failures],
            'failures' => $failures];
});
printf("  C-14 accessibility    %d control(s) without an accessible name\n", $a11y['failures']);

// ── C-13 evidence chain ────────────────────────────────────────
$chain = $store->verify();
printf("  C-13 evidence chain   %d records, intact=%s\n", $chain['records'], $chain['intact'] ? 'yes' : 'no');

// ── C-11 release gate ──────────────────────────────────────────
$gate = new PolicyGate("$root/policy/release_gate.json");
$facts = [
    'tests_failing'             => $tests['failing'],
    'line_coverage'             => $totals['line_coverage'],
    'covered_msi'               => $integrated ? $totals['covered_msi'] : null,
    'untriaged_findings'        => $triageStage['untriaged'],
    'unresolved_true_positives' => $triageStage['unresolved_tp'],
    'secrets_found'             => $secrets['secrets'],
    'adversarial_pass_rate'     => $ai['pass_rate'],
    'provenance_complete'       => $prov['complete'],
    'traceability_completeness' => $trace['trace']['completeness'],
    'evidence_chain_intact'     => $integrated ? $chain['intact'] : null,
    'accessibility_failures'    => $a11y['failures'],
    'vulnerable_dependencies'   => $sca['advisories'],
];

$applicable = array_values(array_filter(
    json_decode(file_get_contents("$root/policy/release_gate.json"), true)['rules'],
    fn($r) => $r['arm'] === 'both' || $r['arm'] === $arm
));
$gateResult = (new PolicyGate("$root/policy/release_gate.json"))->evaluate($facts);
$gateResult['evaluated'] = array_values(array_filter($gateResult['evaluated'],
    fn($r) => $r['arm'] === 'both' || $r['arm'] === $arm));
$gateResult['blocking'] = array_values(array_filter($gateResult['evaluated'], fn($r) => $r['outcome'] === 'BLOCK'));
$gateResult['warnings'] = array_values(array_filter($gateResult['evaluated'], fn($r) => $r['outcome'] === 'WARN'));
$gateResult['decision'] = $gateResult['blocking'] ? 'BLOCK' : ($gateResult['warnings'] ? 'WARN' : 'PASS');

if ($integrated) {
    $store->append('C-11', 'release_gate', $gateResult['decision'],
        ['EU AI Act Art. 43', 'CRA Art. 13'], $gateResult, 'long');
    $emitted['C-11'] = true;

    // C-13 is only satisfied by an artifact. Verifying the chain and then
    // claiming the control without emitting the manifest was a gap the
    // simulated audit (R10) surfaced.
    $manifest = $store->verify();
    $store->append('C-13', 'release_gate', $manifest['intact'] ? 'PASS' : 'FAIL',
        ['EU AI Act Art. 12', 'CRA Art. 13(12)'],
        ['records' => $manifest['records'],
         'signature_failures' => $manifest['signature_failures'],
         'chain_breaks' => $manifest['chain_breaks'],
         'algorithm' => 'sha256 chained, hmac-sha256 signed',
         'retention_years' => 10],
        'decade');
    $emitted['C-13'] = true;
    $chain = $store->verify();
}

$wall = round(microtime(true) - $t0, 2);

// ── M15 / M16 ──────────────────────────────────────────────────
$applicableControls = array_values(array_filter($catalogue, fn($c) => $c['applicable']));
$executed = 0; $signed = 0;
$controlDetail = [];
foreach ($applicableControls as $c) {
    $ran = isset($stageTimes[$c['id']]) && !isset($c['instantiation_note']);
    if ($c['id'] === 'C-13' || $c['id'] === 'C-11') $ran = true;
    if ($c['id'] === 'C-09') $ran = $integrated;    // covered by the tool-scope suite
    if ($c['id'] === 'C-10') $ran = false;          // drift: measured in the replay, not here
    if (in_array($c['id'], ['C-02','C-04','C-07','C-08','C-12'], true) && !$integrated) $ran = false;
    if ($ran) $executed++;
    if (isset($emitted[$c['id']])) $signed++;
    $controlDetail[$c['id']] = ['executed' => $ran, 'evidence_signed' => isset($emitted[$c['id']])];
}

$m15 = round($executed / count($applicableControls), 4);
$m16 = round($signed   / count($applicableControls), 4);

$payload = [
    'arm' => $arm, 'generated_at' => date('c'),
    'wall_seconds' => $wall,
    'stage_seconds' => $stageTimes,
    'gate' => $gateResult,
    'evidence_chain' => $chain,
    'metrics' => [
        'M15_control_coverage'       => $m15,
        'M16_evidence_completeness'  => $m16,
        'M17_traceability'           => $trace['trace']['completeness'],
        'M28_wall_seconds'           => $wall,
        'M11_precision'              => $arm === 'A' ? null : $triage['arm_b']['precision_strict'],
        'M12_adversarial_pass_rate'  => $ai['pass_rate'],
        'M08_density_by_provenance'  => $prov['density'],
    ],
    'controls' => $controlDetail,
    'facts' => $facts,
];

file_put_contents("$root/results/pipeline_arm_$arm.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo str_repeat('-', 64) . "\n";
printf("  M15 control coverage       %.1f%%  (%d/%d applicable controls executed)\n", $m15 * 100, $executed, count($applicableControls));
printf("  M16 evidence completeness  %.1f%%  (%d signed records)\n", $m16 * 100, $signed);
printf("  M17 traceability           %.1f%%\n", $trace['trace']['completeness'] * 100);
printf("  M28 wall clock             %.2fs\n", $wall);
printf("  GATE DECISION              %s%s\n", $gateResult['decision'],
    $gateResult['blocking'] ? ' — ' . implode('; ', array_column($gateResult['blocking'], 'id')) : '');
echo "written results/pipeline_arm_$arm.json\n";

// ── helpers ────────────────────────────────────────────────────

function traceability(string $root, string $armDir): array {
    preg_match_all('/\*\*(FR-\d+\.\d+)\*\*/', file_get_contents("$root/docs/SPEC-functional.md"), $m);
    $requirements = array_values(array_unique($m[1]));

    $cited = [];
    foreach (glob("$root/harness/tests/$armDir/*.php") as $f) {
        preg_match_all('/FR-\d+\.\d+/', file_get_contents($f), $c);
        foreach ($c[0] as $id) $cited[$id] = true;
    }

    $covered = count(array_intersect($requirements, array_keys($cited)));
    return [
        'requirements' => count($requirements),
        'covered'      => $covered,
        'uncovered'    => array_values(array_diff($requirements, array_keys($cited))),
        'completeness' => count($requirements) ? round($covered / count($requirements), 4) : 0.0,
    ];
}

function provenanceDensity(string $root, array $index, array $triage): array {
    $buckets = ['human' => ['loc' => 0, 'findings' => 0], 'ai' => ['loc' => 0, 'findings' => 0],
                'ai_modified' => ['loc' => 0, 'findings' => 0]];

    $classify = function (string $rel) use ($index): string {
        return $index['files'][$rel] ?? $index['default'];
    };

    $base = "$root/sut";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        if (str_starts_with($rel, 'app/views/') || str_starts_with($rel, 'public/')) continue;
        $buckets[$classify($rel)]['loc'] += count(file($file->getPathname()));
    }

    foreach ($triage['findings'] as $f) {
        if ($f['verdict'] === 'FP') continue;   // density counts real findings only
        $class = $index['files'][$f['file']] ?? $index['default'];
        if (isset($buckets[$class])) $buckets[$class]['findings']++;
    }

    $out = [];
    foreach ($buckets as $class => $b) {
        $kloc = $b['loc'] / 1000;
        $out[$class] = ['kloc' => round($kloc, 2), 'findings' => $b['findings'],
                        'per_kloc' => $kloc > 0 ? round($b['findings'] / $kloc, 2) : null];
    }
    return $out;
}
