# Phase 1 Data Model: Plugin Audit & Baseline

**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md)

The "data" produced by Phase 0 is documentation, not database rows. Each
entity below describes the schema of one *row* in a Markdown deliverable
table (or, where the deliverable is prose, the required attributes of one
*record*). Schemas are normative: the contract files under `contracts/`
reference these entities by name.

---

## Entity: HookRegistration

A single WordPress / WooCommerce hook the plugin registers.

| Field                              | Type      | Required | Constraints                                              |
|------------------------------------|-----------|----------|----------------------------------------------------------|
| `hook_name`                        | string    | yes      | Verbatim hook name (e.g., `woocommerce_email_recipient_new_order`). |
| `hook_type`                        | enum      | yes      | One of: `action`, `filter`.                              |
| `callback`                         | string    | yes      | Fully-qualified callback identifier (`Class::method` or closure descriptor with file:line). |
| `source_file`                      | string    | yes      | Repo-relative path (e.g., `includes/Core.php`).          |
| `source_line`                      | integer   | yes      | 1-based line number of the registration call.            |
| `priority`                         | integer   | yes      | Hook priority; default WordPress value is 10.            |
| `accepted_args`                    | integer   | yes      | Number of args the callback accepts; default 1.          |
| `registration_precondition`        | string    | yes      | `"always"` if registered unconditionally, otherwise a short description of the runtime condition. |
| `exercised_under_default_config`   | enum      | yes      | One of: `yes`, `no`, `unknown`.                          |
| `relates_to_critical_email_flow`   | enum      | yes      | One of: `new_order`, `processing_order`, `completed_order`, `customer_note`, `new_account`, `password_reset`, `none`, `multiple` (with note). |
| `notes`                            | string    | no       | Free-text observations (e.g., "registered at very high priority 9999 — ordering-sensitive"). |

**Validation rules**:

- If `registration_precondition != "always"`, `notes` MUST describe the
  condition mechanism (filter check, option check, class-exists guard).
- If `priority` is outside the range `[1, 999]`, `notes` MUST justify the
  unusual priority.
- One row per registration call; if a single callback is attached to
  multiple hooks, produce one row per hook.

---

## Entity: Component

One of the plugin's top-level architectural modules. The plugin currently
has three: Core, Admin, GlobalView.

| Field                  | Type     | Required | Constraints                                                |
|------------------------|----------|----------|------------------------------------------------------------|
| `name`                 | string   | yes      | `Core` \| `Admin` \| `GlobalView` (extensible if future modules added). |
| `source_files`         | string[] | yes      | List of repo-relative paths owned by this component.       |
| `responsibility`       | string   | yes      | One-paragraph summary of the component's role.             |
| `public_hooks`         | string[] | yes      | Hook names this component registers (links to HookRegistration). |
| `consumed_wc_apis`     | string[] | yes      | WooCommerce APIs invoked (e.g., `WC()->mailer()`, `wc_get_product()`, `WC_Order::get_items()`). |
| `consumed_wp_apis`     | string[] | yes      | WordPress APIs invoked (e.g., `get_post_meta`, `add_action`, `wp_nonce_field`). |
| `inter_component_deps` | string[] | yes      | Other component names this depends on (empty array if none). |
| `hpos_assumptions`     | string   | yes      | Free-text description of any post-table or HPOS-specific assumption. `"none"` if HPOS-agnostic. |

**Validation rules**:

- Every entry in `public_hooks` MUST correspond to a `HookRegistration`
  row whose `source_file` is in this component's `source_files`.
- `hpos_assumptions` MUST NOT be left blank; explicit `"none"` is
  required if no assumption exists.

---

## Entity: CompatibilityDatapoint

A single cell in the compatibility matrix.

| Field                | Type    | Required | Constraints                                          |
|----------------------|---------|----------|------------------------------------------------------|
| `php_version`        | string  | yes      | Major.minor (e.g., `7.4`, `8.1`).                    |
| `wc_version`         | string  | yes      | Major.minor.patch as installed (e.g., `8.9.3`).      |
| `hpos_state`         | enum    | yes      | `enabled` \| `disabled`.                             |
| `wp_version`         | string  | yes      | WordPress version used for the test.                 |
| `status`             | enum    | yes      | `works` \| `partial` \| `broken` \| `untested`.      |
| `evidence_ref`       | string  | yes      | Link to a baseline-qa-scenario row, log file, or screenshot. `"none"` only if `status == untested`. |
| `risk_entry_ref`     | string  | conditional | Required when `status` ∈ {`partial`, `broken`}; links to a RiskEntry by id. |
| `notes`              | string  | no       | Free-text observation (e.g., "settings tab renders but checkbox does not save"). |

**Validation rules**:

- No cell may be silently empty; `untested` is an explicit status, not an
  absence.
- A `broken` or `partial` cell without a corresponding `risk_entry_ref`
  is a contract violation.
- Matrix MUST be the Cartesian product of all PHP versions × all WC
  versions × `{enabled, disabled}` HPOS states declared in research R-001
  and R-002.

---

## Entity: RiskEntry

A single discovered risk.

| Field                | Type     | Required | Constraints                                              |
|----------------------|----------|----------|----------------------------------------------------------|
| `id`                 | string   | yes      | Format `R-NNN` (e.g., `R-001`); sequential, monotonically assigned. |
| `title`              | string   | yes      | Short imperative sentence (e.g., "Null product in order item causes fatal in email recipient filter"). |
| `description`        | string   | yes      | Paragraph describing the failure mode and triggering condition. |
| `source_refs`        | string[] | yes      | Repo-relative `file:line` references where the risk lives. |
| `severity`           | enum     | yes      | `high` \| `medium` \| `low`.                             |
| `likelihood`         | enum     | yes      | `high` \| `medium` \| `low`.                             |
| `owning_phase`       | integer  | yes      | One of: `1`, `2`, `3`, `4`, `5`, `6` (corresponds to `plan.md` phases). |
| `mitigation_summary` | string   | yes      | One-paragraph proposed fix; not binding on the owning phase. |
| `related_principles` | string[] | yes      | Constitution principle numerals affected (e.g., `["II", "III"]`). |
| `discovered_during`  | enum     | yes      | `static-review` \| `runtime-exercise` \| `lint` \| `wpcs` \| `static-analysis` \| `qa-scenario`. |

**Validation rules**:

- `severity = high` requires either `likelihood ≥ medium` or
  `related_principles` to include the `(NON-NEGOTIABLE)` principle II.
- `id` values MUST NOT be reused; once retired, mark `status: closed` in
  a follow-up phase but never reissue.

**State transitions** (across phases, for reference):

```text
discovered → triaged → in-flight (Phase N) → resolved
                                         ↘
                                          deferred (re-routed to later phase)
```

Phase 0 only emits `discovered` and `triaged` states.

---

## Entity: BaselineQAScenario

One verifiable scenario in the baseline QA checklist.

| Field              | Type     | Required | Constraints                                                |
|--------------------|----------|----------|------------------------------------------------------------|
| `id`               | string   | yes      | Format `QA-NNN`; sequential.                               |
| `name`             | string   | yes      | Short imperative title (e.g., "New order email is delivered when no suppression rules apply"). |
| `category`         | enum     | yes      | One of: `order-level-suppression`, `product-level-suppression`, `deleted-product`, `hpos-enabled`, `hpos-disabled`, `guest-checkout`, `customer-email`, `admin-email`. |
| `preconditions`    | string[] | yes      | Numbered or bulleted list of required environment state.   |
| `steps`            | string[] | yes      | Ordered list of actions a tester performs.                 |
| `expected_outcome` | string   | yes      | The single observable result that makes this scenario pass. |
| `observed_outcome` | string   | yes      | The result actually observed during baseline execution.    |
| `result`           | enum     | yes      | `pass` \| `fail` \| `blocked`.                             |
| `risk_entry_ref`   | string   | conditional | Required when `result ∈ {fail, blocked}`; links to RiskEntry. |
| `notes`            | string   | no       | Free-text (e.g., test environment specifics).              |

**Validation rules**:

- Every category in the enum MUST have at least one BaselineQAScenario
  row in the deliverable (Constitution principle VIII minimum
  validation set).
- A `fail` or `blocked` scenario without a `risk_entry_ref` is a
  contract violation.

---

## Entity: KnownRegression

A historical defect or observed misbehavior that future phases must
explicitly confirm has not been reintroduced.

| Field                       | Type    | Required | Constraints                                                     |
|-----------------------------|---------|----------|-----------------------------------------------------------------|
| `id`                        | string  | yes      | Format `KR-NNN`; sequential.                                    |
| `summary`                   | string  | yes      | One-sentence description of the defect.                         |
| `first_observed`            | string  | no       | ISO date or `"unknown"`.                                        |
| `source_evidence`           | string  | no       | Link to issue, support ticket, commit SHA, or `"oral"`.         |
| `current_status`            | enum    | yes      | `present` \| `fixed-but-watch` \| `cannot-reproduce`.           |
| `watching_phases`           | integer[] | yes    | Phase numbers (1–6) that MUST include a regression check for this entry. |
| `regression_check_summary`  | string  | yes      | One sentence describing what each watching phase must verify.   |

**Validation rules**:

- `watching_phases` MUST NOT be empty; a regression with no owner is a
  contract violation.

---

## Cross-entity invariants

- Every `RiskEntry.source_refs` path MUST exist in the current source
  tree at the time of audit; stale references invalidate the entry.
- Every `BaselineQAScenario.category` MUST be represented at least once
  in the deliverable (enforced as a structural completeness check).
- The set of `Component.public_hooks` across all components MUST equal
  the set of `HookRegistration.hook_name` rows (i.e., every registered
  hook has an owning component, and no component claims a hook it
  doesn't register).
- For every `CompatibilityDatapoint` with `status ∈ {partial, broken}`,
  the referenced `RiskEntry.owning_phase` SHOULD be Phase 2 (HPOS) or
  Phase 1 (Runtime Safety) unless explicitly justified in `notes`.
