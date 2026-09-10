#!/usr/bin/env python3
"""
Assemble the submission package.

Collects the current manuscript, the corrected figures and supplementary
workbooks, and the reproducibility artifact into one folder, with a manifest
recording the SHA-256 of every file so the package can be checked after
copying or zipping.

Deliberately excludes superseded files: the pre-§7 manuscript backup, the
pre-correction diagram, the stale copy of the draft in `initial docszip`, and
the paired-stats template superseded by analyse.py. Including them is how a
stale draft ends up attached to a submission.

Re-runnable: it rebuilds the folder from scratch each time.

Usage: python harness/bin/build_submission_package.py [dest]
"""

import hashlib
import json
import pathlib
import shutil
import sys
from datetime import date

SAIQE = pathlib.Path(__file__).resolve().parents[2]
RESEARCH = pathlib.Path("C:/Users/Admin/OneDrive/REsearchSAIQE")
DEST = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else RESEARCH / "SUBMISSION_PACKAGE"

# (source, destination-relative, description for the manifest)
FILES = [
    # ── manuscript ────────────────────────────────────────────
    (RESEARCH / "manuscripts/06_manuscript_draft.md",
     "01_manuscript/manuscript.md",
     "Manuscript v2.0. Master copy. §7 filled from measured data; all placeholders resolved."),
    (SAIQE / "results/springer/sn-article.tex",
     "01_manuscript/springer/sn-article.tex",
     "SUBMISSION FILE. Manuscript in Springer sn-jnl class, sn-basic reference style, for Software Quality Journal. Generated from the markdown master -- do not hand-edit."),
    (SAIQE / "results/springer/sn-bibliography.bib",
     "01_manuscript/springer/sn-bibliography.bib",
     "43 BibTeX entries generated from the reference list. All 75 in-text citations verified to resolve."),
    (SAIQE / "results/springer/figures/fig1_saiqe_framework.pdf",
     "01_manuscript/springer/figures/fig1_saiqe_framework.pdf",
     "Figure 1 as PDF (Springer does not accept SVG). All 76 text runs verified present after conversion."),
    (SAIQE / "results/springer/figures/fig2_paired_outcomes.pdf",
     "01_manuscript/springer/figures/fig2_paired_outcomes.pdf",
     "Figure 2 as PDF. All 28 text runs verified present after conversion."),
    (SAIQE / "harness/springer/README_SPRINGER.md",
     "01_manuscript/springer/README.md",
     "How to compile, the seven author fields still to complete, and what was changed to meet Springer requirements."),


    # ── figures ───────────────────────────────────────────────
    (RESEARCH / "initial docszip/05_saiqe_framework_diagram.svg",
     "02_figures/figure1_saiqe_framework.svg",
     "Figure 1 (was Figure 3; renumbered when the PRISMA figure was withdrawn). CORRECTED: Pillar 1 leads with security-property authoring; the mutation gate is marked not-validated-here."),
    (RESEARCH / "files/11_figure5_paired_outcomes.svg",
     "02_figures/figure2_paired_outcomes.svg",
     "Figure 2 (was Figure 5). NEW. Generated from statistics.json, so it cannot disagree with Table 5."),

    # ── supplementary ─────────────────────────────────────────
    (SAIQE / "results/04_saiqe_supplementary_matrices_UPDATED.xlsx",
     "03_supplementary/S1_supplementary_matrices.xlsx",
     "Tables 2, 3, 4, 6 + metrics catalogue. CORRECTED: Pillar-Capabilities and Maturity-Model no longer present the mutation gate as validated; catalogue carries measured outcomes and an explicit status for every metric."),
    (SAIQE / "results/09_results_calculator_FILLED.xlsx",
     "03_supplementary/S2_results_calculator.xlsx",
     "Filled results calculator: 31 seeded defects, 11 modules, 52 triaged findings, 10 audit requests, 22 result rows. Unmeasurable cells left EMPTY by design."),
    (RESEARCH / "manuscripts/02_annotated_bibliography.md",
     "03_supplementary/S3_annotated_bibliography.md",
     "Annotated bibliography."),
    (RESEARCH / "initial docszip/03_research_protocol_and_methodology.md",
     "03_supplementary/S4_research_protocol.md",
     "Working protocol. NOTE: describes three evaluation options; option 4B/instantiation was the one executed, 4C (expert panel) was not."),
    (RESEARCH / "files/08_expert_validation_instrument.md",
     "03_supplementary/S5_expert_validation_instrument.md",
     "Expert validation instrument. NOT YET DEPLOYED — included because the paper states the panel is outstanding work."),

    # ── artifact: design and ground truth ─────────────────────
    (SAIQE / "docs/PRE-REGISTRATION.md",
     "04_artifact/PRE-REGISTRATION.md",
     "Arms, metric set, per-module authoring budget and analysis plan, fixed before the first measurement run."),
    (SAIQE / "docs/SPEC-functional.md",
     "04_artifact/SPEC-functional.md",
     "The ONLY authoring source for Arm A; shared by both arms."),
    (SAIQE / "docs/SPEC-security-properties.md",
     "04_artifact/SPEC-security-properties.md",
     "SP-01..SP-12. The only material Arm B had that Arm A did not."),
    (SAIQE / "register/seeded_defects.json",
     "04_artifact/seeded_defects.json",
     "Frozen ground truth: 31 defects, 17 security / 13 functional / 1 accessibility."),
    (SAIQE / "register/modules.json",
     "04_artifact/modules.json",
     "The 11 modules; the unit of analysis."),
    (SAIQE / "register/provenance.json",
     "04_artifact/provenance.json",
     "Human- vs model-authored index, recorded at authoring time. Basis of the M08 split."),
    (SAIQE / "policy/control_catalogue.json",
     "04_artifact/control_catalogue.json",
     "Regulation → control → evidence. Basis of M15."),
    (SAIQE / "policy/release_gate.json",
     "04_artifact/release_gate.json",
     "Gate rules, each carrying the obligation it serves."),
    (SAIQE / "policy/semgrep/saiqe-properties.yaml",
     "04_artifact/saiqe-properties.yaml",
     "Security-property rule set (Arm B only), derived from SP-01..SP-12."),

    # ── artifact: raw results ─────────────────────────────────
    (SAIQE / "results/detection_arm_A.json", "04_artifact/raw_results/detection_arm_A.json", "M01/M10 raw, Arm A: per-defect detection."),
    (SAIQE / "results/detection_arm_B.json", "04_artifact/raw_results/detection_arm_B.json", "M01/M10 raw, Arm B."),
    (SAIQE / "results/quality_by_module.json", "04_artifact/raw_results/quality_by_module.json", "M04/M05 raw, per module, both arms."),
    (SAIQE / "results/triage.json", "04_artifact/raw_results/triage.json", "M11 raw: all 52 findings with verdict and recorded reason."),
    (SAIQE / "results/statistics.json", "04_artifact/raw_results/statistics.json", "Wilcoxon, Cliff's δ, Vargha-Delaney Â₁₂. Source of Figure 5."),
    (SAIQE / "results/corpus_audit.json", "04_artifact/corpus_audit.json",
     "Post-hoc criteria audit of the 41 literature sources against IC1-IC5 / EC1-EC6. NOT intra-rater agreement -- no prior screening decisions exist, so no kappa."),
    (SAIQE / "results/pipeline_arm_A.json", "04_artifact/raw_results/pipeline_arm_A.json", "M15/M16/M17/M28 + gate decision, Arm A."),
    (SAIQE / "results/pipeline_arm_B.json", "04_artifact/raw_results/pipeline_arm_B.json", "M15/M16/M17/M28 + gate decision, Arm B."),
    (SAIQE / "results/evidence_audit.json", "04_artifact/raw_results/evidence_audit.json", "M18 raw: 10 auditor requests against each arm's retained evidence."),
    (SAIQE / "results/replay.json", "04_artifact/raw_results/replay.json", "Appendix C raw: the 24-release series. ILLUSTRATIVE, not delivery-performance data."),
    (SAIQE / "results/adversarial/adversarial_clean_before_fence_fix.json",
     "04_artifact/raw_results/adversarial_clean_AS_FIRST_MEASURED.json",
     "M12 raw: 36/38. The two failures are the delimiter-escape reported in §7.4."),
    (SAIQE / "results/adversarial/adversarial_clean.json",
     "04_artifact/raw_results/adversarial_clean_AFTER_REPAIR.json",
     "M12b raw: 38/38 after the fence-delimiter strip."),
    (SAIQE / "evidence/arm_B.jsonl", "04_artifact/raw_results/evidence_arm_B.jsonl",
     "The signed, hash-chained evidence records Arm B produced. Arm A produced none — that is the M16/M18 result."),

    (SAIQE / "docs/submission/00_START_HERE.md",
     "00_START_HERE.md",
     "Read first: contents, and the four outstanding items in priority order."),
    (SAIQE / "docs/submission/RESPONSE_TO_REVIEW.md",
     "05_review/RESPONSE_TO_REVIEW.md",
     "Point-by-point response mapping each review item to what changed."),

    # ── review trail ──────────────────────────────────────────
    (RESEARCH / "10_manuscript_review.md",
     "05_review/manuscript_review.md",
     "Independent review of the results-bearing draft."),
]


def sha256(path):
    h = hashlib.sha256()
    h.update(path.read_bytes())
    return h.hexdigest()


def clean(dest):
    """
    Empty the package folder without requiring the folder itself to be
    removable. OneDrive keeps a handle on synced directories, so rmtree fails
    with WinError 5 mid-sync; deleting files and pruning empty directories
    afterwards achieves the same result and tolerates the lock.
    """
    if not dest.exists():
        return
    for p in sorted(dest.rglob("*"), key=lambda q: len(q.parts), reverse=True):
        try:
            if p.is_file() or p.is_symlink():
                p.unlink()
            elif p.is_dir():
                p.rmdir()
        except OSError:
            pass  # a locked directory will simply be reused


def main():
    clean(DEST)
    DEST.mkdir(parents=True, exist_ok=True)

    manifest = []
    missing = []

    for src, rel, desc in FILES:
        if not src.exists():
            missing.append(f"{rel}  <- MISSING SOURCE {src}")
            continue
        dst = DEST / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)
        manifest.append({
            "file": rel,
            "bytes": dst.stat().st_size,
            "sha256": sha256(dst),
            "description": desc,
        })

    (DEST / "MANIFEST.json").write_text(json.dumps({
        "package": "SAIQE submission package",
        "built": date.today().isoformat(),
        "manuscript_version": "v2.0",
        "files": len(manifest),
        "note": "Superseded files are deliberately excluded: the pre-§7 manuscript backup, the pre-correction diagram, the stale draft in 'initial docszip', and paired_stats.py.",
        "contents": manifest,
    }, indent=2), encoding="utf-8")

    print(f"built {DEST}")
    print(f"  {len(manifest)} files")
    if missing:
        print("\n  MISSING:")
        for m in missing:
            print(f"    {m}")
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
