# Springer submission set — Software Quality Journal

Generated from the markdown master by `harness/springer/`. **Do not hand-edit
the `.tex` or `.bib`** — edit `manuscripts/06_manuscript_draft.md` and
regenerate, or the two will drift and the numbers will stop agreeing with the
raw results.

```bash
# LaTeX route
python harness/springer/make_bib.py     <master.md> sn-bibliography.bib citemap.json
python harness/springer/md_to_snjnl.py  <master.md> citemap.json sn-article.tex sn-article.tex.template
python harness/springer/check_tex.py    sn-article.tex

# Word route
node   harness/springer/make_docx.js    <master.md> figures/ sn-manuscript.docx
python harness/springer/check_docx.py   sn-manuscript.docx
```

## Which one to submit

Springer Nature accepts **either** for Software Quality Journal. Both are here,
both generated from the same master, so they say the same thing.

- **`sn-manuscript.docx`** is the easier route from where you are: you can open
  it now, fill the author block, and send it to a co-author with track changes.
  No TeX toolchain needed.
- **`sn-article.tex`** produces better typesetting, especially for the wide
  tables, and is what Springer will prefer if you are comfortable with Overleaf.

Submit one, not both. Whichever you pick, the other stays useful as a reading
copy.

## Contents

| File | What it is |
|---|---|
| `sn-article.tex` | The manuscript in Springer's `sn-jnl` class, `sn-basic` (author–year) reference style |
| `sn-bibliography.bib` | 43 entries, generated from the reference list. Every one of the 75 in-text `\citep` calls resolves — checked, not assumed |
| `figures/fig1_saiqe_framework.pdf` | Figure 1, converted from SVG. All 76 text runs verified present after conversion |
| `figures/fig2_paired_outcomes.pdf` | Figure 2, likewise; 28 text runs verified |
| `sn-manuscript.docx` | The Word route. 7 tables, both figures embedded at 300 dpi, 71 parenthetical citations, Statements and Declarations |
| `figures/*.png` | 300 dpi renders, embedded in the .docx |

The Word reference list carries 56 entries against the `.bib`'s 43. That is
correct, not a discrepancy: the `.bib` holds only works cited by author-year
key, while standards and regulatory instruments are cited by designation in
prose and belong in a Word reference list but have no BibTeX key.

## Compiling

No TeX distribution is installed on the authoring machine, so **this has not
been compiled** — only structurally validated (balanced environments and braces,
no unreplaced tokens, table column arity, no stray markdown). Treat the first
compile as a proofreading pass.

Easiest route: upload `sn-article.tex`, `sn-bibliography.bib` and `figures/` to
Overleaf and select **Springer Nature LaTeX Template**. `sn-jnl.cls` and
`sn-basic.bst` ship with it. Locally you would need those two files alongside,
then `pdflatex → bibtex → pdflatex → pdflatex`.

## Before you compile: seven fields

The author block carries seven `CONFIRM-` placeholders. Springer's Editorial
Manager will not accept a submission without them:

```
\author*[1]{\fnm{CONFIRM-given-name} \sur{CONFIRM-family-name}}
\affil*[1]{\orgname{CONFIRM-organisation},
           \orgaddress{\street{CONFIRM-street}, \city{CONFIRM-city},
                       \postcode{CONFIRM-postcode}, \country{CONFIRM-country}}}
```

Add `\orcid{...}` to the author line if you have one — SQJ asks for it.

## What was changed to meet Springer requirements

- **Abstract cut from 462 to 247 words.** SQJ specifies 150–250. The original
  exceeded it by 85%.
- **Keywords cut from 10 to 6.** Springer asks for 4–6.
- **Three tables had no caption at all** — the pipeline walkthrough, the arms
  comparison, and the appendix table. Springer requires a caption on every
  table; all three were written and added to the master, so the markdown
  version now has them too.
- **Figures converted SVG → PDF.** Springer does not accept SVG.
- **Figures renumbered** 3→1 and 5→2 earlier, when the PRISMA figure was
  withdrawn; there are no numbering gaps.
- **`ISO/IEC/IEEE 15026`** was cited in author–year form but has no year in the
  reference list, so it would have compiled as `[?]`. It is now cited by
  designation in prose, which is the normal handling for a standard.
- **Declarations restructured** into Springer's required *Statements and
  Declarations* section with their headings in their order: competing
  interests, funding, ethics approval, data availability, code availability,
  author contributions, use of generative AI.

## Known limits of the structural checks

**Neither output has been opened.** No TeX distribution, no LibreOffice and no
poppler on the authoring machine, so nothing could be rendered or paginated.
`check_tex.py` and `check_docx.py` read the source and the OOXML respectively.
They verify structure, not appearance.

`check_docx.py` cannot see layout, pagination, or whether one of the six-column
tables overruns the page. **Open the .docx in Word before submitting.**

`check_tex.py` is not a compiler. It will not catch a missing package, an
overfull hbox, a float that will not place, or a `.bst` incompatibility. The
wide tables (Table 1 and Table 5 are 5–6 columns) use fixed-width `p{}` columns
and are the most likely thing to need manual width tuning on first compile.
