# Quickstart: Plugin Audit & Baseline Execution

**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md) |
**Research**: [research.md](./research.md) | **Data model**: [data-model.md](./data-model.md)

Step-by-step guide for a maintainer to execute the Phase 0 audit end to
end. Following these steps produces every deliverable defined in the
contracts under `contracts/`. Time estimate: ~2–3 focused days for a
maintainer familiar with WordPress / WooCommerce.

---

## 0. Prerequisites

- Local PHP 7.4 binary on `PATH` (additionally 8.0–8.3 if you intend to
  execute the full compatibility matrix locally instead of via CI).
- Composer 2.x.
- A WordPress test environment with WooCommerce installable, HPOS
  togglable. Recommended: [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
  or a Docker-based stack of your choice.
- WP-CLI accessible against that environment.
- Clean working tree on the audit branch; record the audit commit SHA
  before starting.

```bash
git rev-parse HEAD > specs/00-plugin-audit-and-baseline/deliverables/_AUDIT_COMMIT_SHA.txt
```

---

## 1. Prepare audit deliverables directory

```bash
mkdir -p specs/00-plugin-audit-and-baseline/deliverables/baselines
```

You will populate this directory; the `contracts/*.schema.md` files
define the required structure of each output.

---

## 2. Lint baseline (FR-007)

```bash
# PHP lint pass — every PHP file under audit, excluding vendor/
find . -path ./vendor -prune -o -name '*.php' -print0 \
  | xargs -0 -n1 php -l \
  > specs/00-plugin-audit-and-baseline/deliverables/baselines/php-lint.txt 2>&1
```

The first line of the output file MUST be a commented invocation
record per research R-004. Edit the file to prepend:

```text
# Command: find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
# PHP version: <output of `php -v`>
```

---

## 3. WPCS baseline (FR-008)

```bash
# Install PHPCS + WPCS in a Composer scratch dir if not already on PATH
composer global require --dev wp-coding-standards/wpcs squizlabs/php_codesniffer

# Run against plugin sources (NOT vendor/)
phpcs --standard=WordPress --extensions=php \
  disable-emails-per-product-for-woocommerce.php includes/ \
  > specs/00-plugin-audit-and-baseline/deliverables/baselines/wpcs.txt 2>&1 || true
```

Prepend the invocation and version lines as in step 2. Non-zero exit is
expected if violations exist — this is the *baseline*, not a gate.

---

## 4. Static analysis baseline (FR-009)

```bash
composer require --dev phpstan/phpstan szepeviktor/phpstan-wordpress
vendor/bin/phpstan analyse --level=5 includes/ disable-emails-per-product-for-woocommerce.php \
  > specs/00-plugin-audit-and-baseline/deliverables/baselines/static-analysis.txt 2>&1 || true
```

If install or run fails (e.g., autoload conflicts with the plugin's own
vendor/), record the failure in
`deliverables/baselines/static-analysis.txt` with reproduction steps and
move on — research R-003 explicitly allows documented skip.

---

## 5. Hook inventory (FR-001)

Per research R-005, this is manual extraction backed by `grep`:

```bash
grep -nE 'add_(action|filter)\(|register_[a-z_]+\(|before_woocommerce_init|woocommerce_settings_tabs_array' \
  disable-emails-per-product-for-woocommerce.php includes/*.php
```

For each match:

1. Open the file at the line.
2. Identify hook name, type, callback, priority, accepted args, and any
   surrounding conditional.
3. Convert to one row in
   `deliverables/hook-inventory.md` per the
   [contract](./contracts/hook-inventory.schema.md).

When the table is complete, run the contract's required completeness
checks against it.

---

## 6. Architecture notes (FR-002, FR-003, FR-004, FR-005)

Author `deliverables/architecture-notes.md` per the
[contract](./contracts/architecture-notes.schema.md). Sections 1–6 are
mandatory.

The most time-intensive section is **3. End-to-end suppression flow**:
for each of the six critical email flows from Constitution principle II,
walk the source code from WooCommerce's send-email entry point to the
plugin's recipient-filter decision, and record the chain of hooks /
callbacks / meta reads / decision points.

For **5. Order & product metadata access**, the search command is:

```bash
grep -nE 'get_post_meta|update_post_meta|delete_post_meta|save_post_[a-z_]+' \
  disable-emails-per-product-for-woocommerce.php includes/*.php
```

Annotate each result with HPOS-safe status.

---

## 7. Compatibility matrix (FR-006)

Per research R-001, R-002, and R-006:

1. For each PHP version × WC version × HPOS state cell, stand up a clean
   environment (e.g., a `wp-env` profile or a Docker container).
2. Activate the plugin and WooCommerce.
3. Execute the baseline QA checklist (step 8) against the cell.
4. Aggregate the QA results into a single cell status per the
   [contract](./contracts/compatibility-matrix.schema.md).

Untested cells require a justification note. Broken / partial cells
require a RiskEntry reference (step 9).

---

## 8. Baseline QA checklist (FR-011)

Author `deliverables/baseline-qa-checklist.md` per the
[contract](./contracts/baseline-qa-checklist.schema.md). Every category
in the data-model enum MUST be represented.

The checklist is run **twice** per compatibility-matrix cell (once with
HPOS enabled, once disabled). Record results per cell, then aggregate
into the matrix.

WP-CLI helpers for the deleted-product scenario:

```bash
# Create a product, attach to an order, then delete the product
wp wc product create --name="Audit Test Product" --type=simple --regular_price=10 --user=1
# … create an order via the storefront / WP-CLI, then:
wp post delete <product_id> --force
# Now trigger the order's processing email and confirm no fatal occurs
```

---

## 9. Risk inventory (FR-010, FR-014)

As you proceed through steps 5–8, each finding (null-product call site,
HPOS-unsafe meta access, unmatched declaration vs. behavior, broken QA
scenario, lint/static-analysis hit) becomes a candidate `RiskEntry`.

Author `deliverables/risk-inventory.md` per the
[contract](./contracts/risk-inventory.schema.md). Per research R-008,
every entry MUST have an `owning_phase` in `[1..6]`.

FR-014 is the **HPOS declaration reconciliation** check:

1. Verify the plugin's `FeaturesUtil::declare_compatibility('custom_order_tables', …, true)`
   call site.
2. Cross-reference against step 6 section 5 (every metadata access
   annotated HPOS-safe).
3. If any HPOS-unsafe access exists while compatibility is declared
   `true`, emit a **high-severity** RiskEntry with `owning_phase = 2`
   and `related_principles = ["V"]`.

---

## 10. Known regressions (FR-012)

Author `deliverables/known-regressions.md` per the
[contract](./contracts/known-regressions.schema.md). If none are known,
the file is still created with the explicit "No known regressions
recorded at the time of audit." entry.

Sources to scan:

- Existing GitHub issues (open and closed).
- `readme.txt` changelog entries describing fixes.
- Recent commit messages (`git log --oneline`) for fix-style commits.
- The user-supplied `plan.md` Phase summaries (each "Phase N — Goal"
  paragraph implicitly catalogues regressions to watch).

---

## 11. Contract validation

Before declaring Phase 0 complete, run each contract file's
**Required completeness checks** against the corresponding deliverable.
Document the pass result inline at the bottom of each deliverable:

```markdown
---

**Contract validation**: All required completeness checks pass as of
<YYYY-MM-DD>, verified against
`specs/00-plugin-audit-and-baseline/contracts/<contract>.md`.
```

---

## 12. Constitution re-check

Re-evaluate `plan.md` § Constitution Check against the produced
deliverables. Confirm all PASS gates still pass; if any gate now fails,
treat it as a Phase 0 incompletion and resolve before exiting.

---

## 13. Handoff

When all 12 prior steps are complete:

1. Commit the deliverables with message
   `audit(phase-0): baseline + hook inventory + risk inventory`.
2. Invoke `/speckit-tasks` to generate Phase 0's task list from this
   plan (the task list will reference these deliverables as inputs to
   verification tasks, not new work — Phase 0 work is the audit
   execution itself).
3. The audit deliverables are now the canonical input to Phase 1
   (`01-runtime-safety-stabilization`) planning.
