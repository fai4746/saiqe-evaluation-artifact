#!/usr/bin/env python3
"""
Figure 2 — per-module paired outcomes, Arm A against Arm B.

Drawn directly from results/statistics.json so the figure cannot disagree with
the table. Emits SVG with no external dependencies.

The panel that matters is the contrast between the two: detection separates the
arms cleanly, covered mutation score does not. Plotting them side by side is
the argument of §7.5 in one image.

Usage: python harness/bin/make_figure5.py [output.svg]
"""

import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
STATS = json.loads((ROOT / "results" / "statistics.json").read_text())

OUT = pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "results" / "figure5_paired_outcomes.svg"

PANELS = [
    ("M01", "Seeded-defect detection rate", "detection"),
    ("M04c", "Mutation score, covered code", "covered_msi"),
]

W, H = 980, 470
PAD_L, PAD_T = 74, 92
PANEL_W, PANEL_H = 380, 260
GAP = 96

modules = STATS["modules"]
vec = STATS["per_module_vectors"]
res = {r["metric"]: r for r in STATS["results"]}

esc = lambda s: str(s).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
p = []
add = p.append

add(f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" '
    f'font-family="Segoe UI, Helvetica, Arial, sans-serif">')
add(f'<rect width="{W}" height="{H}" fill="#ffffff"/>')
add(f'<text x="40" y="38" font-size="17" font-weight="600" fill="#171717">'
    f'Figure 2 — Per-module outcomes, baseline against SAIQE (n = {len(modules)} modules)</text>')
add('<text x="40" y="60" font-size="12" fill="#595959">'
    'Each line is one module. The measure that separates the arms is detection, not mutation score.</text>')

for idx, (mid, title, key) in enumerate(PANELS):
    x0 = PAD_L + idx * (PANEL_W + GAP)
    y0 = PAD_T
    r = res[mid]

    add(f'<text x="{x0}" y="{y0 - 22}" font-size="13" font-weight="600" fill="#262626">{esc(title)}</text>')
    sig = "significant" if r.get("significant") else "not significant"
    colour = "#1a7f4b" if r.get("significant") else "#a33"
    add(f'<text x="{x0}" y="{y0 - 6}" font-size="11" fill="{colour}">'
        f"Cliff's δ = {r['cliffs_delta']:+.2f} ({r['cliffs_magnitude']}), p = {r['p_value']:.4f} — {sig}</text>")

    # axes
    add(f'<line x1="{x0}" y1="{y0}" x2="{x0}" y2="{y0 + PANEL_H}" stroke="#c9c9c9" stroke-width="1"/>')
    add(f'<line x1="{x0}" y1="{y0 + PANEL_H}" x2="{x0 + PANEL_W}" y2="{y0 + PANEL_H}" stroke="#c9c9c9" stroke-width="1"/>')

    for pct in range(0, 101, 25):
        yy = y0 + PANEL_H - (pct / 100) * PANEL_H
        add(f'<line x1="{x0}" y1="{yy:.1f}" x2="{x0 + PANEL_W}" y2="{yy:.1f}" stroke="#eeeeee" stroke-width="1"/>')
        add(f'<text x="{x0 - 10}" y="{yy + 4:.1f}" font-size="10" fill="#8c8c8c" text-anchor="end">{pct}%</text>')

    xa, xb = x0 + 96, x0 + PANEL_W - 96
    add(f'<text x="{xa}" y="{y0 + PANEL_H + 22}" font-size="12" fill="#404040" text-anchor="middle">Arm A</text>')
    add(f'<text x="{xb}" y="{y0 + PANEL_H + 22}" font-size="12" fill="#404040" text-anchor="middle">Arm B</text>')

    ya = lambda v: y0 + PANEL_H - v * PANEL_H

    for i, m in enumerate(modules):
        a, b = vec["A"][key][i], vec["B"][key][i]
        if a is None or b is None:
            continue
        up = b > a
        stroke = "#2f6f9f" if up else "#b5651d"
        dash = "" if abs(b - a) > 0.001 else ' stroke-dasharray="3 3"'
        add(f'<line x1="{xa}" y1="{ya(a):.1f}" x2="{xb}" y2="{ya(b):.1f}" '
            f'stroke="{stroke}" stroke-width="1.6" opacity="0.72"{dash}/>')
        add(f'<circle cx="{xa}" cy="{ya(a):.1f}" r="3.4" fill="#ffffff" stroke="{stroke}" stroke-width="1.6"/>')
        add(f'<circle cx="{xb}" cy="{ya(b):.1f}" r="3.4" fill="{stroke}"/>')

    # medians
    for xx, arm in ((xa, "A"), (xb, "B")):
        vals = sorted(v for v in vec[arm][key] if v is not None)
        med = vals[len(vals) // 2] if len(vals) % 2 else (vals[len(vals) // 2 - 1] + vals[len(vals) // 2]) / 2
        add(f'<line x1="{xx - 30}" y1="{ya(med):.1f}" x2="{xx + 30}" y2="{ya(med):.1f}" '
            f'stroke="#171717" stroke-width="2.4"/>')
        add(f'<text x="{xx + 36}" y="{ya(med) + 4:.1f}" font-size="10" fill="#171717">med {med * 100:.1f}%</text>')

    add(f'<text x="{x0}" y="{y0 + PANEL_H + 42}" font-size="10.5" fill="#8c8c8c">'
        f'improved {r["improved_in"]} · regressed {r["regressed_in"]} · tied {r["tied_in"]}</text>')

add(f'<text x="40" y="{H - 30}" font-size="10.5" fill="#8c8c8c">'
    'Blue: module improved under SAIQE. Orange: module regressed. Dashed: unchanged. '
    'Heavy bar: median. Wilcoxon signed-rank, two-sided, α = 0.05.</text>')
add(f'<text x="40" y="{H - 14}" font-size="10.5" fill="#8c8c8c">'
    'Generated from results/statistics.json by harness/bin/make_figure5.py — the figure cannot disagree with Table 5.</text>')
add('</svg>')

OUT.write_text("\n".join(p), encoding="utf-8")
print(f"written {OUT}")
for mid, title, key in PANELS:
    r = res[mid]
    print(f"  {mid:5} {title:34} δ={r['cliffs_delta']:+.3f}  p={r['p_value']:.4f}  "
          f"improved {r['improved_in']}/regressed {r['regressed_in']}/tied {r['tied_in']}")
