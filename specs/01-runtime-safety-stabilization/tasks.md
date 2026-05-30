---

description: "Task list for Phase 1 — Runtime Safety Stabilization"
---

# Tasks: Runtime Safety Stabilization

**Input**: Design documents from `specs/01-runtime-safety-stabilization/`

**Prerequisites**: `plan.md` (loaded), `spec.md` (loaded), `research.md` (loaded), `data-model.md` (loaded), `contracts/` (loaded), `quickstart.md` (loaded)

**Tests**: Manual QA per `quickstart.md` only. Automated tests are explicitly out of scope per spec.md § Assumptions — that work belongs to Phase 5. Do **not** add PHPUnit or similar harnesses in this phase.

**Organization**: Tasks are grouped by user story to enable independent verification. Tasks include exact file paths, function names, anchor lines from the current source, and verbatim before/after snippets so the implementer does not need to infer context.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to a user story from `spec.md` (US1, US2, US3)
- Each task names the exact file path; many tasks include verbatim source anchors

## Path Conventions

This is a WordPress plugin project. All implementation files live at the repository root or under `includes/`. There is no `src/` or `tests/` directory.

- **Plugin bootstrap**: `disable-emails-per-product-for-woocommerce.php`
- **Plugin classes**: `includes/Core.php`, `includes/Admin.php`, `includes/GlobalView.php`
- **Translations**: `languages/` (read-only; path fix is in code, files unchanged)

## Document references the implementer should keep open

- `specs/01-runtime-safety-stabilization/contracts/internal-validation.md` — guard-clause contract G1–G10 (definitive)
- `specs/01-runtime-safety-stabilization/research.md` — decisions R1–R10
- `specs/01-runtime-safety-stabilization/quickstart.md` — manual QA matrix QA-1 through QA-8

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm prerequisites are in place before any source change.

- [X] T001 Verify `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY = false` are set in the test site's `wp-config.php` per `specs/01-runtime-safety-stabilization/quickstart.md` § Prerequisites
- [X] T002 [P] Verify Phase 0 baseline files exist at `specs/00-plugin-audit-and-baseline/deliverables/baselines/php-lint.txt` and `specs/00-plugin-audit-and-baseline/deliverables/baselines/wpcs.txt` (or note their absence and proceed using current `php -l` + `phpcs` runs as the baseline)
- [X] T003 [P] Confirm `vendor/bin/phpcs` is available; if absent, install with `composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs woocommerce/woocommerce-sniffs` and run `vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs,vendor/woocommerce/woocommerce-sniffs`
- [X] T004 Reproduce the pre-fix crash from `specs/01-runtime-safety-stabilization/quickstart.md` § "Pre-fix repro" and confirm the fatal-error line `PHP Fatal error: Uncaught Error: Call to a member function is_type() on bool in .../includes/Core.php:41` appears in `wp-content/debug.log` (skip if Phase 0 risk inventory already records this reproduction)

**Checkpoint**: Environment ready. The deleted-product crash is confirmed reproducible on the unpatched code.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Add the bootstrap-level constant required by US3 (text-domain fix) but reused by no other story. The plugin metadata header (`Requires Plugins`) is also added here because it is a one-line additive change that has no risk of conflicting with the US1/US2 work in `includes/Core.php`.

**⚠️ CRITICAL**: All three story phases (US1, US2, US3) can begin after this phase completes. Tasks here touch only `disable-emails-per-product-for-woocommerce.php`.

- [X] T005 Add a new header line `Requires Plugins: woocommerce` to the docblock of `disable-emails-per-product-for-woocommerce.php` between the existing `Domain Path: /languages` line and the `License: GPL3` line (see `specs/01-runtime-safety-stabilization/contracts/plugin-metadata.md` for the full header table)
- [X] T006 In `disable-emails-per-product-for-woocommerce.php`, immediately after the existing line `define('DEPPWC_BASENAME', plugin_basename(__FILE__));`, add `define('DEPPWC_PLUGIN_FILE', __FILE__);` (this constant is consumed by T013 in `Admin::load_text_domain`)
- [X] T007 Run `php -l disable-emails-per-product-for-woocommerce.php` and confirm it reports `No syntax errors detected`

**Checkpoint**: Bootstrap carries the dependency header and the new constant. No behavior has changed yet because no callsite uses `DEPPWC_PLUGIN_FILE` until T013.

---

## Phase 3: User Story 1 — Orders containing deleted products do not break the store (Priority: P1) 🎯 MVP

**Goal**: Eliminate the fatal-error crash path triggered when an order line item references a deleted product. After this phase, the QA-1 and QA-2 scenarios in `quickstart.md` pass cleanly.

**Independent Test**: Repeat the QA-1 scenario from `quickstart.md`. Acceptance: the order's status change saves cleanly, no PHP fatal error is logged in `wp-content/debug.log`, and the product-level suppression rule on the *remaining* product still fires.

**Reference**: Guards G1–G5 in `specs/01-runtime-safety-stabilization/contracts/internal-validation.md`. All edits in this phase are to `Core::filter_woocommerce_email_recipient` in `includes/Core.php`.

### Implementation for User Story 1

- [X] T008 [US1] In `includes/Core.php`, inside `Core::filter_woocommerce_email_recipient`, between the existing top-of-function guard `if (!is_a($order, 'WC_Order') || !is_a($email_instance, 'WC_Email')) { return $recipient; }` (around line 32–34) and the `foreach ($order->get_items() as $key => $item)` line (around line 37), insert:

```php
$items = $order->get_items();
if (!is_array($items) && !($items instanceof \Traversable)) {
    return $recipient;
}
```

Then change the `foreach ($order->get_items() ...` line to `foreach ($items as $key => $item) {` so it iterates the validated local variable instead of re-calling `get_items()`. (Implements guard G2 + INV-O2.)

- [X] T009 [US1] In `includes/Core.php`, inside the same `Core::filter_woocommerce_email_recipient` method, replace the unsafe block

```php
$product = $item->get_product();

// If it is a variation, get the parent product ID
$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
```

with this defensive block:

```php
if (!($item instanceof \WC_Order_Item_Product)) {
    continue;
}

$product = $item->get_product();
if (!is_a($product, 'WC_Product')) {
    continue;
}

if ($product->is_type('variation')) {
    $product_id = $product->get_parent_id();
    if ($product_id <= 0) {
        continue;
    }
} else {
    $product_id = $product->get_id();
}

if ($product_id <= 0) {
    continue;
}
```

This implements guards G3 + G4 and satisfies invariants INV-I1, INV-I2, INV-P1, INV-P2, INV-P3 in one contiguous edit. Preserve all other code in the loop (the `get_post_meta`, the `is_array && isset` check, and the `$recipient = ''; break;` assignment) unchanged.

- [X] T010 [US1] Run `php -l includes/Core.php` and confirm it reports `No syntax errors detected`. If it does not, re-check that braces are balanced and that no closing `}` was accidentally deleted from the original `foreach` body.
- [X] T011 [US1] Execute the QA-1 scenario from `specs/01-runtime-safety-stabilization/quickstart.md` end-to-end on a local site. Confirm: (a) no fatal error appears in `wp-content/debug.log` during or after the order status change, (b) the `Processing order` email for the suppressed product is suppressed, (c) the `New order` (admin) email is delivered. Record the result in any local QA log; do not commit log output.
- [X] T012 [US1] Execute the QA-2 scenario from `specs/01-runtime-safety-stabilization/quickstart.md` end-to-end. Confirm: (a) no fatal error, (b) all standard transactional emails are delivered (no suppression fires because no rule was configured).

**Checkpoint**: US1 is complete. Deleted-product orders no longer crash. Suppression on a *different* product in the same order still works.

---

## Phase 4: User Story 2 — Email suppression remains reliable across common order types (Priority: P1)

**Goal**: Harden the order-level recipient filter and the mailer iteration entry point so that suppression on variable products, guest checkouts, and refunded orders behaves identically to simple-product orders. After this phase, QA-3 through QA-6 in `quickstart.md` pass cleanly.

**Independent Test**: Run QA-3, QA-4, QA-5, QA-6 from `quickstart.md` in sequence. Acceptance: each scenario logs zero PHP fatal errors and the configured suppression rule fires (or does not fire) consistently with how the simple-product case behaves.

**Reference**: Guards G6 + G7 in `specs/01-runtime-safety-stabilization/contracts/internal-validation.md`. Edits are to `Core::filter_woocommerce_order_email_recipient` and `Core::init` in `includes/Core.php`. US1's edits to `Core::filter_woocommerce_email_recipient` are a prerequisite (they cover the variable-product variation path that QA-3 exercises).

### Implementation for User Story 2

- [X] T013 [US2] In `includes/Core.php`, replace the entire body of `Core::filter_woocommerce_order_email_recipient` (currently lines ~62–74) with the guarded version below. Keep the method signature `public function filter_woocommerce_order_email_recipient($recipient, $order): mixed` unchanged.

```php
public function filter_woocommerce_order_email_recipient($recipient, $order): mixed
{
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if ('wc-settings' === $page) {
        return $recipient;
    }

    if (!is_a($order, 'WC_Order')) {
        return $recipient;
    }

    $order_id = $order->get_id();
    if ($order_id <= 0) {
        return $recipient;
    }

    if (get_post_meta($order_id, '_disable_order_emails', true)) {
        $recipient = '';
    }

    return $recipient;
}
```

This implements guard G6 and satisfies INV-O1 (in this function), INV-O3, INV-OE1. Preserve the leading docblock comment ("Credit: https://www.businessbloomer.com/...") above the method.

- [X] T014 [US2] In `includes/Core.php`, replace the entire body of `Core::init` with the guarded version below. Keep the method signature `public function init(): void` unchanged.

```php
public function init(): void
{
    if (!function_exists('WC') || !WC() || !WC()->mailer()) {
        return;
    }

    $emails = WC()->mailer()->get_emails();
    if (!is_array($emails)) {
        return;
    }

    foreach ($emails as $email) {
        if (!is_a($email, 'WC_Email')) {
            continue;
        }
        if ($email->is_enabled()) {
            add_filter('woocommerce_email_recipient_' . $email->id, [
                $this,
                'filter_woocommerce_email_recipient'
            ], 10, 3);
            add_filter('woocommerce_email_recipient_' . $email->id, [
                $this,
                'filter_woocommerce_order_email_recipient'
            ], 9999, 2);
        }
    }
}
```

This implements guard G7 and satisfies INV-E2. Do not change the filter priorities (10 and 9999) or the `accepted_args` values (3 and 2).

- [X] T015 [US2] Run `php -l includes/Core.php` and confirm `No syntax errors detected`.
- [X] T016 [US2] Execute the QA-3 scenario from `specs/01-runtime-safety-stabilization/quickstart.md` end-to-end (variable product with suppression on the parent). Confirm: no fatal error; the `Completed order` email is suppressed; admin `New order` email is delivered.
- [X] T017 [US2] Execute the QA-4 scenario (simple product with suppression of `Customer invoice`). Confirm: no fatal error; the customer invoice email is suppressed when triggered from the order admin screen.
- [X] T018 [US2] Execute the QA-5 scenario (guest checkout). Confirm: no fatal error; the configured suppression rule applies the same way as for a logged-in customer.
- [X] T019 [US2] Execute the QA-6 scenario (refunded order). Confirm: no fatal error during refund processing; refund email handling is consistent with the configured suppression rule.

**Checkpoint**: US2 is complete. Suppression behavior is consistent across simple, variable, guest, and refunded orders.

---

## Phase 5: User Story 3 — Plugin declares its WooCommerce dependency and localizes correctly (Priority: P2)

**Goal**: Ensure WordPress surfaces a native dependency notice when WooCommerce is missing, and ensure translations actually load. After this phase, QA-7 and QA-8 in `quickstart.md` pass cleanly. The `Requires Plugins` header and the `DEPPWC_PLUGIN_FILE` constant were already added in Phase 2 (T005, T006). This phase consumes them.

**Independent Test**: Run QA-7 (activation without WooCommerce) and QA-8 (translated locale loads). Acceptance: WordPress shows a native dependency notice (on WP ≥ 6.5); translated strings render in the test locale.

**Reference**: Guards G8–G10 in `specs/01-runtime-safety-stabilization/contracts/internal-validation.md`. The only code edit in this phase is to `includes/Admin.php`. T005 and T006 from Phase 2 satisfy G9 and G10.

### Implementation for User Story 3

- [X] T020 [US3] In `includes/Admin.php`, replace the entire body of `Admin::load_text_domain` with the corrected path expression. The current body is:

```php
public function load_text_domain(): void
{
    load_plugin_textdomain('disable-emails-per-product-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages');
}
```

Replace with:

```php
public function load_text_domain(): void
{
    load_plugin_textdomain(
        'disable-emails-per-product-for-woocommerce',
        false,
        dirname(plugin_basename(DEPPWC_PLUGIN_FILE)) . '/languages'
    );
}
```

This implements guard G8. The constant `DEPPWC_PLUGIN_FILE` was defined by T006 in the bootstrap.

- [X] T021 [US3] Run `php -l includes/Admin.php` and confirm `No syntax errors detected`.
- [X] T022 [US3] Execute the QA-7 scenario from `specs/01-runtime-safety-stabilization/quickstart.md` on a test site running WordPress 6.5 or newer. Confirm: the Plugins screen shows a "WooCommerce required" dependency notice; activation is blocked; no PHP fatal error appears on any admin page while WooCommerce is inactive.
- [X] T023 [US3] Execute the QA-8 scenario. Confirm: with a matching translation MO file present under `languages/` and the site locale switched, plugin-rendered strings appear in the test locale. (If no MO file is shipped, generate a minimal one from `languages/disable-emails-per-product-for-woocommerce.pot` containing a translation for one visible string and confirm only that string appears translated.)

**Checkpoint**: US3 is complete. WordPress enforces the WooCommerce dependency; translations load from the correct path.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verify quality gates and re-validate the constitution check now that all implementation is complete.

- [X] T024 [P] Run `php -l disable-emails-per-product-for-woocommerce.php`, `php -l includes/Core.php`, `php -l includes/Admin.php`. Each must report `No syntax errors detected`. (Re-run after any final touch-ups.)
- [X] T025 [P] Run `vendor/bin/phpcs --standard=WordPress,WooCommerce disable-emails-per-product-for-woocommerce.php includes/Core.php includes/Admin.php`. Compare the warning + error counts to the recorded Phase 0 baseline at `specs/00-plugin-audit-and-baseline/deliverables/baselines/wpcs.txt`. Acceptance: counts on these three files must not exceed the baseline. If the baseline file is missing, treat the current run as authoritative and capture its output for the PR description.
- [X] T026 Re-run the QA-1 scenario from `specs/01-runtime-safety-stabilization/quickstart.md` one final time on the fully-patched code to confirm no regression was introduced by later phases.
- [X] T027 Confirm by inspection that none of the following pre-existing hook registrations were changed: `add_action('save_post_shop_order', ...)` in `includes/Admin.php` line ~15 (HPOS migration is Phase 2 work), the `before_woocommerce_init` HPOS declaration in `disable-emails-per-product-for-woocommerce.php` lines ~40–47 (HPOS posture is Phase 2 work), and the `dwepp_disable_global_view` filter in the bootstrap line ~32 (Phase 4 extensibility scope).
- [X] T028 Re-evaluate the Constitution Check table in `specs/01-runtime-safety-stabilization/plan.md` § "Post-Design Re-check" against the *implemented* code. If all rows still PASS, no action. If any row downgrades, document the cause and either fix or escalate per `.specify/memory/constitution.md` § Governance.
- [X] T029 Tick every checkbox in `specs/01-runtime-safety-stabilization/quickstart.md` § "Acceptance checklist for the reviewer" that this phase's work satisfies. Anything left unchecked is a blocker for closing Phase 1.

**Checkpoint**: All quality gates pass. Phase 1 is ready for review and release packaging.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. Can start immediately.
- **Foundational (Phase 2)**: Depends on Phase 1 completion (specifically T001 — debug logging on — is helpful for T004 verification).
- **User Story 1 (Phase 3)**: Depends on Phase 2 completion. Edits `includes/Core.php`.
- **User Story 2 (Phase 4)**: Depends on Phase 2 + Phase 3 completion. Same file (`includes/Core.php`) as US1 — must run sequentially with US1, not in parallel. The QA-3 scenario (variable product) implicitly relies on US1's variation guard (T009).
- **User Story 3 (Phase 5)**: Depends on Phase 2 completion (specifically T006 — the `DEPPWC_PLUGIN_FILE` constant). Edits `includes/Admin.php`. **Can run in parallel with Phase 3/4** because it touches a different file.
- **Polish (Phase 6)**: Depends on Phases 3, 4, and 5 all being complete.

### Within Each User Story

- Source edits (`includes/Core.php` or `includes/Admin.php`) come first.
- `php -l` lint check immediately after each source edit.
- Manual QA scenarios from `quickstart.md` come after the lint check passes.
- Story is "complete" only when its mapped QA scenarios pass.

### Parallel Opportunities

- T002 and T003 (Phase 1) can run in parallel — both are read-only environment checks on different artifacts.
- T024 and T025 (Phase 6) can run in parallel — `php -l` and `phpcs` are independent.
- Phase 5 (US3, Admin.php) can run in parallel with Phase 3 + Phase 4 (US1 + US2, Core.php) once Phase 2 is complete — they touch different files. The implementer must take care to merge changes in a single PR or coordinate so that T024 (final lint of all three files) is unambiguous.

---

## Parallel Example: US3 alongside US1 + US2

```text
# After Phase 2 (T005, T006, T007) completes, the following can be developed in parallel:

# Track A (Core.php — US1 then US2):
T008 → T009 → T010 → T011 → T012   # US1
T013 → T014 → T015 → T016 → T017 → T018 → T019   # US2

# Track B (Admin.php — US3):
T020 → T021 → T022 → T023   # US3

# Then Phase 6 merges + validates:
T024 → T025 → T026 → T027 → T028 → T029
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T004).
2. Complete Phase 2: Foundational (T005–T007).
3. Complete Phase 3: US1 (T008–T012).
4. **STOP and VALIDATE**: Confirm QA-1 + QA-2 pass on the patched code.
5. If only US1 ships, the most critical production fatal-error path is closed. US2 and US3 can follow in separate releases if needed — but per the spec, US1 and US2 are both Priority 1 and the typical release scope is "all three stories".

### Incremental Delivery

1. Phase 1 + Phase 2 → foundation ready.
2. Phase 3 (US1) → deleted-product crash eliminated → optionally deploy as a hotfix.
3. Phase 4 (US2) → suppression reliability confirmed across order types → deploy.
4. Phase 5 (US3) → dependency declared and translations load → deploy.
5. Phase 6 → lint + WPCS + constitution re-check → ready for release packaging.

### Single-Developer Strategy (Default for this Phase)

Phase 1 of this plugin's stabilization roadmap is small (≤50 LOC delta projected) and is most safely executed by a single developer in strict task order T001 → T029. The "parallel" execution paths above exist so that if two LLM passes or two developers want to split the work, they can do so safely along the `Core.php` / `Admin.php` file boundary.

---

## Notes for the implementer

- **No new dependencies**. Do not run `composer require` for anything in this phase (T003 is a one-time dev tooling install for the *quality gate*, not a runtime dependency).
- **No HPOS work** in this phase. Do not touch the `before_woocommerce_init` HPOS declaration block in the bootstrap or the `save_post_shop_order` action in `Admin.php`. Both belong to Phase 2 of the roadmap.
- **No admin settings refactor** in this phase. Do not edit `includes/GlobalView.php` or the settings rendering in `Admin::add_product_tab_content`. Both belong to Phase 3 of the roadmap.
- **No new filters**. Do not introduce a `dwepp_excluded_email_ids` filter or similar. That belongs to Phase 4.
- **Preserve all existing code paths** for orders whose data is intact. The constitutional contract (principle II) is fail-closed-to-deliver: every guard added returns `$recipient` unchanged or `continue`s the loop. No guard may suppress, throw, or alter `$recipient` on rejection.
- **Commit cadence**: commit after each phase completes (after T007, T012, T019, T023, T029). Use the conventional commit prefix already established in this repo (see `git log` for prior style).
- **Do not bump the plugin version header**. Release packaging (version bump + readme.txt changelog) is a separate manual step per constitution principle IX and is out of scope for the implementer of this task list.
- **If a task fails**, stop. Do not skip ahead. Re-read the referenced contract or research entry before retrying. The `internal-validation.md` contract and the `research.md` decisions are the authoritative tie-breakers.
