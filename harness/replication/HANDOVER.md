# Handover — the two second-author studies

Both kits are built. Neither study has been run: the results do not exist until
the second author does the work. These are the steps, in order.

Read `docs/PRE-REGISTRATION-AMENDMENT-01.md` first. It records that both
studies were added **after** the §7 results were known, which is why everything
they produce must be labelled post-hoc in the paper.

---

## Study A — blind replication, MOD-09

**What it buys.** §9 currently states that the detection figures are an upper
bound, because the people who placed the seeded defects also wrote the tests.
This measures whether that caveat bites.

### 1. Send the kit

```
results/replication_kit/
```

Hand that folder over and nothing else. It holds the two specifications, the
API signatures, and the brief. It does **not** hold the defect register, the
system source, either existing suite, or any result — the build script verifies
that no defect key appears anywhere in it before it will finish.

**Ankur must not read the study results before finishing.** If he does, the
exercise stops being blind and the paper cannot claim that it was.

### 2. Receive `your-tests/` back and install it

```bash
cp <his>/your-tests/*.php harness/tests/Replication/
```

### 3. Check the blinding, then measure

```bash
php harness/bin/check_blinding.php          # must PASS before anything else
php harness/bin/run_detection.php R
php harness/replication/score_replication.php MOD-09
```

`check_blinding.php` fails the run if any test names a defect, references the
register, or touches the activation mechanism. `score_replication.php` refuses
to treat the comparison as like-for-like if the test count is not exactly
seven, the budget both arms worked under.

### 4. Report whichever way it goes

The amendment commits the paper to reporting both outcomes:

- **Replication close to Arm B** → the upper-bound caveat in §9 narrows, and
  the detection claim is stronger than the draft is able to assert.
- **Replication well short of Arm B** → the caveat was right, part of the
  headline figure is an artifact of author knowledge, and §7.5 gains a real
  finding.

The second is the uncomfortable outcome and the more valuable one. Do not run
this study unless you are willing to publish it.

---

## Study B — second-rater screening, all 41 sources

**What it buys.** §2.1 and §9 both state that no inter-rater agreement is
available. This replaces that with a measured Cohen's kappa.

### 1. Send the kit

```
results/screening_kit/
```

Criteria, verdict vocabulary, and a CSV of the 41 sources with empty verdict
and reason columns. The first author's verdicts live in
`results/corpus_audit.json` and are deliberately absent from the kit.

About two minutes a source, so roughly ninety minutes.

**Not 10%.** The protocol suggests a colleague screen a tenth. At 41 sources
that is four, and a kappa on four observations is not reportable. All 41.

### 2. Score the returned CSV

```bash
python harness/replication/build_screening_kit.py score <filled.csv>
```

Reports raw agreement, chance agreement, Cohen's kappa with its
interpretation, and every disagreement by source. `unsure` rows are excluded
from the kappa and reported separately rather than forced into a bucket.

### 3. Update the paper

Replace the "no inter-rater agreement is available" sentences in §2.1 and §9
with the measured figure. If kappa is low, that is itself worth reporting: it
would say the criteria are underspecified, which is a finding about the
protocol rather than about the rater.

---

## What neither study fixes

- Study A covers **one module of eleven**. It does not re-establish the whole
  detection figure.
- Study B measures **consistency, not validity**. Two raters can agree and both
  be wrong, and the screening yields were still never recorded, so the review
  does not become PRISMA-reportable.
- **Neither rater is independent.** A co-author has an interest in the paper
  being accepted. This is weaker than an external assessor and the paper says
  so.

---

## If you decide not to run them

Then say nothing about them. Delete the kits, leave §9's caveat as it stands,
and leave §2.1 saying that no inter-rater agreement is available. Both are
honest as written. What is not acceptable is implying either study happened, or
running one and reporting it only if the number is flattering.
