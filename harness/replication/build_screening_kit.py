#!/usr/bin/env python3
"""
Build the second-rater screening kit, and score it when it comes back.

  python build_screening_kit.py build    -> results/screening_kit/
  python build_screening_kit.py score <filled.csv>

WHY ALL 41 AND NOT 10%. The protocol suggests a colleague screen 10% of the
corpus. At n = 41 that is four sources, and Cohen's kappa on four observations
is not a reportable statistic — it is dominated by chance and its confidence
interval spans almost the whole range. The second rater screens the full 41.
At roughly two minutes per source from bibliographic metadata that is about
ninety minutes of work, and it yields a kappa worth printing.

The rater sees the criteria and the references. They do not see the first
author's verdicts, which is the whole point.
"""
import csv
import json
import pathlib
import shutil
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
KIT = ROOT / "results" / "screening_kit"
AUDIT = ROOT / "results" / "corpus_audit.json"

# The verdict vocabulary the first author used, collapsed to what a second
# rater can reasonably decide from metadata alone.
CHOICES = [
    ("include", "Satisfies IC1-IC5, no exclusion criterion trips. Primary evidence."),
    ("retain_secondary", "EC2: a survey or systematic review. Retained for gap comparison, not counted as primary evidence."),
    ("retain_foundational", "Pre-2019 and outside the stated 2014 extension, but retained as a foundational source. An IC2 exception."),
    ("method_reference", "Cited for methodology rather than as evidence. Outside the evidence corpus; IC2 does not apply."),
    ("tier3_non_evidential", "Grey literature Tier 3. Admissible only to show a practice exists, never for an evidentiary claim."),
    ("flag", "Fails or cannot satisfy a criterion as cited. Needs author action before submission."),
]


def build():
    audit = json.loads(AUDIT.read_text(encoding="utf-8"))
    if KIT.exists():
        shutil.rmtree(KIT, ignore_errors=True)
    KIT.mkdir(parents=True)

    shutil.copy2(ROOT / "docs" / "SPEC-security-properties.md", KIT / "_unused_do_not_read.md")
    (KIT / "_unused_do_not_read.md").unlink()   # keep the kit minimal

    # Criteria, lifted from the protocol so the rater has them to hand.
    (KIT / "CRITERIA.md").write_text("""# Inclusion and exclusion criteria

Apply these to each source in `screening_sheet.csv`.

## Inclusion
- **IC1** addresses AI/ML applied to software quality, testing, security
  verification or compliance automation, **or** addresses the quality, security
  or compliance properties of AI-containing systems
- **IC2** published 2019-01-01 or later; extended back to 2014 for foundational
  work on test-suite effectiveness, mutation validity, and DevSecOps
- **IC3** peer-reviewed venue, **or** grey literature at Tier 1 or Tier 2
- **IC4** English
- **IC5** full text obtainable

## Exclusion
- **EC1** AI as the application domain with no assurance, quality or security
  dimension
- **EC2** secondary studies — surveys and systematic reviews. Retained for
  snowballing and gap comparison, **not** counted as primary evidence
- **EC3** position pieces, editorials, keynote abstracts with no method or data
- **EC4** vendor material whose sole content is product marketing
- **EC5** duplicate reporting of the same study
- **EC6** under 4 pages

## Grey-literature tiers
- **Tier 1** standards bodies and regulators: ISO, IEEE, NIST, ENISA, the
  European Commission, OWASP, CSA, MITRE
- **Tier 2** established industry research: World Quality Report, DORA,
  Veracode, GitClear, major consultancy research
- **Tier 3** vendor blogs and practitioner posts. Usable only to establish that
  a practice exists, never as evidence

## Two criteria you cannot check, and should not guess
IC5 (full text obtainable) and EC6 (page count) are not decidable from the
metadata in the sheet. Leave them out of your reasoning; the first author's
audit records them as unverified for the same reason.
""", encoding="utf-8")

    (KIT / "VERDICTS.md").write_text(
        "# Verdict vocabulary\n\nUse exactly one of these strings in the `verdict` column.\n\n"
        + "\n".join("- **`%s`** — %s" % (c, d) for c, d in CHOICES)
        + "\n\nIf you genuinely cannot decide, write `unsure` and say why in `reason`.\n"
          "An honest `unsure` is more useful than a coin-flip: it is excluded from\n"
          "the kappa and reported separately.\n",
        encoding="utf-8")

    with (KIT / "screening_sheet.csv").open("w", newline="", encoding="utf-8") as fh:
        w = csv.writer(fh)
        w.writerow(["n", "source", "year", "verdict", "reason"])
        for i, e in enumerate(audit["entries"], 1):
            w.writerow([i, e["source"], e["year"], "", ""])

    (KIT / "README.md").write_text("""# Second-rater screening — {n} sources

You are the second rater. The paper currently states that no inter-rater
agreement is available because the corpus was screened by one author. This
exercise replaces that statement with a measured Cohen's kappa.

## What to do

1. Read `CRITERIA.md` and `VERDICTS.md`.
2. Open `screening_sheet.csv`. For each of the {n} rows, fill in `verdict`
   with one of the six strings, and `reason` with a sentence saying why.
3. Hand the filled CSV back.

About two minutes per source, so roughly ninety minutes.

## What you must not do

Do not look at the first author's verdicts. They are in
`results/corpus_audit.json` in the repository, and they are not in this kit on
purpose. If you read them first, the agreement figure measures nothing.

You may look sources up — that is screening, not cheating. Judge each on the
criteria, not on whether you think it belongs in the paper.

## Why all {n} and not a sample

The protocol suggests a colleague screen 10%. At {n} sources that is four, and
a kappa on four observations is not reportable. The full set is the smallest
sample that yields a statistic worth printing.
""".format(n=len(audit["entries"])), encoding="utf-8")

    print("screening kit written to %s" % KIT)
    print("  sources to screen   %d" % len(audit["entries"]))
    print("  verdict choices     %d" % len(CHOICES))
    leak = "verdict" in (KIT / "screening_sheet.csv").read_text(encoding="utf-8").split("\n")[1]
    print("  first author's verdicts included: %s" % ("YES - LEAK" if leak else "no"))


def kappa(a, b):
    """Cohen's kappa for two raters over the same items."""
    assert len(a) == len(b)
    n = len(a)
    cats = sorted(set(a) | set(b))
    agree = sum(1 for x, y in zip(a, b) if x == y)
    po = agree / n
    pe = sum((a.count(c) / n) * (b.count(c) / n) for c in cats)
    k = (po - pe) / (1 - pe) if pe != 1 else 0.0
    return po, pe, k, agree, n, cats


def interpret(k):
    if k < 0: return "worse than chance"
    if k < 0.21: return "slight"
    if k < 0.41: return "fair"
    if k < 0.61: return "moderate"
    if k < 0.81: return "substantial"
    return "almost perfect"


def score(filled_path):
    audit = json.loads(AUDIT.read_text(encoding="utf-8"))
    mine = {e["source"]: e["verdict"] for e in audit["entries"]}

    theirs, unsure, missing = {}, [], []
    with open(filled_path, newline="", encoding="utf-8-sig") as fh:
        for row in csv.DictReader(fh):
            src = (row.get("source") or "").strip()
            v = (row.get("verdict") or "").strip().lower()
            if not v:
                missing.append(src)
            elif v == "unsure":
                unsure.append(src)
            else:
                theirs[src] = v

    paired = [(mine[s], theirs[s]) for s in theirs if s in mine]
    if len(paired) < 15:
        print("Only %d usable pairs. Kappa is not reportable below about 15; "
              "ask for the remaining rows." % len(paired))
        return 1

    a = [p[0] for p in paired]
    b = [p[1] for p in paired]
    po, pe, k, agree, n, cats = kappa(a, b)

    print("Second-rater agreement on the review corpus")
    print("=" * 66)
    print("items screened by both      %d" % n)
    print("unsure (excluded)           %d" % len(unsure))
    print("not screened                %d" % len(missing))
    print("raw agreement               %d/%d = %.1f%%" % (agree, n, po * 100))
    print("expected by chance          %.3f" % pe)
    print("Cohen's kappa               %.3f  (%s)" % (k, interpret(k)))
    print("")
    print("disagreements:")
    for s, (x, y) in zip([s for s in theirs if s in mine], paired):
        if x != y:
            print("   %-32s author: %-20s rater: %s" % (s[:32], x, y))

    out = {
        "generated_at": __import__("datetime").datetime.now().isoformat(timespec="seconds"),
        "items": n, "raw_agreement": round(po, 4), "expected_agreement": round(pe, 4),
        "cohens_kappa": round(k, 4), "interpretation": interpret(k),
        "unsure": unsure, "not_screened": missing,
        "disagreements": [
            {"source": s, "author": x, "second_rater": y}
            for s, (x, y) in zip([s for s in theirs if s in mine], paired) if x != y
        ],
    }
    (ROOT / "results" / "screening_agreement.json").write_text(
        json.dumps(out, indent=2, ensure_ascii=False), encoding="utf-8")
    print("\nwritten results/screening_agreement.json")
    print("Report this kappa in §2.1 and §9, replacing the statement that no")
    print("inter-rater agreement is available.")
    return 0


if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "build"
    if cmd == "build":
        build()
    elif cmd == "score":
        sys.exit(score(sys.argv[2]))
    else:
        sys.exit("usage: build_screening_kit.py build | score <filled.csv>")
