#!/usr/bin/env python3
"""
Locate the machine-written tells in the master, section by section.

Written because the first attempt at making the manuscript read as human prose
was a paraphrasing pass over the whole document, which damaged roughly seventy
paragraphs factually and left many of them ungrammatical. Running a paraphraser
over correct prose trades accuracy for a register change, and the trade is not
worth making blind.

So instead: measure. The things that mark text as model-written are countable --
em-dash density, a small set of connectives, stacked tricolons, "not X but Y"
frames, a known vocabulary, and above all uniformly-long sentences. Counting
them per section says which sections actually need rewriting by hand and which
are already fine, so the edit stays small and the facts stay intact.

Usage: python style_audit.py <master.md> [--section N]
"""
import pathlib
import re
import statistics
import sys

MASTER = pathlib.Path(sys.argv[1])
text = MASTER.read_text(encoding="utf-8")

# Strip what is generated rather than written: tables, code, the bibliography.
text = re.sub(r"```.*?```", "", text, flags=re.S)
text = re.sub(r"^\|.*$", "", text, flags=re.M)
cut = re.search(r"^## References", text, re.M)
if cut:
    text = text[:cut.start()]

TELLS = {
    "em-dash":       r"—",
    "moreover/furthermore": r"\b(?:Moreover|Furthermore|Additionally|In addition)\b",
    "it is worth":   r"(?i)\bit is (?:worth|important) (?:noting|to note)\b",
    "not X but Y":   r"\bnot (?:because|only|merely|simply)\b[^.]{0,80}\bbut\b",
    "crucially/notably": r"\b(?:Crucially|Notably|Importantly|Significantly),",
    "LLM lexicon":   r"(?i)\b(?:leverage|robust|delve|underscore|pivotal|seamless|"
                     r"holistic|paradigm|myriad|landscape|realm|intricate|nuanced)\b",
    "this/these+noun stacking": r"(?i)\bthis (?:means|suggests|indicates|implies)\b",
    "colon-then-list": r":\s+(?:\w+,\s+){2,}",
}


def sections(t):
    """Split on level-2 headings, keeping anything before the first as 'front'."""
    out, cur, name = [], [], "front matter"
    for line in t.split("\n"):
        m = re.match(r"^##\s+(.*)", line)
        if m and not line.startswith("###"):
            out.append((name, "\n".join(cur)))
            name, cur = m.group(1).strip(), []
        else:
            cur.append(line)
    out.append((name, "\n".join(cur)))
    return [(n, b) for n, b in out if b.strip()]


def sentences(b):
    b = re.sub(r"§\d+(\.\d+)?", "S", b)
    b = re.sub(r"\b(?:et al|e\.g|i\.e|cf|Fig|vs|approx|No)\.", "X", b)
    b = re.sub(r"\d+\.\d+", "N", b)
    return [s.strip() for s in re.split(r"(?<=[.!?])\s+", b) if len(s.split()) > 3]


print("Style audit -- %s" % MASTER.name)
print("=" * 88)
print("%-42s %5s %6s %5s %5s  %s" % ("section", "words", "sent", "mean", "sd", "tells"))
print("-" * 88)

rows = []
for name, body in sections(text):
    body_txt = re.sub(r"[*_`#>\[\]]", "", body)
    w = len(body_txt.split())
    if w < 60:
        continue
    sents = sentences(body_txt)
    lens = [len(s.split()) for s in sents]
    mean = statistics.mean(lens) if lens else 0
    sd = statistics.pstdev(lens) if len(lens) > 1 else 0
    hits = {}
    for label, pat in TELLS.items():
        n = len(re.findall(pat, body))
        if n:
            hits[label] = n
    per1k = sum(hits.values()) / (w / 1000)
    rows.append((per1k, name, w, len(sents), mean, sd, hits))

for per1k, name, w, ns, mean, sd, hits in sorted(rows, reverse=True):
    top = ", ".join("%s:%d" % (k, v) for k, v in
                    sorted(hits.items(), key=lambda kv: -kv[1])[:3])
    print("%-42s %5d %6d %5.1f %5.1f  %4.1f/kw  %s"
          % (name[:42], w, ns, mean, sd, per1k, top))

print("-" * 88)
allw = len(re.sub(r"[*_`#>\[\]]", "", text).split())
tot = {k: len(re.findall(p, text)) for k, p in TELLS.items()}
print("whole document: %d words" % allw)
for k, v in sorted(tot.items(), key=lambda kv: -kv[1]):
    print("   %-28s %4d   (%.1f per 1k words)" % (k, v, v / (allw / 1000)))
lens = [len(s.split()) for s in sentences(re.sub(r"[*_`#>\[\]]", "", text))]
print("   sentence length: mean %.1f  sd %.1f  median %d  "
      "under 12 words %.0f%%  over 35 %.0f%%"
      % (statistics.mean(lens), statistics.pstdev(lens),
         statistics.median(lens),
         100 * sum(1 for x in lens if x < 12) / len(lens),
         100 * sum(1 for x in lens if x > 35) / len(lens)))
