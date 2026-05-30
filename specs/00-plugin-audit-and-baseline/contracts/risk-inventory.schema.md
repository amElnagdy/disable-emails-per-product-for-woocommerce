# Contract: `deliverables/risk-inventory.md`

**Entity**: [RiskEntry](../data-model.md#entity-riskentry)

## Required structure

```markdown
# Risk Inventory — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>

## Summary by severity

| Severity | Count | Owning phases |
|----------|-------|---------------|
| high     | <N>   | <list>        |
| medium   | <N>   | <list>        |
| low      | <N>   | <list>        |

## Summary by owning phase

| Phase | Count | High | Medium | Low |
|-------|-------|------|--------|-----|
| 1 (Runtime Safety) | <N> | <N> | <N> | <N> |
| 2 (HPOS)           | <N> | <N> | <N> | <N> |
| 3 (Admin)          | <N> | <N> | <N> | <N> |
| 4 (Extensibility)  | <N> | <N> | <N> | <N> |
| 5 (Testing/QA)     | <N> | <N> | <N> | <N> |
| 6 (Features)       | <N> | <N> | <N> | <N> |

## Entries

### R-001 — <title>

- **Severity**: high | medium | low
- **Likelihood**: high | medium | low
- **Owning phase**: 1 | 2 | 3 | 4 | 5 | 6
- **Related principles**: I, II, III, IV, V, VI, VII, VIII, IX, X
- **Source refs**: `includes/Core.php:41`, …
- **Discovered during**: static-review | runtime-exercise | lint | wpcs | static-analysis | qa-scenario
- **Description**: paragraph describing failure mode and triggering condition.
- **Mitigation summary**: paragraph proposing a fix (non-binding on the owning phase).

### R-002 — <title>

…
```

## Required completeness checks

1. Sequential, monotonically increasing `R-NNN` ids; no gaps, no reuse.
2. Every entry has all required RiskEntry fields populated.
3. The summary tables' counts equal the actual derivable counts.
4. Every `severity = high` entry either has `likelihood ≥ medium` OR
   `related_principles` includes `II` (transactional email safety
   is NON-NEGOTIABLE).
5. Every `source_refs` path is a valid `file:line` reference in the
   audited commit.
