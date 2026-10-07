# Second-rater screening — 41 sources

You are the second rater. The paper currently states that no inter-rater
agreement is available because the corpus was screened by one author. This
exercise replaces that statement with a measured Cohen's kappa.

## What to do

1. Read `CRITERIA.md` and `VERDICTS.md`.
2. Open `screening_sheet.csv`. For each of the 41 rows, fill in `verdict`
   with one of the six strings, and `reason` with a sentence saying why.
3. Hand the filled CSV back.

About two minutes per source, so roughly ninety minutes.

## What you must not do

Do not look at the first author's verdicts. They are in
`results/corpus_audit.json` in the repository, and they are not in this kit on
purpose. If you read them first, the agreement figure measures nothing.

You may look sources up — that is screening, not cheating. Judge each on the
criteria, not on whether you think it belongs in the paper.

## Why all 41 and not a sample

The protocol suggests a colleague screen 10%. At 41 sources that is four, and
a kappa on four observations is not reportable. The full set is the smallest
sample that yields a statistic worth printing.
