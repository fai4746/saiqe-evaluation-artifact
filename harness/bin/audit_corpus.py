#!/usr/bin/env python3
"""
Post-hoc criteria audit of the review corpus.

WHAT THIS IS: every source in the assembled corpus is checked against the
inclusion and exclusion criteria stated in the protocol (IC1–IC5, EC1–EC6), and
the verdict recorded with a reason.

WHAT THIS IS NOT: intra-rater agreement. There are no prior screening decisions
to compare against — that is the whole reason the re-screen could not be run —
so no kappa is computed and none should be reported. This audit says whether the
corpus that exists satisfies the criteria that were stated. It says nothing
about the consistency of the process that assembled it.

Audit population is the literature corpus only. Standards and regulatory
instruments are the Phase 2 (regulatory document analysis) corpus, governed by a
different protocol, and are not screened against IC/EC.

Judgement calls on IC1, EC1 and EC3 are the author's own; they are not
independent, and the paper says so.

Usage: python harness/bin/audit_corpus.py [--xlsx <supplementary.xlsx>]
"""

import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / "results" / "corpus_audit.json"

# verdict codes
INC = "include"                  # satisfies IC1-IC5, no EC trips
SEC = "retain_secondary"         # EC2: secondary study, not counted as primary evidence
FND = "retain_foundational"      # IC2 exception: pre-window, retained as foundational
MTH = "method_reference"         # cited for method, not part of the evidence corpus
TIER3 = "tier3_non_evidential"   # grey Tier 3: usable only to show a practice exists
FLAG = "flag"                    # fails or cannot satisfy a criterion; needs author action

# (key, year, verdict, criteria note)
CORPUS = [
    ("Ami et al.", 2024, INC, "Peer-reviewed interview study on SAST use. IC1–IC5 satisfied; no EC trips."),
    ("Barr et al.", 2015, SEC, "EC2: a survey, therefore secondary. Also pre-window; the oracle problem is foundational to testing but is not one of the three categories the stated IC2 extension names. Retained for framing, not counted as primary evidence."),
    ("Bessey et al.", 2010, FND, "IC2 exception: 2010, outside the window and outside the stated 2014 extension. Retained as the foundational source for the false-positive abandonment threshold. The exception must be declared, not silent."),
    ("Beurer-Kellner et al.", 2025, FLAG, "IC3 unsatisfied as cited: preprint with no venue, and the entry uses 'et al.' in place of a full author list. Resolve the venue and complete the authors, or drop."),
    ("Binbeshr & Imam", 2025, SEC, "EC2: comparative analysis of 18 primary studies, therefore secondary. Peer-reviewed (EASE companion). Retained for gap comparison — which is how §3.3 uses it."),
    ("Capgemini, Sogeti & OpenText", 2025, INC, "Grey Tier 2 (industry survey, n>2,000). IC3 satisfied via the Tier 1/2 clause. Used for adoption figures, which is within Tier 2's remit."),
    ("Chandramouli et al.", 2024, INC, "Grey Tier 1 (NIST SP 800-204D). IC3 satisfied."),
    ("Chen et al.", 2026, FLAG, "IC3 unsatisfied as cited: arXiv preprint. Load-bearing in §3.2 for the agentic-testing counter-evidence. Check for a published version; if none, label as preprint at the point of use."),
    ("Cloud Security Alliance", 2026, INC, "Grey Tier 1 (CSA named as Tier 1 in the protocol). NOTE: cited in text as [CSA, 2026] but listed under 'Cloud Security Alliance AI Safety Initiative' — citation key does not match the entry."),
    ("GitClear", 2025, INC, "Grey Tier 2 (vendor analysis), correctly labelled. Used for a volume claim, not a security claim."),
    ("Greshake et al.", 2023, INC, "Peer-reviewed (AISec). IC1–IC5 satisfied."),
    ("He et al.", 2026, INC, "Quasi-experimental DiD study. IC1–IC5 satisfied on the entry as written; confirm the venue is final."),
    ("Hevner et al.", 2004, MTH, "Design Science Research methodology. Cited for method, not evidence. Correctly outside the evidence corpus; IC2 does not apply."),
    ("Inozemtseva & Holmes", 2014, INC, "IC2 satisfied via the stated extension — test-suite effectiveness is explicitly one of the three named categories."),
    ("Iranmanesh et al.", 2025, FLAG, "IC3 unsatisfied as cited: arXiv preprint. Cited in §3.3 and §5.3 for specific F1 figures, so the venue matters."),
    ("Just et al.", 2014, INC, "IC2 satisfied via the stated extension — mutation validity is explicitly named. This is the source the whole Pillar 1 gate argument rests on."),
    ("Kelly & Weaver", 2004, FND, "IC2 exception: 2004, outside window and outside the stated extension. Retained as the foundational GSN source. Declare the exception."),
    ("Lemieux et al.", 2023, INC, "Peer-reviewed (ICSE). IC1–IC5 satisfied."),
    ("Mohammed et al.", 2025, SEC, "EC2: systematic literature review over 36 studies, therefore secondary. Peer-reviewed. Retained for gap comparison."),
    ("Muhammad et al.", 2026, INC, "Peer-reviewed (Frontiers in AI). IC1–IC5 satisfied."),
    ("Nitin et al.", 2025, FLAG, "IC3 unsatisfied as cited: arXiv preprint. Cited in §3.5 and §5.2 for the proof-of-vulnerability mechanism."),
    ("OWASP GenAI Security Project", 2025, INC, "Grey Tier 1 (OWASP named as Tier 1). IC3 satisfied."),
    ("Pearce et al.", 2022, INC, "Peer-reviewed (IEEE S&P). IC1–IC5 satisfied."),
    ("Peffers et al.", 2007, MTH, "DSR methodology. Cited for method, not evidence. IC2 does not apply."),
    ("Perry et al.", 2023, INC, "Peer-reviewed (ACM CCS). IC1–IC5 satisfied."),
    ("Pizzorno & Berger", 2024, FLAG, "Listed with an arXiv identifier and no venue, but not marked as a preprint in the reference list. Either it is published — in which case add the venue — or it is a preprint and should carry the same flag as the others."),
    ("Rajapakse et al.", 2022, SEC, "EC2: systematic review of 52 studies, therefore secondary. Peer-reviewed (IST). Retained for gap comparison."),
    ("Sadowski et al.", 2018, FND, "IC2 exception: 2018, one year outside the window. Static analysis in industrial practice is arguably within the DevSecOps-foundations extension, but 2018 > 2014 so the extension clause does not literally cover it. Declare the exception."),
    ("Schäfer et al.", 2024, INC, "Peer-reviewed (IEEE TSE). The anchor result for LLM test generation. IC1–IC5 satisfied."),
    ("Schreiber & Tippe", 2025, FLAG, "IC3 unsatisfied as cited: arXiv preprint. Carries the repository-mining counterpoint in §3.4, which the paper explicitly relies on to qualify the benchmark figures."),
    ("Shukla et al.", 2025, INC, "Peer-reviewed (IEEE ISTAS). IC1–IC5 satisfied."),
    ("Siddiq et al.", 2025, FLAG, "Peer-reviewed (ICSE) so IC3 is satisfied, but verify the first author's name: the oracle-study author is commonly cited as Mohammed Latif Siddiq, and the initial given here may be wrong."),
    ("Veracode", 2026, INC, "Grey Tier 2 (vendor benchmarking), correctly labelled. Load-bearing for the 55% security pass rate; the tiering caveat in §2.1 applies and is stated."),
    ("Wang et al.", 2024, SEC, "EC2: survey, therefore secondary. Peer-reviewed (IEEE TSE). Retained for landscape framing."),
    ("Ward & Habli", 2020, INC, "Peer-reviewed (SAFECOMP workshops). IC1–IC5 satisfied."),
    ("Wienczkowski", 2026, FLAG, "IC3 unsatisfied: single-authored arXiv preprint marked 'submitted for review'. RESOLVED: it was the sole support for the structural–adaptive fragmentation gap in §1.2 and §3.3; both now rest on Binbeshr & Imam (2025) and Mohammed et al. (2025), peer-reviewed, and cite this source for terminology only. Venue check still outstanding."),
    ("Willison", 2025, TIER3, "Grey Tier 3. The protocol excludes Tier 3 from any evidentiary claim; the reference list correctly says 'cite as a design heuristic, not evidence'. Verify §3.5 uses it only heuristically — it currently does."),
    ("Wohlin", 2014, MTH, "Snowballing guidelines. Cited for method, not evidence. IC2 does not apply."),
    ("Yang et al.", 2024, INC, "Peer-reviewed. IC1–IC5 satisfied."),
    ("Zakharchenko", 2026, INC, "Peer-reviewed (Software, MDPI). IC1–IC5 satisfied."),
    ("Zhao et al.", 2025, FLAG, "IC3 unsatisfied as cited: arXiv preprint. Cited in §3.5 for PoC-generation rates."),
]

# Cited in the text but absent from the reference list.
MISSING = [
    ("Kitchenham & Charters", 2007,
     "Cited in §2.1 as the source of the systematic review procedure, and ABSENT from the reference list. "
     "The review's own methodological basis is uncited."),
    ("Garousi et al.", 2019,
     "Cited in §2.1 for grey-literature inclusion and appraisal, and ABSENT from the reference list. "
     "The tiering scheme the paper relies on is uncited."),
]

# Criteria that cannot be checked from bibliographic metadata alone.
UNVERIFIABLE = {
    "IC5": "full text obtainable — not checkable without retrieving every source",
    "EC6": "under 4 pages — not checkable without the documents",
    "EC5": "duplicate reporting of the same study — no retrieved-record set exists to deduplicate against",
}


def main():
    counts = {}
    for _, _, verdict, _ in CORPUS:
        counts[verdict] = counts.get(verdict, 0) + 1

    payload = {
        "audit": "post-hoc criteria audit of the review corpus",
        "is_not": "intra-rater agreement; no prior screening decisions exist, so no kappa is computed",
        "population": "literature corpus only; standards and regulatory instruments belong to the Phase 2 corpus and are governed by a different protocol",
        "sources_audited": len(CORPUS),
        "verdicts": counts,
        "cited_but_missing_from_references": [
            {"key": k, "year": y, "issue": n} for k, y, n in MISSING
        ],
        "criteria_not_checkable_from_metadata": UNVERIFIABLE,
        "independence": "IC1, EC1 and EC3 require judgement; that judgement is the author's own and is not independent",
        "entries": [
            {"source": k, "year": y, "verdict": v, "note": n} for k, y, v, n in CORPUS
        ],
    }

    OUT.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    label = {
        INC: "include", SEC: "retain as secondary (EC2)",
        FND: "retain as foundational (IC2 exception)", MTH: "method reference",
        TIER3: "Tier 3, non-evidential", FLAG: "FLAG — needs author action",
    }

    print("Post-hoc criteria audit of the review corpus")
    print("=" * 74)
    print(f"population   {len(CORPUS)} literature sources "
          f"(standards/regulatory instruments excluded — Phase 2 corpus)\n")

    for verdict in (INC, SEC, FND, MTH, TIER3, FLAG):
        rows = [(k, y, n) for k, y, v, n in CORPUS if v == verdict]
        if not rows:
            continue
        print(f"{label[verdict].upper()}  ({len(rows)})")
        for k, y, n in rows:
            print(f"   {k} ({y})")
            if verdict == FLAG:
                print(f"      {n[:150]}")
        print()

    print("CITED BUT ABSENT FROM THE REFERENCE LIST")
    for k, y, n in MISSING:
        print(f"   {k} ({y}) — {n[:120]}")
    print()

    print("CRITERIA NOT CHECKABLE FROM METADATA")
    for c, why in UNVERIFIABLE.items():
        print(f"   {c}: {why}")

    print("\n" + "=" * 74)
    print(f"written {OUT}")
    print("NOTE: this is a criteria audit, not intra-rater agreement. No kappa is reported.")


if __name__ == "__main__":
    sys.exit(main())
