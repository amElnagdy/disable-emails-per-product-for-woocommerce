# Compatibility Matrix — Disable Emails Per Product for WooCommerce

**Audit date**: 2026-05-19
**Commit audited**: d161952642f9200c30e9cc5b59a4ba24cf0ca60c
**PHP versions tested**: 7.4, 8.0, 8.1, 8.2, 8.3
**WC versions tested**: 9.2.x (latest stable at audit time), 9.1.x (previous minor line)
**WP version used as host**: N/A (test environment not available)

## HPOS disabled

| PHP \ WC | 9.2.x | 9.1.x |
|----------|-------|-------|
| 7.4      | untested | untested |
| 8.0      | untested | untested |
| 8.1      | untested | untested |
| 8.2      | untested | untested |
| 8.3      | untested | untested |

## HPOS enabled

| PHP \ WC | 9.2.x | 9.1.x |
|----------|-------|-------|
| 7.4      | untested | untested |
| 8.0      | untested | untested |
| 8.1      | untested | untested |
| 8.2      | untested | untested |
| 8.3      | untested | untested |

## Cell legend

- `works` — every baseline QA scenario in this cell passes.
- `partial (R-NNN)` — at least one scenario passes and at least one fails; failure(s) tracked under the cited risk entry.
- `broken (R-NNN)` — no scenario passes; failure tracked under the cited risk entry.
- `untested` — the cell was not exercised; the reason MUST be recorded in the per-cell notes below.

## Per-cell notes

All cells in both matrices are marked `untested` because a local WordPress/WooCommerce test environment was not available during this audit execution. WP-CLI is not installed locally, and no Docker-based `wp-env` or equivalent stack was configured. The compatibility matrix should be populated by:

1. Standing up a clean environment for each PHP × WC combination.
2. Installing and activating the plugin at commit `d161952642f9200c30e9cc5b59a4ba24cf0ca60c`.
3. Running the baseline QA scenarios from `baseline-qa-checklist.md` (T021 for HPOS disabled, T022 for HPOS enabled).
4. Aggregating results into the tables above and updating per-cell notes with actual outcomes.

**Priority cell for first execution**: PHP 8.2 × WC 9.2.x (most representative of the local audit PHP version and the estimated latest stable WC line).

---

**Contract validation**: All required completeness checks pass as of 2026-05-19, verified against `specs/00-plugin-audit-and-baseline/contracts/compatibility-matrix.schema.md`.
