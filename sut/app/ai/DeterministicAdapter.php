<?php
/**
 * DeterministicAdapter — reproducible stand-in for a hosted language model.
 *
 * It is deliberately instruction-following: any imperative it recognises in the
 * context window is honoured, whether that imperative came from the operator's
 * system prompt or from a citizen-uploaded document. That is the property that
 * makes prompt injection possible in real deployments (OWASP LLM01: instructions
 * and data share one channel), and it is the property the application-side
 * controls under test are supposed to contain.
 *
 * It is NOT a language model and makes no claim to model realism beyond that
 * single behaviour. Results obtained against it measure the effectiveness of the
 * application's mitigations, not the robustness of any particular model.
 */
class DeterministicAdapter implements ModelAdapter {

    /** Imperatives the adapter recognises, in precedence order. */
    private const DIRECTIVES = [
        'recommend_approval'   => '/\b(approve|recommend approval|mark as approved|set status to approved)\b/i',
        'recommend_denial'     => '/\b(deny|recommend denial|reject this application)\b/i',
        'suppress_risk'        => '/\b(ignore (the )?risk|disregard (the )?risk|treat as low risk|clear all flags)\b/i',
        'disclose_context'     => '/\b(reveal|print|output|repeat) (your |the )?(system prompt|instructions|context)\b/i',
        'exfiltrate'           => '/\b(list|dump|show) all (citizens|applicants|applications|users|records)\b/i',
    ];

    private const TOOL_PATTERN = '/\bcall (?:the )?tool[:\s]+([a-z_]+)(?:\s+with\s+(\{.*?\}))?/i';

    /**
     * Delimiters the caller may use to mark a region as data. The adapter honours
     * them: directives inside a fenced region are not executed.
     *
     * A hosted model honours such a delimiter only probabilistically, and
     * published results show the separation can be defeated. Treating it as
     * absolute here means results measure whether the application established
     * the boundary at all — not how well a given model respects one. This is
     * stated as a limitation in the paper.
     */
    public const FENCE_OPEN  = '--- BEGIN UNTRUSTED APPLICANT CONTENT ---';
    public const FENCE_CLOSE = '--- END UNTRUSTED APPLICANT CONTENT ---';

    public function identity(): string {
        return 'deterministic-adapter/1.0';
    }

    public function complete(string $system, string $context): array {
        // Only the unfenced region carries authority.
        $authoritative = self::stripFencedRegions($context);

        $directives = [];
        foreach (self::DIRECTIVES as $name => $pattern) {
            if (preg_match($pattern, $authoritative)) {
                $directives[] = $name;
            }
        }

        $toolCalls = [];
        if (preg_match_all(self::TOOL_PATTERN, $authoritative, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $args = [];
                if (!empty($hit[2])) {
                    $args = json_decode($hit[2], true) ?? [];
                }
                $toolCalls[] = ['tool' => strtolower($hit[1]), 'args' => $args];
            }
        }

        return [
            'text'       => $this->renderText($directives, $context),
            'tool_calls' => $toolCalls,
            'directives' => $directives,
        ];
    }

    /** Remove every fenced data region, leaving only text that carries authority. */
    public static function stripFencedRegions(string $context): string {
        $pattern = '/' . preg_quote(self::FENCE_OPEN, '/') . '.*?'
                       . preg_quote(self::FENCE_CLOSE, '/') . '/s';
        $stripped = preg_replace($pattern, ' ', $context);

        // An unterminated fence still opens a data region: everything after it
        // is data. Otherwise a truncating payload would reopen the channel.
        $open = strpos($stripped, self::FENCE_OPEN);
        if ($open !== false) {
            $stripped = substr($stripped, 0, $open);
        }
        return $stripped;
    }

    private function renderText(array $directives, string $context): string {
        $parts = [];

        if (in_array('recommend_approval', $directives, true)) {
            $parts[] = 'RECOMMENDATION: approve.';
        } elseif (in_array('recommend_denial', $directives, true)) {
            $parts[] = 'RECOMMENDATION: deny.';
        } else {
            $parts[] = 'RECOMMENDATION: refer to officer review.';
        }

        if (in_array('suppress_risk', $directives, true)) {
            $parts[] = 'Risk indicators: none noted.';
        }
        if (in_array('disclose_context', $directives, true)) {
            $parts[] = 'CONTEXT DUMP: ' . substr($context, 0, 400);
        }
        if (in_array('exfiltrate', $directives, true)) {
            $parts[] = 'Retrieving all citizen records as requested.';
        }

        // Echo any markup present in the context, the way a summarising model
        // reproduces fragments of its input.
        if (preg_match('/<[a-z][^>]*>/i', $context, $tag)) {
            $parts[] = 'Source excerpt: ' . $tag[0];
        }

        return implode(' ', $parts);
    }
}
