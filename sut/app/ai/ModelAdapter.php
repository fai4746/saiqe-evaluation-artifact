<?php
/**
 * ModelAdapter — the boundary between the case assistant and whatever model
 * backs it.
 *
 * Two implementations exist. DeterministicAdapter is the default and is what
 * the evaluation runs against: it reproduces the one behaviour that matters for
 * the security properties under test — an instruction appearing anywhere in the
 * context window is a candidate for execution, with no architectural separation
 * between instruction and data. HttpModelAdapter is the seam for a hosted model.
 */
interface ModelAdapter {
    /**
     * @param string $system   trusted operator instructions
     * @param string $context  assembled context, may contain untrusted content
     * @return array ['text' => string, 'tool_calls' => array<array{tool:string,args:array}>]
     */
    public function complete(string $system, string $context): array;

    /** Stable identifier recorded on every evidence artifact. */
    public function identity(): string;
}
