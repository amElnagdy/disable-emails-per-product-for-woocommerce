# Implementation Plan: Plugin Audit & Baseline

**Branch**: `00-plugin-audit-and-baseline` | **Date**: 2026-05-18 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/00-plugin-audit-and-baseline/spec.md`

## Summary

Phase 0 of the project's stabilization roadmap. Produce a complete, written
baseline of the plugin's current behavior, hook surface, HPOS posture,
compatibility envelope, and known risks — without modifying production
source code. All discovered defects are routed to later phases via the risk
inventory; Phase 0 itself only produces documentation. The deliverables are
the canonical reference for every subsequent stabilization and feature
phase.

## Technical Context

**Language/Version**: PHP 7.4+ (plugin runtime); audit deliverables authored
in Markdown.

**Primary Dependencies**: WordPress (current stable), WooCommerce
(supported version range, to be defined in research). Composer present (the
plugin ships a `vendor/` directory and `composer.json` per recent commit).

**Storage**: N/A for the audit deliverables (committed as Markdown under
`specs/00-plugin-audit-and-baseline/`). Plugin under audit uses WordPress
post meta (`wp_postmeta`) for both product and order metadata in its
current implementation.

**Testing**: Manual QA against a local WooCommerce-equipped WordPress
instance with HPOS togglable; PHP lint (`php -l`); WordPress Coding
Standards via PHP_CodeSniffer with the `WordPress` and `WooCommerce`
rulesets; optional static analysis (PHPStan or Psalm) as feasible.

**Target Platform**: WordPress admin and front-end with WooCommerce
installed; PHP-FPM or mod_php on a standard LAMP / LEMP stack.

**Project Type**: WordPress plugin (single project, PSR-4 autoloaded under
`DisableEmailsPerProductForWooCommerce\` from `includes/`).

**Performance Goals**: N/A for the audit. The audit MUST record a
performance-relevant observation only when it materially affects suppression
correctness (e.g., hook priority ordering that races with other plugins).

**Constraints**:

- **No production source code changes** during this phase. Audit produces
  documentation only.
- **Six critical email flows** (per Constitution principle II) MUST be
  individually traced in the architecture notes.
- **HPOS compatibility declaration vs. observed behavior** MUST be
  reconciled.
- All audit deliverables live under `specs/00-plugin-audit-and-baseline/`
  and are committed to the repository.

**Scale/Scope**: Three source files in `includes/` (`Core.php`,
`Admin.php`, `GlobalView.php`) plus the bootstrap
`disable-emails-per-product-for-woocommerce.php`. Approximate combined
size: ~250 LOC of plugin code (excluding `vendor/`). Hook surface estimated
at <15 registrations.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Evaluated against `.specify/memory/constitution.md` v1.0.0 (principles I–X):

| # | Principle | Gate | Status | Notes |
|---|-----------|------|--------|-------|
| I | Stabilization Before Features | This is a stabilization-track phase preceding all feature work. | PASS | Audit is the documented prerequisite of every later phase per `plan.md` (project root). |
| II | Transactional Email Safety (NON-NEGOTIABLE) | Audit must not alter email delivery. The six critical flows must each be traced. | PASS | No code is changed. FR-003 requires end-to-end documentation of the suppression flow; spec edge cases require per-flow tracing. |
| III | Defensive Runtime Programming | Audit must surface unguarded WooCommerce object access as risk entries. | PASS | FR-010 routes all such findings to risk inventory with severity/likelihood/owning phase. |
| IV | WooCommerce Compatibility First | Compatibility matrix is a mandatory deliverable. | PASS | FR-006 mandates a populated PHP × WC × HPOS matrix with status per cell. |
| V | HPOS Safety Requirements | HPOS declaration must be reconciled against behavior. | PASS | FR-014 explicitly requires this reconciliation; mismatches become high-severity risk entries. |
| VI | Admin Stability | Audit must not destabilize admin; admin surface is documented. | PASS | Audit only reads/exercises admin screens; FR-004 captures settings registration. |
| VII | Extensibility & Maintainability | N/A for audit phase (no API surface added). | N/A | Filter prefix `dwepp_` recorded as-is; no new filters introduced. |
| VIII | Testing & Regression Prevention | Baseline QA checklist + lint baselines are mandatory deliverables. | PASS | FR-007, FR-008, FR-011 enforce. |
| IX | Safe Release Strategy | N/A — no release in this phase. | N/A | Stable tag / `Tested up to` unchanged. |
| X | Observability & Debugging | Audit improves visibility into existing suppression by documenting it. | PASS | FR-003 traces every suppression decision point. |

**Overall**: ✅ All applicable gates PASS. No violations. No entries
required in Complexity Tracking.

**Re-check trigger**: After Phase 1 design (data-model + contracts), this
table must be re-evaluated to confirm no design choice has introduced a new
violation.

## Project Structure

### Documentation (this feature)

```text
specs/00-plugin-audit-and-baseline/
├── plan.md                       # This file
├── research.md                   # Phase 0 (/speckit-plan) — decisions log
├── data-model.md                 # Phase 1 (/speckit-plan) — entity schemas
├── quickstart.md                 # Phase 1 (/speckit-plan) — execution guide
├── contracts/                    # Phase 1 (/speckit-plan) — deliverable schemas
│   ├── hook-inventory.schema.md
│   ├── compatibility-matrix.schema.md
│   ├── risk-inventory.schema.md
│   ├── architecture-notes.schema.md
│   ├── baseline-qa-checklist.schema.md
│   └── known-regressions.schema.md
├── deliverables/                 # Audit output (populated during execution, NOT by /speckit-plan)
│   ├── hook-inventory.md
│   ├── architecture-notes.md
│   ├── compatibility-matrix.md
│   ├── risk-inventory.md
│   ├── baseline-qa-checklist.md
│   ├── known-regressions.md
│   └── baselines/
│       ├── php-lint.txt
│       ├── wpcs.txt
│       └── static-analysis.txt   # If tooling available; otherwise rationale recorded
├── checklists/
│   └── requirements.md           # Spec quality checklist (already created)
└── tasks.md                      # Phase 2 output (/speckit-tasks command — NOT created here)
```

### Source Code (repository root)

The plugin's actual source layout (read-only for this phase):

```text
disable-emails-per-product-for-woocommerce/
├── disable-emails-per-product-for-woocommerce.php   # Plugin bootstrap; HPOS compatibility declaration
├── includes/
│   ├── Core.php                                     # Email recipient filtering (product- and order-level suppression)
│   ├── Admin.php                                    # Product tab + order checkbox + settings save handlers
│   └── GlobalView.php                               # WooCommerce → Settings → "Disable Emails Per Product" tab
├── vendor/                                          # Composer autoload (not audited; pinned dependency)
├── languages/                                       # Text-domain translations
├── readme.txt                                       # WordPress.org plugin metadata
├── composer.json                                    # PSR-4 autoload definition
└── README.md                                        # Developer-facing readme
```

**Structure Decision**: This is a **single WordPress plugin** project.
Phase 0 produces audit documentation under
`specs/00-plugin-audit-and-baseline/deliverables/`. The plugin's source
tree (`includes/`, bootstrap file, `vendor/`, `languages/`) is **read-only**
for this phase — defects discovered are routed to later phases via the risk
inventory contract defined in Phase 1.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified.**

No violations recorded. Table intentionally empty.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|--------------------------------------|
| _(none)_  | _(none)_   | _(none)_                             |
