# SAIQE — evaluation artifact

Instantiation and two-arm evaluation harness for **Security-Integrated
AI-driven Quality Engineering**, the framework described in the accompanying
paper. Everything here runs locally and produces the numbers reported in §7.

System under test: **FCCSMS**, a PHP 8 federal case- and citizen-service
management application, included under `sut/`.

---

## What this measures

Two pipelines over the same code, the same database and the same tools. They
differ in four respects and no others:

| | Arm A (baseline) | Arm B (SAIQE) |
|---|---|---|
| Test authoring source | functional specification only | functional spec **+** a module-agnostic 12-property security checklist |
| Admission gate | line coverage ≥ 60% | mutation score (covered MSI) ≥ 60% |
| Findings | filed to a backlog, untriaged | every finding triaged, precision measured |
| Provenance / evidence | not recorded | tagged at authoring; signed append-only evidence spine |

**Both arms author exactly 70 tests**, on an identical per-module budget. Without
that constraint Arm B would be a superset of Arm A and would detect at least as
much by construction.

## Headline results

| Measure | Arm A | Arm B |
|---|---|---|
| Seeded-defect detection (31 defects) | 35.5% | **96.8%** (δ = +0.87, p = 0.0039) |
| — security defects only (17) | 17.6% | **100%** |
| Mutation score, covered code | 43.09% | 47.32% *(δ = −0.02, p = 1.000 — no discrimination)* |
| Security-finding precision, strict | not measured | 3.85% (2/52) |
| Adversarial pass rate, clean build as first measured | no lane | 94.7% (36/38) |
| — after the defeated control was repaired | no lane | 100% (38/38) |
| Audit requests satisfiable, of 10 | **0** | **10** |
| Release-gate decision | PASS | **BLOCK** |
| Gate-stage wall clock (median of 5) | 5.52 s | 8.07 s (+46.2%) |

The mutation-gate result is a **null**: the framework's headline admission
criterion did not separate the arms. The security-property checklist did. See
§7.5 of the paper, and `docs/SECTION-7-evaluation.md` here.

Two figures are reported in pairs on purpose. The adversarial suite failed 2 of
38 trials on its first clean run — a delimiter-escape defeating the framework's
own prompt-injection fencing — and passes 38/38 only after that control was
repaired. The gap between those rows is the finding; reporting only the 100%
would be selective. Likewise the 3.85% precision is reported prominently because
it is far below the 20–30% abandonment threshold the framework's own argument
rests on.

Every figure in the paper is cross-checked against the raw files in `results/`:

```bash
php harness/bin/verify_manuscript.php <path-to-manuscript.md>
```

---

## Layout

```
sut/                       FCCSMS, the system under test
register/
  seeded_defects.json      31 defects, frozen ground truth
  modules.json             11 modules, the unit of analysis
  provenance.json          human- vs model-authored index
docs/
  PRE-REGISTRATION.md      arms, metrics, budget, analysis plan — fixed before running
  SPEC-functional.md       the only authoring source for Arm A
  SPEC-security-properties.md  the extra source for Arm B (SP-01..SP-12)
  SECTION-7-evaluation.md  the written results section
policy/
  control_catalogue.json   regulation → control → evidence
  release_gate.json        gate rules, each carrying its obligation
  semgrep/                 SAIQE property rules (Arm B only)
harness/
  tests/ArmA  tests/ArmB   70 tests each
  src/                     evidence store, policy gate, fixtures
  bin/                     the runners (below)
results/                   all measured output
evidence/                  signed append-only evidence records
```

## Prerequisites

- PHP 8.2+ with **pcov** (mutation and coverage need a coverage driver)
- MySQL/MariaDB
- Composer, Python 3.10+
- `pip install semgrep detect-secrets scipy numpy openpyxl`

## Setup

```bash
composer install
mysql -u root -e "CREATE DATABASE fccsms_saiqe CHARACTER SET utf8mb4"
mysql -u root fccsms_saiqe < raw/fccsms_baseline.sql
```

`sut/config/config.local.php` points the SUT at `fccsms_saiqe`. Measurement runs
never touch a classroom or production instance.

## Running

```bash
php harness/bin/check_blinding.php          # must pass before anything else
php harness/bin/run_detection.php A         # M01, M10 — per defect, per arm
php harness/bin/run_detection.php B
vendor/bin/infection --test-framework-options="--testsuite=arm-a" --threads=4
php harness/bin/aggregate_quality.php       # M04, M05 by module
php harness/bin/triage_findings.php         # M11 + SAST recall
php harness/bin/run_adversarial.php         # M12
php harness/bin/run_pipeline.php A          # M15, M16, M17, M28, gate
php harness/bin/run_pipeline.php B
php harness/bin/run_evidence_audit.php      # M18
php harness/bin/run_replay.php              # M02*, M03*, M09*, M25*, M26*, M27*
python harness/bin/analyse.py               # Wilcoxon, Cliff's δ, A12
python harness/bin/fill_workbook.py <xlsx>  # populate the results calculator
```

Activate a seeded defect for a manual run with `SAIQE_DEFECTS=<key>`.

## Reading the results honestly

- Metrics marked `*` come from a **simulated release series**, not production.
  They measure how each pipeline responds to a controlled fault stream and say
  nothing about field behaviour.
- The AI component is backed by a **deterministic adapter**, not a hosted model.
  M12 measures whether the application established its controls, not how well a
  given model respects a delimiter.
- Cells the harness could not measure are **left empty**, never estimated.
  `fill_workbook.py` prints the list at the end of every run.
- The author wrote both the defects and the tests. Blinding controls are
  described in `docs/PRE-REGISTRATION.md` §4 and are a mitigation, not a
  solution.

## Substitutions

OPA/Conftest, gitleaks and OWASP ZAP were unreachable from the build
environment. The policy gate is evaluated by `harness/src/PolicyGate.php` against
the same declarative rules; secret scanning uses `detect-secrets`; DAST was not
run and is recorded as an unsatisfied control rather than counted as covered.

## Licence

See `LICENSE`. FCCSMS under `sut/` is included as the system under test.
