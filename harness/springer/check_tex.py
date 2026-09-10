#!/usr/bin/env python3
"""
Structural sanity check on the generated LaTeX.

No TeX distribution is installed on this machine, so this cannot substitute for
a real compile. It catches the failures that would otherwise only surface on
Overleaf: unbalanced environments or braces, unreplaced template tokens, and
table rows whose column count disagrees with their preamble.

Usage: python check_tex.py <file.tex>
"""
import pathlib
import re
import sys

path = pathlib.Path(sys.argv[1])
tex = path.read_text(encoding="utf-8")
body = "\n".join(l for l in tex.split("\n") if not l.lstrip().startswith("%"))

problems = 0

# ── environments ──────────────────────────────────────────────
envs = {}
for kind, name in re.findall(r"\\(begin|end)\{([a-zA-Z*]+)\}", body):
    envs.setdefault(name, [0, 0])[0 if kind == "begin" else 1] += 1
unbalanced = {k: v for k, v in envs.items() if v[0] != v[1]}
print("environments   " + ", ".join("%s %d/%d" % (k, v[0], v[1]) for k, v in sorted(envs.items())))
if unbalanced:
    print("  UNBALANCED   %s" % unbalanced)
    problems += len(unbalanced)

# ── braces ────────────────────────────────────────────────────
depth, lowest = 0, 0
for ch in re.sub(r"\\[{}]", "", body):
    if ch == "{":
        depth += 1
    elif ch == "}":
        depth -= 1
        lowest = min(lowest, depth)
print("braces         end=%d min=%d %s" % (depth, lowest, "OK" if depth == 0 and lowest == 0 else "PROBLEM"))
if depth != 0 or lowest < 0:
    problems += 1

# ── template tokens ───────────────────────────────────────────
for tok in ("__ABSTRACT__", "__BODY__", "__KEYWORDS__", "__DECLARATIONS__", "__APPENDIX__"):
    if tok in tex:
        print("  UNREPLACED   %s" % tok)
        problems += 1

# ── table column arity ────────────────────────────────────────
for tm in re.finditer(r"\\begin\{tabular\}\{@\{\}(.*?)@\{\}\}(.*?)\\end\{tabular\}", body, re.S):
    preamble, content = tm.group(1), tm.group(2)
    ncol = len(re.findall(r"p\{[^}]*\}|[lcr]", preamble))
    for row in content.split("\\\\"):
        row = row.strip()
        if not row or row.startswith("\\toprule") or row.startswith("\\midrule") or row.startswith("\\bottomrule"):
            continue
        cleaned = re.sub(r"\\[a-zA-Z]+\{[^{}]*\}", "", row)
        n = cleaned.count("&") + 1
        if n != ncol and "&" in row:
            print("  ROW ARITY    expected %d cols, row has %d: %s" % (ncol, n, row[:70]))
            problems += 1

# ── stray markdown that survived ──────────────────────────────
for pat, label in ((r"\*\*[^*\n]+\*\*", "bold markdown"),
                   (r"^#{1,4}\s", "markdown heading"),
                   (r"\]\(http", "markdown link")):
    hits = re.findall(pat, body, re.M)
    if hits:
        print("  STRAY MD     %s x%d  %s" % (label, len(hits), str(hits[:2])[:70]))
        problems += 1

# ── required Springer front matter ────────────────────────────
for req in (r"\\documentclass\[sn-basic\]\{sn-jnl\}", r"\\title", r"\\author",
            r"\\affil", r"\\abstract\{", r"\\keywords\{", r"\\maketitle",
            r"\\bibliography\{sn-bibliography\}"):
    if not re.search(req, tex):
        print("  MISSING      %s" % req)
        problems += 1

confirm = re.findall(r"CONFIRM-[a-z-]+", tex)
print("placeholders   %d to complete: %s" % (len(confirm), ", ".join(sorted(set(confirm)))))

print("")
print("PROBLEMS: %d" % problems)
sys.exit(1 if problems else 0)
