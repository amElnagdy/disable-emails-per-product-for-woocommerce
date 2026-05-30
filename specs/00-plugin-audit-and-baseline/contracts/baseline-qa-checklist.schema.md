# Contract: `deliverables/baseline-qa-checklist.md`

**Entity**: [BaselineQAScenario](../data-model.md#entity-baselineqascenario)

## Required structure

```markdown
# Baseline QA Checklist — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>
**Test environment**:
- WordPress version: <X.Y.Z>
- WooCommerce version: <X.Y.Z>
- PHP version: <X.Y>
- HPOS state: <enabled | disabled> (run separately for each state)

## Summary

| Result   | Count |
|----------|-------|
| pass     | <N>   |
| fail     | <N>   |
| blocked  | <N>   |

## Categories represented

- [x] order-level-suppression
- [x] product-level-suppression
- [x] deleted-product
- [x] hpos-enabled
- [x] hpos-disabled
- [x] guest-checkout
- [x] customer-email
- [x] admin-email

## Scenarios

### QA-001 — <name>

- **Category**: …
- **Preconditions**:
  1. …
- **Steps**:
  1. …
- **Expected outcome**: …
- **Observed outcome**: …
- **Result**: pass | fail | blocked
- **Risk entry ref**: R-NNN (required if result ≠ pass)
- **Notes**: …

### QA-002 — <name>

…
```

## Required completeness checks

1. Every category in the data-model enum is represented by at least one
   scenario (Constitution principle VIII minimum validation set).
2. Sequential `QA-NNN` ids; no gaps, no reuse.
3. Every `fail` or `blocked` scenario has a `risk_entry_ref`.
4. Both HPOS states (`enabled`, `disabled`) are exercised; the checklist
   is run twice (once per state) and results recorded per scenario.
