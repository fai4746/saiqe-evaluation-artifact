<?php
/**
 * ToolBroker — mediates every tool the case assistant may invoke.
 *
 * The assistant runs on behalf of an officer who is looking at one case. Its
 * authority is therefore scoped to that case and to that officer's role; a tool
 * call outside either bound is a privilege escalation regardless of how
 * convincing the model's justification was.
 */
class ToolBroker {

    /** tool => [required role ids, whether the call must name the case in scope] */
    public const REGISTRY = [
        'get_application'   => ['roles' => [ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_QR], 'case_scoped' => true],
        'get_risk_score'    => ['roles' => [ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_QR], 'case_scoped' => true],
        'list_documents'    => ['roles' => [ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_QR], 'case_scoped' => true],
        'flag_for_review'   => ['roles' => [ROLE_OFFICER, ROLE_SUPERVISOR],          'case_scoped' => true],
        'list_applications' => ['roles' => [ROLE_ADMIN],                             'case_scoped' => false],
        'set_status'        => ['roles' => [ROLE_SUPERVISOR],                        'case_scoped' => true],
    ];

    private int $roleId;
    private int $applicationInScope;
    private array $log = [];

    public function __construct(int $roleId, int $applicationInScope) {
        $this->roleId = $roleId;
        $this->applicationInScope = $applicationInScope;
    }

    /**
     * @return array ['allowed'=>bool, 'reason'=>string]
     */
    public function authorise(string $tool, array $args): array {
        $verdict = $this->decide($tool, $args);
        $this->log[] = ['tool' => $tool, 'args' => $args] + $verdict;
        return $verdict;
    }

    private function decide(string $tool, array $args): array {
        if (!isset(self::REGISTRY[$tool])) {
            return ['allowed' => false, 'reason' => 'unregistered_tool'];
        }

        // Defect: ai_tool_scope_unbounded — registry entries stop being enforced,
        // so a tool call carried in from untrusted content executes with the
        // assistant's full ambient authority (OWASP LLM06, excessive agency).
        if (defectActive('ai_tool_scope_unbounded')) {
            return ['allowed' => true, 'reason' => 'ok'];
        }

        $spec = self::REGISTRY[$tool];
        if (!in_array($this->roleId, $spec['roles'], true)) {
            return ['allowed' => false, 'reason' => 'role_not_permitted'];
        }
        if ($spec['case_scoped']) {
            $target = (int)($args['application_id'] ?? 0);
            if ($target !== $this->applicationInScope) {
                return ['allowed' => false, 'reason' => 'out_of_scope_application'];
            }
        }
        return ['allowed' => true, 'reason' => 'ok'];
    }

    /** Every authorisation decision made during a run, for the evidence record. */
    public function decisions(): array {
        return $this->log;
    }
}
