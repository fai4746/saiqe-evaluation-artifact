# Reproduction run, 24 November 2026

Output from re-running the pipeline against the published artifact, kept here
rather than in `results/` so that the original 12 August 2026 measurements the
manuscript reports stay in place. A reproduction that overwrites what it was
checking is not a reproduction.

## What reproduced

Seeded-defect detection was unchanged: 11 of 31 for Arm A, 30 of 31 for Arm B,
with SD-28 again escaping both. The evidence-request exercise again returned
none of ten against all ten.

## What did not

The release decision. `results/pipeline_arm_A.json` in this directory records
**BLOCK** where the original records **PASS**, because the dependency audit now
reports one advisory against `symfony/filesystem`:

```
CVE-2026-135803 — Filesystem Path Treats Any Prefix Before "://"
                  as a Remote/Scheme
```

That advisory did not exist when the original run was taken. Gate rule G-12
applies to both arms, so both now block; Arm B additionally fails its
covered-code mutation threshold, as it did originally.

The PASS/BLOCK contrast in §7.3 is therefore a measurement against a live
advisory database at a point in time, not a property of the two arms. The
detection and evidence findings do not depend on that database and were
unaffected. §7.3 carries a dated reproduction note saying so.

## Also corrected by this run

Re-running both suites gave Arm A 107 assertions and Arm B **850**, 957
combined. The manuscript had reported 849 for Arm B. Corrected there.

## Caveat on what a pipeline run proves

`run_pipeline.php` executes PHPUnit and the dependency audit, and consumes
stored reports for mutation, coverage, triage, secret scanning and the
adversarial suite. A fresh pipeline timestamp therefore does **not** establish
that Infection, coverage collection, SAST, secret scanning or adversarial
testing ran in that invocation. The C-06 record now states its source and the
stored report's mtime for this reason.

The false-pass class in the gate was found during review of this run and is
reported in §7.4; the fix is in `harness/bin/run_pipeline.php` and postdates
the archived v1.1.0 release.
