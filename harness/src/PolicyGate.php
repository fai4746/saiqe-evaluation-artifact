<?php
/**
 * PolicyGate — the release decision, expressed as data rather than as code
 * scattered through the pipeline (framework Pillar 3).
 *
 * Rules are declarative and each carries the obligation it serves, so a BLOCK
 * can be explained to an auditor in the same terms the regulation uses.
 * Decision states are PASS / WARN / BLOCK.
 *
 * The framework specifies Open Policy Agent with Conftest. Neither binary was
 * reachable from the build environment, so this evaluator stands in; the same
 * rules are also emitted as Rego (policy/rego/release_gate.rego) so they run
 * unchanged under OPA where it is available. The substitution is recorded in
 * the paper's instrumentation note.
 */
class PolicyGate {

    private array $rules;

    public function __construct(string $policyPath) {
        $this->rules = json_decode(file_get_contents($policyPath), true)['rules'];
    }

    /**
     * @param array $facts pipeline facts, e.g.
     *        ['msi'=>0.31,'line_coverage'=>0.70,'untriaged_findings'=>0, ...]
     * @return array ['decision'=>'PASS'|'WARN'|'BLOCK', 'evaluated'=>[...]]
     */
    public function evaluate(array $facts): array {
        $evaluated = [];
        $worst = 'PASS';

        foreach ($this->rules as $rule) {
            $fact = $facts[$rule['fact']] ?? null;

            if ($fact === null) {
                $evaluated[] = $rule + ['observed' => null, 'outcome' => 'NOT_EVALUATED',
                    'explanation' => "fact '{$rule['fact']}' was not produced by this pipeline"];
                continue;
            }

            $satisfied = $this->compare($fact, $rule['operator'], $rule['threshold']);
            $outcome = $satisfied ? 'PASS' : $rule['on_violation'];

            $evaluated[] = $rule + [
                'observed'    => $fact,
                'outcome'     => $outcome,
                'explanation' => $satisfied
                    ? "{$rule['fact']} = " . self::fmt($fact) . " satisfies {$rule['operator']} " . self::fmt($rule['threshold'])
                    : "{$rule['fact']} = " . self::fmt($fact) . " violates {$rule['operator']} " . self::fmt($rule['threshold']),
            ];

            if ($outcome === 'BLOCK') $worst = 'BLOCK';
            elseif ($outcome === 'WARN' && $worst !== 'BLOCK') $worst = 'WARN';
        }

        return [
            'decision'  => $worst,
            'evaluated' => $evaluated,
            'blocking'  => array_values(array_filter($evaluated, fn($r) => $r['outcome'] === 'BLOCK')),
            'warnings'  => array_values(array_filter($evaluated, fn($r) => $r['outcome'] === 'WARN')),
        ];
    }

    private function compare(mixed $fact, string $op, mixed $threshold): bool {
        return match ($op) {
            '>='  => $fact >= $threshold,
            '<='  => $fact <= $threshold,
            '>'   => $fact >  $threshold,
            '<'   => $fact <  $threshold,
            '=='  => $fact == $threshold,
            'is'  => $fact === $threshold,
            default => throw new InvalidArgumentException("unknown operator $op"),
        };
    }

    private static function fmt(mixed $v): string {
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_float($v)) return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
        return (string)$v;
    }
}
