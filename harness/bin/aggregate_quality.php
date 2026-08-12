<?php
/**
 * M04 mutation score and M05 coverage, aggregated to the module.
 *
 * The unit of analysis is the module, so a whole-codebase MSI is the wrong
 * comparison: it is diluted by files no arm was ever asked to cover. This maps
 * every mutant and every covered line back to the module that owns the file.
 *
 * Usage: php harness/bin/aggregate_quality.php
 */

$root    = dirname(__DIR__, 2);
$modules = json_decode(file_get_contents("$root/register/modules.json"), true)['modules'];

// file path (as it appears in reports) => module id
$owner = [];
// Where a module lists measured_units, those are the files mutation and
// coverage are attributed to: the rest of its primary units are request-bound
// controllers that neither arm exercises directly.
foreach ($modules as $m) {
    foreach (($m['measured_units'] ?? $m['primary_units']) as $unit) {
        $owner[normalise($unit)] = $m['id'];
    }
}

$out = ['generated_at' => date('c'), 'arms' => []];

foreach (['A' => 'a', 'B' => 'b'] as $arm => $lower) {
    $mutation = mutationByModule("$root/results/infection_arm_$arm.json", $owner);
    $coverage = coverageByModule("$root/results/clover_arm_$lower.xml", $owner);

    $perModule = [];
    foreach ($modules as $m) {
        $id = $m['id'];
        $mu = $mutation[$id] ?? ['killed' => 0, 'escaped' => 0, 'uncovered' => 0, 'timeout' => 0];
        $cv = $coverage[$id] ?? ['statements' => 0, 'covered' => 0, 'branches' => 0, 'branches_covered' => 0];

        $totalMutants   = $mu['killed'] + $mu['escaped'] + $mu['uncovered'] + $mu['timeout'];
        $coveredMutants = $mu['killed'] + $mu['escaped'] + $mu['timeout'];

        $perModule[$id] = [
            'module'          => $m['name'],
            'mutants_total'   => $totalMutants,
            'mutants_killed'  => $mu['killed'] + $mu['timeout'],
            'mutants_escaped' => $mu['escaped'],
            'mutants_uncovered' => $mu['uncovered'],
            'msi'             => $totalMutants   ? round(($mu['killed'] + $mu['timeout']) / $totalMutants, 4) : null,
            'covered_msi'     => $coveredMutants ? round(($mu['killed'] + $mu['timeout']) / $coveredMutants, 4) : null,
            'statements'      => $cv['statements'],
            'line_coverage'   => $cv['statements'] ? round($cv['covered'] / $cv['statements'], 4) : null,
            'branches'        => $cv['branches'],
            'branch_coverage' => $cv['branches'] ? round($cv['branches_covered'] / $cv['branches'], 4) : null,
        ];
    }

    $out['arms'][$arm] = ['by_module' => $perModule, 'totals' => totals($perModule)];
}

file_put_contents("$root/results/quality_by_module.json",
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

report($out, $modules);

// ── helpers ────────────────────────────────────────────────────

function normalise(string $path): string {
    return strtolower(str_replace('\\', '/', trim($path, '/')));
}

/** Map a report file path onto a module by matching the owning unit's suffix. */
function moduleFor(string $file, array $owner): ?string {
    $n = normalise($file);
    foreach ($owner as $unit => $moduleId) {
        if (str_ends_with($n, $unit)) return $moduleId;
    }
    return null;
}

function mutationByModule(string $jsonPath, array $owner): array {
    $data = json_decode(file_get_contents($jsonPath), true);
    $buckets = [];

    $collect = function (string $key, string $bucket) use ($data, $owner, &$buckets) {
        foreach ($data[$key] ?? [] as $mutant) {
            $file = $mutant['mutator']['originalFilePath'] ?? '';
            $mid  = moduleFor($file, $owner);
            if ($mid === null) continue;
            $buckets[$mid] ??= ['killed' => 0, 'escaped' => 0, 'uncovered' => 0, 'timeout' => 0];
            $buckets[$mid][$bucket]++;
        }
    };

    $collect('killed',    'killed');
    $collect('escaped',   'escaped');
    $collect('uncovered', 'uncovered');
    $collect('timeouted', 'timeout');
    $collect('errored',   'killed');   // a fatal on the mutant is a detection

    return $buckets;
}

function coverageByModule(string $cloverPath, array $owner): array {
    $xml = simplexml_load_file($cloverPath);
    $buckets = [];

    foreach ($xml->xpath('//file') as $file) {
        $mid = moduleFor((string)$file['name'], $owner);
        if ($mid === null) continue;
        $buckets[$mid] ??= ['statements' => 0, 'covered' => 0, 'branches' => 0, 'branches_covered' => 0];

        foreach ($file->line as $line) {
            $type = (string)$line['type'];
            $hits = (int)$line['count'];
            if ($type === 'stmt' || $type === 'method') {
                $buckets[$mid]['statements']++;
                if ($hits > 0) $buckets[$mid]['covered']++;
            }
            if ($type === 'cond') {
                $total   = (int)$line['branchcount'];
                $covered = (int)$line['coveredbranchcount'];
                $buckets[$mid]['branches']        += $total;
                $buckets[$mid]['branches_covered']+= $covered;
            }
        }
    }
    return $buckets;
}

function totals(array $perModule): array {
    $sum = fn(string $k) => array_sum(array_column($perModule, $k));
    $mutants = $sum('mutants_total');
    $killed  = $sum('mutants_killed');
    $covered = $killed + $sum('mutants_escaped');
    $stmts   = $sum('statements');

    $coveredStatements = 0;
    foreach ($perModule as $m) {
        if ($m['line_coverage'] !== null) $coveredStatements += (int)round($m['line_coverage'] * $m['statements']);
    }

    return [
        'mutants_total' => $mutants,
        'msi'           => $mutants ? round($killed / $mutants, 4) : null,
        'covered_msi'   => $covered ? round($killed / $covered, 4) : null,
        'line_coverage' => $stmts   ? round($coveredStatements / $stmts, 4) : null,
    ];
}

function report(array $out, array $modules): void {
    echo "M04 mutation score / M05 coverage, by module\n";
    echo str_repeat('=', 86) . "\n";
    printf("%-8s %-34s %14s %14s %12s\n", 'MODULE', 'NAME', 'MSI A -> B', 'COV-MSI A->B', 'LINE A->B');
    echo str_repeat('-', 86) . "\n";

    foreach ($modules as $m) {
        $id = $m['id'];
        $a = $out['arms']['A']['by_module'][$id];
        $b = $out['arms']['B']['by_module'][$id];
        printf("%-8s %-34s %6s ->%6s %6s ->%6s %5s ->%5s\n",
            $id, substr($m['name'], 0, 33),
            pct($a['msi']),         pct($b['msi']),
            pct($a['covered_msi']), pct($b['covered_msi']),
            pct($a['line_coverage']), pct($b['line_coverage']));
    }

    echo str_repeat('-', 86) . "\n";
    $ta = $out['arms']['A']['totals'];
    $tb = $out['arms']['B']['totals'];
    printf("%-43s %6s ->%6s %6s ->%6s %5s ->%5s\n", 'TOTAL',
        pct($ta['msi']), pct($tb['msi']),
        pct($ta['covered_msi']), pct($tb['covered_msi']),
        pct($ta['line_coverage']), pct($tb['line_coverage']));
    echo "\nwritten results/quality_by_module.json\n";
}

function pct(?float $v): string {
    return $v === null ? '  n/a' : sprintf('%.1f%%', $v * 100);
}
