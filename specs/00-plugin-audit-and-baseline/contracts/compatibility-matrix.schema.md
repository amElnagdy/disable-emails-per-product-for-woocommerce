# Contract: `deliverables/compatibility-matrix.md`

**Entity**: [CompatibilityDatapoint](../data-model.md#entity-compatibilitydatapoint)

## Required structure

```markdown
# Compatibility Matrix — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>
**PHP versions tested**: 7.4, 8.0, 8.1, 8.2, 8.3
**WC versions tested**: <list — see research R-002>
**WP version used as host**: <single WP version per test environment>

## HPOS disabled

| PHP \ WC | <WC_version_A> | <WC_version_B> | … |
|----------|----------------|----------------|---|
| 7.4      | works          | partial (R-005)| … |
| 8.0      | works          | works          | … |
| 8.1      | …              | …              | … |
| 8.2      | …              | …              | … |
| 8.3      | …              | …              | … |

## HPOS enabled

| PHP \ WC | <WC_version_A> | <WC_version_B> | … |
|----------|----------------|----------------|---|
| 7.4      | broken (R-002) | broken (R-002) | … |
| 8.0      | …              | …              | … |
| …        | …              | …              | … |

## Cell legend

- `works` — every baseline QA scenario in this cell passes.
- `partial (R-NNN)` — at least one scenario passes and at least one fails;
  failure(s) tracked under the cited risk entry.
- `broken (R-NNN)` — no scenario passes; failure tracked under the cited
  risk entry.
- `untested` — the cell was not exercised; the reason MUST be recorded in
  the per-cell notes below.

## Per-cell notes

For each cell whose status is not `works`, include:

- Cell coordinates (PHP × WC × HPOS).
- Brief failure summary.
- `evidence_ref` (link to log, screenshot, or QA scenario id).
- `risk_entry_ref` (RiskEntry id).
```

## Required completeness checks

1. Both matrices (HPOS disabled, HPOS enabled) are fully populated — no
   empty cells.
2. Every `partial` / `broken` cell has a `risk_entry_ref` that resolves
   to a row in `risk-inventory.md`.
3. Every `untested` cell has a justification in per-cell notes.
4. The Cartesian product of PHP × WC declared at the top equals the
   product of cells in both matrices.
