#!/usr/bin/env python3
"""
Convert the manuscript's author-year citations to MDPI's numbered style.

MDPI Algorithms requires references "numbered in order of appearance in the
text (including table captions and figure legends)", cited as [1], [1-3] or
[1,3], with the bracket placed BEFORE the punctuation.

This walks the body in reading order, assigns numbers on first appearance,
reorders the reference list to match, and converts each entry from the
manuscript's APA-like form towards MDPI/ACS form:

    Schäfer, M., Nadi, S., & Tip, F. (2024). Title. Journal, 50(1), 85-105.
 -> Schäfer, M.; Nadi, S.; Tip, F. Title. Journal 2024, 50, 85-105.

WHAT IT DOES NOT DO: abbreviate journal names. MDPI house style abbreviates
them, but doing that reliably needs an abbreviation table, and a guessed
abbreviation is a wrong citation. Algorithms accepts free-format submission
with references "in any style, provided that you use consistent formatting",
and the copy-editor abbreviates at the production stage. Full names are
therefore correct for submission and safer than invention.

Every entry is scored for conversion confidence, and anything that did not
match a clean pattern is listed at the end for a human pass.

Usage: python renumber.py <master.md> <out-citemap.json> <out-references.json>
"""
import json
import pathlib
import re
import sys
import unicodedata

MASTER = pathlib.Path(sys.argv[1])
OUT_MAP = pathlib.Path(sys.argv[2])
OUT_REFS = pathlib.Path(sys.argv[3])

src = MASTER.read_text(encoding="utf-8")
body_all, refs_block = src.split("## References", 1)

# The body in reading order: everything before References, plus the appendix,
# which sits after the reference list in the master but before it in the paper.
appendix = ""
am = re.search(r"## Appendix C[^\n]*\n(.*?)\n---", src, re.S)
if am:
    appendix = am.group(1)
body = body_all + "\n" + appendix

main_refs, _, std_refs = refs_block.partition("### Standards and regulatory instruments")
std_refs = std_refs.split("### Verification notes")[0]

ENTRY = re.compile(r"^([A-Z][^\n]*?\((\d{4})[a-z]?\)\.[^\n]*)$", re.M)
STD_ENTRY = re.compile(r"^((?:ISO/IEC|NIST|MITRE|OWASP|European|[A-Z])[^\n]{20,})$", re.M)


def ascii_fold(s):
    return unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()


# ── parse the reference list ──────────────────────────────────
entries = []
for m in ENTRY.finditer(main_refs):
    raw = re.sub(r"\s*\[[^\]]*\]\s*$", "", m.group(1)).strip()
    surname = re.sub(r"^\*+", "", raw.split(",")[0].split(".")[0]).strip()
    entries.append({"raw": raw, "year": m.group(2), "surname": surname})

standards = []
for line in std_refs.split("\n"):
    line = line.strip()
    if len(line) > 25 and not line.startswith(("#", "-", "*", "|")):
        standards.append({"raw": re.sub(r"\s*\[[^\]]*\]\s*$", "", line).strip()})

# ── walk the body in order ────────────────────────────────────
CITE = re.compile(r"\[([^\]\[]*?\d{4}[a-z]?(?:\s*,\s*preprint)?)\]")

order, mapping, unresolved = [], {}, []


def match_atom(atom):
    a = re.sub(r",\s*preprint$", "", atom).strip()
    ym = re.search(r"(\d{4})", a)
    if not ym:
        return None
    year = ym.group(1)
    name = re.sub(r"\s*et al\.?$", "", a[: ym.start()].rstrip(" ,")).strip()
    first = re.split(r"\s*&\s*", name)[0].strip()
    key = ascii_fold(first).lower()[:6]
    for e in entries:
        if e["year"] == year and ascii_fold(e["surname"]).lower().startswith(key):
            return e
    for e in entries:
        if e["year"] == year and key in ascii_fold(e["raw"]).lower()[:70]:
            return e
    return None


for m in CITE.finditer(body):
    inner = m.group(1)
    if any(x in inner for x in ("Grey literature", "Industry survey", "⚠")):
        continue
    for part in (p.strip() for p in inner.split(";")):
        if part in mapping:
            continue
        e = match_atom(part)
        if e is None:
            if part not in unresolved:
                unresolved.append(part)
            continue
        if e["raw"] not in [o["raw"] for o in order]:
            order.append(e)
        mapping[part] = [o["raw"] for o in order].index(e["raw"]) + 1

# Cited works come first, in order of appearance. Standards follow, then any
# reference never cited by key — which is itself worth reporting.
numbered = list(order)
for s in standards:
    numbered.append(s)
uncited = [e for e in entries if e["raw"] not in [o["raw"] for o in order]]
numbered.extend(uncited)

# ── APA-like -> MDPI/ACS-like ─────────────────────────────────
def to_mdpi(raw):
    """Returns (converted, confidence) where confidence is high|medium|low."""
    s = raw.replace("*", "").strip()

    m = re.match(r"^(.*?)\s*\((\d{4}[a-z]?)\)\.\s*(.*)$", s, re.S)
    if not m:
        return s, "low"                      # standards and odd entries: leave alone
    authors, year, rest = m.group(1).strip(), m.group(2), m.group(3).strip()

    # Authors: semicolons, no ampersand.
    if re.search(r"[A-Z]\.", authors):
        a = authors.replace(", & ", "; ").replace(" & ", "; ")
        a = re.sub(r"([A-Z]\.),\s+(?=[A-ZÀ-Ý])", r"\1; ", a)
        a = re.sub(r"\s*,\s*et al\.?", " et al.", a)
    else:                                     # organisation as author
        a = authors.replace(", & ", "; ").replace(" & ", "; ").replace(", ", "; ")
    a = a.rstrip(".") + "."

    # Title is up to the first sentence break; the remainder is the venue.
    tm = re.match(r"^(.*?[^A-Z\d])\.\s+(.*)$", rest, re.S)
    if not tm:
        return "%s %s %s" % (a, rest.rstrip("."), year), "medium"
    title, venue = tm.group(1).strip().rstrip("."), tm.group(2).strip()

    # Keep the arXiv identifier when it is the only venue: for a preprint it
    # *is* the venue, and stripping it leaves the entry unlocatable.
    arxiv = re.search(r"arXiv:\s*(\S+?)\.?(?:\s|$)", venue)
    venue = re.sub(r"\s*(DOI|doi):\s*\S+\.?", "", venue)
    venue = re.sub(r"\s*arXiv:\s*\S+\.?", "", venue).strip()
    venue = re.sub(r"\s*\(v\d+\)\s*", " ", venue).strip()
    venue = venue.rstrip(". ").strip()
    if arxiv and (not venue or re.fullmatch(r"[\s.,;]*", venue)):
        venue = "arXiv:%s" % arxiv.group(1).rstrip(".")
    elif arxiv:
        venue = "%s. arXiv:%s" % (venue, arxiv.group(1).rstrip("."))

    conf = "high"
    # volume(issue), pages  ->  year, volume, pages
    vm = re.search(r",\s*(\d+)\s*\(\d+\)\s*,\s*([\d–—-]+)\s*$", venue)
    if vm:
        venue = venue[: vm.start()] + " %s, %s, %s" % (year, vm.group(1), vm.group(2))
    else:
        vm = re.search(r",\s*(\d+)\s*,\s*([\d–—-]+)\s*$", venue)
        if vm:
            venue = venue[: vm.start()] + " %s, %s, %s" % (year, vm.group(1), vm.group(2))
        else:
            vm = re.search(r",\s*(\d+)\s*\(\d+\)\s*,?\s*$", venue)
            if vm:
                venue = venue[: vm.start()] + " %s, %s" % (year, vm.group(1))
            else:
                venue = "%s %s" % (venue, year)
                conf = "medium"

    return "%s %s. %s." % (a, title, venue), conf


out_refs = []
for i, e in enumerate(numbered, 1):
    conv, conf = to_mdpi(e["raw"])
    out_refs.append({"n": i, "mdpi": conv, "source": e["raw"], "confidence": conf})

OUT_MAP.write_text(json.dumps({"map": mapping, "unresolved": unresolved},
                              indent=2, ensure_ascii=False), encoding="utf-8")
OUT_REFS.write_text(json.dumps(out_refs, indent=2, ensure_ascii=False), encoding="utf-8")

cited = len(order)
print("citations renumbered in order of appearance")
print("  distinct cited works     %d" % cited)
print("  standards appended       %d" % len(standards))
print("  never cited by key       %d" % len(uncited))
print("  total reference entries  %d" % len(out_refs))
print("  unresolved citations     %d" % len(unresolved))
for u in unresolved:
    print("     UNRESOLVED: %s" % u)

low = [r for r in out_refs if r["confidence"] == "low"]
med = [r for r in out_refs if r["confidence"] == "medium"]
print("\nconversion confidence: %d high, %d medium, %d low"
      % (len(out_refs) - len(low) - len(med), len(med), len(low)))
if med or low:
    print("\nThese need a human eye before submission:")
    for r in (med + low):
        print("  [%d] %s" % (r["n"], r["mdpi"][:105]))

print("\nwritten %s, %s" % (OUT_MAP.name, OUT_REFS.name))
sys.exit(1 if unresolved else 0)
