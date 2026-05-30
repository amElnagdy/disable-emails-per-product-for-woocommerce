# Phase 0 Research: Plugin Audit & Baseline

**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

The spec did not surface any `[NEEDS CLARIFICATION]` markers, but Phase 0
must still record the decisions made to resolve the open methodology
questions that were considered (and resolved via Assumptions) during
specification. This document is the durable rationale log for those
decisions; downstream phases consult it before deviating.

---

## R-001: PHP version range for the compatibility matrix

- **Decision**: PHP 7.4, 8.0, 8.1, 8.2, 8.3.
- **Rationale**: This range covers (a) the floor used by WordPress core
  security maintenance (7.4) and (b) the upper bound currently supported
  by stable WordPress / WooCommerce releases (8.3). The plugin source
  uses PHP 7.4-compatible syntax with one PHP 8-only return type
  (`mixed`, in `Core::filter_woocommerce_order_email_recipient`) — so
  PHP 7.4 is a known constraint to verify, not a casual default.
- **Alternatives considered**:
  - 8.0+ only (rejected: silently abandons hosts still on 7.4; existing
    `Requires PHP` header in `readme.txt` may say 7.4, to be confirmed
    during audit).
  - 7.2 / 7.3 (rejected: outside any current WP-supported window).
- **Owning deliverable**: `deliverables/compatibility-matrix.md`.

---

## R-002: WooCommerce version range for the compatibility matrix

- **Decision**: Two most recent WooCommerce minor lines available at the
  time the audit is executed, plus the minimum version declared in
  `readme.txt` if different. The audit MUST record the exact versions
  used.
- **Rationale**: Constitution principle IV requires WooCommerce
  compatibility to be validated, not assumed. The plugin's
  `Requires Plugins: woocommerce` header is missing in the bootstrap;
  this absence itself is a Phase 1 risk entry candidate, but for Phase 0
  matrix execution we test against the publicly released line(s) that
  most stores actually run.
- **Alternatives considered**:
  - Single latest only (rejected: regressions in the latest minor are
    common; one-version-only would miss compatibility breakage on the
    prior LTS-style line).
  - Every minor from 7.0 onwards (rejected: combinatorial explosion with
    PHP rows; no value beyond the two most recent for current stores).
- **Owning deliverable**: `deliverables/compatibility-matrix.md`.

---

## R-003: Static analysis tooling choice

- **Decision**: Attempt PHPStan level 5 with the
  `szepeviktor/phpstan-wordpress` extension. If install fails or the
  environment cannot host it, record the failure in
  `deliverables/baselines/static-analysis.txt` with reproduction steps
  and skip without blocking the rest of the audit.
- **Rationale**: PHPStan + the WordPress extension is the de-facto WP
  ecosystem static analyzer; it understands WordPress stubs and reduces
  false positives on `apply_filters`, `add_action`, etc. Level 5 is the
  highest level that produces actionable signal on a small legacy
  codebase without flooding the output.
- **Alternatives considered**:
  - Psalm (rejected: weaker WordPress stub ecosystem).
  - PHPStan level 1 / 2 (rejected: barely above lint — would miss the
    null-product class of bugs flagged in `plan.md` Phase 1).
  - Skip entirely (rejected as default: spec FR-009 requires *attempting*
    it; only documented failure justifies skipping).
- **Owning deliverable**: `deliverables/baselines/static-analysis.txt`.

---

## R-004: Lint and WPCS baseline storage format

- **Decision**: Store raw tool output verbatim, one file per tool, under
  `deliverables/baselines/`. Use plain text (`.txt`) with the exact
  invocation command as the first commented line and the tool version on
  the second line.
- **Rationale**: A baseline whose format is "verbatim output" can be
  diffed deterministically by any future phase with `diff` or
  `git diff --no-index`. Wrapping in JSON or HTML would couple the
  baseline to a renderer; raw text is universal.
- **Alternatives considered**:
  - SARIF (rejected: WPCS / `php -l` don't emit SARIF natively;
    transformation introduces a moving part).
  - JUnit XML (rejected: same reason; not native to these tools).
- **Owning deliverable**: `deliverables/baselines/php-lint.txt`,
  `deliverables/baselines/wpcs.txt`.

---

## R-005: Hook inventory extraction methodology

- **Decision**: Manual extraction backed by `grep` over `includes/**.php`
  and the bootstrap file for the patterns
  `add_action\(`, `add_filter\(`, `register_*\(`, plus the
  WooCommerce-specific entry points
  `woocommerce_settings_tabs_array`,
  `woocommerce_admin_field_*`, and
  `before_woocommerce_init`. Each match is converted to one
  Hook Registration row per the contract schema.
- **Rationale**: The plugin is ~250 LOC; tool-driven discovery (e.g.,
  static reflection of the autoloaded classes) is overkill and brittle.
  `grep` plus eyeballed verification is faster and more reliable for a
  codebase this size, and it surfaces conditional registrations that
  reflection would miss.
- **Alternatives considered**:
  - PHPStan rule for `add_action` calls (rejected: setup cost outweighs
    benefit at this scale).
  - WP-CLI `wp eval` introspection at runtime (rejected: only sees
    registrations that fire under the test config; misses dead code).
- **Owning deliverable**: `deliverables/hook-inventory.md`.

---

## R-006: HPOS reconciliation methodology

- **Decision**: Two-pass verification:
  1. **Static**: read every line of `includes/**.php` and the bootstrap
     for any `get_post_meta` / `update_post_meta` / `delete_post_meta` /
     `save_post_*` reference whose post ID is an order ID. Each such
     reference is a candidate HPOS-unsafe access path.
  2. **Runtime**: with HPOS enabled in a clean WooCommerce test
     installation, exercise the baseline QA checklist (per FR-011) and
     record any divergence from the HPOS-disabled run.
- **Rationale**: HPOS reconciliation requires both code-reading
  (to find access paths that *would* break if orders moved out of
  `wp_posts`) and live exercise (to confirm any plugin-side compatibility
  declaration is honest). Either pass alone would miss half the picture.
- **Alternatives considered**:
  - Runtime-only (rejected: false greens — code paths not exercised by
    the QA checklist would hide).
  - Static-only (rejected: would miss WooCommerce internal divergences
    that only surface at runtime).
- **Owning deliverable**: `deliverables/risk-inventory.md` (mismatches),
  `deliverables/compatibility-matrix.md` (HPOS rows).

---

## R-007: Audit reproducibility — Composer / vendor handling

- **Decision**: The audit does NOT modify `composer.json` or the
  `vendor/` directory. If `vendor/autoload.php` is missing locally, run
  `composer install --no-dev` once and record the resolved versions in
  `deliverables/architecture-notes.md`. Do not run `composer update`.
- **Rationale**: A recent commit (`7e92ea3`) explicitly includes
  `composer.json` in the plugin release. Pinning the vendor state during
  audit guarantees the lint and static-analysis baselines are reproducible
  by any maintainer.
- **Alternatives considered**:
  - `composer update` to latest (rejected: changes the analysis subject;
    risks unrelated drift in baselines).
  - Exclude `vendor/` from analysis (already true for the audit; the
    plugin's own code is the only subject).
- **Owning deliverable**: `deliverables/architecture-notes.md`.

---

## R-008: Risk inventory phase-routing schema

- **Decision**: Every risk entry MUST carry an `owning_phase` field
  whose value is one of `1` (Runtime Safety), `2` (HPOS), `3` (Admin
  Stability), `4` (Extensibility), `5` (Testing/QA), `6` (Feature
  Expansion). Defects that don't fit any later phase are routed to
  Phase 1 by default and flagged in `deliverables/architecture-notes.md`
  under "Unrouted findings" for re-triage.
- **Rationale**: Constitution principle I requires stabilization phases
  to be the receiver for discovery findings. A required `owning_phase`
  field forces explicit triage and prevents findings from being orphaned.
- **Alternatives considered**:
  - Free-text "next steps" field (rejected: not machine-greppable; risks
    findings rotting unaddressed).
  - Route everything to Phase 1 (rejected: hides phase intent; Phase 3
    admin findings should not be conflated with Phase 1 runtime fatals).
- **Owning deliverable**: `deliverables/risk-inventory.md` and contract
  schema at `contracts/risk-inventory.schema.md`.

---

## Resolved

All open methodology decisions are recorded above. No `[NEEDS
CLARIFICATION]` markers remain.

Phase 1 (data-model + contracts + quickstart) may begin.
