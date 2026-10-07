#!/usr/bin/env python3
"""
Build the blind replication kit for the second author.

WHY THIS IS NOT A COPY OF THE REPO: the seeded defects are visible in the
system under test as `defectActive('missing_csrf_token')` calls. Anyone who
reads the implementation has read the register, and the blinding is void.

So the kit carries specifications and API signatures only — no method bodies.
The replicating author writes specification-based tests against a documented
surface, which is both a stronger control and a realistic way to author tests.

Excluded from the kit, deliberately:
  register/seeded_defects.json     the ground truth
  register/provenance.json         names the model-authored units
  the SUT source                   leaks defect keys in defectActive() calls
  harness/tests/                   both existing suites
  results/                         every measured outcome
  docs/SECTION-7-evaluation.md     the results section

Usage: python harness/replication/build_kit.py <module-id> [dest]
"""
import json
import pathlib
import re
import shutil
import sys

ROOT = pathlib.Path(__file__).resolve().parents[2]
MODULE = (sys.argv[1] if len(sys.argv) > 1 else "MOD-09").upper()
DEST = pathlib.Path(sys.argv[2]) if len(sys.argv) > 2 else ROOT / "results" / "replication_kit"

modules = json.loads((ROOT / "register" / "modules.json").read_text(encoding="utf-8"))["modules"]
mod = next((m for m in modules if m["id"] == MODULE), None)
if mod is None:
    sys.exit("unknown module %s" % MODULE)

# Test budget per module, from the pre-registration.
BUDGET = {"MOD-01": 14, "MOD-02": 8, "MOD-03": 6, "MOD-04": 6, "MOD-05": 4,
          "MOD-06": 7, "MOD-07": 4, "MOD-08": 4, "MOD-09": 7, "MOD-10": 4,
          "MOD-11": 6}

SIG = re.compile(
    r"^\s*(?:(public|protected|private)\s+)?(?:(static)\s+)?function\s+(\w+)\s*\((.*?)\)\s*(?::\s*([^\s{]+))?",
    re.M | re.S)
CLASS = re.compile(r"^\s*(?:final\s+)?class\s+(\w+)", re.M)
DOC = re.compile(r"/\*\*(.*?)\*/\s*$", re.S)


def preceding_docblock(src, pos):
    """
    The docblock immediately above `pos`, or None.

    Must be anchored to the nearest `*/` with only whitespace between it and the
    declaration, and matched back to its own `/**`. A lazy regex over the whole
    preceding text spans from the first docblock in the file to the last `*/`,
    which drags method bodies into the output — that is how body text leaked
    into a signatures-only kit on the first attempt.
    """
    before = src[:pos]
    if not before.rstrip().endswith("*/"):
        return None
    close = before.rstrip().rfind("*/")
    open_ = before.rfind("/**", 0, close)
    if open_ == -1:
        return None
    inner = before[open_ + 3:close]
    if "/**" in inner or "*/" in inner:
        return None
    text = " ".join(l.strip().lstrip("*").strip() for l in inner.split("\n"))
    text = re.sub(r"\s+", " ", text).strip()
    return text or None


def class_body_span(src):
    """Character range of the class body, so globals declared after it are skipped."""
    cls = CLASS.search(src)
    if not cls:
        return 0, len(src)
    start = src.find("{", cls.end())
    if start == -1:
        return 0, len(src)
    depth = 0
    for i in range(start, len(src)):
        if src[i] == "{":
            depth += 1
        elif src[i] == "}":
            depth -= 1
            if depth == 0:
                return start, i
    return start, len(src)


def signatures(php_path):
    """Public API surface: class, method signatures, and docblocks. No bodies."""
    src = php_path.read_text(encoding="utf-8")
    cls = CLASS.search(src)
    lo, hi = class_body_span(src)
    out = ["class %s" % (cls.group(1) if cls else php_path.stem), "{"]
    for m in SIG.finditer(src):
        if not (lo <= m.start() <= hi):
            continue                      # free function outside the class
        vis, static, name, args, ret = m.groups()
        if vis == "private":
            continue                      # not part of the surface

        doc = preceding_docblock(src, m.start())
        # A docblock naming a seeded fault would hand over the register.
        if doc and "Defect:" not in doc:
            out.append("    /** %s */" % doc[:260])

        args = re.sub(r"\s+", " ", args).strip()
        out.append("    %s%sfunction %s(%s)%s;"
                   % ((vis or "public") + " ", (static + " ") if static else "",
                      name, args, (": " + ret) if ret else ""))
        out.append("")
    out.append("}")
    return "\n".join(out)


def main():
    if DEST.exists():
        shutil.rmtree(DEST, ignore_errors=True)
    (DEST / "specifications").mkdir(parents=True, exist_ok=True)
    (DEST / "api-surface").mkdir(parents=True, exist_ok=True)
    (DEST / "your-tests").mkdir(parents=True, exist_ok=True)

    for spec in ("SPEC-functional.md", "SPEC-security-properties.md"):
        shutil.copy2(ROOT / "docs" / spec, DEST / "specifications" / spec)

    units = mod.get("measured_units") or mod["primary_units"]
    # Include every unit the module's behaviour is reachable through.
    extra = {"MOD-09": ["core/Auth.php"]}.get(MODULE, [])
    emitted = []
    for rel in dict.fromkeys(list(units) + extra):
        src = ROOT / "sut" / rel
        if not src.exists():
            continue
        if "controllers" in rel:
            continue                     # request-bound; not directly testable
        name = pathlib.Path(rel).stem + ".api.txt"
        (DEST / "api-surface" / name).write_text(signatures(src), encoding="utf-8")
        emitted.append(name)

    budget = BUDGET.get(MODULE, 6)
    frs = sorted(set(re.findall(r"\*\*(FR-%s\.\d+)\*\*" % MODULE.split("-")[1],
                                (ROOT / "docs" / "SPEC-functional.md").read_text(encoding="utf-8"))))

    brief = """# Blind replication — {mid} {mname}

You are the replicating author. The purpose of this exercise is to find out
whether the detection result in the paper survives when the person writing the
tests did **not** place the faults.

## What you have, and what you deliberately do not

**You have**
- `specifications/SPEC-functional.md` — the functional requirements
- `specifications/SPEC-security-properties.md` — the twelve security properties
- `api-surface/` — class and method signatures with docblocks, no method bodies

**You do not have, and must not seek**
- the seeded-defect register
- the system-under-test source code
- either of the existing test suites
- any results from the study

The source is withheld for a specific reason: the seeded faults are visible in
it as `defectActive('...')` calls. Reading the implementation would tell you
where the faults are, and the exercise would be worthless. Specification-based
authoring is the control.

If you believe a specification is ambiguous, write the test for the reading you
think correct and note the ambiguity in a comment. Do not ask which reading
matches the implementation.

## Your task

Author **exactly {budget} tests** for {mid}, in `your-tests/`.

The budget is the same one both existing arms worked under, so your suite is
comparable to theirs. Not {bminus}, not {bplus}.

Work through the twelve security properties in
`SPEC-security-properties.md` in order, and write a test wherever a property
has a referent in this module. Cover the functional requirements below as well.
Cite the property ID and the requirement ID in each test.

Requirements in scope: {frs}

## Rules that are checked mechanically

1. No test may name a defect, reference the register, or touch the activation
   mechanism. A checker enforces this and fails the run.
2Ascii. Each test cites at least one `FR-` or `SP-` identifier.
3. Exactly {budget} test methods.

## How to write them

PHPUnit, one class, named `Mod{num}ReplicationTest`. Assume the harness
bootstrap provides the classes in `api-surface/` and a `TestSupport` helper
with `resetDefects()`, `makeApplication()` and `session()`. You do not need a
working environment to author them — they will be run against the harness by
the first author after you hand them back.

```php
<?php
use PHPUnit\\Framework\\TestCase;

final class Mod{num}ReplicationTest extends TestCase
{{
    protected function setUp(): void {{ TestSupport::resetDefects(); }}

    /** FR-xx.x / SP-xx — one sentence on what property this pins down */
    public function testSomethingSpecific(): void
    {{
        $this->assertSame(...);
    }}
}}
```

## When you are done

Hand back `your-tests/` only. Do not look at the study results first — if you
do, the exercise is no longer blind and the paper cannot claim it was.
""".format(mid=MODULE, mname=mod["name"], budget=budget,
           bminus=budget - 1, bplus=budget + 1,
           num=MODULE.split("-")[1],
           frs=", ".join(frs) if frs else "see the specification")

    (DEST / "README.md").write_text(brief, encoding="utf-8")

    # Leak check on the kit itself.
    register = json.loads((ROOT / "register" / "seeded_defects.json").read_text(encoding="utf-8"))
    keys = [d["key"] for d in register["defects"]]
    leaks = []
    for f in DEST.rglob("*"):
        if not f.is_file():
            continue
        body = f.read_text(encoding="utf-8", errors="ignore")
        for k in keys:
            if k in body:
                leaks.append("%s contains defect key '%s'" % (f.relative_to(DEST), k))

    print("kit written to %s" % DEST)
    print("  module            %s %s" % (MODULE, mod["name"]))
    print("  test budget       %d" % budget)
    print("  specifications    2")
    print("  api surfaces      %d  (%s)" % (len(emitted), ", ".join(emitted)))
    print("  requirements      %s" % (", ".join(frs) if frs else "n/a"))
    print("")
    if leaks:
        print("LEAK CHECK FAILED — the kit reveals the register:")
        for l in leaks:
            print("   %s" % l)
        return 1
    print("LEAK CHECK PASSED — no defect key appears anywhere in the kit")
    return 0


if __name__ == "__main__":
    sys.exit(main())
