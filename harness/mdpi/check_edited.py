#!/usr/bin/env python3
"""
Audit a hand-edited .docx against the verified master.

Written for one job: after a manuscript has been through paraphrasing or
language-editing tools, find out what the edits broke. Editing passes of that
kind reliably damage three things that matter more than prose quality --
reported figures, citation integrity, and required statements -- and all three
are invisible to a read-through.

Usage: python check_edited.py <edited.docx> <master.md>
"""
import html
import json
import pathlib
import re
import sys
import zipfile

EDITED = pathlib.Path(sys.argv[1])
MASTER = pathlib.Path(sys.argv[2])

xml = zipfile.ZipFile(EDITED).read("word/document.xml").decode("utf-8")
# Stripping tags is not enough: a .docx stores printed text XML-escaped, so a
# literal "&" is held as "&amp;". Reading that as a leaked entity reports every
# correctly-escaped ampersand in the file as a defect. Unescape once.
paras = [html.unescape(re.sub(r"<[^>]+>", "", p))
         for p in re.findall(r"<w:p[ >].*?</w:p>", xml, re.S)]
edited = "\n".join(paras)
master = MASTER.read_text(encoding="utf-8")

ROOT = pathlib.Path(__file__).resolve().parents[2]
res = lambda n: json.loads((ROOT / "results" / n).read_text(encoding="utf-8"))

problems, warnings = [], []
print("Audit of %s" % EDITED.name)
print("=" * 76)
print("words   edited %d   master %d   (%+d)"
      % (len(edited.split()), len(master.split()),
         len(edited.split()) - len(master.split())))
print()

# ── 1. reported figures ───────────────────────────────────────
detA, detB = res("detection_arm_A.json"), res("detection_arm_B.json")
q = res("quality_by_module.json")
tri = res("triage.json")
aud = res("evidence_audit.json")
stat = {r["metric"]: r for r in res("statistics.json")["results"]}

FIGURES = [
    ("Arm A detection",        "35.5%"),
    ("Arm B detection",        "96.8%"),
    ("security detection A",   "17.6%"),
    ("Arm A MSI",              "27.52%"),
    ("Arm B MSI",              "31.66%"),
    ("covered MSI A",          "43.09%"),
    ("covered MSI B",          "47.32%"),
    ("line coverage A",        "62.44%"),
    ("line coverage B",        "69.82%"),
    ("precision strict",       "3.85%"),
    ("precision actionable",   "38.46%"),
    ("adversarial as measured","94.7%"),
    ("adversarial after fix",  "100.0%"),
    ("control coverage A",     "43.75%"),
    ("control coverage B",     "81.25%"),
    ("traceability A",         "85.2%"),
    ("traceability B",         "92.6%"),
    ("overhead",               "46.2%"),
    ("M01 p-value",            "0.0039"),
    ("M04 p-value",            "0.206"),
    ("M04c p-value",           "1.000"),
    ("M05 p-value",            "0.031"),
    ("Cliff delta M01",        "+0.87"),
    ("Cliff delta M04c",       "0.02"),
]
print("REPORTED FIGURES")
missing_fig = []
for label, val in FIGURES:
    ok = val in edited
    if not ok:
        missing_fig.append((label, val))
    print("  %-26s %-10s %s" % (label, val, "present" if ok else "*** GONE ***"))
if missing_fig:
    problems.append("%d reported figures no longer appear in the text: %s"
                    % (len(missing_fig), ", ".join("%s (%s)" % m for m in missing_fig)))

# ── 2. citations ──────────────────────────────────────────────
print()
print("CITATIONS")
ay = re.findall(r"\([A-Z][A-Za-z'À-ÿ.\s&]{2,30},\s*(?:19|20)\d{2}[a-z]?\)", edited)
numbered = re.findall(r"\[\d+(?:[,–\-]\d+)*\]", edited)
master_cites = re.findall(r"\[[^\]\[]*?\d{4}[a-z]?\]", master)
print("  author-year in edited   %d" % len(ay))
print("  numbered in edited      %d" % len(numbered))
print("  citations in master     %d" % len(master_cites))
if len(ay) + len(numbered) < len(master_cites) * 0.8:
    problems.append("citation count collapsed: %d in the edited file against %d in the master"
                    % (len(ay) + len(numbered), len(master_cites)))

# ── 3. required statements ────────────────────────────────────
print()
print("REQUIRED STATEMENTS")
STATEMENTS = [
    ("AI / generative-AI disclosure", r"(?i)generative ai|large language model|Claude Opus|AI assistance"),
    ("Competing interests",           r"(?i)competing interests|conflicts of interest"),
    ("Funding",                       r"(?i)funding"),
    ("Data availability",             r"(?i)data availability"),
    ("Author contributions",          r"(?i)author contributions"),
    ("Ethics / IRB",                  r"(?i)ethics approval|institutional review board"),
]
for label, pat in STATEMENTS:
    ok = re.search(pat, edited) is not None
    print("  %-32s %s" % (label, "present" if ok else "*** ABSENT ***"))
    if not ok:
        problems.append("required statement absent: %s" % label)

# ── 4. which venue's file is this ─────────────────────────────
print()
print("VENUE")
mdpi_marks = ["Correspondence:", "Institutional Review Board Statement",
              "Informed Consent Statement", "Supplementary Materials"]
springer_marks = ["Corresponding author:", "Statements and Declarations"]
m_hits = [m for m in mdpi_marks if m in edited]
s_hits = [m for m in springer_marks if m in edited]
print("  MDPI markers      %d/%d  %s" % (len(m_hits), len(mdpi_marks), m_hits))
print("  Springer markers  %d/%d  %s" % (len(s_hits), len(springer_marks), s_hits))
if len(s_hits) > len(m_hits):
    problems.append("this is the SPRINGER file, not the MDPI one: it lacks MDPI's required "
                    "Institutional Review Board and Informed Consent headings and uses "
                    "author-year citations instead of numbered")

# ── 5. prose damage signals ───────────────────────────────────
print()
print("PROSE SIGNALS")
contractions = re.findall(r"\b(?:they|it|we|that|there|doesn|don|isn|aren|can|won)['’](?:re|s|ve|ll|t)\b",
                          edited, re.I)
# After unescaping, anything still matching was escaped twice and really does
# print literally.
entities = re.findall(r"&(?:amp|lt|gt|quot|#\d+);", edited)
print("  contractions            %d %s" % (len(contractions), sorted(set(c.lower() for c in contractions))[:6]))
print("  HTML entities leaked    %d %s" % (len(entities), sorted(set(entities))[:5]))
if contractions:
    warnings.append("%d contractions: academic prose does not use them, and they are a "
                    "paraphrasing-tool signature" % len(contractions))
if entities:
    problems.append("%d raw HTML entities in the text (e.g. %s) -- these will print literally"
                    % (len(entities), entities[0]))

# key terminology that must survive intact
TERMS = ["evidentiary risk", "mutation score", "seeded-defect", "Cliff",
         "Wilcoxon", "prompt injection", "evidence spine", "pre-registration"]
lost = [t for t in TERMS if t.lower() not in edited.lower()]
print("  key terms lost          %d %s" % (len(lost), lost))
if lost:
    problems.append("defined terminology no longer present: %s" % lost)

print()
print("=" * 76)
print("PROBLEMS: %d" % len(problems))
for p in problems:
    print("  - %s" % p)
print("WARNINGS: %d" % len(warnings))
for w in warnings:
    print("  - %s" % w)
sys.exit(1 if problems else 0)
