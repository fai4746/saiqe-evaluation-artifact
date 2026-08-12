# Generic Security-Property Checklist (Arm B test-authoring source)

**Version 1.0 — frozen 2026-08-12, before any test was written.**

This checklist is **module-agnostic**. The same twelve properties are applied to
every module in MOD-01..MOD-11. It was not written against the seeded-defect
register, and no property below names a defect, a file, or a function.

This is the *only* material Arm B has that Arm A does not. Arm A authors from
`SPEC-functional.md` alone. If Arm B detects more seeded defects, the cause is
the presence of a generic security checklist in the authoring loop — which is
precisely the design decision under test — and not foreknowledge of where
defects were placed.

Derivation: OWASP ASVS 4.0.3 chapters V1–V13, OWASP Top 10 for LLM
Applications (2025) for SP-11 and SP-12.

---

| ID | Property | Applied to a module by asking |
|---|---|---|
| **SP-01** | **Input boundaries hold.** Every numeric or bounded input rejects values outside its stated range, at the boundary and beyond. | For each bounded input the module accepts: what happens at min−1, max, max+1, and at an extreme value? |
| **SP-02** | **Type confusion does not grant access.** Identity and authorisation comparisons are type-strict. | For each equality check on an identity or role: does a value of a different type that loosely compares equal pass? |
| **SP-03** | **Authentication tokens are verified, not parsed.** A token with a wrong, absent, or attacker-chosen signature is rejected. | Can a token be constructed that the module accepts without possessing the signing secret? |
| **SP-04** | **Object access is scoped to the principal.** Every retrieval or mutation addressed by identifier is constrained to objects the principal owns or is authorised for. | Substitute another principal's object identifier: is the operation refused? |
| **SP-05** | **Untrusted text does not reach an interpreter.** Values that flow into SQL, HTML, a filesystem path, or a shell are parameterised, escaped, or allow-listed. | For each sink the module reaches: does a metacharacter-bearing input change the structure of what the interpreter receives? |
| **SP-06** | **Uploaded content is admitted on what it is, not what it claims to be.** | Does a file whose declared type and actual content disagree get admitted? |
| **SP-07** | **Storage identifiers are not attacker-chosen.** Names and paths derived from user input are rejected or replaced. | Can an input determine where a byte stream lands on disk? |
| **SP-08** | **Secrets never enter logs or telemetry.** Credentials, tokens, and full identifiers are absent from every persisted record. | Search every record the module writes for the secret it just handled. |
| **SP-09** | **Session identity is re-established at authentication and expires.** | Does the identifier held before login still authenticate after it? |
| **SP-10** | **Repeated failure is rate-limited or locked out.** | After the stated threshold of failures, is the next attempt still processed normally? |
| **SP-11** | **Instructions and data are separated.** Content originating outside the trust boundary cannot alter control decisions, including decisions made by a model. | Place an imperative in every field an outside party controls: does any control decision change? |
| **SP-12** | **Delegated authority is bounded.** An automated component acts only within the role and the object scope of the principal it acts for. | Can a request carried in from untrusted content cause an action outside that scope? |

---

## Authoring rule for Arm B

For each module, walk SP-01 to SP-12 in order. Where a property has a
meaningful referent in that module, write a test for it and cite both the
property ID and the functional requirement it constrains. Where it has no
referent, record `n/a` with a one-line reason in the test class docblock.

No test may name a defect key, cite the register, or be justified by knowledge
of an injected fault.
