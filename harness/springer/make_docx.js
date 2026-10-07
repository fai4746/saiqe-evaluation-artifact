/**
 * Build the Springer Word (.docx) manuscript from the markdown master.
 *
 * Springer Nature accepts Word or LaTeX for Software Quality Journal. This is
 * the Word route, generated from the same master as the LaTeX so the two
 * cannot disagree.
 *
 * Conventions followed: numbered sections, table captions above the table,
 * figure captions below the figure, parenthetical author-year citations, and a
 * Statements and Declarations section with Springer's headings in their order.
 *
 * Usage: node make_docx.js <master.md> <figuresDir> <out.docx>
 */
const fs = require("fs");
const path = require("path");
const D = require(path.join(__dirname, "..", "..", "node_modules", "docx"));

const [, , MASTER, FIGDIR, OUT] = process.argv;

// The master is stored with CRLF endings; normalise, or every line-anchored
// regex below fails silently.
const src = fs.readFileSync(MASTER, "utf8").replace(/\r\n?/g, "\n");

const TWIP = 1440;
const PAGE_W = 12240, PAGE_H = 15840;            // US Letter, DXA
const MARGIN = TWIP;                              // 1 inch
const TEXT_W = PAGE_W - 2 * MARGIN;

// Figure number in the manuscript -> file stem on disk
const FIGURES = { "1": "fig1_saiqe_framework", "2": "fig2_paired_outcomes" };

// ── split the master ──────────────────────────────────────────
const beforeRefs = src.split("## References")[0];
const refsBlock = src.split("## References")[1] || "";

const absM = beforeRefs.match(/## Abstract\s*\n([\s\S]*?)\n\*\*Keywords:\*\*\s*(.*)\n/);
const abstract = absM ? absM[1].trim().replace(/\s+/g, " ") : "";
const keywords = absM ? absM[2].trim() : "";

let body = absM ? beforeRefs.slice(absM.index + absM[0].length) : beforeRefs;
body = body.split("## Declarations")[0].split("## Appendix C")[0];

const declM = src.match(/## Declarations\s*\n([\s\S]*?)\n---/);
const declarations = declM ? declM[1] : "";
const appxM = src.match(/## Appendix C[^\n]*\n([\s\S]*?)\n---/);
const appendix = appxM ? appxM[1] : "";

// References: keep the formatted entries, drop the editorial annotations.
const references = refsBlock
  .split("### Verification notes")[0]
  .split("\n")
  .map((l) => l.trim())
  .filter((l) => /^[A-Z].*\(\d{4}[a-z]?\)\./.test(l) || /^ISO\/IEC|^NIST|^MITRE|^European|^OWASP/.test(l))
  .map((l) => l.replace(/\s*\[[^\]]*\]\s*$/, "").replace(/\*/g, "").trim())
  .filter(Boolean);

// ── inline markdown -> TextRun[] ──────────────────────────────
function runs(md, base = {}) {
  const out = [];
  // Bracketed author-year citations become parenthetical, per Springer basic.
  md = md.replace(/\[([^\]\[]*?\d{4}[a-z]?(?:\s*,\s*preprint)?)\]/g, "($1)");
  const re = /(\*\*[^*]+\*\*|\*[^*\n]+\*|`[^`]+`)/g;
  let last = 0, m;
  while ((m = re.exec(md)) !== null) {
    if (m.index > last) out.push(new D.TextRun({ ...base, text: md.slice(last, m.index) }));
    const tok = m[0];
    if (tok.startsWith("**")) out.push(new D.TextRun({ ...base, text: tok.slice(2, -2), bold: true }));
    else if (tok.startsWith("`")) out.push(new D.TextRun({ ...base, text: tok.slice(1, -1), font: "Consolas", size: 18 }));
    else out.push(new D.TextRun({ ...base, text: tok.slice(1, -1), italics: true }));
    last = m.index + tok.length;
  }
  if (last < md.length) out.push(new D.TextRun({ ...base, text: md.slice(last) }));
  return out.length ? out : [new D.TextRun({ ...base, text: "" })];
}

const para = (md, opts = {}) =>
  new D.Paragraph({ children: runs(md), spacing: { after: 120, line: 276 }, ...opts });

const caption = (text, before = 120) =>
  new D.Paragraph({
    children: runs(text, { size: 18 }),
    spacing: { before, after: 80 },
  });

// ── tables ────────────────────────────────────────────────────
function cellsOf(row) {
  return row.trim().replace(/^\||\|$/g, "").split("|").map((c) => c.trim());
}

function buildTable(block) {
  const header = cellsOf(block[0]);
  const rows = block.slice(2).map(cellsOf);
  const n = header.length;
  const colW = Math.floor(TEXT_W / n);
  const widths = Array(n).fill(colW);

  const mk = (texts, isHeader) =>
    new D.TableRow({
      tableHeader: isHeader,
      children: texts.map(
        (t, i) =>
          new D.TableCell({
            width: { size: widths[i], type: D.WidthType.DXA },
            shading: isHeader
              ? { type: D.ShadingType.CLEAR, fill: "EFEFEF" }
              : undefined,
            margins: { top: 60, bottom: 60, left: 80, right: 80 },
            children: [
              new D.Paragraph({
                children: runs(t || "", { size: 17, bold: isHeader || undefined }),
                spacing: { after: 0 },
              }),
            ],
          })
      ),
    });

  return new D.Table({
    columnWidths: widths,
    width: { size: TEXT_W, type: D.WidthType.DXA },
    rows: [mk(header, true), ...rows.map((r) => mk([...r, ...Array(n).fill("")].slice(0, n), false))],
  });
}

// ── figures ───────────────────────────────────────────────────
function buildFigure(num, captionText) {
  const stem = FIGURES[num];
  const file = path.join(FIGDIR, stem + ".png");
  if (!fs.existsSync(file)) return [para("[figure missing: " + stem + "]")];
  // Scale to the text width, preserving aspect from the 300 dpi render.
  const dims = { fig1_saiqe_framework: [3250, 2188], fig2_paired_outcomes: [3063, 1519] }[stem];
  const wPt = 468;                                  // 6.5 inch text width, in points
  const hPt = Math.round((dims[1] / dims[0]) * wPt);
  return [
    new D.Paragraph({
      alignment: D.AlignmentType.CENTER,
      spacing: { before: 200, after: 80 },
      children: [
        new D.ImageRun({
          type: "png",
          data: fs.readFileSync(file),
          transformation: { width: wPt, height: hPt },
        }),
      ],
    }),
    caption(captionText, 0),
  ];
}

// ── block conversion ──────────────────────────────────────────
/**
 * Join hard-wrapped prose into one line per paragraph.
 *
 * The master is wrapped at ~78 columns. Without this, every wrapped line
 * becomes its own Word paragraph, and an inline span that opens on one line and
 * closes on the next -- **bold like this** -- never matches, leaving both
 * markers visible in the output. Structural lines are left alone.
 */
function reflow(md) {
  const out = [];
  let buf = [];
  const flush = () => {
    if (buf.length) { out.push(buf.map((x) => x.trim()).join(" ")); buf = []; }
  };
  for (const ln of md.split("\n")) {
    const s = ln.trim();
    const structural =
      !s || /^[#|>]/.test(s) || /^[-*] /.test(s) || s.startsWith("---") || /^\d+\.\s/.test(s);
    if (structural) { flush(); out.push(ln); } else { buf.push(ln); }
  }
  flush();
  return out.join("\n");
}

function convert(md) {
  const out = [];
  const lines = reflow(md).split("\n");
  let pendingCaption = null;

  for (let i = 0; i < lines.length; i++) {
    const ln = lines[i];

    const fig = ln.match(/^\*\*Figure (\d+)\s*[—-]\s*(.+?)\*\*(.*)$/);
    if (fig) {
      let cap = fig[2] + " " + fig[3];
      while (i + 1 < lines.length && lines[i + 1].trim()) cap += " " + lines[++i].trim();
      cap = cap.replace(/\s*Supplied as\s*`[^`]*`\s*\.?/g, "").replace(/\s+/g, " ").trim();
      out.push(...buildFigure(fig[1], "Fig. " + fig[1] + "  " + cap));
      continue;
    }

    const cap = ln.match(/^\*\*(Table\s*[A-Z]?\d+[a-z]?\s*[—-]\s*.+?)\*\*\s*(.*)$/);
    if (cap) {
      let text = cap[1] + " " + cap[2];
      while (i + 1 < lines.length && lines[i + 1].trim() && !lines[i + 1].trim().startsWith("|"))
        text += " " + lines[++i].trim();
      pendingCaption = text.replace(/\s+/g, " ").trim();
      continue;
    }

    if (ln.trim().startsWith("|") && lines[i + 1] && /^\s*\|[\s:|-]+\|\s*$/.test(lines[i + 1])) {
      const block = [];
      while (i < lines.length && lines[i].trim().startsWith("|")) block.push(lines[i++]);
      i--;
      if (pendingCaption) { out.push(caption(pendingCaption, 200)); pendingCaption = null; }
      out.push(buildTable(block));
      out.push(new D.Paragraph({ text: "", spacing: { after: 120 } }));
      continue;
    }

    if (pendingCaption && ln.trim()) { out.push(para(pendingCaption)); pendingCaption = null; }

    if (ln.startsWith("### ")) {
      out.push(new D.Paragraph({
        children: runs(ln.slice(4), { bold: true, size: 22 }),
        spacing: { before: 240, after: 100 },
        outlineLevel: 1,
      }));
    } else if (ln.startsWith("## ")) {
      out.push(new D.Paragraph({
        children: runs(ln.slice(3), { bold: true, size: 26 }),
        spacing: { before: 320, after: 120 },
        outlineLevel: 0,
      }));
    } else if (ln.trim() === "---") {
      // section rule in markdown; nothing in Word
    } else if (/^[-*] /.test(ln)) {
      while (i < lines.length && /^[-*] /.test(lines[i])) {
        out.push(new D.Paragraph({
          children: runs(lines[i].slice(2)),
          bullet: { level: 0 },
          spacing: { after: 80 },
        }));
        i++;
      }
      i--;
    } else if (ln.trim()) {
      out.push(para(ln));
    }
  }
  return out;
}

// ── declarations ──────────────────────────────────────────────
function declField(name) {
  const m = declarations.match(new RegExp("\\*\\*" + name + "\\.\\*\\*\\s*([\\s\\S]*?)(?=\\n\\*\\*|$)"));
  return m ? m[1].replace(/\s+/g, " ").trim() : "";
}

const DECL = [
  ["Competing interests", declField("Competing interests")],
  ["Funding", declField("Funding")],
  ["Ethics approval and consent to participate", declField("Ethics approval")],
  ["Data availability", declField("Data availability")],
  ["Code availability",
    "The evaluation harness, both test suites, the security-property rule set, the seeded-defect " +
    "register and the pre-registration are published with the artifact cited under Data availability."],
  ["Author contributions", declField("Author contributions")],
  ["Use of generative AI", declField("AI assistance disclosure")],
];

// ── assemble ──────────────────────────────────────────────────
const children = [];

children.push(new D.Paragraph({
  children: runs("Security-Integrated AI-Driven Quality Engineering for Reliable, Compliant, and Trusted Digital Systems",
    { bold: true, size: 32 }),
  spacing: { after: 240 },
}));

children.push(new D.Paragraph({
  children: runs("S A Mohaiminul Islam¹·²  ·  Ankur Sarkar³", { size: 24 }),
  spacing: { after: 60 },
}));
children.push(new D.Paragraph({
  children: runs("¹ Washington University of Science and Technology, Alexandria, Virginia, USA   ² Agile1Tech, Fairfax, Virginia, USA   ³ University of the Potomac, Washington, D.C., USA", { size: 20, italics: true }),
  spacing: { after: 60 },
}));
children.push(new D.Paragraph({
  children: runs("Corresponding author: S A Mohaiminul Islam, mohaiminulbd271@gmail.com, ORCID 0009-0004-0332-9271   ·   Ankur Sarkar, ankursylhet@gmail.com, ORCID 0009-0009-4381-0787", { size: 20 }),
  spacing: { after: 300 },
}));

children.push(new D.Paragraph({ children: runs("Abstract", { bold: true, size: 24 }), spacing: { after: 120 } }));
children.push(para(abstract));
children.push(new D.Paragraph({
  children: [new D.TextRun({ text: "Keywords  ", bold: true }), ...runs(keywords)],
  spacing: { before: 160, after: 320 },
}));

children.push(...convert(body));

children.push(new D.Paragraph({
  children: runs("Appendix A  Synthetic release series (illustrative)", { bold: true, size: 26 }),
  spacing: { before: 400, after: 120 },
  pageBreakBefore: true,
}));
children.push(...convert(appendix));

children.push(new D.Paragraph({
  children: runs("Statements and Declarations", { bold: true, size: 26 }),
  spacing: { before: 400, after: 120 },
}));
for (const [name, val] of DECL) {
  children.push(new D.Paragraph({ children: runs(name, { bold: true, size: 22 }), spacing: { before: 180, after: 60 } }));
  children.push(para(val || ""));
}

children.push(new D.Paragraph({
  children: runs("References", { bold: true, size: 26 }),
  spacing: { before: 400, after: 120 },
  pageBreakBefore: true,
}));
for (const r of references) {
  children.push(new D.Paragraph({
    children: runs(r, { size: 20 }),
    spacing: { after: 100 },
    indent: { left: 360, hanging: 360 },
  }));
}

const doc = new D.Document({
  creator: "SAIQE manuscript pipeline",
  title: "Security-Integrated AI-Driven Quality Engineering",
  styles: { default: { document: { run: { font: "Times New Roman", size: 22 } } } },
  sections: [{
    properties: {
      page: { size: { width: PAGE_W, height: PAGE_H }, margin: { top: MARGIN, bottom: MARGIN, left: MARGIN, right: MARGIN } },
    },
    children,
  }],
});

D.Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(OUT, buf);
  const tables = children.filter((c) => c instanceof D.Table).length;
  const imgs = children.filter((c) => JSON.stringify(Object.keys(c)).length && c.constructor === D.Paragraph &&
                (c.root || []).some((r) => r && r.constructor && r.constructor.name === "ImageRun")).length;
  console.log("written " + OUT);
  console.log("  bytes        " + buf.length);
  console.log("  blocks       " + children.length);
  console.log("  tables       " + tables);
  console.log("  images       " + imgs);
  console.log("  references   " + references.length);
  console.log("  abstract     " + abstract.split(/\s+/).length + " words");
});
