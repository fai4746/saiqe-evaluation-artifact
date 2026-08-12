#!/usr/bin/env python3
"""
Paired analysis across the 11 modules — the analysis plan fixed in
docs/PRE-REGISTRATION.md §7.

Wilcoxon signed-rank, two-sided, alpha = 0.05, with Cliff's delta and
Vargha-Delaney A12 reported alongside every p value. With n = 11 the study is
powered for large effects only; that is stated with the results, not discovered
in review.

Reads results/*.json produced by the harness. Writes results/statistics.json.
Adds no comparison that was not pre-specified.
"""

import json
import pathlib
import sys

import numpy as np
from scipy import stats

ROOT = pathlib.Path(__file__).resolve().parents[2]
RESULTS = ROOT / "results"
ALPHA = 0.05

# Pre-specified comparisons only. Adding to this list after seeing results is
# p-hacking; anything added later must be labelled exploratory.
COMPARISONS = [
    ("M01", "Seeded-defect detection rate", "detection", True),
    ("M04", "Mutation score (MSI)", "msi", True),
    ("M04c", "Mutation score, covered code", "covered_msi", True),
    ("M05", "Line coverage", "line_coverage", True),
]


def cliffs_delta(a, b):
    """P(b > a) - P(a > b). Range -1..1."""
    a, b = np.asarray(a, float), np.asarray(b, float)
    gt = sum((y > x) for x in a for y in b)
    lt = sum((y < x) for x in a for y in b)
    return (gt - lt) / (len(a) * len(b))


def magnitude(d):
    """Romano et al. thresholds."""
    d = abs(d)
    if d < 0.147:
        return "negligible"
    if d < 0.330:
        return "small"
    if d < 0.474:
        return "medium"
    return "large"


def a12(a, b):
    a, b = np.asarray(a, float), np.asarray(b, float)
    gt = sum((y > x) for x in a for y in b)
    eq = sum((y == x) for x in a for y in b)
    return (gt + 0.5 * eq) / (len(a) * len(b))


def load_vectors():
    """Per-module paired values, in a fixed module order."""
    det = {
        arm: json.loads((RESULTS / f"detection_arm_{arm}.json").read_text())
        for arm in ("A", "B")
    }
    quality = json.loads((RESULTS / "quality_by_module.json").read_text())

    modules = sorted(det["A"]["summary"]["by_module"].keys())
    vectors = {}

    for arm in ("A", "B"):
        by_mod = det[arm]["summary"]["by_module"]
        q = quality["arms"][arm]["by_module"]
        vectors[arm] = {
            "detection": [by_mod[m]["rate"] for m in modules],
            "msi": [q[m]["msi"] for m in modules],
            "covered_msi": [q[m]["covered_msi"] for m in modules],
            "line_coverage": [q[m]["line_coverage"] for m in modules],
        }
    return modules, vectors


def analyse(name, label, a_raw, b_raw, higher_is_better):
    pairs = [(x, y) for x, y in zip(a_raw, b_raw) if x is not None and y is not None]
    if len(pairs) < 6:
        return {"metric": name, "label": label, "status": "too few complete pairs",
                "n": len(pairs)}

    a = np.array([p[0] for p in pairs], float)
    b = np.array([p[1] for p in pairs], float)
    diffs = b - a
    non_tied = int(np.sum(diffs != 0))

    out = {
        "metric": name,
        "label": label,
        "n_pairs": len(pairs),
        "n_non_tied": non_tied,
        "arm_a_median": round(float(np.median(a)), 4),
        "arm_a_iqr": [round(float(np.percentile(a, 25)), 4), round(float(np.percentile(a, 75)), 4)],
        "arm_b_median": round(float(np.median(b)), 4),
        "arm_b_iqr": [round(float(np.percentile(b, 25)), 4), round(float(np.percentile(b, 75)), 4)],
        "median_difference": round(float(np.median(diffs)), 4),
        "improved_in": int(np.sum(diffs > 0)),
        "regressed_in": int(np.sum(diffs < 0)),
        "tied_in": int(np.sum(diffs == 0)),
    }

    if non_tied == 0:
        out["wilcoxon"] = None
        out["p_value"] = None
        out["significant"] = None
    else:
        res = stats.wilcoxon(a, b, zero_method="wilcox", alternative="two-sided",
                             method="exact" if non_tied <= 25 else "approx")
        out["wilcoxon_w"] = round(float(res.statistic), 2)
        out["p_value"] = round(float(res.pvalue), 5)
        out["significant"] = bool(res.pvalue < ALPHA)

    d = cliffs_delta(a, b)
    out["cliffs_delta"] = round(float(d), 4)
    out["cliffs_magnitude"] = magnitude(d)
    out["vargha_delaney_a12"] = round(float(a12(a, b)), 4)
    out["direction"] = ("improvement" if (np.median(diffs) > 0) == higher_is_better
                        else "regression" if np.median(diffs) != 0 else "no change")
    return out


def main():
    modules, vectors = load_vectors()

    print("SAIQE paired analysis — n = %d modules" % len(modules))
    print("=" * 76)

    results = []
    for name, label, key, hib in COMPARISONS:
        r = analyse(name, label, vectors["A"][key], vectors["B"][key], hib)
        results.append(r)

        if "arm_a_median" not in r:
            print("\n%s %s\n  %s" % (name, label, r["status"]))
            continue

        print("\n%s  %s" % (name, label))
        print("  n pairs              %d (%d non-tied)" % (r["n_pairs"], r["n_non_tied"]))
        print("  Arm A median (IQR)   %.3f (%.3f–%.3f)" % (r["arm_a_median"], *r["arm_a_iqr"]))
        print("  Arm B median (IQR)   %.3f (%.3f–%.3f)" % (r["arm_b_median"], *r["arm_b_iqr"]))
        print("  median difference    %+.3f" % r["median_difference"])
        print("  improved / regressed / tied   %d / %d / %d"
              % (r["improved_in"], r["regressed_in"], r["tied_in"]))
        if r.get("p_value") is not None:
            print("  Wilcoxon W = %.1f, p = %.4f  [%s at alpha=%.2f]"
                  % (r["wilcoxon_w"], r["p_value"],
                     "significant" if r["significant"] else "not significant", ALPHA))
        print("  Cliff's delta        %+.3f (%s)" % (r["cliffs_delta"], r["cliffs_magnitude"]))
        print("  Vargha-Delaney A12   %.3f" % r["vargha_delaney_a12"])
        print("  direction            %s" % r["direction"])

    payload = {
        "alpha": ALPHA,
        "n_modules": len(modules),
        "modules": modules,
        "power_note": ("With n = %d modules the study is powered to detect large effects only; "
                       "smaller differences may be present but undetected." % len(modules)),
        "pre_specified": [c[0] for c in COMPARISONS],
        "results": results,
        "per_module_vectors": {arm: vectors[arm] for arm in ("A", "B")},
    }
    (RESULTS / "statistics.json").write_text(json.dumps(payload, indent=2))

    print("\n" + "=" * 76)
    print(payload["power_note"])
    print("written results/statistics.json")


if __name__ == "__main__":
    sys.exit(main())
