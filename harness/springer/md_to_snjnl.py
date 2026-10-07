#!/usr/bin/env python3
"""
Convert the manuscript to Springer Nature's sn-jnl LaTeX class.

Targets Software Quality Journal: sn-basic reference style (author-year),
Springer's "Statements and Declarations" section, booktabs tables.

Regenerable: edit the markdown master and re-run. Nothing is hand-maintained on
the LaTeX side, so the two cannot drift.

Usage:
  python md_to_snjnl.py <manuscript.md> <citemap.json> <out.tex> <template.tex>
"""
import json
import pathlib
import re
import sys

MS = pathlib.Path(sys.argv[1])
MAPF = pathlib.Path(sys.argv[2])
OUT = pathlib.Path(sys.argv[3])
TEMPLATE = pathlib.Path(sys.argv[4])

src = MS.read_text(encoding="utf-8")
citemap = json.loads(MAPF.read_text(encoding="utf-8"))["map"]

body = src.split("## References", 1)[0]
body = re.sub(r"^# .*?\n", "", body, count=1)
body = re.sub(r"^\*\*Manuscript draft.*?\n", "", body, flags=re.M)
body = re.sub(r"^> .*\n", "", body, flags=re.M)

m = re.search(r"## Abstract\s*\n(.*?)\n\*\*Keywords:\*\*\s*(.*?)\n", body, re.S)
abstract = " ".join(m.group(1).split()) if m else ""
keywords = [k.strip() for k in m.group(2).split(";")] if m else []
body = body[m.end():] if m else body
body = body.split("## Declarations")[0].split("## Appendix C")[0]

dm = re.search(r"## Declarations\s*\n(.*?)\n---", src, re.S)
declarations = dm.group(1) if dm else ""
am = re.search(r"## Appendix C[^\n]*\n(.*?)\n---", src, re.S)
appendix = am.group(1) if am else ""

TOKEN = "\x00%d\x00"

# Figure number in the manuscript -> filename stem under figures/
FIGURES = {
    "1": "fig1_saiqe_framework",
    "2": "fig2_paired_outcomes",
}

SPECIALS = [
    ("&", r"\&"), ("%", r"\%"), ("$", r"\$"), ("#", r"\#"),
    ("_", r"\_"), ("{", r"\{"), ("}", r"\}"),
    ("~", r"\textasciitilde{}"), ("^", r"\textasciicircum{}"),
]

UNICODE = [
    ("§", "Sect.~"), ("—", "---"), ("–", "--"),
    ("×", r"$\times$"), ("≥", r"$\ge$"), ("≤", r"$\le$"),
    ("δ", r"$\delta$"), ("α", r"$\alpha$"), ("κ", r"$\kappa$"),
    ("Δ", r"$\Delta$"), ("−", "-"), ("→", r"$\rightarrow$"),
    ("●", r"$\bullet$"), ("◐", r"$\ominus$"), ("○", r"$\circ$"),
    ("·", r"$\cdot$"), ("€", r"\texteuro{}"),
    ("⟨", "["), ("⟩", "]"), ("†", r"$\dagger$"),
    ("‑", "-"), ("’", "'"), ("‘", "`"),
    ("“", "``"), ("”", "''"), ("⚠", ""),
    ("Â", ""), ("…", r"\ldots{}"), (" ", "~"),
]


def protect(s):
    """Escape TeX specials and normalise unicode the class cannot set."""
    s = s.replace("\\", r"\textbackslash{}")
    for a, b in SPECIALS:
        s = s.replace(a, b)
    for a, b in UNICODE:
        s = s.replace(a, b)
    return s


def inline(s):
    """Inline markdown to LaTeX. Citations resolve first, before escaping."""
    store = []

    def stash(tex):
        store.append(tex)
        return TOKEN % (len(store) - 1)

    def cite(mo):
        parts = [p.strip() for p in mo.group(1).split(";")]
        keys = [citemap.get(p) for p in parts]
        if all(keys):
            return stash(r"\citep{" + ",".join(keys) + "}")
        return mo.group(0)

    s = re.sub(r"\[([^\]\[]*?\d{4}[a-z]?(?:\s*,\s*preprint)?)\]", cite, s)
    s = re.sub(r"`([^`]+)`", lambda mo: stash(r"\texttt{" + protect(mo.group(1)) + "}"), s)
    s = re.sub(r"\*\*(.+?)\*\*", lambda mo: stash(r"\textbf{" + inline(mo.group(1)) + "}"), s)
    s = re.sub(r"(?<!\*)\*([^*\n]+?)\*(?!\*)", lambda mo: stash(r"\emph{" + inline(mo.group(1)) + "}"), s)

    s = protect(s)
    for i, tex in enumerate(store):
        s = s.replace(TOKEN % i, tex)
    return s


def cells(row):
    return [c.strip() for c in row.strip().strip("|").split("|")]


def is_separator(row):
    return bool(re.match(r"^\s*\|[\s:|\-]+\|\s*$", row))


def table(rows, number, caption=None):
    """Markdown pipe table to a booktabs tabular inside a float."""
    header, sep, data = rows[0], rows[1], rows[2:]
    ncol = len(cells(sep))
    align = ""
    for c in cells(sep):
        if c.startswith(":") and c.endswith(":"):
            align += "c"
        elif c.endswith(":"):
            align += "r"
        else:
            align += "l"
    align = align[:ncol] or "l" * ncol
    # Wide tables need line-breaking columns or they overrun the text block.
    if ncol >= 5:
        align = "p{0.16\\textwidth}" * ncol

    out = [
        r"\begin{table}[htbp]",
        r"\centering",
        r"\caption{" + (inline(caption) if caption else "CONFIRM-caption-table-%d" % number)
        + r"}\label{tab%d}" % number,
        r"\footnotesize",
        r"\begin{tabular}{@{}" + align + r"@{}}",
        r"\toprule",
        " & ".join(inline(c) for c in cells(header)) + r" \\",
        r"\midrule",
    ]
    for r in data:
        c = cells(r) + [""] * ncol
        out.append(" & ".join(inline(x) for x in c[:ncol]) + r" \\")
    out += [r"\bottomrule", r"\end{tabular}", r"\end{table}", ""]
    return out


def reflow(md):
    """
    Join hard-wrapped prose into one line per paragraph.

    The master is wrapped at ~78 columns. Processing it line by line splits
    inline spans across the wrap -- **bold beginning on one line and closing on
    the next never matches, and both markers survive into the output. Headings,
    tables, list items and blank lines are left alone.
    """
    out, buf = [], []

    def flush():
        if buf:
            out.append(" ".join(x.strip() for x in buf))
            buf.clear()

    for ln in md.split("\n"):
        stripped = ln.strip()
        structural = (not stripped
                      or stripped.startswith(("#", "|", "- ", "* ", "---", ">"))
                      or re.match(r"^\d+\.\s", stripped))
        if structural:
            flush()
            out.append(ln)
        else:
            buf.append(ln)
    flush()
    return "\n".join(out)


def convert(md):
    out = []
    lines = reflow(md).split("\n")
    i = 0
    tables = 0
    pending_caption = None
    while i < len(lines):
        ln = lines[i]

        # A bold "Table N — ..." line is the caption for the table that follows,
        # possibly after a line or two of qualifying prose. Hold it rather than
        # emitting it as body text.
        cap = re.match(r"^\*\*(Table\s*[A-Z]?[0-9]+[a-z]?\s*[—-]\s*.+?)\*\*\s*(.*)$", ln)
        if cap:
            pending_caption = " ".join((cap.group(1) + " " + cap.group(2)).split())
            i += 1
            while i < len(lines) and lines[i].strip() and not lines[i].strip().startswith("|"):
                pending_caption += " " + lines[i].strip()
                i += 1
            pending_caption = re.sub(r"\s+", " ", pending_caption).strip()
            continue

        if ln.strip().startswith("|") and i + 1 < len(lines) and is_separator(lines[i + 1]):
            blk = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                blk.append(lines[i])
                i += 1
            tables += 1
            out += table(blk, tables, pending_caption)
            pending_caption = None
            continue

        if pending_caption and ln.strip():
            # Caption text that turned out not to precede a table: emit as prose.
            out.append(inline(pending_caption))
            pending_caption = None
        fig = re.match(r"^\*\*Figure (\d+)\s*[—-]\s*(.+?)\*\*(.*)$", ln)
        if fig:
            num, short, rest = fig.group(1), fig.group(2), fig.group(3)
            # The caption continues until the paragraph ends.
            tail = []
            i += 1
            while i < len(lines) and lines[i].strip():
                tail.append(lines[i])
                i += 1
            caption = " ".join((short + " " + rest + " " + " ".join(tail)).split())
            # Drop the markdown-only pointer to the SVG; LaTeX includes the PDF.
            # The filename contains a dot, so the backticked span must be matched
            # as a whole rather than up to the first period.
            caption = re.sub(r"\s*Supplied as\s*`[^`]*`\s*\.?", "", caption)
            caption = re.sub(r"\s*Supplied as\s+\S+\.\s", " ", caption)
            caption = re.sub(r"\s*Generated directly from the statistics file[^.]*\.", "", caption)
            caption = re.sub(r"\s*Renumbered from [^.]*\.", "", caption)
            caption = re.sub(r"\s{2,}", " ", caption).strip()
            stem = FIGURES.get(num, "figure%s" % num)
            out += [
                r"\begin{figure}[htbp]",
                r"\centering",
                r"\includegraphics[width=\textwidth]{figures/%s.pdf}" % stem,
                r"\caption{" + inline(caption) + "}" ,
                r"\label{fig%s}" % num,
                r"\end{figure}",
                "",
            ]
            continue
        if ln.startswith("#### "):
            out.append(r"\subsubsection{" + inline(ln[5:]) + "}")
        elif ln.startswith("### "):
            out.append(r"\subsection{" + inline(re.sub(r"^###\s*\d+\.\d+\s*", "", ln)) + "}")
        elif ln.startswith("## "):
            out.append(r"\section{" + inline(re.sub(r"^##\s*\d+\.\s*", "", ln)) + "}")
        elif ln.strip() == "---":
            pass
        elif re.match(r"^[-*] ", ln):
            items = []
            while i < len(lines) and re.match(r"^[-*] ", lines[i]):
                items.append(r"\item " + inline(lines[i][2:]))
                i += 1
            out += [r"\begin{itemize}"] + items + [r"\end{itemize}", ""]
            continue
        elif ln.strip():
            out.append(inline(ln))
        else:
            out.append("")
        i += 1
    return "\n".join(out), tables


def field(raw, name):
    mo = re.search(r"\*\*" + name + r"\.\*\*\s*(.*?)(?=\n\*\*|\Z)", raw, re.S)
    return " ".join(mo.group(1).split()) if mo else ""


def springer_declarations(raw):
    """Springer requires these headings, in this order, under one section."""
    fields = [
        ("Competing interests", field(raw, "Competing interests")),
        ("Funding", field(raw, "Funding")),
        ("Ethics approval and consent to participate", field(raw, "Ethics approval")),
        ("Data availability", field(raw, "Data availability")),
        ("Code availability",
         "The evaluation harness, both test suites, the security-property rule set, "
         "the seeded-defect register and the pre-registration are published with the "
         "artifact cited under Data availability."),
        ("Author contributions", field(raw, "Author contributions")),
        ("Use of generative AI", field(raw, "AI assistance disclosure")),
    ]
    out = [r"\section*{Statements and Declarations}", ""]
    for name, val in fields:
        out += [r"\subsection*{" + name + "}", inline(val) if val else "", ""]
    return "\n".join(out)


body_tex, n_body_tables = convert(body)
appendix_tex, n_appx_tables = convert(appendix)

tex = (TEMPLATE.read_text(encoding="utf-8")
       .replace("__ABSTRACT__", inline(abstract))
       .replace("__KEYWORDS__", ", ".join(inline(k) for k in keywords))
       .replace("__BODY__", body_tex)
       .replace("__APPENDIX__", appendix_tex)
       .replace("__DECLARATIONS__", springer_declarations(declarations)))

OUT.write_text(tex, encoding="utf-8")

print("written %s" % OUT)
print("  abstract words    %d" % len(abstract.split()))
print("  keywords          %d" % len(keywords))
print("  sections          %d" % tex.count("\\section{"))
print("  subsections       %d" % tex.count("\\subsection{"))
print("  tables            %d  (body %d, appendix %d)"
      % (n_body_tables + n_appx_tables, n_body_tables, n_appx_tables))
print("  citep             %d" % tex.count("\\citep{"))

leftover = re.findall(r"\[[A-Z][^\]\n]{0,45}\d{4}[^\]\n]{0,20}\]", tex)
print("  unconverted cites %d" % len(leftover))
for l in leftover[:6]:
    print("     " + l)

nonascii = sorted(set(ch for ch in tex if ord(ch) > 127))
print("  non-ascii chars   %d  %s"
      % (len(nonascii), " ".join("U+%04X %s" % (ord(c), c) for c in nonascii[:12])))

md_left = re.findall(r"\*\*[^*\n]+\*\*|(?<!\w)_[^_\n]+_(?!\w)", tex)
print("  unconverted md    %d" % len(md_left))
