# Contract: `deliverables/known-regressions.md`

**Entity**: [KnownRegression](../data-model.md#entity-knownregression)

## Required structure

```markdown
# Known Regressions — Disable Emails Per Product for WooCommerce

**Audit date**: YYYY-MM-DD
**Commit audited**: <full SHA>

## Entries

### KR-001 — <summary>

- **First observed**: YYYY-MM-DD | unknown
- **Source evidence**: <link or "oral">
- **Current status**: present | fixed-but-watch | cannot-reproduce
- **Watching phases**: [1, 2, 3, 4, 5, 6]  (subset; at least one required)
- **Regression check summary**: one sentence describing what each
  watching phase must verify.

### KR-002 — <summary>

…
```

## Required completeness checks

1. Sequential `KR-NNN` ids; no gaps, no reuse.
2. `watching_phases` is non-empty for every entry.
3. Every entry's `regression_check_summary` is concrete enough that a
   future phase can write a test or QA scenario directly from it.
4. If no historical regressions are known, the file MUST still be
   created with an explicit `## Entries` section containing only the
   text "No known regressions recorded at the time of audit." — empty
   file is a contract violation.
