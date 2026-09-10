#!/usr/bin/env python3
"""
Build sn-bibliography.bib from the manuscript's reference list, and verify that
every in-text citation resolves to an entry.

The citation-integrity check is the point as much as the .bib is: a citation
that does not resolve here would render as a bold [?] in the compiled PDF, which
is exactly the sort of thing that reaches a referee.
"""
import json, pathlib, re, sys, unicodedata

MS = pathlib.Path(sys.argv[1])
OUT_BIB = pathlib.Path(sys.argv[2])
OUT_MAP = pathlib.Path(sys.argv[3])

text = MS.read_text(encoding="utf-8")
body, refs = text.split("## References", 1)

# Standards get their own handling: they are Phase 2 corpus, cited by name.
main_refs, _, std_refs = refs.partition("### Standards and regulatory instruments")
std_refs = std_refs.split("### Verification notes")[0]

ENTRY = re.compile(r"^([A-Z][^\n]*?\((\d{4})[a-z]?\)\.[^\n]*)$", re.M)

def ascii_key(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]", "", s.lower())

entries = []
for block in (main_refs, std_refs):
    for m in ENTRY.finditer(block):
        raw, year = m.group(1).strip(), m.group(2)
        raw = re.sub(r"\s*\[[^\]]*\]\s*$", "", raw).strip()   # drop trailing [⚠ …] notes
        surname = raw.split(",")[0].split(".")[0].strip()
        surname = re.sub(r"^\*+|\*+$", "", surname)
        entries.append({"raw": raw, "year": year, "surname": surname,
                        "key": ascii_key(surname) + year})

# de-duplicate keys
seen = {}
for e in entries:
    k = e["key"]
    if k in seen:
        suffix = "abcdefgh"[seen[k]]
        seen[k] += 1
        e["key"] = k + suffix
    else:
        seen[k] = 1

# ── in-text citations ─────────────────────────────────────────
CITE = re.compile(r"\[([^\]\[]*?\d{4}[a-z]?(?:\s*,\s*preprint)?)\]")
atoms, cite_spans = set(), []
for m in CITE.finditer(body):
    inner = m.group(1)
    if "⚠" in inner or "Grey literature" in inner or "Industry survey" in inner:
        continue
    parts = [p.strip() for p in inner.split(";")]
    ok = True
    for p in parts:
        if not re.search(r"\d{4}", p):
            ok = False
    if ok:
        cite_spans.append((m.group(0), parts))
        atoms.update(parts)

def match_atom(atom):
    a = re.sub(r",\s*preprint$", "", atom).strip()
    ym = re.search(r"(\d{4})", a)
    if not ym:
        return None
    year = ym.group(1)
    name = a[: ym.start()].rstrip(" ,")
    name = re.sub(r"\s*et al\.?$", "", name).strip()
    first = re.split(r"\s*&\s*", name)[0].strip()
    cands = [e for e in entries if e["year"] == year
             and ascii_key(e["surname"]).startswith(ascii_key(first)[:6])]
    if not cands:
        cands = [e for e in entries if e["year"] == year
                 and ascii_key(first)[:5] in ascii_key(e["raw"])[:60]]
    return cands[0] if cands else None

mapping, unresolved = {}, []
for atom in sorted(atoms):
    e = match_atom(atom)
    if e:
        mapping[atom] = e["key"]
    else:
        unresolved.append(atom)

# ── emit .bib ─────────────────────────────────────────────────
def bib_escape(s):
    return (s.replace("&", r"\&").replace("%", r"\%")
             .replace("_", r"\_").replace("#", r"\#")
             .replace("*", ""))

used = set(mapping.values())
lines = ["% Generated from the manuscript reference list by harness/springer/make_bib.py.",
         "% Do not hand-edit: edit the manuscript reference list and regenerate.",
         "% Springer Nature 'sn-basic' author-year style.", ""]
for e in entries:
    if e["key"] not in used:
        continue
    raw = bib_escape(e["raw"])
    m = re.match(r"^(.*?)\s*\((\d{4}[a-z]?)\)\.\s*(.*)$", raw, re.S)
    authors, year, rest = (m.group(1), m.group(2), m.group(3)) if m else (e["surname"], e["year"], raw)
    rest = rest.strip()
    tm = re.match(r"^(.*?)\.\s+(.*)$", rest, re.S)
    title, venue = (tm.group(1), tm.group(2)) if tm else (rest, "")
    lines += [f"@article{{{e['key']},",
              f"  author  = {{{authors.strip()}}},",
              f"  title   = {{{title.strip()}}},",
              f"  journal = {{{venue.strip().rstrip('.')}}},",
              f"  year    = {{{year}}},", "}", ""]
OUT_BIB.write_text("\n".join(lines), encoding="utf-8")
OUT_MAP.write_text(json.dumps({"map": mapping, "unresolved": unresolved}, indent=2, ensure_ascii=False), encoding="utf-8")

print(f"reference entries parsed   {len(entries)}")
print(f"unique in-text citations   {len(atoms)}")
print(f"resolved                   {len(mapping)}")
print(f"bib entries written        {len(used)}")
if unresolved:
    print(f"\nUNRESOLVED ({len(unresolved)}) — these would compile as [?]:")
    for u in unresolved:
        print(f"   {u}")
    sys.exit(1)
print("\nAll in-text citations resolve.")
