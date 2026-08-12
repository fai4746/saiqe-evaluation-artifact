<?php
/**
 * M18 evidence assembly — the simulated audit exercise.
 *
 * Ten requests in the form a supervisory authority would issue. Each is
 * resolved mechanically against what the arm actually retained: a request is
 * SATISFIED only when a signed record exists that carries the obligation, names
 * the control, and can be tied to a specific artifact digest.
 *
 * WHAT THIS MEASURES: whether the request is answerable at all from retained
 * artifacts. The playbook's timed variant needs a second person who does not
 * know where things are; that was not available, so the minutes columns are
 * left unmeasured rather than estimated. Requests-satisfiable is reported
 * instead, and is the more discriminating number.
 *
 * Usage: php harness/bin/run_evidence_audit.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/EvidenceStore.php';

$root = SAIQE_ROOT;

$requests = [
    ['R01', 'Demonstrate that the eligibility engine was tested before this release, and show the result.',
     'ISO/IEC/IEEE 29119-3', ['control' => 'C-01']],
    ['R02', 'Show that the test suite relied upon is capable of detecting faults, not merely of executing the code.',
     'EU AI Act Art. 17', ['control' => 'C-02']],
    ['R03', 'Produce the static security analysis performed on the code in this release.',
     'CRA Annex I Part I; NIST SSDF PW.7', ['control' => 'C-03']],
    ['R04', 'For each security finding raised, show the disposition and the reasoning recorded for it.',
     'CRA Art. 13(8)', ['control' => 'C-04']],
    ['R05', 'List the third-party components in this release and show they carried no known vulnerability at build time.',
     'CRA Annex II; NIST SSDF PS.3', ['control' => 'C-05']],
    ['R06', 'Show which parts of this release were machine-generated and how that was recorded at the time.',
     'EU AI Act Art. 12; NIST SSDF PS.3.2', ['control' => 'C-07']],
    ['R07', 'Demonstrate that the AI component was tested against adversarial input before release.',
     'EU AI Act Art. 15; NIST AI 600-1', ['control' => 'C-08']],
    ['R08', 'Show the release decision, who or what made it, and on what evidence.',
     'EU AI Act Art. 43; CRA Art. 13', ['control' => 'C-11']],
    ['R09', 'Trace a named requirement to the test that verifies it and to that test\'s result in this release.',
     'EU AI Act Annex IV(2)(g)', ['control' => 'C-12']],
    ['R10', 'Demonstrate that the retained records for this release have not been altered since they were made.',
     'CRA Art. 13(12)', ['control' => 'C-13']],
];

$rows = [];
$summary = [];

foreach (['A', 'B'] as $arm) {
    $store   = new EvidenceStore("$root/evidence/arm_$arm.jsonl", $arm, 'saiqe-evaluation-key-2026');
    $records = $store->all();
    $chain   = $store->verify();

    $satisfied = 0;
    foreach ($requests as [$id, $text, $obligation, $criteria]) {
        $match = null;
        foreach ($records as $r) {
            if ($r['control_id'] !== $criteria['control']) continue;
            // A record answers an obligation request only if it carries the
            // obligation reference, a payload digest, and a valid signature.
            if (empty($r['obligations']) || empty($r['payload_digest'])) continue;
            $match = $r;
            break;
        }

        $ok = $match !== null && $chain['intact'];
        if ($ok) $satisfied++;

        $rows[] = [
            'request' => $id, 'arm' => $arm, 'text' => $text, 'obligation' => $obligation,
            'satisfied' => $ok,
            'record_id' => $match['record_id'] ?? null,
            'basis' => $ok
                ? "signed record {$match['record_id']} for {$criteria['control']}, digest {$match['payload_digest']}"
                : "no retained record carries " . $criteria['control'] . " with an obligation reference in this arm",
            'minutes' => null,
        ];
    }

    $summary[$arm] = [
        'requests'            => count($requests),
        'satisfied'           => $satisfied,
        'satisfiable_ratio'   => round($satisfied / count($requests), 4),
        'evidence_records'    => count($records),
        'chain_intact'        => $chain['intact'],
        'minutes_per_request' => null,
    ];
}

$payload = [
    'generated_at' => date('c'),
    'method' => 'Mechanical resolution against retained artifacts. Timed human assembly not performed — see the note in this file and §9.',
    'requests' => $requests,
    'summary'  => $summary,
    'rows'     => $rows,
];

file_put_contents("$root/results/evidence_audit.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "M18 evidence assembly — simulated audit\n" . str_repeat('=', 72) . "\n";
printf("%-5s %-56s %5s %5s\n", 'REQ', 'REQUEST', 'A', 'B');
echo str_repeat('-', 72) . "\n";
foreach ($requests as $i => [$id, $text]) {
    $a = $rows[$i]['satisfied'];
    $b = $rows[count($requests) + $i]['satisfied'];
    printf("%-5s %-56s %5s %5s\n", $id, substr($text, 0, 55), $a ? 'yes' : 'NO', $b ? 'yes' : 'NO');
}
echo str_repeat('-', 72) . "\n";
printf("%-62s %5d %5d\n", 'satisfiable', $summary['A']['satisfied'], $summary['B']['satisfied']);
printf("%-62s %4.0f%% %4.0f%%\n", 'of 10 requests',
    $summary['A']['satisfiable_ratio'] * 100, $summary['B']['satisfiable_ratio'] * 100);
echo "\nMinutes per request: not measured (needs a second person who does not know the layout).\n";
echo "written results/evidence_audit.json\n";
