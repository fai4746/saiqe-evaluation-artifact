# Pre-registration — SAIQE two-arm evaluation on FCCSMS

**Registered 2026-08-12, before the first measurement run.**
Timestamp of record: the Git commit that introduces this file.

Nothing below may change once measurement begins. If it must, the change is
recorded as an amendment with its date and reason, and the affected results are
reported as exploratory.

---

## 1. Claim under test

Admitting generated tests on **mutation score** rather than on **coverage**, and
authoring them against a **generic security-property checklist** rather than
functional requirements alone, produces a suite that detects more real faults —
including faults that coverage alone would not have predicted.

## 2. Design

Paired, within-subject. Unit of analysis is the **module**; n = 11.
Both arms run against the same system under test, the same database, the same
tool versions, on the same machine.

| | Arm A (baseline) | Arm B (SAIQE) |
|---|---|---|
| Authoring source | `SPEC-functional.md` only | `SPEC-functional.md` + `SPEC-security-properties.md` |
| Admission gate | line coverage ≥ 60% per module | mutation score (MSI) ≥ 60% per module |
| Static analysis | run, findings to a backlog, no triage | run, every finding triaged, precision measured |
| Provenance | not tracked | tagged at authoring time |
| Evidence | per-tool artifacts, no shared store | signed records in a shared store with obligation references |
| Feedback loop | none | findings seeded back as regression tests |

## 3. Test-authoring budget — the control that makes this a comparison

**Each arm may author at most the same number of tests per module.** The budget
is set by Arm A's suite, authored first, and is fixed per module:

| Module | Budget | Module | Budget |
|---|---|---|---|
| MOD-01 | 14 | MOD-07 | 4 |
| MOD-02 | 8 | MOD-08 | 4 |
| MOD-03 | 6 | MOD-09 | 7 |
| MOD-04 | 6 | MOD-10 | 4 |
| MOD-05 | 4 | MOD-11 | 6 |
| MOD-06 | 7 | **Total** | **70** |

Arm B therefore spends part of a fixed budget on security properties, and
correspondingly authors fewer functional cases. Without this constraint Arm B
would be a superset of Arm A and would detect at least as much by construction,
which would test nothing.

## 4. Blinding

The author wrote both the seeded defects and the tests. Full blinding is not
available to a single researcher. Three controls stand in for it, all
mechanically checkable:

- **B1** Tests are authored from the two frozen spec documents. Neither cites the
  defect register, and neither was written after inspecting it.
- **B2** No test file may contain a defect key, reference `register/`, or name a
  defect. Enforced by `harness/bin/check_blinding.php`, which fails the run.
- **B3** The security checklist is **module-agnostic**: the same twelve
  properties are applied to all eleven modules. Arm B's advantage can therefore
  come only from having a checklist, not from knowing where faults were placed.

This is a limitation, not a solution. It is reported in §9.

## 5. Metrics — fixed before any run

Primary: **M04** mutation score, **M01** seeded-defect detection rate, **M11** security-finding precision.

Secondary: M05 branch coverage, M08 vulnerability density by provenance, M10 seeded vulnerability escapes, M12 adversarial suite pass rate, M15 control coverage ratio, M16 evidence completeness, M17 traceability completeness, M18 evidence assembly effort, M28 pipeline duration overhead.

From the operational replay (see §6): M02 change failure rate, M03 MTTR, M09 remediation lead time, M25 drift detection latency, M26/M27 DORA deployment frequency and lead time.

No metric may be added after results are seen. Any that is, is labelled exploratory.

## 6. Operational replay — and what it is not

Metrics M02, M03, M09, M25, M26 and M27 normally require a production system
with real users and incidents. To bring them into measurement range, both arms
process an identical, deterministic **replay of 24 synthetic releases**, each
release activating a defined subset of seeded defects. Change failure, time to
restore, remediation lead time and drift latency are then measured from harness
timestamps and gate decisions over that series.

**These are simulated-operation figures, not field figures.** They measure how
each arm's pipeline responds to a controlled fault stream. They do not measure
production behaviour, and they are reported under that label throughout, with
the limitation restated in §9. This is a deliberate extension of the evaluation
playbook, which had listed these metrics as unobtainable.

## 7. Analysis plan

Paired non-parametric: Wilcoxon signed-rank across the 11 modules, two-sided,
α = 0.05, with Cliff's delta and Vargha-Delaney Â₁₂ reported alongside every p
value. Medians and IQRs, never means, for ordinal data.

With n = 11 the study is powered for large effects only. This is stated in the
results rather than discovered in review.

## 8. Stopping rule

Both arms run to completion over all 31 seeded defects and all 24 replay
releases. No run is discarded. Null and negative results are reported in §7.5.
