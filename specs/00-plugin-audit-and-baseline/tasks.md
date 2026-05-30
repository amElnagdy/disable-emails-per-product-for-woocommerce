---

description: "Task list for Phase 0 — Plugin Audit & Baseline"
---

# Tasks: Plugin Audit & Baseline

**Feature directory**: `specs/00-plugin-audit-and-baseline/`

**Input design docs** (all present):

- [plan.md](./plan.md) — Phase 0 plan + Constitution Check
- [spec.md](./spec.md) — Feature specification (US1–US4)
- [research.md](./research.md) — Decisions R-001 … R-008
- [data-model.md](./data-model.md) — Entity schemas
- [contracts/](./contracts/) — Six deliverable schemas
- [quickstart.md](./quickstart.md) — 13-step execution guide

**Tests requested?** No automated tests — Phase 0 produces **documentation
artifacts only**. "Validation" tasks check each artifact against its contract.

**Audience note**: These tasks are written to be executable by a small / less
capable model without further context. Every task gives exact file paths,
exact commands, and the exact contract section to validate against. Do not
collapse multiple tasks into one — execute strictly in the listed order
within a phase. Across phases, parallelism is allowed only where `[P]` is
marked.

## Format

`- [ ] [TaskID] [P?] [Story?] Description with file path`

- **[P]** = parallelizable (different output file, no dependency on an
  unfinished task)
- **[USn]** = which user story the task belongs to (US1–US4); Setup,
  Foundational, and Polish phases carry no story label
- Repo-relative paths used throughout

## Path Conventions

- **Audit deliverables**: `specs/00-plugin-audit-and-baseline/deliverables/`
- **Audit baselines (raw tool output)**:
  `specs/00-plugin-audit-and-baseline/deliverables/baselines/`
- **Contracts (read-only schemas)**:
  `specs/00-plugin-audit-and-baseline/contracts/`
- **Plugin source under audit (READ-ONLY this phase)**:
  `disable-emails-per-product-for-woocommerce.php`, `includes/`
- **Plugin Composer vendor (READ-ONLY this phase)**: `vendor/`

---

## Phase 1: Setup

**Purpose**: Create deliverable scaffolding and capture the immutable audit
input state.

- [X] T001 Create the audit deliverables directory tree.
  - Run: `mkdir -p specs/00-plugin-audit-and-baseline/deliverables/baselines`
  - Verify the path exists. If `deliverables/` already exists with prior
    audit content, STOP and ask the operator before overwriting.

- [X] T002 Capture the audit commit SHA so all deliverables reference the
  same source state.
  - Run: `git rev-parse HEAD > specs/00-plugin-audit-and-baseline/deliverables/_AUDIT_COMMIT_SHA.txt`
  - This file is referenced by every deliverable's header (`Commit audited:`
    field defined in `contracts/*.schema.md`).

- [X] T003 Verify required tool prerequisites are on `PATH`.
  - Run each and record the version in
    `specs/00-plugin-audit-and-baseline/deliverables/_TOOL_VERSIONS.txt`:
    - `php -v`
    - `composer -V`
    - `git --version`
    - `wp --info` (WP-CLI) — optional but recommended for QA steps later
  - If any tool is missing, install it before proceeding to Phase 2.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Install the analysis tooling the baseline tasks depend on,
without modifying the plugin's own `vendor/` directory (per research R-007).

**⚠️ CRITICAL**: All user-story phases assume PHPCS, WPCS, and (optionally)
PHPStan are runnable. Do not begin Phase 3 until this phase succeeds.

- [X] T004 Install PHPCS + WordPress Coding Standards in a sandbox
  Composer location (do NOT modify the plugin's `composer.json` or
  `vendor/`).
  - Use a global Composer scratch dir, e.g.:
    `composer global require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs`
  - Run `phpcs --config-set installed_paths $(composer global config home)/vendor/wp-coding-standards/wpcs`
    so the `WordPress` standard is discoverable.
  - Verify `phpcs -i` lists `WordPress`.

- [X] T005 Attempt to install PHPStan + WordPress extension in the same
  sandbox.
  - `composer global require --dev phpstan/phpstan szepeviktor/phpstan-wordpress`
  - If install fails (autoload conflicts with plugin `vendor/`, network
    unavailable, etc.): create the file
    `specs/00-plugin-audit-and-baseline/deliverables/baselines/static-analysis.txt`
    with the exact failure output and a reproduction command, then mark
    this task complete. Research R-003 explicitly allows documented skip.

---

## Phase 3: User Story 1 — Hook Inventory & Architecture Notes (Priority: P1) 🎯 MVP

**Goal**: Produce a complete `hook-inventory.md` and `architecture-notes.md`
so any maintainer can locate every plugin-registered hook and trace each of
the six critical email flows end to end.

**Independent Test**: Open `deliverables/hook-inventory.md` — every
`add_action` / `add_filter` / `register_*` call in the source tree appears
as exactly one row, with verifiable `source_file:source_line` references.
Open `deliverables/architecture-notes.md` section 3 — all six critical
flows from Constitution principle II are traced.

### Implementation for User Story 1

- [X] T006 [US1] Extract every hook registration from plugin source via
  `grep`.
  - Run:
    `grep -nE 'add_(action|filter)\(|register_[a-z_]+\(|before_woocommerce_init|woocommerce_settings_tabs_array|woocommerce_admin_field_' disable-emails-per-product-for-woocommerce.php includes/*.php`
  - Save raw output to
    `specs/00-plugin-audit-and-baseline/deliverables/_hooks-raw-grep.txt`
    (working artifact; safe to delete after T007 completes).

- [X] T007 [US1] Author
  `specs/00-plugin-audit-and-baseline/deliverables/hook-inventory.md`
  conforming to `contracts/hook-inventory.schema.md`.
  - Use the structure from the contract verbatim (Header → Summary →
    Table → Per-file breakdown).
  - One table row per `_hooks-raw-grep.txt` match. For each row populate
    every required HookRegistration field
    (data-model.md § HookRegistration):
    `hook_name`, `hook_type`, `callback`, `source_file`, `source_line`,
    `priority`, `accepted_args`, `registration_precondition`,
    `exercised_under_default_config`, `relates_to_critical_email_flow`,
    `notes`.
  - For `priority`: default is `10` if the source call omits the
    priority arg.
  - For `accepted_args`: default is `1` if omitted.
  - For `registration_precondition`: read the surrounding code; if the
    `add_*` call lives inside an `if`, `apply_filters` gate, or
    closure body that runs only on a hook, write that condition
    explicitly. Otherwise write `"always"`.
  - For `exercised_under_default_config`: set to `yes` when the
    surrounding component is instantiated unconditionally in the
    bootstrap; `unknown` when there is doubt; `no` when clearly gated
    behind a non-default condition.
  - For `relates_to_critical_email_flow`: set to one of `new_order`,
    `processing_order`, `completed_order`, `customer_note`,
    `new_account`, `password_reset`, `none`, or `multiple` (with a
    `notes` clarification).

- [X] T008 [US1] Validate
  `deliverables/hook-inventory.md` against
  `contracts/hook-inventory.schema.md` § Required completeness checks.
  - All four checks (every call has one row; every row's file:line
    resolves; summary counts match the table; every
    `relates_to_critical_email_flow != "none"` row is referenced in
    `architecture-notes.md` section 3 — note this fourth check is
    re-verified after T010).
  - If any check fails, fix
    `deliverables/hook-inventory.md` and re-validate.
  - Append the contract-validation footer (template from quickstart
    § 11) at the end of the file when all checks pass.

- [X] T009 [US1] Author sections 1 (Bootstrap) and 2 (Components) of
  `specs/00-plugin-audit-and-baseline/deliverables/architecture-notes.md`
  per `contracts/architecture-notes.schema.md`.
  - **Section 1 Bootstrap**: read
    `disable-emails-per-product-for-woocommerce.php` and record the
    plugin header values (Plugin Name, Version, Requires PHP if any,
    Requires Plugins if any, Tested up to from `readme.txt`, WC tested
    up to from `readme.txt`), constants/globals defined (e.g.,
    `DEPPWC_PREFIX`, `DEPPWC_BASENAME`), component instantiation order
    (verbatim), and the HPOS compatibility declaration call site
    (verbatim quote with file:line).
  - **Section 2 Components**: one subsection per component. The plugin
    currently has three: `Core` (`includes/Core.php`), `Admin`
    (`includes/Admin.php`), `GlobalView` (`includes/GlobalView.php`).
    For each, populate every required Component field
    (data-model.md § Component): `name`, `source_files`,
    `responsibility`, `public_hooks` (link IDs from
    `hook-inventory.md`), `consumed_wc_apis`, `consumed_wp_apis`,
    `inter_component_deps`, `hpos_assumptions`.

- [X] T010 [US1] Author section 3 (End-to-end suppression flow) of
  `deliverables/architecture-notes.md`.
  - One subsection per critical email flow from Constitution principle
    II. For each: 3.1 New order email, 3.2 Processing order email,
    3.3 Completed order email, 3.4 Customer note email,
    3.5 New account email, 3.6 Password reset email.
  - Each subsection contains a 5-step trace per the contract structure:
    (1) WC trigger hook, (2) plugin filter callback (with
    priority/file/line), (3) plugin meta read (with key/storage/entity),
    (4) plugin decision (deliver/suppress), (5) WC sends or skips.
  - If the plugin does **not** intervene in a given flow (e.g., the
    plugin's product-level filter is not attached to that email's
    recipient hook), the subsection must still exist and explicitly
    state "Plugin does not intervene in this flow; default WC delivery
    applies."

- [X] T011 [US1] Author sections 4 (Settings registration), 5 (Order &
  product metadata access), and 6 (Unrouted findings) of
  `deliverables/architecture-notes.md`.
  - **Section 4**: for each WooCommerce settings tab/section/field the
    plugin registers, record file:line, hook priority, ordering
    dependencies relative to WC core, tab slug owned, and any nonce
    used for settings writes (with verification location).
  - **Section 5**: enumerate every metadata read/write from this command
    output:
    `grep -nE 'get_post_meta|update_post_meta|delete_post_meta|save_post_[a-z_]+' disable-emails-per-product-for-woocommerce.php includes/*.php`
    For each result, record `file:line`, `meta_key`, `read|write`,
    `subject_entity` (product / order / variation), and `hpos_safe?`
    (`yes` / `no` / `unknown`). Any `no` or `unknown` becomes a
    candidate RiskEntry consumed in T013.
  - **Section 6**: bullet list of any audit observations that did not
    fit cleanly into a RiskEntry phase routing. May be `(none)` — but
    the section header must exist.

- [X] T012 [US1] Validate
  `deliverables/architecture-notes.md` against
  `contracts/architecture-notes.schema.md` § Required completeness
  checks (all four checks). Fix the file and re-validate until all
  checks pass; then append the contract-validation footer.

**Checkpoint**: After T012, US1 is complete. A maintainer can find every
hook and trace every critical email flow using
`deliverables/hook-inventory.md` + `deliverables/architecture-notes.md`.
This satisfies SC-001, SC-002 from the spec.

---

## Phase 4: User Story 2 — Risk Inventory (Priority: P1)

**Goal**: Produce `deliverables/risk-inventory.md` that catalogs every
discovered risk with severity, likelihood, owning phase, and source refs.

**Independent Test**: Open `deliverables/risk-inventory.md` — every entry
has all required RiskEntry fields, ids are sequential, summary counts
match entries, and every entry's `source_refs` resolves in the audited
commit. Filtering by `severity = high` yields entries each with an
assigned `owning_phase`.

**Cross-story note**: The risk inventory is a **cross-cutting deliverable**
— US4 (baselines) and US3 (compatibility matrix) will **append** entries to
this file in later phases (T016, T019, T021, T023, T026). After each
append, the contract validation in T015 is re-run.

### Implementation for User Story 2

- [X] T013 [US2] Create `deliverables/risk-inventory.md` skeleton per
  `contracts/risk-inventory.schema.md`.
  - Include the header block (audit date, commit audited from
    `_AUDIT_COMMIT_SHA.txt`).
  - Include both `Summary by severity` and `Summary by owning phase`
    tables, populated with zeros initially; counts are refreshed
    whenever entries are added.
  - Include the empty `## Entries` section.

- [X] T014 [US2] Append initial RiskEntries from US1 outputs and
  `plan.md` (project root) Phase 1 list.
  - Source 1 — **architecture-notes.md section 5**: every metadata access
    with `hpos_safe? = no` or `hpos_safe? = unknown` becomes one
    RiskEntry. Typical entries include
    `get_post_meta($order->get_id(), '_disable_order_emails', …)` in
    `includes/Core.php` (HPOS-unsafe order meta read) and
    `update_post_meta($order_id, '_disable_order_emails', …)` in
    `includes/Admin.php` (HPOS-unsafe order meta write).
  - Source 2 — **architecture-notes.md section 1**: if the bootstrap
    declares HPOS compatibility (`FeaturesUtil::declare_compatibility`
    with last arg `true`) **while** section 5 lists any `hpos_safe? =
    no` access, emit ONE additional **high-severity** RiskEntry with
    `owning_phase = 2`, `related_principles = ["V"]`, title prefixed
    with "FR-014:". This is the FR-014 HPOS declaration reconciliation.
  - Source 3 — **`plan.md` (project root) Phase 1 Critical Fixes list**:
    each numbered item (missing `Requires Plugins` header, text-domain
    path, null product on order item, missing/deleted product, email
    recipient filter hardening, defensive validation of order/product/
    email objects) becomes one RiskEntry.
  - For each entry, populate every required RiskEntry field
    (data-model.md § RiskEntry). Use the format defined in
    `contracts/risk-inventory.schema.md` § Entries.
  - Assign sequential ids `R-001`, `R-002`, … in the order added.

- [X] T015 [US2] Refresh the summary tables in
  `deliverables/risk-inventory.md` to reflect current entry counts, then
  validate against `contracts/risk-inventory.schema.md` § Required
  completeness checks (all five). Fix and re-validate until clean.
  Append the contract-validation footer.
  - **Note**: This task is re-run after T016, T019, T021, T023, T026
    each append new entries. Each re-run refreshes the summary tables
    and re-validates the contract.

**Checkpoint**: After T015, US2 has a populated risk inventory backed by
the US1 architecture analysis. Spec SC-004 is partially satisfied (will
be fully satisfied after lint/QA/matrix-driven entries are appended in
subsequent phases).

---

## Phase 5: User Story 4 — Baselines & QA Checklist (Priority: P3)

**Goal**: Produce three lint/static-analysis baselines, a populated
`baseline-qa-checklist.md` covering all 8 categories executed twice
(HPOS enabled + disabled), and a `known-regressions.md` file. Also feed
findings from the baselines into the risk inventory (cross-cuts US2).

**Independent Test**: Open `deliverables/baselines/php-lint.txt` and
`wpcs.txt` — each contains verbatim tool output prefixed with the exact
invocation command and tool version. Open
`deliverables/baseline-qa-checklist.md` — every category in the data-model
enum has at least one scenario, both HPOS states are exercised, every
fail/blocked scenario has a `risk_entry_ref`.

**Order note**: Tasks T016, T017, T018 can run in parallel (different
output files, no inter-dependency). T019 must run sequentially after all
three complete because it triages their combined findings into the risk
inventory.

### Implementation for User Story 4

- [X] T016 [P] [US4] Produce the PHP lint baseline at
  `specs/00-plugin-audit-and-baseline/deliverables/baselines/php-lint.txt`.
  - Run (from repo root, PowerShell):
    `Get-ChildItem -Recurse -Filter *.php -Exclude vendor | ForEach-Object { php -l $_.FullName } | Out-File -Encoding utf8 specs/00-plugin-audit-and-baseline/deliverables/baselines/php-lint.txt`
  - Or (bash/git-bash):
    `find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l > specs/00-plugin-audit-and-baseline/deliverables/baselines/php-lint.txt 2>&1`
  - **Then edit the file** to prepend two lines (research R-004):
    ```
    # Command: <the exact command used above>
    # PHP version: <output of `php -v` first line>
    ```

- [X] T017 [P] [US4] Produce the WPCS baseline at
  `specs/00-plugin-audit-and-baseline/deliverables/baselines/wpcs.txt`.
  - Run:
    `phpcs --standard=WordPress --extensions=php disable-emails-per-product-for-woocommerce.php includes/ > specs/00-plugin-audit-and-baseline/deliverables/baselines/wpcs.txt 2>&1`
  - Non-zero exit is expected when violations exist; do NOT treat it as
    a failure of this task.
  - **Then edit the file** to prepend two lines:
    ```
    # Command: phpcs --standard=WordPress --extensions=php disable-emails-per-product-for-woocommerce.php includes/
    # PHPCS version: <output of `phpcs --version`>
    ```

- [X] T018 [P] [US4] Produce the static analysis baseline at
  `specs/00-plugin-audit-and-baseline/deliverables/baselines/static-analysis.txt`.
  - If T005 succeeded: run
    `phpstan analyse --level=5 includes/ disable-emails-per-product-for-woocommerce.php > specs/00-plugin-audit-and-baseline/deliverables/baselines/static-analysis.txt 2>&1`
    then prepend the invocation + version lines as above.
  - If T005 failed: this task is already satisfied — the failure
    record file already exists from T005. Verify it exists; if not,
    create it with the failure reproduction command per research R-003.

- [X] T019 [US4] Triage findings from T016, T017, T018 into
  `deliverables/risk-inventory.md` as additional RiskEntries.
  - For each finding in `php-lint.txt`: any syntax error is a
    **high-severity, high-likelihood** RiskEntry with `owning_phase =
    1`, `discovered_during = "lint"`. (Expected: zero such entries
    given recent commits.)
  - For each unique WPCS error category in `wpcs.txt`: emit ONE
    RiskEntry per category (not per occurrence) with
    `severity = medium`, `likelihood = low`, `owning_phase = 4`
    (Extensibility / cleanup), `discovered_during = "wpcs"`. Include a
    count of occurrences in the `description`.
  - For each unique PHPStan rule violation in `static-analysis.txt`:
    emit ONE RiskEntry per rule with severity calibrated to the rule's
    safety impact (e.g., `nullable.return` → high; `unused.variable`
    → low), `owning_phase = 1` for safety-class rules else `4`,
    `discovered_during = "static-analysis"`.
  - Re-run T015 to refresh the risk-inventory summary tables and
    re-validate against the contract.

- [X] T020 [US4] Author
  `specs/00-plugin-audit-and-baseline/deliverables/baseline-qa-checklist.md`
  conforming to `contracts/baseline-qa-checklist.schema.md`.
  - Include the header (audit date, commit audited, test environment
    block).
  - Author at least ONE scenario for each category in the data-model
    enum (all 8 required by Constitution principle VIII):
    `order-level-suppression`, `product-level-suppression`,
    `deleted-product`, `hpos-enabled`, `hpos-disabled`,
    `guest-checkout`, `customer-email`, `admin-email`.
  - For each scenario populate every required BaselineQAScenario field
    (data-model.md § BaselineQAScenario). Assign sequential ids
    `QA-001`, `QA-002`, … At authoring time, `observed_outcome` and
    `result` may be left as `pending`; they are filled in T021 and
    T022.
  - Scenarios MUST be concrete enough to execute. Example for the
    `deleted-product` category:
    ```
    ### QA-NNN — Order containing a deleted product does not fatal on processing email
    - Category: deleted-product
    - Preconditions:
      1. WooCommerce active, HPOS disabled
      2. A test product exists, was added to a test order, then deleted with `wp post delete <id> --force`
    - Steps:
      1. Change order status to "processing" via WC admin
      2. Observe the resulting email send attempt (mailcatcher / SMTP log)
    - Expected outcome: No PHP fatal; email is sent (or recipient is
      empty without fatal); WC order screen renders.
    - Observed outcome: <fill during execution>
    - Result: pending
    ```

- [X] T021 [US4] Execute the baseline QA checklist with **HPOS
  disabled** in a test environment.
  - For each scenario in `deliverables/baseline-qa-checklist.md`,
    perform the steps and fill `observed_outcome` and `result`
    (`pass` / `fail` / `blocked`).
  - For each `fail` or `blocked` result, append a corresponding
    RiskEntry to `deliverables/risk-inventory.md` and set
    `risk_entry_ref` in the scenario to that entry's id.
  - Re-run T015 if new RiskEntries were added.

- [X] T022 [US4] Execute the baseline QA checklist with **HPOS
  enabled** in a test environment.
  - **Method**: in the same test environment, enable HPOS via
    WooCommerce → Settings → Advanced → Features (or
    `wp option update woocommerce_custom_orders_table_enabled yes`),
    then re-run all `hpos-enabled` category scenarios AND any
    HPOS-sensitive scenarios that previously ran under HPOS disabled.
  - Append results either inline (as a second result column in the
    scenario) or by duplicating affected scenarios with id
    `QA-NNN-hpos`. Both styles are acceptable; pick one and apply
    consistently across the file.
  - For each `fail` or `blocked` result, append a corresponding
    RiskEntry to `deliverables/risk-inventory.md` and set
    `risk_entry_ref`.
  - Re-run T015 if new RiskEntries were added.

- [X] T023 [US4] Validate
  `deliverables/baseline-qa-checklist.md` against
  `contracts/baseline-qa-checklist.schema.md` § Required completeness
  checks (all four). Fix and re-validate. Append the contract-validation
  footer.

- [X] T024 [US4] Author
  `specs/00-plugin-audit-and-baseline/deliverables/known-regressions.md`
  per `contracts/known-regressions.schema.md`.
  - Scan sources listed in quickstart.md step 10: GitHub issues,
    `readme.txt` changelog, recent commit log (`git log --oneline`),
    project-root `plan.md` Phase summaries.
  - Emit one KnownRegression entry per historical defect, with all
    required fields per data-model.md § KnownRegression.
  - If zero regressions are found, the file MUST still exist with the
    explicit entry text "No known regressions recorded at the time of
    audit." (contract requires this; an empty file is a violation).
  - Validate against the contract (3 required completeness checks),
    fix, re-validate, append contract-validation footer.

**Checkpoint**: After T024, US4 is complete. Spec SC-005 and SC-006 are
satisfied.

---

## Phase 6: User Story 3 — Compatibility Matrix (Priority: P2)

**Goal**: Produce `deliverables/compatibility-matrix.md` covering PHP × WC
× HPOS, with every cell populated (`works` / `partial` / `broken` /
`untested`) and per-cell notes for non-`works` cells.

**Independent Test**: Open `deliverables/compatibility-matrix.md` — both
HPOS-state tables are fully populated, no empty cells, every `partial` or
`broken` cell has a `risk_entry_ref`, every `untested` cell has a
justification note.

**Pre-condition**: T020 must be complete (the QA checklist authored in T020
is what gets executed against each cell here).

### Implementation for User Story 3

- [X] T025 [US3] Resolve the concrete WooCommerce version list per
  research R-002.
  - Determine the two most recent WC minor lines currently released.
    Read `readme.txt` `Requires at least` / `WC requires at least`
    field; if it declares a minimum version, also include that
    minimum.
  - Record the resolved list at the top of
    `specs/00-plugin-audit-and-baseline/deliverables/compatibility-matrix.md`
    in the header `WC versions tested:` field per the contract.

- [X] T026 [US3] Author
  `deliverables/compatibility-matrix.md` skeleton per
  `contracts/compatibility-matrix.schema.md`.
  - Include header with audit date, commit, PHP versions (per
    research R-001: 7.4, 8.0, 8.1, 8.2, 8.3), WC versions (from
    T025), WP version of host environments.
  - Include both `## HPOS disabled` and `## HPOS enabled` tables as
    PHP-rows × WC-columns. Initialize every cell to `untested`.
  - Include the empty `## Per-cell notes` section.

- [X] T027 [US3] Execute the QA checklist across the matrix cells and
  aggregate results into the two tables in
  `deliverables/compatibility-matrix.md`.
  - For each cell (PHP × WC × HPOS state):
    1. Stand up a test environment matching the cell.
    2. Install and activate the plugin at the audit commit SHA.
    3. Run the baseline QA scenarios (from T020) applicable to that
       cell's HPOS state.
    4. Aggregate: `works` if all scenarios pass; `partial` if some
       pass and some fail; `broken` if none pass; `untested` only if
       environment could not be stood up.
    5. If `partial` or `broken`, ensure the failing scenario already
       has an associated RiskEntry (created in T021/T022); set the
       cell to `partial (R-NNN)` or `broken (R-NNN)` using that id.
       If a new failure appears that wasn't in T021/T022, append a
       new RiskEntry and re-run T015.
    6. Update the cell in the matrix table with the resolved value.
  - **Pragmatic scoping**: if standing up every cell is infeasible,
    prioritize (a) the PHP × WC combination most representative of
    real-world stores (latest stable PHP × latest stable WC), then
    expand outward. Cells not exercised remain `untested` with a
    justification recorded in T028.

- [X] T028 [US3] Author per-cell notes in
  `deliverables/compatibility-matrix.md` § Per-cell notes for every cell
  whose status is not `works`.
  - For `untested` cells: brief justification (e.g., "PHP 7.4 image
    not available in CI environment used for this audit").
  - For `partial` / `broken` cells: cell coordinates, failure
    summary, `evidence_ref` (log path or QA scenario id),
    `risk_entry_ref`.

- [X] T029 [US3] Validate
  `deliverables/compatibility-matrix.md` against
  `contracts/compatibility-matrix.schema.md` § Required completeness
  checks (all four). Fix and re-validate. Append the
  contract-validation footer.

**Checkpoint**: After T029, US3 is complete. Spec SC-003 is satisfied.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Finalize the deliverables bundle and prepare handoff to
Phase 1 (`01-runtime-safety-stabilization`).

- [X] T030 Run a final pass over every deliverable in
  `specs/00-plugin-audit-and-baseline/deliverables/` to confirm every
  file has the contract-validation footer (template per quickstart
  § 11). The list of expected deliverables:
  - `hook-inventory.md`
  - `architecture-notes.md`
  - `risk-inventory.md`
  - `baseline-qa-checklist.md`
  - `known-regressions.md`
  - `compatibility-matrix.md`

- [X] T031 Re-evaluate `plan.md` § Constitution Check against the
  produced deliverables (per quickstart § 12).
  - Walk principles I–X and confirm each PASS gate still holds given
    the populated deliverables (e.g., principle II is still satisfied:
    no source code changed; principle V is still satisfied: FR-014
    reconciliation entry exists in risk-inventory).
  - If any gate now fails, do NOT mark T031 complete — fix the
    underlying deliverable first.

- [X] T032 Verify spec.md success criteria SC-001 through SC-008 are
  all satisfied by the produced deliverables.
  - For each SC-NNN, write one line in a new file
    `specs/00-plugin-audit-and-baseline/deliverables/_SUCCESS_CRITERIA.md`
    citing which deliverable section satisfies it.

- [X] T033 Update the SPECKIT START/END block in `CLAUDE.md` (project
  root) to record that Phase 0 deliverables are complete and to point
  Phase 1 work at
  `specs/00-plugin-audit-and-baseline/deliverables/risk-inventory.md`
  filtered to `owning_phase = 1` as its primary input.

- [X] T034 Stage and propose a commit for the audit deliverables.
  - Do NOT auto-execute the commit. Print the proposed message and
    file list for the operator to confirm.
  - Suggested message:
    ```
    audit(phase-0): baseline + hook inventory + risk inventory

    Phase 0 deliverables for 00-plugin-audit-and-baseline:
    - hook-inventory.md (NNN registrations)
    - architecture-notes.md (3 components, 6 critical email flows traced)
    - risk-inventory.md (NNN entries; NNN high severity)
    - compatibility-matrix.md (PHP 7.4-8.3 × WC ... × HPOS on/off)
    - baseline-qa-checklist.md (NNN scenarios across 8 categories)
    - known-regressions.md
    - baselines/{php-lint,wpcs,static-analysis}.txt
    ```
  - Replace `NNN` placeholders with actual counts before committing.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)** — no dependencies; start immediately.
- **Phase 2 (Foundational)** — depends on Phase 1; BLOCKS all user
  stories.
- **Phase 3 (US1)** — depends on Phase 2. Independent of US2/US3/US4.
- **Phase 4 (US2)** — depends on Phase 3 (US1) for initial entries
  derived from architecture notes. Continues to be appended throughout
  Phases 5–6.
- **Phase 5 (US4)** — depends on Phase 2 for tooling. Lint/WPCS/static
  analysis (T016/T017/T018) can run in parallel with each other.
  Independent of US3. Appends to US2 risk inventory (cross-cutting).
- **Phase 6 (US3)** — depends on T020 (QA checklist authored in US4)
  and on US2 risk inventory existing (so cell statuses can cite
  RiskEntries).
- **Phase 7 (Polish)** — depends on all prior phases.

### Within Each User Story

- T006 → T007 → T008 (extract → author → validate)
- T009 → T010 → T011 → T012 (architecture sections in order, then
  validate)
- T013 → T014 → T015 (skeleton → entries → validate); T015 re-runs
  whenever new entries appended
- T016 / T017 / T018 in parallel → T019 (triage) → re-run T015
- T020 → T021 → T022 → T023 → T024
- T025 → T026 → T027 → T028 → T029

### Parallel Opportunities

- T016, T017, T018 (lint, WPCS, static analysis baselines) — different
  output files, no dependencies between them.
- Within Phase 3 / Phase 6, the per-section drafting tasks all write
  to a single deliverable file, so they MUST run sequentially even
  though they conceptually cover different sections (parallelism
  would risk merge conflicts in the same file).

### Parallel Example (Phase 5 baselines)

A single agent invocation should kick off these three commands in
parallel (different output files, no interdependency):

```text
T016: PHP lint  → deliverables/baselines/php-lint.txt
T017: WPCS      → deliverables/baselines/wpcs.txt
T018: PHPStan   → deliverables/baselines/static-analysis.txt
```

Only after all three complete may T019 (triage into risk-inventory)
begin.

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Complete Phase 1 (Setup)
2. Complete Phase 2 (Foundational)
3. Complete Phase 3 (US1 — hook inventory + architecture notes)
4. **STOP and VALIDATE**: hook-inventory.md + architecture-notes.md
   are the absolute minimum a maintainer needs to begin Phase 1
   stabilization planning. If time/budget is constrained, this is the
   shippable MVP.

### Incremental Delivery (recommended)

1. Phase 1 + Phase 2 → tooling ready.
2. Phase 3 (US1) → hook inventory + architecture notes (MVP).
3. Phase 4 (US2) → initial risk inventory.
4. Phase 5 (US4) → baselines + QA checklist (also feeds US2).
5. Phase 6 (US3) → compatibility matrix.
6. Phase 7 (Polish) → contract-validation + handoff.

### Notes

- Tests are NOT included in this task list — Phase 0 produces
  documentation, not code. "Validation" tasks check each deliverable
  against its contract instead.
- Every task references either a specific deliverable file path
  (under `specs/00-plugin-audit-and-baseline/deliverables/`) or a
  specific contract file (under
  `specs/00-plugin-audit-and-baseline/contracts/`). Do not invent
  new file locations.
- The plugin source tree (`disable-emails-per-product-for-woocommerce.php`,
  `includes/`, `vendor/`) is READ-ONLY in this phase per FR-013. Any
  defect discovered becomes a RiskEntry routed to a later phase.
- When a task says "fix and re-validate", do not skip the re-validation
  step — contract checks are the primary correctness signal in this
  phase.
- Commit at logical checkpoints (after each user story phase). The
  final commit (T034) bundles everything for Phase 1 handoff.
