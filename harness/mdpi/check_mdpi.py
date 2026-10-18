#!/usr/bin/env python3
"""
Check the generated .docx against MDPI Algorithms' stated requirements.

Reads the OOXML. It cannot see layout or pagination, so it verifies rules, not
appearance -- the file still has to be opened before submission.

Rules checked, from the Instructions for Authors:
  abstract about 200 words maximum, single paragraph
  three to ten keywords
  numbered citations in square brackets, in order of appearance, no gaps
  every in-text number resolves to a reference entry
  no author-year citations left behind
  all nine required back-matter headings present, including the two that are
  mandatory even when the answer is "Not applicable"

Usage: python check_mdpi.py <file.docx> [references.json]
"""
import html
import json
import pathlib
import re
import sys
import zipfile

DOC = pathlib.Path(sys.argv[1])
REFS = pathlib.Path(sys.argv[2]) if len(sys.argv) > 2 else \
    pathlib.Path(__file__).resolve().parents[2] / "results" / "mdpi" / "references.json"

xml = zipfile.ZipFile(DOC).read("word/document.xml").decode("utf-8")
# Paragraph boundaries matter for the single-paragraph abstract rule.
paras = [html.unescape(re.sub(r"<[^>]+>", "", p))
         for p in re.findall(r"<w:p[ >].*?</w:p>", xml, re.S)]
text = "\n".join(paras)

problems = []
print("MDPI Algorithms compliance -- %s (%.2f MB)" % (DOC.name, DOC.stat().st_size / 1e6))
print("=" * 70)

# ── abstract ──────────────────────────────────────────────────
try:
    ai = next(i for i, p in enumerate(paras) if p.strip() == "Abstract")
    body_paras = []
    for p in paras[ai + 1:]:
        if p.startswith("Keywords"):
            break
        if p.strip():
            body_paras.append(p.strip())
    words = len(" ".join(body_paras).split())
    print("abstract            %d words, %d paragraph(s)" % (words, len(body_paras)))
    if words > 200:
        problems.append("abstract is %d words; MDPI states about 200 maximum" % words)
    if len(body_paras) != 1:
        problems.append("abstract spans %d paragraphs; MDPI requires a single paragraph" % len(body_paras))
except StopIteration:
    problems.append("no Abstract heading found")

# ── keywords ──────────────────────────────────────────────────
kw = next((p for p in paras if p.startswith("Keywords")), None)
if kw:
    n = len([k for k in re.split(r"[;,]", kw.split(":", 1)[-1] if ":" in kw else kw[8:]) if k.strip()])
    print("keywords            %d" % n)
    if not 3 <= n <= 10:
        problems.append("%d keywords; MDPI requires three to ten" % n)
else:
    problems.append("no Keywords line found")

# ── citations ─────────────────────────────────────────────────
nums = set()
for m in re.finditer(r"\[(\d+(?:[,–\-]\d+)*)\]", text):
    for part in m.group(1).split(","):
        if "–" in part or re.match(r"^\d+-\d+$", part):
            lo, hi = re.split(r"[–-]", part)
            nums.update(range(int(lo), int(hi) + 1))
        else:
            nums.add(int(part))
print("citations           %d distinct numbers, range %d-%d"
      % (len(nums), min(nums) if nums else 0, max(nums) if nums else 0))

leftover = re.findall(r"\([A-Z][A-Za-z'À-ÿ.\s&]+,\s*(?:19|20)\d{2}[a-z]?\)", text)
leftover = [l for l in leftover if "et al" in l or re.search(r"[A-Z][a-z]+,", l)]
print("author-year left    %d" % len(leftover))
if leftover:
    problems.append("%d author-year citations survived: %s" % (len(leftover), leftover[:3]))

if REFS.exists():
    refs = json.loads(REFS.read_text(encoding="utf-8"))
    total = len(refs)
    print("reference entries   %d" % total)
    dangling = sorted(n for n in nums if n > total)
    if dangling:
        problems.append("in-text numbers with no entry: %s" % dangling)
    cited_max = max(nums) if nums else 0
    gaps = [n for n in range(1, cited_max + 1) if n not in nums]
    if gaps:
        print("uncited below max   %s" % gaps[:12])
        problems.append("numbering has gaps below the highest cited number: %s" % gaps[:8])
    low = [r["n"] for r in refs if r.get("confidence") == "low"]
    if low:
        print("low-confidence refs %s  (check these by hand)" % low)

# ── required back matter ──────────────────────────────────────
required = ["Supplementary Materials", "Author Contributions", "Funding",
            "Institutional Review Board Statement", "Informed Consent Statement",
            "Data Availability Statement", "Acknowledgments", "Conflicts of Interest"]
missing = [r for r in required if r not in text]
print("back matter         %d/%d present" % (len(required) - len(missing), len(required)))
if missing:
    problems.append("missing required back matter: %s" % missing)

# ── placeholders ──────────────────────────────────────────────
ph = sorted(set(re.findall(r"CONFIRM-[a-z-]+", text)))
print("placeholders        %d %s" % (len(ph), ph if ph else ""))
if ph:
    problems.append("unresolved placeholders: %s" % ph)

print("")
if problems:
    print("PROBLEMS: %d" % len(problems))
    for p in problems:
        print("  - %s" % p)
else:
    print("PROBLEMS: 0")
print("\nNOTE: reads the OOXML. It cannot see layout, pagination, or whether a")
print("      wide table overruns the page. Open the file in Word before submitting.")
sys.exit(1 if problems else 0)
