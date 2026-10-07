# Pre-registration amendment 01

**Dated 7 October 2026. Made after the results in §7 were known.**

The original pre-registration (`PRE-REGISTRATION.md`, 12 August 2026) states:
*"Nothing below may change once measurement begins. If it must, the change is
recorded as an amendment with its date and reason, and the affected results are
reported as exploratory."*

This is that amendment. It adds two studies that were **not** planned before
measurement. Neither alters the pre-registered analysis, neither re-opens a
figure already reported, and both must be labelled in the paper as post-hoc
additions rather than presented as though they had been planned from the start.

---

## Why these were added

Two limitations in the submitted draft are the ones a referee is most likely to
press on, and both became addressable only when a second author joined the work:

1. **§9, internal validity.** The seeded defects and the tests meant to catch
   them were authored by the same team, so the detection figures are stated as
   an upper bound on what the method would achieve in independent hands.
2. **§2.1 and §9, reliability.** The review corpus was screened by a single
   author, so no inter-rater agreement exists.

Neither limitation is dissolved by wishing. Each can be measured.

---

## Study A — blind replication of one module's test suite

**Added:** 7 October 2026. **Status at time of writing:** not yet run.

**Module: MOD-09, Authentication and session.** Chosen by a stated rule rather
than by preference, and fixed before the replicating author received anything:
among the modules with the largest Arm A → Arm B detection gap, MOD-09 carries
the most seeded defects (four), and all four are security defects. It therefore
exercises the paper's actual claim — that a security-property catalogue in the
authoring loop drives detection — rather than a functional-defect module where
the claim is weaker. Arm A detected one of four; Arm B detected four of four.

**Procedure.** The second author receives `results/replication_kit/`,
containing only:
- the two frozen specification documents, unchanged
- class and method signatures with docblocks, no method bodies
- the test budget for the module (seven tests, the same budget both arms worked
  under)

They do **not** receive the seeded-defect register, the system-under-test
source, either existing suite, or any result.

**The source code is withheld deliberately.** The seeded faults are visible in
it as `defectActive('...')` calls, so reading the implementation would disclose
the register and void the exercise. Authoring is therefore
specification-based, which is both the stronger control and a realistic mode of
test design.

**Controls, all mechanical.** `check_blinding.php` fails the run if any test
names a defect, references the register, or touches the activation mechanism.
`score_replication.php` fails the comparison if the test count does not equal
the budget, because an unequal budget makes the comparison not like-for-like.

**Analysis, fixed now.** Per-defect detection for the module's four defects,
compared across Arm A, Arm B and the replication; and agreement between the
replication and Arm B. Descriptive only: four defects in one module will not
support an inferential test, and none will be run on them.

**Both outcomes are reportable, and the paper commits to reporting whichever
occurs.** If the replication approaches Arm B, the upper-bound caveat in §9
narrows and the detection claim strengthens. If it falls substantially short,
the caveat was correct, the headline figure is partly an artifact of author
knowledge, and §7.5 gains a finding. Committing to this in advance is the only
thing that makes the exercise worth running.

---

## Study B — second-rater screening of the review corpus

**Added:** 7 October 2026. **Status at time of writing:** not yet run.

**Sample: all 41 literature sources, not a 10% subset.** The working protocol
suggests a colleague screen 10%. At n = 41 that is four sources, and Cohen's
kappa on four observations is not a reportable statistic. The full corpus is
the smallest sample that yields a figure worth printing, at roughly ninety
minutes of work.

**Procedure.** The second author receives `results/screening_kit/`: the
inclusion and exclusion criteria, the grey-literature tier definitions, the
six-verdict vocabulary, and a CSV of the 41 sources with empty verdict and
reason columns. The first author's verdicts are in
`results/corpus_audit.json` and are **not** in the kit.

**Analysis, fixed now.** Cohen's kappa between the two raters' verdicts, with
raw agreement, expected agreement, and every disagreement listed by source.
Items the second rater marks `unsure` are excluded from the kappa and reported
separately rather than forced into a category.

**IC5 and EC6 remain unverified** for both raters: neither is decidable from
bibliographic metadata. This does not change with a second rater and is already
disclosed.

---

## What these studies do not fix

- **Study A covers one module of eleven.** It tests whether the effect survives
  independent authoring in one place; it does not re-establish the whole
  detection figure, and the paper must not imply otherwise.
- **Study B measures screening consistency, not corpus validity.** Two raters
  can agree and both be wrong. It does not convert the structured review into a
  PRISMA-reportable systematic review; the yields were still never recorded.
- **Neither is independent of the authors.** The second author is a co-author
  with an interest in the paper being accepted, not a disinterested third
  party. This is weaker than an external rater and should be said plainly.
- **Study A's author is a former employee of the organisation that produced the
  system under test**, which is disclosed under competing interests. It does
  not affect the blinding — he has no more access to the register than any
  outside reader — but it is not nothing, and a reader should be told.

---

## Reporting requirement

Wherever either result appears it carries the words **post-hoc** or
**exploratory addition**, with a pointer to this amendment. They were not part
of the pre-registered plan, and a reader must be able to see that without
reconstructing the chronology.
