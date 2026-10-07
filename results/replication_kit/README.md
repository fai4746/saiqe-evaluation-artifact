# Blind replication — MOD-09 Authentication and session

You are the replicating author. The purpose of this exercise is to find out
whether the detection result in the paper survives when the person writing the
tests did **not** place the faults.

## What you have, and what you deliberately do not

**You have**
- `specifications/SPEC-functional.md` — the functional requirements
- `specifications/SPEC-security-properties.md` — the twelve security properties
- `api-surface/` — class and method signatures with docblocks, no method bodies

**You do not have, and must not seek**
- the seeded-defect register
- the system-under-test source code
- either of the existing test suites
- any results from the study

The source is withheld for a specific reason: the seeded faults are visible in
it as `defectActive('...')` calls. Reading the implementation would tell you
where the faults are, and the exercise would be worthless. Specification-based
authoring is the control.

If you believe a specification is ambiguous, write the test for the reading you
think correct and note the ambiguity in a comment. Do not ask which reading
matches the implementation.

## Your task

Author **exactly 7 tests** for MOD-09, in `your-tests/`.

The budget is the same one both existing arms worked under, so your suite is
comparable to theirs. Not 6, not 8.

Work through the twelve security properties in
`SPEC-security-properties.md` in order, and write a test wherever a property
has a referent in this module. Cover the functional requirements below as well.
Cite the property ID and the requirement ID in each test.

Requirements in scope: FR-09.1, FR-09.2, FR-09.3, FR-09.4, FR-09.5

## Rules that are checked mechanically

1. No test may name a defect, reference the register, or touch the activation
   mechanism. A checker enforces this and fails the run.
2Ascii. Each test cites at least one `FR-` or `SP-` identifier.
3. Exactly 7 test methods.

## How to write them

PHPUnit, one class, named `Mod09ReplicationTest`. Assume the harness
bootstrap provides the classes in `api-surface/` and a `TestSupport` helper
with `resetDefects()`, `makeApplication()` and `session()`. You do not need a
working environment to author them — they will be run against the harness by
the first author after you hand them back.

```php
<?php
use PHPUnit\Framework\TestCase;

final class Mod09ReplicationTest extends TestCase
{
    protected function setUp(): void { TestSupport::resetDefects(); }

    /** FR-xx.x / SP-xx — one sentence on what property this pins down */
    public function testSomethingSpecific(): void
    {
        $this->assertSame(...);
    }
}
```

## When you are done

Hand back `your-tests/` only. Do not look at the study results first — if you
do, the exercise is no longer blind and the paper cannot claim it was.
