<?php
/**
 * M11 security-finding precision, and SAST recall against the seeded register.
 *
 * Arm A runs the stock rulesets and files the output. It does not triage — that
 * is the baseline practice under test, and precision is therefore *not measured*
 * for Arm A rather than assumed.
 *
 * Arm B runs the stock rulesets plus the SAIQE property rules and triages every
 * finding. Each verdict below was reached by reading the flagged line and its
 * surrounding function; the reason is recorded so the judgement is auditable
 * rather than asserted.
 *
 * Verdicts follow the three-way scheme in the literature:
 *   TP  — a real problem the team would act on
 *   EFP — "effective false positive": the pattern is really present, but in this
 *         context it carries no consequence the team would act on
 *   FP  — the tool is wrong about what the code does
 *
 * Usage: php harness/bin/triage_findings.php
 */

$root = dirname(__DIR__, 2);

/**
 * Verdicts keyed by "file:line". Every finding in the two report files must
 * appear here or the script fails: an untriaged finding may not be silently
 * dropped from the denominator.
 */
$verdicts = [
    // ── True positives ───────────────────────────────────────────
    'app/models/AuditLogModel.php:65' => ['TP',
        'ORDER BY is built by concatenating the return of a resolver applied to a caller-supplied key. This is the one place in the codebase where a request value reaches SQL structure, and it is correct to flag it.'],
    'app/controllers/AuthController.php:55' => ['TP',
        'A submitted password is passed as an argument into the call that writes the audit record. Whether it survives depends entirely on the callee; the data flow is real and is the right place to require a control.'],

    // ── Effective false positives ────────────────────────────────
    'app/controllers/AppealController.php:115' => ['EFP',
        'The storage name is system-generated here, so the flagged property holds. It does however show a fourth independent upload path that bypasses the shared validator — a real design finding, not the one the rule claims.'],
    'app/controllers/ApplicationController.php:143' => ['EFP',
        'Storage name is bin2hex(random_bytes(16)); the property holds. Duplicate upload path, as above.'],
    'app/controllers/WizardController.php:191' => ['EFP',
        'Storage name is system-generated; the property holds. Duplicate upload path, as above.'],
    'app/models/AuditLogModel.php:66' => ['EFP',
        'LIMIT and OFFSET are interpolated, but from an int parameter and an arithmetic expression on a cast int. Reachable only by changing the signature.'],
    'app/core/CaseAssignmentEngine.php:87' => ['EFP',
        'The interpolated fragment is a generated run of placeholders, not data. Safe by construction, though the construction is worth a comment.'],
    'app/core/EligibilityEngine.php:204' => ['EFP', 'Generated placeholder run, as above.'],
    'app/core/RiskScoreEngine.php:189' => ['EFP', 'Generated placeholder run, as above.'],
    'app/models/ApplicationModel.php:19' => ['EFP', 'Generated placeholder run, as above.'],
    'app/models/SLAModel.php:56' => ['EFP', 'Generated placeholder run, as above.'],
    'app/views/shared/audit_log.php:92' => ['EFP',
        'Request parameters are reflected into an href, but through http_build_query, which percent-encodes. Real reflection, no usable injection.'],
    'database/seed_data.sql:27' => ['EFP', 'Shared demo credential hash committed to the repository. Real, and intentional for a teaching fixture.'],
    'database/seed_data.sql:28' => ['EFP', 'As above.'],
    'database/seed_data.sql:29' => ['EFP', 'As above.'],
    'database/seed_data.sql:30' => ['EFP', 'As above.'],
    'database/seed_data.sql:31' => ['EFP', 'As above.'],
    'database/seed_data.sql:32' => ['EFP', 'As above.'],
    'database/seed_data.sql:33' => ['EFP', 'As above.'],
    'database/seed_data.sql:34' => ['EFP', 'As above.'],

    // ── False positives ──────────────────────────────────────────
    'api/v1/index.php:133' => ['FP',
        'The needle is cast to int before comparison and the haystack is a list of int constants. Loose comparison cannot admit anything the strict one would not.'],
    'app/controllers/UserController.php:31' => ['FP', 'Cast to int before comparison, as above.'],
    'app/core/CaseAssignmentEngine.php:106' => ['FP',
        'The haystack is built with array_map(intval, ...) immediately above. Both sides are integers.'],
    'app/controllers/AuthController.php:50' => ['FP',
        'password_verify() is the check itself, not a record. The rule matched on the enclosing function name containing "log" (processLogin).'],
    'app/controllers/DashboardController.php:121' => ['FP', 'json_encode output on a JSON endpoint, not HTML.'],
    'app/controllers/NotificationController.php:28' => ['FP', 'json_encode output on a JSON endpoint.'],
    'core/Response.php:6' => ['FP', 'json_encode output in the JSON responder.'],
    'app/controllers/DocumentController.php:109' => ['FP', 'A string literal is echoed. No variable reaches output.'],
    'app/controllers/SupervisorController.php:115' => ['FP', 'String literal.'],
    'app/controllers/SupervisorController.php:117' => ['FP', 'String literal.'],
    'app/controllers/DocumentController.php:71' => ['FP',
        'Storage name comes from UploadValidator::storedName(), which is system-generated on the clean path.'],
    'app/controllers/QualityReviewerController.php:79' => ['FP',
        'Not SQL. The rule\'s append pattern matched a plain string builder for reviewer notes.'],
    'app/core/FormRenderer.php:27' => ['FP', 'Not SQL. Attribute string builder, and both parts are escaped.'],
];

// Filter-clause appends: literal SQL fragments carrying bound placeholders.
foreach ([
    'app/models/ApplicationModel.php' => [154, 155, 156, 157, 158, 159],
    'app/models/AuditLogModel.php'    => [60, 61, 62, 63, 64, 113, 114, 115, 116],
    'app/models/CaseModel.php'        => [41, 42, 56, 57],
] as $file => $lines) {
    foreach ($lines as $line) {
        $verdicts["$file:$line"] = ['FP',
            'The appended fragment is a string literal containing a bound placeholder; the value goes to execute(). No caller data reaches statement structure.'];
    }
}

// ── Load findings ──────────────────────────────────────────────

$stock    = loadFindings("$root/results/sast/semgrep.json");
$property = loadFindings("$root/results/sast/semgrep_saiqe.json");

$armA = $stock;                               // stock rulesets only
$armB = dedupe(array_merge($stock, $property)); // stock + SAIQE property rules

$triagedB = [];
$untriaged = [];
foreach ($armB as $f) {
    $key = $f['file'] . ':' . $f['line'];
    if (!isset($verdicts[$key])) { $untriaged[] = $key . '  (' . $f['rule'] . ')'; continue; }
    [$verdict, $reason] = $verdicts[$key];
    $triagedB[] = $f + ['verdict' => $verdict, 'reason' => $reason];
}

if ($untriaged) {
    fwrite(STDERR, "UNTRIAGED FINDINGS — refusing to report precision:\n");
    foreach (array_unique($untriaged) as $u) fwrite(STDERR, "  $u\n");
    exit(1);
}

$count = fn(string $v) => count(array_filter($triagedB, fn($f) => $f['verdict'] === $v));
$tp = $count('TP'); $efp = $count('EFP'); $fp = $count('FP');
$total = count($triagedB);

// SAST recall against the seeded register: which seeded defect sites did any
// analyser flag? Defects are runtime toggles, so both branches are present in
// source at all times and are statically reachable.
$register = json_decode(file_get_contents("$root/register/seeded_defects.json"), true);
$flaggedFiles = array_unique(array_column($triagedB, 'file'));
$recall = [];
foreach ($register['defects'] as $d) {
    if ($d['class'] !== 'security') continue;
    $site = strtolower(explode('::', str_replace(' ', '', $d['site']))[0]);
    $hit = false;
    foreach ($triagedB as $f) {
        if ($f['verdict'] === 'TP' && str_contains($site, strtolower($f['file']))) { $hit = true; break; }
    }
    $recall[] = ['defect' => $d['id'], 'key' => $d['key'], 'cwe' => $d['cwe'], 'flagged_as_tp' => $hit];
}
$recalled = count(array_filter($recall, fn($r) => $r['flagged_as_tp']));

$payload = [
    'generated_at' => date('c'),
    'arm_a' => [
        'rulesets'  => ['p/php', 'p/owasp-top-ten', 'p/secrets', 'p/security-audit', 'p/xss', 'p/sql-injection', 'p/command-injection', 'p/insecure-transport'],
        'findings'  => count($armA),
        'triaged'   => false,
        'precision' => null,
        'note'      => 'Baseline practice files findings without triage. Precision is not measured, not assumed.',
    ],
    'arm_b' => [
        'rulesets'         => 'stock + policy/semgrep/saiqe-properties.yaml',
        'findings'         => $total,
        'true_positive'    => $tp,
        'effective_fp'     => $efp,
        'false_positive'   => $fp,
        'precision_strict' => $total ? round($tp / $total, 4) : null,
        'precision_actionable' => $total ? round(($tp + $efp) / $total, 4) : null,
        'noise_rate'       => $total ? round(($efp + $fp) / $total, 4) : null,
    ],
    'sast_recall_on_seeded_security_defects' => [
        'seeded_security_defects' => count($recall),
        'located_by_a_true_positive' => $recalled,
        'recall' => count($recall) ? round($recalled / count($recall), 4) : null,
        'detail' => $recall,
    ],
    'findings' => $triagedB,
];

file_put_contents("$root/results/triage.json",
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

printf("M11 security-finding precision\n%s\n", str_repeat('=', 62));
printf("Arm A  findings %d, untriaged — precision not measured\n", count($armA));
printf("Arm B  findings %d\n", $total);
printf("         true positive        %3d\n", $tp);
printf("         effective FP         %3d\n", $efp);
printf("         false positive       %3d\n", $fp);
printf("         precision (strict)   %.1f%%\n", $total ? $tp / $total * 100 : 0);
printf("         precision (actionable) %.1f%%\n", $total ? ($tp + $efp) / $total * 100 : 0);
printf("\nSAST recall on the %d seeded security defects: %d located (%.1f%%)\n",
    count($recall), $recalled, count($recall) ? $recalled / count($recall) * 100 : 0);
echo "written results/triage.json\n";

// ── helpers ────────────────────────────────────────────────────

function loadFindings(string $path): array {
    $d = json_decode(file_get_contents($path), true);
    $out = [];
    foreach ($d['results'] ?? [] as $x) {
        $file = str_replace('\\', '/', $x['path']);
        $file = preg_replace('#^sut/#', '', $file);
        $out[] = [
            'rule' => substr($x['check_id'], strrpos($x['check_id'], '.') + 1),
            'file' => $file,
            'line' => (int)$x['start']['line'],
        ];
    }
    return $out;
}

function dedupe(array $findings): array {
    $seen = []; $out = [];
    foreach ($findings as $f) {
        $k = $f['file'] . ':' . $f['line'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $f;
    }
    return $out;
}
