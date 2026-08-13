<?php
/**
 * Cross-check every headline figure in the manuscript against the raw result
 * files. Catches the class of error a reviewer will find first: a number that
 * moved in one place and not another.
 *
 * Usage: php harness/bin/verify_manuscript.php <path-to-manuscript.md>
 */

$root = dirname(__DIR__, 2);
$ms   = $argv[1] ?? 'C:/Users/Admin/OneDrive/REsearchSAIQE/manuscripts/06_manuscript_draft.md';
$text = file_get_contents($ms);

$load = fn(string $p) => json_decode(file_get_contents("$root/results/$p"), true);

$detA  = $load('detection_arm_A.json');
$detB  = $load('detection_arm_B.json');
$q     = $load('quality_by_module.json');
$tri   = $load('triage.json');
$pipeA = $load('pipeline_arm_A.json');
$pipeB = $load('pipeline_arm_B.json');
$aud   = $load('evidence_audit.json');
$stat  = $load('statistics.json');
$rep   = $load('replay.json');
$advC  = $load('adversarial/adversarial_clean.json');
$advB  = $load('adversarial/adversarial_clean_before_fence_fix.json');

$eff = [];
foreach ($stat['results'] as $r) $eff[$r['metric']] = $r;

$pc = fn($v, $d = 1) => number_format($v * 100, $d);

// label => [expected string that must appear, actual source value]
$checks = [
    'M01 Arm A detection'      => $pc($detA['summary']['detection_rate']) . '%',
    'M01 Arm B detection'      => $pc($detB['summary']['detection_rate']) . '%',
    'M01s Arm A security'      => $pc($detA['summary']['security_detected'] / $detA['summary']['security_total']) . '%',
    'M01f Arm A functional'    => $pc($detA['summary']['functional_detected'] / $detA['summary']['functional_total']) . '%',
    'M01f Arm B functional'    => $pc($detB['summary']['functional_detected'] / $detB['summary']['functional_total']) . '%',
    'M04 Arm A MSI (2dp)'      => $pc($q['arms']['A']['totals']['msi'], 2) . '%',
    'M04 Arm B MSI (2dp)'      => $pc($q['arms']['B']['totals']['msi'], 2) . '%',
    'M04c Arm A covered (2dp)' => $pc($q['arms']['A']['totals']['covered_msi'], 2) . '%',
    'M04c Arm B covered (2dp)' => $pc($q['arms']['B']['totals']['covered_msi'], 2) . '%',
    'M05 Arm A coverage (2dp)' => $pc($q['arms']['A']['totals']['line_coverage'], 2) . '%',
    'M05 Arm B coverage (2dp)' => $pc($q['arms']['B']['totals']['line_coverage'], 2) . '%',
    'M11 precision strict'     => number_format($tri['arm_b']['true_positive'] / $tri['arm_b']['findings'] * 100, 2) . '%',
    'M11a precision actionable'=> number_format(($tri['arm_b']['true_positive'] + $tri['arm_b']['effective_fp']) / $tri['arm_b']['findings'] * 100, 2) . '%',
    'M12 clean, as measured'   => $pc($advB['pass_rate']) . '%',
    'M12b after repair'        => $pc($advC['pass_rate']) . '%',
    'M15 Arm A'                => $pc($pipeA['metrics']['M15_control_coverage'], 2) . '%',
    'M15 Arm B'                => $pc($pipeB['metrics']['M15_control_coverage'], 2) . '%',
    'M16 Arm B'                => $pc($pipeB['metrics']['M16_evidence_completeness']) . '%',
    'M17 Arm A'                => $pc($pipeA['metrics']['M17_traceability']) . '%',
    'M17 Arm B'                => $pc($pipeB['metrics']['M17_traceability']) . '%',
    'M01 p-value'              => number_format($eff['M01']['p_value'], 4),
    'M04 p-value'              => number_format($eff['M04']['p_value'], 3),
    'M04c p-value'             => number_format($eff['M04c']['p_value'], 3),
    'M05 p-value'              => number_format($eff['M05']['p_value'], 3),
];

$deltas = [
    'M04 delta pp'  => number_format(($q['arms']['B']['totals']['msi'] - $q['arms']['A']['totals']['msi']) * 100, 2),
    'M04c delta pp' => number_format(($q['arms']['B']['totals']['covered_msi'] - $q['arms']['A']['totals']['covered_msi']) * 100, 2),
    'M05 delta pp'  => number_format(($q['arms']['B']['totals']['line_coverage'] - $q['arms']['A']['totals']['line_coverage']) * 100, 2),
];

$counts = [
    'seeded defects'          => count(json_decode(file_get_contents("$root/register/seeded_defects.json"), true)['defects']),
    'Arm A security escapes'  => $detA['summary']['security_escapes'],
    'Arm B security escapes'  => $detB['summary']['security_escapes'],
    'audit satisfiable A'     => $aud['summary']['A']['satisfied'],
    'audit satisfiable B'     => $aud['summary']['B']['satisfied'],
    'findings triaged'        => $tri['arm_b']['findings'],
    'true positives'          => $tri['arm_b']['true_positive'],
    'releases shipped A'      => $rep['arms']['A']['shipped'],
    'releases shipped B'      => $rep['arms']['B']['shipped'],
    'never detected A'        => $rep['arms']['A']['defects_never_detected'],
    'never detected B'        => $rep['arms']['B']['defects_never_detected'],
];

echo "Manuscript cross-check against raw results\n" . str_repeat('=', 68) . "\n";
$bad = 0;

foreach ($checks as $label => $value) {
    $ok = str_contains($text, $value);
    printf("%-28s %-12s %s\n", $label, $value, $ok ? 'present' : 'NOT FOUND IN MANUSCRIPT');
    if (!$ok) $bad++;
}

echo str_repeat('-', 68) . "\n";
foreach ($deltas as $label => $value) {
    printf("%-28s %-12s (computed from raw)\n", $label, '+' . $value . ' pp');
}

echo str_repeat('-', 68) . "\n";
foreach ($counts as $label => $value) {
    printf("%-28s %s\n", $label, $value);
}

echo str_repeat('=', 68) . "\n";
$brackets = preg_match_all('/\[(VALUE|State|Complete|If case|SELECT|METHODS|STATE)[^\]]*\]/i', $text);
printf("unresolved placeholders      %d\n", $brackets);
printf("figures not found            %d\n", $bad);
exit($bad + $brackets > 0 ? 1 : 0);
