#!/usr/bin/env python3
"""
Align a hand-edited .docx against the markdown master and classify every
divergent paragraph.

The point is to keep the human editing pass -- which is what makes the prose
read as prose -- without keeping the damage it caused. A paraphrasing pass
breaks facts silently: numbers drift, citations vanish, a clause gets merged
into a false attribution. Prose quality is visible on a read-through; that
damage is not.

So each divergent paragraph is checked mechanically against its master
counterpart for numbers, citations and length, and reported as either SAFE
(adopt the edited wording) or a named failure (repair by hand). Nothing is
rewritten here; this only produces the worklist.

Usage: python merge_edited.py <edited.docx> <master.md> [--out report.txt]
"""
import difflib
import html
import pathlib
import re
import sys
import zipfile

EDITED = pathlib.Path(sys.argv[1])
MASTER = pathlib.Path(sys.argv[2])
OUT = None
if "--out" in sys.argv:
    OUT = pathlib.Path(sys.argv[sys.argv.index("--out") + 1])


def docx_paragraphs(path):
    xml = zipfile.ZipFile(path).read("word/document.xml").decode("utf-8")
    out = []
    for m in re.finditer(r"(<w:p[ >].*?</w:p>)|(<w:tbl>.*?</w:tbl>)", xml, re.S):
        if m.group(2):
            out.append("[TABLE]")
            continue
        t = html.unescape(re.sub(r"<[^>]+>", "", m.group(1))).strip()
        if t:
            out.append(t)
    return out


def master_blocks(path):
    """Prose blocks only. Tables and fenced code are regenerated, not edited."""
    text = path.read_text(encoding="utf-8")
    text = re.sub(r"```.*?```", "", text, flags=re.S)
    out = []
    for b in re.split(r"\n\s*\n", text):
        b = b.strip()
        if not b or b.startswith("|"):
            continue
        out.append(" ".join(b.split()))
    return out


def norm(s):
    """Comparison key: ignore markup, entities, citation style and case."""
    s = re.sub(r"&(?:amp|lt|gt|quot|#\d+);", " ", s)
    s = re.sub(r"[*_`#]", "", s)
    s = re.sub(r"[^a-z0-9 ]", " ", s.lower())
    return " ".join(s.split())


# Facts that must survive an edit untouched.
def numbers(s):
    # Percentages, p-values, deltas, counts -- anything the results depend on.
    return sorted(re.findall(r"\d+(?:\.\d+)?%?", s))


def surnames(s):
    """Citation surnames, independent of author-year vs numbered style."""
    keys = re.findall(r"\[([^\]\[]*?\d{4}[a-z]?)\]", s)          # master
    keys += re.findall(r"\(([A-Z][^()]{2,40},\s*(?:19|20)\d{2}[a-z]?)\)", s)  # edited
    names = set()
    for k in keys:
        head = re.split(r",\s*(?:19|20)\d{2}", k)[0]
        for part in re.split(r"\s*(?:&|and|;)\s*", head):
            part = part.replace("et al.", "").replace("et al", "").strip(" .")
            if part and part[0].isupper():
                names.add(part.split()[-1].lower())
    return names


CONTRACTION = re.compile(
    r"\b\w+['\u2019](?:re|ve|ll|t|s)\b(?!\s*(?:own|$))", re.I)
ENTITY = re.compile(r"&(?:amp|lt|gt|quot|#\d+);")


def classify(m, e):
    """Return list of reasons the edited paragraph cannot be adopted as-is."""
    bad = []
    mn, en = numbers(m), numbers(e)
    lost_n = [n for n in set(mn) if n not in set(en)]
    if lost_n:
        bad.append("NUMBERS lost %s" % sorted(lost_n)[:6])
    ms, es = surnames(m), surnames(e)
    lost_c = sorted(ms - es)
    if lost_c:
        bad.append("CITATION lost %s" % lost_c[:5])
    mw, ew = len(norm(m).split()), len(norm(e).split())
    if mw >= 25 and ew < mw * 0.72:
        bad.append("SHORTER %d->%d words" % (mw, ew))
    if ENTITY.search(e):
        bad.append("ENTITY %s" % sorted(set(ENTITY.findall(e)))[:4])
    c = CONTRACTION.findall(e)
    if c:
        bad.append("CONTRACTION %s" % sorted(set(x.lower() for x in c))[:5])
    return bad


mp = master_blocks(MASTER)
ed = docx_paragraphs(EDITED)
sm = difflib.SequenceMatcher(None, [norm(p)[:140] for p in mp],
                             [norm(p)[:140] for p in ed], autojunk=False)

lines = []
w = lines.append
w("Merge worklist: %s" % EDITED.name)
w("=" * 78)
w("master prose blocks %d   edited paragraphs %d" % (len(mp), len(ed)))
w("")

safe = repair = unmatched_m = unmatched_e = 0
reasons = {}
for tag, i1, i2, j1, j2 in sm.get_opcodes():
    if tag == "equal":
        continue
    if tag == "delete":
        for i in range(i1, i2):
            unmatched_m += 1
            w("[DROPPED  m%03d] %s" % (i, mp[i][:300]))
            w("")
        continue
    if tag == "insert":
        for j in range(j1, j2):
            unmatched_e += 1
            w("[ADDED    e%03d] %s" % (j, ed[j][:300]))
            w("")
        continue
    # replace: pair by best similarity, not by position. Positional pairing
    # inside a replace block lines a prose block up against a heading as soon
    # as the edit added or removed one paragraph, and every fact in it then
    # reads as lost.
    mi_list, ej_list = list(range(i1, i2)), list(range(j1, j2))
    scored = []
    for mi in mi_list:
        a = norm(mp[mi])
        for ej in ej_list:
            r = difflib.SequenceMatcher(None, a, norm(ed[ej])).quick_ratio()
            scored.append((r, mi, ej))
    scored.sort(reverse=True)
    used_m, used_e, matched = set(), set(), []
    for r, mi, ej in scored:
        if r < 0.55 or mi in used_m or ej in used_e:
            continue
        used_m.add(mi)
        used_e.add(ej)
        matched.append((mi, ej))
    for mi in mi_list:
        if mi not in used_m:
            unmatched_m += 1
            w("[DROPPED  m%03d] %s" % (mi, mp[mi][:300]))
            w("")
    for ej in ej_list:
        if ej not in used_e:
            unmatched_e += 1
            w("[ADDED    e%03d] %s" % (ej, ed[ej][:300]))
            w("")
    for mi, ej in sorted(matched):
        bad = classify(mp[mi], ed[ej])
        if bad:
            repair += 1
            for b in bad:
                reasons[b.split()[0]] = reasons.get(b.split()[0], 0) + 1
            w("[REPAIR   m%03d/e%03d] %s" % (mi, ej, "; ".join(bad)))
            w("    MASTER: %s" % mp[mi][:600])
            w("    EDITED: %s" % ed[ej][:600])
            w("")
        else:
            safe += 1
            w("[SAFE     m%03d/e%03d]" % (mi, ej))
            w("    EDITED: %s" % ed[ej][:300])
            w("")

w("=" * 78)
w("adopt edited wording as-is   %d" % safe)
w("needs hand repair            %d   %s" % (repair, reasons))
w("in master, no edited match   %d" % unmatched_m)
w("in edited, no master match   %d" % unmatched_e)

report = "\n".join(lines)
if OUT:
    OUT.write_text(report, encoding="utf-8")
    print("\n".join(lines[-6:]))
    print("\nwritten to %s" % OUT)
else:
    print(report)
