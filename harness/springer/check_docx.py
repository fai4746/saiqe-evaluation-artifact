#!/usr/bin/env python3
"""
Structural check on the generated .docx.

Neither LibreOffice nor poppler is installed on this machine, so the document
cannot be rendered and inspected. This reads the OOXML directly instead and
verifies the things that would otherwise only be visible on opening it in Word.

It is not a substitute for looking at the file. Open it before submitting.

Usage: python check_docx.py <file.docx>
"""
import pathlib
import re
import sys
import zipfile

path = pathlib.Path(sys.argv[1])
z = zipfile.ZipFile(path)
doc = z.read("word/document.xml").decode("utf-8")

W = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
problems = 0

media = [n for n in z.namelist() if n.startswith("word/media/")]
paras = doc.count("<w:p ") + doc.count("<w:p>")
tables = doc.count("<w:tbl>")
drawings = doc.count("<w:drawing>")
text = "".join(re.findall(r"<w:t[^>]*>([^<]*)</w:t>", doc))

print("file            %s (%.2f MB)" % (path.name, path.stat().st_size / 1e6))
print("paragraphs      %d" % paras)
print("tables          %d" % tables)
print("drawings        %d   media files %d  %s"
      % (drawings, len(media), ", ".join(sorted(p.split("/")[-1] for p in media))))
print("body words      %d" % len(text.split()))

# ── every table must be well formed ───────────────────────────
row_counts = []
for tbl in re.findall(r"<w:tbl>.*?</w:tbl>", doc, re.S):
    rows = re.findall(r"<w:tr[ >].*?</w:tr>", tbl, re.S)
    widths = {len(re.findall(r"<w:tc>", r)) for r in rows}
    row_counts.append((len(rows), widths))
    if len(widths) > 1:
        print("  RAGGED TABLE   rows have differing cell counts: %s" % widths)
        problems += 1
print("table shapes    %s" % ", ".join("%dx%s" % (r, list(w)[0] if len(w) == 1 else w)
                                       for r, w in row_counts))

# ── content that must be present ──────────────────────────────
required = [
    ("Abstract", "Abstract heading"),
    ("Keywords", "Keywords"),
    ("Statements and Declarations", "Springer declarations section"),
    ("Competing interests", "competing interests"),
    ("Data availability", "data availability"),
    ("Use of generative AI", "AI disclosure"),
    ("References", "references heading"),
    ("96.8", "headline detection figure"),
    ("1.000", "the mutation null p-value"),
    ("3.85", "precision figure"),
]
for needle, label in required:
    if needle not in text:
        print("  MISSING        %s (%r)" % (label, needle))
        problems += 1

# ── citations must have been converted ────────────────────────
bracketed = re.findall(r"\[[A-Z][^\]]{0,40}\d{4}[^\]]{0,15}\]", text)
if bracketed:
    print("  UNCONVERTED    %d bracketed citations remain: %s" % (len(bracketed), bracketed[:3]))
    problems += 1
paren = len(re.findall(r"\([A-Z][^()]{0,45}\d{4}[a-z]?\)", text))
print("citations       %d parenthetical, %d bracketed" % (paren, len(bracketed)))

# ── stray markdown ────────────────────────────────────────────
for pat, label in ((r"\*\*", "bold markers"), (r"^#{2,4}\s", "heading markers"), (r"\|\s*---", "table pipes")):
    hits = re.findall(pat, text, re.M)
    if hits:
        print("  STRAY MD       %s x%d" % (label, len(hits)))
        problems += 1

confirm = sorted(set(re.findall(r"CONFIRM-[a-z-]+", text)))
print("placeholders    %d: %s" % (len(confirm), ", ".join(confirm)))

print("")
print("PROBLEMS: %d" % problems)
print("NOTE: this reads the XML. It cannot see layout, pagination, or whether a")
print("      table overruns the page. Open the file in Word before submitting.")
sys.exit(1 if problems else 0)
