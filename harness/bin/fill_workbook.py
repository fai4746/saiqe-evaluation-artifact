#!/usr/bin/env python3
"""
Fill 09_results_calculator.xlsx from the harness output.

Rule from the evaluation playbook, Part 6: never fill a cell that was not
measured. Cells for metrics this instantiation could not produce are left empty
and listed at the end of the run so they can be marked "not measured" in the
paper rather than estimated.

Usage: python harness/bin/fill_workbook.py <path-to-xlsx> [output-path]
"""

import json
import pathlib
import shutil
import sys

import openpyxl

ROOT = pathlib.Path(__file__).resolve().parents[2]
RESULTS = ROOT / "results"


def load(name):
    p = RESULTS / name
    return json.loads(p.read_text()) if p.exists() else None


def clear_rows(ws, start_row, end_row, cols):
    for r in range(start_row, end_row + 1):
        for c in range(1, cols + 1):
            ws.cell(row=r, column=c).value = None


def main():
    if len(sys.argv) < 2:
        sys.exit("usage: fill_workbook.py <path-to-xlsx> [output-path]")

    src = pathlib.Path(sys.argv[1])
    dst = pathlib.Path(sys.argv[2]) if len(sys.argv) > 2 else RESULTS / "09_results_calculator_FILLED.xlsx"
    shutil.copy(src, dst)

    register = json.loads((ROOT / "register" / "seeded_defects.json").read_text())
    modules = json.loads((ROOT / "register" / "modules.json").read_text())["modules"]
    det = {a: load(f"detection_arm_{a}.json") for a in ("A", "B")}
    quality = load("quality_by_module.json")
    triage = load("triage.json")
    audit = load("evidence_audit.json")
    pipe = {a: load(f"pipeline_arm_{a}.json") for a in ("A", "B")}
    stats_ = load("statistics.json")
    replay = load("replay.json")

    wb = openpyxl.load_workbook(dst)
    unmeasured = []

    # ── Seeded-Defects ────────────────────────────────────────
    ws = wb["Seeded-Defects"]
    clear_rows(ws, 5, ws.max_row, 10)
    detA = {r["defect_id"]: r["detected"] for r in det["A"]["results"]}
    detB = {r["defect_id"]: r["detected"] for r in det["B"]["results"]}
    for i, d in enumerate(register["defects"]):
        r = 5 + i
        ws.cell(r, 1, d["id"])
        ws.cell(r, 2, d["module"])
        ws.cell(r, 3, d["site"])
        ws.cell(r, 4, d["class"].capitalize())
        ws.cell(r, 5, d.get("cwe") or d.get("owasp_llm") or "")
        ws.cell(r, 6, d["severity"].capitalize())
        ws.cell(r, 7, d["expected"])
        ws.cell(r, 8, 1 if detA.get(d["id"]) else 0)
        ws.cell(r, 9, 1 if detB.get(d["id"]) else 0)
        ws.cell(r, 10, d["injected"])

    # ── Module-Runs ───────────────────────────────────────────
    ws = wb["Module-Runs"]
    clear_rows(ws, 5, ws.max_row, 18)
    modA, modB = det["A"]["summary"]["by_module"], det["B"]["summary"]["by_module"]
    qA, qB = quality["arms"]["A"]["by_module"], quality["arms"]["B"]["by_module"]

    findings_by_module = {}
    for f in triage["findings"]:
        if f["verdict"] == "FP":
            continue
        findings_by_module[f["file"]] = findings_by_module.get(f["file"], 0) + 1

    for i, m in enumerate(modules):
        r = 5 + i
        mid = m["id"]
        a, b = modA.get(mid, {}), modB.get(mid, {})
        qa, qb = qA.get(mid, {}), qB.get(mid, {})

        ws.cell(r, 1, f"{mid} {m['name']}")
        ws.cell(r, 2, round((qa.get("statements") or 0) / 1000, 3) or None)
        ws.cell(r, 3, a.get("present"))
        ws.cell(r, 4, a.get("detected"))
        ws.cell(r, 5, b.get("detected"))
        ws.cell(r, 6, a.get("rate"))
        ws.cell(r, 7, b.get("rate"))
        # Branch coverage: pcov reports line coverage only, not branch coverage.
        ws.cell(r, 8, None)
        ws.cell(r, 9, None)
        ws.cell(r, 10, round((qa.get("msi") or 0) * 100, 1) if qa.get("msi") is not None else None)
        ws.cell(r, 11, round((qb.get("msi") or 0) * 100, 1) if qb.get("msi") is not None else None)
        ws.cell(r, 16, None)
        ws.cell(r, 17, None)
    unmeasured.append("Module-Runs H/I branch coverage — pcov reports line coverage only; line coverage is reported instead (M05).")
    unmeasured.append("Module-Runs P/Q per-module CI seconds — the pipeline is timed per run, not per module (M28 is whole-pipeline).")

    # ── Findings-Triage ───────────────────────────────────────
    ws = wb["Findings-Triage"]
    clear_rows(ws, 5, ws.max_row, 9)
    for i, f in enumerate(triage["findings"]):
        r = 5 + i
        ws.cell(r, 1, f"F{i + 1:03d}")
        ws.cell(r, 2, "B")
        ws.cell(r, 3, "Semgrep")
        ws.cell(r, 4, f["rule"])
        ws.cell(r, 5, f["file"])
        ws.cell(r, 6, "")
        ws.cell(r, 7, f["verdict"])
        ws.cell(r, 8, f["reason"])
        ws.cell(r, 9, None)
    unmeasured.append("Findings-Triage I minutes-to-triage — triage time was not instrumented per finding.")

    # ── Evidence-Audit ────────────────────────────────────────
    ws = wb["Evidence-Audit"]
    clear_rows(ws, 5, ws.max_row, 8)
    n = len(audit["requests"])
    for i, (rid, text, obligation, _) in enumerate(audit["requests"]):
        r = 5 + i
        rowA = audit["rows"][i]
        rowB = audit["rows"][n + i]
        ws.cell(r, 1, rid)
        ws.cell(r, 2, text)
        ws.cell(r, 3, obligation)
        ws.cell(r, 4, None)          # minutes: not measured
        ws.cell(r, 5, 1 if rowA["satisfied"] else 0)
        ws.cell(r, 6, None)
        ws.cell(r, 7, 1 if rowB["satisfied"] else 0)
        ws.cell(r, 8, f"A: {rowA['basis']} | B: {rowB['basis']}")
    unmeasured.append("Evidence-Audit D/F minutes — the timed variant needs a second assessor; requests-satisfiable is reported instead (M18).")

    # ── Results-Table ─────────────────────────────────────────
    ws = wb["Results-Table"]
    clear_rows(ws, 5, ws.max_row, 7)

    eff = {r["metric"]: r for r in stats_["results"]}

    def effect(mid):
        r = eff.get(mid)
        if not r or r.get("p_value") is None:
            return ""
        return f"δ={r['cliffs_delta']:+.2f} ({r['cliffs_magnitude']}), p={r['p_value']:.4f}"

    rows = [
        ("M01", "Seeded-defect detection rate (all 31)",
         det["A"]["summary"]["detection_rate"], det["B"]["summary"]["detection_rate"],
         "detection_arm_*.json", effect("M01")),
        ("M01s", "Seeded-defect detection rate, security defects only",
         round(det["A"]["summary"]["security_detected"] / det["A"]["summary"]["security_total"], 4),
         round(det["B"]["summary"]["security_detected"] / det["B"]["summary"]["security_total"], 4),
         "detection_arm_*.json", "descriptive"),
        ("M04", "Mutation score, whole measured source (MSI)",
         quality["arms"]["A"]["totals"]["msi"], quality["arms"]["B"]["totals"]["msi"],
         "quality_by_module.json", effect("M04")),
        ("M04c", "Mutation score, covered code (covered MSI)",
         quality["arms"]["A"]["totals"]["covered_msi"], quality["arms"]["B"]["totals"]["covered_msi"],
         "quality_by_module.json", effect("M04c")),
        ("M05", "Line coverage",
         quality["arms"]["A"]["totals"]["line_coverage"], quality["arms"]["B"]["totals"]["line_coverage"],
         "quality_by_module.json", effect("M05")),
        ("M08h", "Finding density, human-authored code (per KLOC)",
         None, pipe["B"]["metrics"]["M08_density_by_provenance"]["human"]["per_kloc"],
         "pipeline_arm_B.json", "Arm A does not track provenance"),
        ("M08a", "Finding density, model-authored code (per KLOC)",
         None, pipe["B"]["metrics"]["M08_density_by_provenance"]["ai"]["per_kloc"],
         "pipeline_arm_B.json", "Arm A does not track provenance"),
        ("M10", "Seeded security defects escaping the suite",
         det["A"]["summary"]["security_escapes"], det["B"]["summary"]["security_escapes"],
         "detection_arm_*.json", "descriptive"),
        ("M11", "Security-finding precision (strict)",
         None, triage["arm_b"]["precision_strict"],
         "triage.json", "Arm A does not triage — not measured"),
        ("M11a", "Security-finding precision (actionable: TP+EFP)",
         None, triage["arm_b"]["precision_actionable"], "triage.json", ""),
        ("M12", "Adversarial suite pass rate (AI component)",
         None, pipe["B"]["metrics"]["M12_adversarial_pass_rate"],
         "adversarial/*.json", "Arm A has no AI verification lane"),
        ("M15", "Control coverage ratio",
         pipe["A"]["metrics"]["M15_control_coverage"], pipe["B"]["metrics"]["M15_control_coverage"],
         "pipeline_arm_*.json", "descriptive"),
        ("M16", "Evidence completeness ratio",
         pipe["A"]["metrics"]["M16_evidence_completeness"], pipe["B"]["metrics"]["M16_evidence_completeness"],
         "pipeline_arm_*.json", "descriptive"),
        ("M17", "Traceability completeness (requirement→test)",
         pipe["A"]["metrics"]["M17_traceability"], pipe["B"]["metrics"]["M17_traceability"],
         "pipeline_arm_*.json", "descriptive"),
        ("M18", "Audit requests satisfiable (of 10)",
         audit["summary"]["A"]["satisfied"], audit["summary"]["B"]["satisfied"],
         "evidence_audit.json", "descriptive"),
        ("M28", "Pipeline wall-clock seconds (gate stages)",
         pipe["A"]["metrics"]["M28_wall_seconds"], pipe["B"]["metrics"]["M28_wall_seconds"],
         "pipeline_arm_*.json", "descriptive"),
    ]

    if replay:
        ra, rb = replay["arms"]["A"], replay["arms"]["B"]
        rows += [
            ("M02*", "Change failure rate (simulated operation)",
             ra["M02_change_failure_rate"], rb["M02_change_failure_rate"], "replay.json", "simulated"),
            ("M03*", "Median releases to restore (simulated operation)",
             ra["M03_median_releases_to_restore"], rb["M03_median_releases_to_restore"], "replay.json", "simulated"),
            ("M09*", "Median remediation lead, releases (simulated)",
             ra["M09_median_remediation_lead"], rb["M09_median_remediation_lead"], "replay.json", "simulated"),
            ("M25*", "Drift detection latency, releases (simulated)",
             replay["M25_drift"]["arm_a"], replay["M25_drift"]["arm_b"], "replay.json", "simulated"),
            ("M26*", "Deployment frequency, shipped/attempted (simulated)",
             ra["M26_deployment_frequency"], rb["M26_deployment_frequency"], "replay.json", "simulated"),
            ("M27*", "Median lead time per release, seconds (simulated)",
             ra["M27_median_lead_seconds"], rb["M27_median_lead_seconds"], "replay.json", "simulated"),
        ]
    else:
        unmeasured.append("M02/M03/M09/M25/M26/M27 — replay output not found.")

    for i, (mid, label, a, b, source, note) in enumerate(rows):
        r = 5 + i
        ws.cell(r, 1, mid)
        ws.cell(r, 2, label)
        ws.cell(r, 3, a)
        ws.cell(r, 4, b)
        ws.cell(r, 5, (b - a) if isinstance(a, (int, float)) and isinstance(b, (int, float)) else None)
        ws.cell(r, 6, source)
        ws.cell(r, 7, note)

    # ── Expert sheets: no panel was run ────────────────────────
    ws = wb["Expert-Responses"]
    ws.cell(2, 1, "NO PANEL RUN. This instantiation is the quantified demonstration only. "
                  "Expert validation (evaluation option 4C) has not been carried out; these rows are "
                  "intentionally empty and must be reported as not conducted, not as null results.")
    unmeasured.append("Expert-Responses / Expert-Summary — no expert panel was recruited or run.")

    wb.save(dst)

    print(f"written {dst}")
    print("\nCells deliberately left empty (report as 'not measured'):")
    for u in unmeasured:
        print(f"  - {u}")


if __name__ == "__main__":
    main()
