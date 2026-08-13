#!/usr/bin/env python3
"""
Bring 04_saiqe_supplementary_matrices.xlsx into line with the evaluated
framework, and record measured outcomes against the metrics catalogue.

Two sheets asserted the claim §7.5 retracts (that generated tests are gated on
mutation score, presented as validated). The catalogue also carried baseline
targets with no record of what was actually achieved.

Usage: python harness/bin/update_supplementary.py <path-to-xlsx> [output]
"""

import json
import pathlib
import shutil
import sys

import openpyxl
from openpyxl.styles import Font

ROOT = pathlib.Path(__file__).resolve().parents[2]
RESULTS = ROOT / "results"


def load(name):
    p = RESULTS / name
    return json.loads(p.read_text()) if p.exists() else None


def main():
    if len(sys.argv) < 2:
        sys.exit("usage: update_supplementary.py <path-to-xlsx> [output]")

    src = pathlib.Path(sys.argv[1])
    dst = pathlib.Path(sys.argv[2]) if len(sys.argv) > 2 else RESULTS / "04_saiqe_supplementary_matrices_UPDATED.xlsx"
    shutil.copy(src, dst)

    detA, detB = load("detection_arm_A.json"), load("detection_arm_B.json")
    quality = load("quality_by_module.json")
    triage = load("triage.json")
    pipeA, pipeB = load("pipeline_arm_A.json"), load("pipeline_arm_B.json")
    audit = load("evidence_audit.json")
    replay = load("replay.json")
    advC = load("adversarial/adversarial_clean.json")
    advB = load("adversarial/adversarial_clean_before_fence_fix.json")

    wb = openpyxl.load_workbook(dst)
    changes = []

    # ── Pillar-Capabilities: P1 generated-test validation ──────
    ws = wb["Pillar-Capabilities"]
    for row in ws.iter_rows(min_row=1):
        if row[1].value == "Generated-test validation":
            row[2].value = ("Gates generated tests on mutation score before admission. "
                            "RETAINED ON LITERATURE (Just et al. 2014); this instantiation "
                            "found no discrimination between arms (Cliff's d = -0.02, "
                            "p = 1.000) — see paper §7.5. Not a validated control.")
            changes.append("Pillar-Capabilities: P1 generated-test validation annotated")
        if row[1].value == "Security-targeted test generation":
            row[2].value = ("Authors tests against a module-agnostic security-property "
                            "catalogue rather than functional requirements alone. PRIMARY "
                            "decision of Pillar 1: produced the whole of the measured "
                            "detection gain (35.5% -> 96.8%, d = +0.87, p = 0.0039).")
            changes.append("Pillar-Capabilities: P1 security-property authoring promoted")

    # ── Maturity-Model: Level 4 P1 cell ───────────────────────
    ws = wb["Maturity-Model"]
    for row in ws.iter_rows(min_row=1):
        if str(row[0].value).strip() == "4":
            row[2].value = ("Test authoring driven by a security-property catalogue applied "
                            "to every module; provenance recorded at authoring time; "
                            "per-control boundary tests alongside end-to-end suites. "
                            "A mutation-score admission gate is optional at this level: "
                            "its discriminating power is unestablished (§7.5).")
            changes.append("Maturity-Model: Level 4 P1 criterion rewritten")

    # ── Metrics-Catalogue: measured outcome columns ───────────
    ws = wb["Metrics-Catalogue"]
    header_row = next(i for i, r in enumerate(ws.iter_rows(values_only=True), 1)
                      if r[0] == "ID")
    first_free = ws.max_column + 1
    bold = Font(bold=True)
    for offset, title in enumerate(["Arm A measured", "Arm B measured", "Status in this study"]):
        c = ws.cell(header_row, first_free + offset, title)
        c.font = bold

    def pct(v, dp=1):
        return None if v is None else round(v * 100, dp)

    measured = {
        "M01": (pct(detA["summary"]["detection_rate"]), pct(detB["summary"]["detection_rate"]),
                "Measured. Renamed: seeded-defect DETECTION rate, not production escape rate."),
        "M02": (100.0, 83.3, "Simulated release series only. Read off gate decisions — partly circular. Appendix C."),
        "M03": (None, None, "Not measured. Needs production operation."),
        "M04": (pct(quality["arms"]["A"]["totals"]["msi"], 2), pct(quality["arms"]["B"]["totals"]["msi"], 2),
                "Measured. NO significant difference between arms (p = 0.206); covered-code MSI p = 1.000."),
        "M05": (pct(quality["arms"]["A"]["totals"]["line_coverage"], 2), pct(quality["arms"]["B"]["totals"]["line_coverage"], 2),
                "Measured as LINE coverage. Branch coverage unavailable: the coverage driver does not report it."),
        "M08": (None, 6.68, "Measured for Arm B only (human-authored code, per KLOC). Arm A does not record provenance."),
        "M09": (None, None, "Not measured. Needs production operation."),
        "M10": (14, 0, "Measured. Seeded security defects escaping the suite."),
        "M11": (None, round(triage["arm_b"]["true_positive"] / triage["arm_b"]["findings"] * 100, 2),
                "Measured for Arm B only. 2/52. FAR below the 20-30% abandonment threshold this metric's own rationale cites."),
        "M12": (None, pct(advB["pass_rate"]),
                f"Measured for Arm B only. {pct(advB['pass_rate'])}% as first measured; {pct(advC['pass_rate'])}% after the defeated control was repaired."),
        "M15": (pct(pipeA["metrics"]["M15_control_coverage"], 2), pct(pipeB["metrics"]["M15_control_coverage"], 2), "Measured."),
        "M16": (pct(pipeA["metrics"]["M16_evidence_completeness"]), pct(pipeB["metrics"]["M16_evidence_completeness"]), "Measured."),
        "M17": (pct(pipeA["metrics"]["M17_traceability"]), pct(pipeB["metrics"]["M17_traceability"]), "Measured."),
        "M18": (audit["summary"]["A"]["satisfied"], audit["summary"]["B"]["satisfied"],
                "Measured as requests SATISFIABLE of 10, not person-hours. Timed assembly needs a second assessor."),
        "M21": (None, None, "Not measured. Needs production operation and human gate data."),
        "M22": (None, None, "Not measured. Needs the reviewer sub-study."),
        "M23": (None, None, "Not measured. Needs the reviewer sub-study."),
        "M25": (None, None, "Simulated only: drift never detected by Arm A, detected on appearance by Arm B. Appendix C."),
        "M26": (50.0, 25.0, "Simulated release series only. Partly circular. Appendix C."),
        "M27": (4.56, 6.61, "Simulated release series only, in seconds per release. Appendix C."),
        # Medians of five runs each, as reported in the paper — not the single
        # run recorded in the last pipeline execution.
        "M28": (5.52, 8.07,
                "Measured, median of 5 runs each, in seconds. +46.2% — EXCEEDS this catalogue's own <20% target."),
    }

    filled = 0
    for row in ws.iter_rows(min_row=header_row + 1):
        mid = row[0].value
        if mid in measured:
            a, b, note = measured[mid]
            ws.cell(row[0].row, first_free, a)
            ws.cell(row[0].row, first_free + 1, b)
            ws.cell(row[0].row, first_free + 2, note)
            filled += 1
        elif mid:
            ws.cell(row[0].row, first_free + 2, "Not measured in this study.")

    changes.append(f"Metrics-Catalogue: measured outcomes recorded for {filled} metrics")

    # ── README sheet: provenance of this update ───────────────
    ws = wb["README"]
    last = ws.max_row + 2
    ws.cell(last, 1, "Updated after evaluation").font = bold
    ws.cell(last + 1, 1, "2026-08-12")
    ws.cell(last + 1, 2, ("Pillar-Capabilities and Maturity-Model amended: the mutation-score "
                          "admission gate is no longer presented as a validated control, because "
                          "the evaluation found no discrimination between arms (paper §7.5). "
                          "Security-property-driven authoring is now the primary Pillar 1 decision. "
                          "Metrics-Catalogue carries measured Arm A / Arm B outcomes and an explicit "
                          "status for every metric, including those NOT measured."))

    wb.save(dst)

    print(f"written {dst}\n")
    for c in changes:
        print(f"  - {c}")


if __name__ == "__main__":
    main()
