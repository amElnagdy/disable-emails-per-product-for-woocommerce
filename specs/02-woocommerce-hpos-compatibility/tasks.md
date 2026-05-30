---

description: "Task list for Phase 2 — WooCommerce & HPOS Compatibility"
---

# Tasks: WooCommerce & HPOS Compatibility

**Input**: Design documents from `specs/02-woocommerce-hpos-compatibility/`

**Prerequisites**: `plan.md` (loaded), `spec.md` (loaded), `research.md` (loaded), `data-model.md` (loaded), `contracts/` (loaded), `quickstart.md` (loaded)

**Tests**: Manual QA per `quickstart.md` only. Automated tests are explicitly out of scope per spec.md § Out of Scope — that work belongs to Phase 5. Do **not** add PHPUnit or similar harnesses in this phase.

**Organization**: Tasks are grouped by user story to enable independent verification. Tasks include exact file paths, function names, verbatim before/after snippets, and explicit verification steps so a small-model implementer does not need to infer context.

**Critical assumption**: Phase 1 (`specs/01-runtime-safety-stabilization/`) is fully merged before Phase 2 begins. Phase 2 tasks reference the *Phase-1-modified* source state of `includes/Core.php::filter_woocommerce_order_email_recipient`. If Phase 1 has not yet been applied, complete Phase 1 first.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to a user story from `spec.md` (US1, US2, US3)
- Each task names the exact file path; most tasks include verbatim source anchors

## Path Conventions

This is a WordPress plugin project. All implementation files live at the repository root or under `includes/`. There is no `src/` or `tests/` directory.

- **Plugin bootstrap**: `disable-emails-per-product-for-woocommerce.php` (READ-ONLY in this phase)
- **Plugin classes**: `includes/Core.php`, `includes/Admin.php`, `includes/GlobalView.php` (GlobalView is READ-ONLY in this phase)
- **Translations**: `languages/` (no changes)
- **Readme**: `readme.txt` (changelog entry only)

## Document references the implementer should keep open

- `specs/02-woocommerce-hpos-compatibility/contracts/order-crud-access.md` — guard contract G1–G6 (definitive for code edits)
- `specs/02-woocommerce-hpos-compatibility/contracts/external-hooks.md` — hook diff and replacement contract
- `specs/02-woocommerce-hpos-compatibility/contracts/hpos-declaration.md` — declaration lifecycle and release-time gate
- `specs/02-woocommerce-hpos-compatibility/research.md` — decisions R1–R10
- `specs/02-woocommerce-hpos-compatibility/quickstart.md` — manual QA matrix QA-1 through QA-11

## Cheaper-LLM execution notes

- Every implementation task in Phase 3 quotes the **exact** "before" block to find and the **exact** "after" block to replace it with. Use string-equality matching; do not re-format whitespace, do not collapse blank lines, do not change quote style.
- Every implementation task is followed by a `php -l` verification task. Run it before proceeding.
- If a "before" block does not match the file's current content character-for-character, **stop**. Re-read `specs/02-woocommerce-hpos-compatibility/contracts/order-crud-access.md` and confirm the file is in the expected post-Phase-1 state.
- The Phase 1 modifications to `Core::filter_woocommerce_order_email_recipient` are quoted verbatim in T011's "before" block. If your `Core.php` does not match it, Phase 1 was not applied; apply Phase 1 first.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm prerequisites are in place before any source change.

- [X] T001 Verify `WP_DEBUG`, `WP_DEBUG_LOG`, and `WP_DEBUG_DISPLAY = false` are set in the test site's `wp-config.php` per `specs/02-woocommerce-hpos-compatibility/quickstart.md` § Prerequisites
- T00[X] T002 [P] Verify the test site has WooCommerce 8.2+ installed and active. Check the value via the admin top bar or WooCommerce → Status. If the version is below 8.2, upgrade WooCommerce before proceeding — HPOS-enabled QA rows cannot be exercised on older versions
- T00[X] T003 [P] Verify the test site can switch HPOS modes by visiting **WooCommerce → Settings → Advanced → Features → Custom order tables**. Confirm the three modes ("WordPress post tables", "High-performance order storage", "Enable compatibility mode" checkbox) are all available. If WooCommerce prompts for a data sync when switching, allow it to complete
- T00[X] T004 [P] Confirm `vendor/bin/phpcs` is available with the WordPress + WooCommerce sniffs installed. If absent, run `composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs woocommerce/woocommerce-sniffs` then `vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs,vendor/woocommerce/woocommerce-sniffs`
- T00[X] T005 Reproduce the pre-Phase-2 HPOS-enabled bug from `specs/02-woocommerce-hpos-compatibility/quickstart.md` § "Pre-fix repro". Switch the test site to HPOS-enabled (no sync), place an order, check "Disable Order Emails", click Update, reload, and confirm the checkbox renders **unchecked**. Also confirm the meta value did not persist by inspecting via `wp wc shop_order get <id> --field=meta_data` (the `_disable_order_emails` key should be absent). Skip this task if the Phase 0 risk inventory already documents this reproduction

**Checkpoint**: Environment ready. The pre-Phase-2 HPOS silent-breakage is confirmed reproducible on the current code.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Confirm Phase 1's modifications are present in the source. Phase 2 builds directly on the Phase 1 defensive guards in `includes/Core.php`. If Phase 1 is missing, the "before" snippets in Phase 3 will not match.

**⚠️ CRITICAL**: All implementation tasks (T008+) assume Phase 1 has been merged. If Phase 1's tasks T005-T029 have not been completed, complete them first.

- T00[X] T006 Verify Phase 1 has been applied by checking that `disable-emails-per-product-for-woocommerce.php` contains the line `* Requires Plugins: woocommerce` in its header docblock and the line `define('DEPPWC_PLUGIN_FILE', __FILE__);` in its constants block. If either is missing, complete Phase 1 first
- T00[X] T007 Verify Phase 1's recipient-filter modifications are present in `includes/Core.php` by confirming `Core::filter_woocommerce_order_email_recipient` matches the exact body quoted in T011 below. If it does not match (e.g., it still contains the raw `if (get_post_meta($order->get_id(), '_disable_order_emails', true))` without the Phase 1 guards), complete Phase 1 first

**Checkpoint**: Phase 1 baseline confirmed. Phase 2 implementation can begin.

---

## Phase 3: User Story 1 — Order-level email suppression works correctly under HPOS enabled (Priority: P1) 🎯 MVP

**Goal**: Migrate the order-level suppression code paths from legacy WP post-meta access to the WooCommerce order CRUD API so the save, read, and render paths all work correctly under HPOS-enabled storage. After this phase, every HPOS-enabled row of the QA matrix in `quickstart.md` passes.

**Independent Test**: Switch the test site to HPOS-enabled (no sync). Repeat the QA-2 scenario from `quickstart.md`: place a new order, check "Disable Order Emails", click Update, reload, and confirm the checkbox stays checked. Then trigger a status transition and confirm the customer-facing email is suppressed. Acceptance: checkbox persists, email is suppressed, no PHP warnings or fatals in `wp-content/debug.log`.

**Reference**: Guards G1, G2, G3 in `specs/02-woocommerce-hpos-compatibility/contracts/order-crud-access.md`. Hook registration change is in `specs/02-woocommerce-hpos-compatibility/contracts/external-hooks.md` § "Action: woocommerce_process_shop_order_meta". Edits in this phase touch `includes/Admin.php` and `includes/Core.php`.

### Implementation for User Story 1

- T00[X] T008 [US1] In `includes/Admin.php`, inside `Admin::__construct` (the method starting at line 8), find the exact line:

```php
		add_action('save_post_shop_order', [$this, 'save_disable_order_emails']);
```

and replace it with:

```php
		add_action('woocommerce_process_shop_order_meta', [$this, 'save_disable_order_emails']);
```

Preserve the surrounding tab/space indentation byte-for-byte. Do not touch any other line in the constructor. (Implements the hook replacement from `contracts/external-hooks.md` § "Action: save_post_shop_order — REMOVED IN PHASE 2".)

- T00[X] T009 [US1] In `includes/Admin.php`, replace the entire `disable_order_emails` method (currently lines 106–118) with the version below. Find the exact "before" block:

```php
	public function disable_order_emails($order): void
	{
		woocommerce_wp_checkbox(
			array(
				'id'            => '_disable_order_emails',
				'label'         => __('Disable Order Emails', 'disable-emails-per-product-for-woocommerce'),
				'description'   => 'Check this if you wish to disable emails when order status changes. Make sure to update the order after checking this box and before changing the status.',
				'wrapper_class' => 'form-field-wide',
				'style'         => 'width:auto',
			)
		);
		wp_nonce_field('disable_order_emails_action', 'disable_order_emails_nonce');
	}
```

and replace with:

```php
	public function disable_order_emails($order): void
	{
		$value = '';
		if ($order instanceof \WC_Order) {
			$value = $order->get_meta('_disable_order_emails');
		}

		woocommerce_wp_checkbox(
			array(
				'id'            => '_disable_order_emails',
				'value'         => $value,
				'cbvalue'       => 'yes',
				'label'         => __('Disable Order Emails', 'disable-emails-per-product-for-woocommerce'),
				'description'   => __('Check this if you wish to disable emails when order status changes. Make sure to update the order after checking this box and before changing the status.', 'disable-emails-per-product-for-woocommerce'),
				'wrapper_class' => 'form-field-wide',
				'style'         => 'width:auto',
			)
		);
		wp_nonce_field('disable_order_emails_action', 'disable_order_emails_nonce');
	}
```

Key changes:

1. Added the `$value = ''; if ($order instanceof \WC_Order) { $value = $order->get_meta('_disable_order_emails'); }` preface (implements INV-O5).
2. Added `'value' => $value,` to the checkbox args so `woocommerce_wp_checkbox` does not fall back to `get_post_meta(get_the_ID(), ...)`.
3. Added `'cbvalue' => 'yes',` to make the checked value explicit (matches what the save callback writes).
4. Wrapped the `description` string in `__()` for translatability. The English fallback text is byte-identical to the previous string.

Preserve the leading docblock comment above the method ("Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/" with its `@param $order` and `@return void` lines). (Implements guard G2 in `contracts/order-crud-access.md`.)

- [X] T010 [US1] In `includes/Admin.php`, replace the entire `save_disable_order_emails` method (currently lines 128–152) with the version below. Find the exact "before" block:

```php
	public function save_disable_order_emails($order_id): void
	{
		global $pagenow, $typenow;

		// Combine checks for page, post type, autosave, and nonce verification
		if (
			'post.php' !== $pagenow || 'shop_order' !== $typenow ||
			defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
			!isset($_POST['disable_order_emails_nonce']) ||
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['disable_order_emails_nonce'])), 'disable_order_emails_action')
		) {
			return;
		}

		// Ensure the current user has the capability to edit the order
		if (!current_user_can('edit_post', $order_id)) {
			return;
		}

		// Update or delete the meta based on whether _disable_order_emails is set
		if (isset($_POST['_disable_order_emails'])) {
			update_post_meta($order_id, '_disable_order_emails', sanitize_text_field($_POST['_disable_order_emails']));
		} else {
			delete_post_meta($order_id, '_disable_order_emails');
		}
	}
```

and replace with:

```php
	public function save_disable_order_emails($order_id): void
	{
		// Combine checks for autosave and nonce verification
		if (
			defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ||
			!isset($_POST['disable_order_emails_nonce']) ||
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['disable_order_emails_nonce'])), 'disable_order_emails_action')
		) {
			return;
		}

		// Ensure the current user has the capability to edit the order
		if (!current_user_can('edit_post', $order_id)) {
			return;
		}

		// Load the order through WooCommerce CRUD so the write is HPOS-safe
		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}

		// Update or delete the meta based on whether _disable_order_emails is set
		if (isset($_POST['_disable_order_emails'])) {
			$order->update_meta_data('_disable_order_emails', sanitize_text_field(wp_unslash($_POST['_disable_order_emails'])));
		} else {
			$order->delete_meta_data('_disable_order_emails');
		}
		$order->save_meta_data();
	}
```

Key changes:

1. Removed the `global $pagenow, $typenow;` line (no longer needed — the new hook implies admin form context).
2. Removed the `'post.php' !== $pagenow || 'shop_order' !== $typenow ||` portion of the gate (R2 in research.md).
3. Preserved the autosave check, the nonce check, and the capability check **verbatim**. Security posture is unchanged.
4. Added `wc_get_order($order_id)` hydration and the `instanceof \WC_Order` guard (implements INV-O4).
5. Replaced `update_post_meta` with `$order->update_meta_data` + `$order->save_meta_data()`.
6. Replaced `delete_post_meta` with `$order->delete_meta_data` + `$order->save_meta_data()`.
7. Added `wp_unslash` on `$_POST['_disable_order_emails']` before `sanitize_text_field` (was missing previously — fixes a latent WPCS warning).

Preserve the leading docblock comment above the method ("Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/" with its `@param $order_id` and `@return void` lines). (Implements guard G3 in `contracts/order-crud-access.md`.)

- [X] T011 [US1] In `includes/Core.php`, find the exact `filter_woocommerce_order_email_recipient` method body produced by Phase 1 (after T013 of Phase 1's tasks). The expected "before" block is:

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

Replace **only** the line `if (get_post_meta($order_id, '_disable_order_emails', true)) {` with `if (!empty($order->get_meta('_disable_order_emails'))) {`. Every other line, including blank lines and indentation, must remain byte-identical.

The expected "after" body is:

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

		if (!empty($order->get_meta('_disable_order_emails'))) {
			$recipient = '';
		}

		return $recipient;
	}
```

Note: `$order_id` is left in the body (used by the positive-ID guard above the suppression check) even though it is no longer the lookup key. Do not remove it. (Implements guard G1 in `contracts/order-crud-access.md` and INV-OE2 in `data-model.md`.)

- [X] T012 [US1] Run `php -l includes/Admin.php` and confirm it reports `No syntax errors detected in includes/Admin.php`. If it does not, re-check that braces are balanced and the docblock comments above the two replaced methods are still present
- [X] T013 [US1] Run `php -l includes/Core.php` and confirm it reports `No syntax errors detected in includes/Core.php`. If it does not, re-check that only the single line in T011 was changed and that the surrounding method body is intact
- [X] T014 [US1] Confirm by static grep that the old hook is gone and the new hook is registered. Run `grep -n "save_post_shop_order\|woocommerce_process_shop_order_meta" includes/Admin.php`. Expected output: exactly one match, on the line modified by T008, mentioning `woocommerce_process_shop_order_meta`. Zero matches for `save_post_shop_order`
- [X] T015 [US1] Confirm by static grep that no order-keyed legacy meta calls remain. Run `grep -n "_disable_order_emails" includes/`. Every match must be either: (a) inside a CRUD call (`get_meta`, `update_meta_data`, `delete_meta_data`), (b) inside the `'id' => '_disable_order_emails'` argument to `woocommerce_wp_checkbox`, or (c) inside an `isset($_POST['_disable_order_emails'])` check or a `$_POST['_disable_order_emails']` access. **Zero** matches may appear inside `get_post_meta(...)`, `update_post_meta(...)`, or `delete_post_meta(...)`
- [X] T016 [US1] On the test site, switch HPOS to **"High-performance order storage"** (with the compatibility-mode checkbox **off**). If WooCommerce prompts for a data sync, run it
- [X] T017 [US1] Execute the QA-1 scenario from `specs/02-woocommerce-hpos-compatibility/quickstart.md` under HPOS-enabled mode. Confirm: order edit screen renders, "Disable Order Emails" checkbox is visible, no warnings in `debug.log`
- [X] T018 [US1] Execute the QA-2 scenario (save & reload) under HPOS-enabled mode. Confirm: checkbox persists across reload, value is written to the HPOS tables (verify via `wp wc shop_order get <id> --field=meta_data` showing `_disable_order_emails` with value `yes`), no warnings
- [X] T019 [US1] Execute the QA-3 scenario (uncheck & reload) under HPOS-enabled mode. Confirm: after uncheck + save + reload, the checkbox is unchecked and the `_disable_order_emails` meta is absent from the CLI output
- [X] T020 [US1] Execute the QA-4 scenario (status change on a suppressed order) under HPOS-enabled mode. Confirm: customer-facing email is **not** sent (verify in MailHog/Mailpit); no warnings
- [X] T021 [US1] Execute the QA-5 scenario (status change on a non-suppressed order, regression check) under HPOS-enabled mode. Confirm: customer-facing email **is** sent; no warnings

**Checkpoint**: US1 is complete. Order-level suppression now works under HPOS-enabled storage. All HPOS-enabled QA rows pass.

---

## Phase 4: User Story 2 — Existing order-level suppression remains reliable under HPOS disabled (Priority: P1)

**Goal**: Confirm the Phase 3 code changes do not regress behavior on HPOS-disabled installations. No new code is written in this phase — US1's changes are intentionally storage-agnostic. This phase is QA-only and verifies that the migration is invisible to administrators on legacy installations.

**Independent Test**: Switch the test site to HPOS-disabled. Repeat the QA matrix scenarios that were exercised under US1 (QA-1 through QA-5) and confirm identical observable behavior to the pre-Phase-2 release. Acceptance: every scenario passes with zero PHP warnings or fatals.

**Reference**: `specs/02-woocommerce-hpos-compatibility/spec.md` § Story 2; `specs/02-woocommerce-hpos-compatibility/quickstart.md` QA-1 through QA-5.

### Implementation for User Story 2 (QA only — no code edits)

- [X] T022 [US2] On the test site, switch HPOS to **"WordPress post tables"** (legacy mode). If WooCommerce prompts for a data sync, run it. Confirm via WooCommerce → Status that "Custom Order Tables" reports as not active
- [X] T023 [US2] Execute the QA-1 scenario under HPOS-disabled mode (order edit screen render for an existing order). Confirm: screen renders, checkbox visible, no warnings
- [X] T024 [US2] Execute the QA-2 scenario under HPOS-disabled mode (save & reload on a new order). Confirm: checkbox persists, no warnings. Verify the value via `wp post meta get <order_id> _disable_order_emails` returns `yes`
- [X] T025 [US2] Execute the QA-3 scenario under HPOS-disabled mode (uncheck & reload). Confirm: checkbox is unchecked after reload and `wp post meta get <order_id> _disable_order_emails` returns an empty string
- [X] T026 [US2] Execute the QA-4 scenario under HPOS-disabled mode (status change on a suppressed order). Confirm: customer-facing email is **not** sent; no warnings
- [X] T027 [US2] Execute the QA-5 scenario under HPOS-disabled mode (status change on a non-suppressed order). Confirm: customer-facing email **is** sent; no warnings
- [X] T028 [US2] Execute the QA-6 scenario (cross-mode data continuity) end-to-end as written in `quickstart.md`. This involves switching modes mid-test. Confirm the same order's suppression configuration follows the data through HPOS-disabled → sync mode → HPOS-enabled. Each mode-switch sync MUST complete cleanly

**Checkpoint**: US2 is complete. HPOS-disabled installations observe zero behavioral regression versus the pre-Phase-2 plugin. Cross-mode data continuity is confirmed.

---

## Phase 5: User Story 3 — HPOS compatibility declaration matches observed behavior (Priority: P2)

**Goal**: Verify that the bootstrap's `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` call accurately reflects what the migrated code actually delivers. If every HPOS-enabled QA row from Phases 3 passes, the declaration remains. If any row fails or remains unresolved, the declaration MUST be removed per FR-007 before release. Update `readme.txt` with a changelog entry that honestly describes the outcome.

**Independent Test**: Inspect WooCommerce → Status → "Custom Order Tables" on the test site. The plugin's row reports the same compatibility state as written in the bootstrap. If declared compatible, every HPOS-enabled QA row in this task list (T017–T021, plus the sync-mode subset in Phase 6) has a recorded pass.

**Reference**: `specs/02-woocommerce-hpos-compatibility/contracts/hpos-declaration.md` — release-reviewer checklist. `specs/02-woocommerce-hpos-compatibility/spec.md` § FR-007.

### Implementation for User Story 3

- [X] T029 [US3] Read `specs/02-woocommerce-hpos-compatibility/contracts/hpos-declaration.md` § "Preconditions for retaining the declaration at release". Walk through the five preconditions one-by-one against the current source: (a) hook registration on `woocommerce_process_shop_order_meta` (verified by T014), (b) zero order-keyed legacy meta calls in `Admin.php` (verified by T015), (c) render path uses `$order->get_meta(...)` and passes `value` to `woocommerce_wp_checkbox` (verified by T009), (d) recipient filter uses `$order->get_meta(...)` (verified by T011), (e) every HPOS-enabled QA row passes (verified by T017–T021)
- [X] T030 [US3] Verify the bootstrap declaration is unchanged from Phase 1. Run `grep -n "declare_compatibility" disable-emails-per-product-for-woocommerce.php`. Expected: exactly one match, with the third argument `true`, inside the `before_woocommerce_init` callback. If the third argument is anything other than `true`, **stop** and confirm with the spec author whether the contingency removal was intentional
- [X] T031 [US3] If every precondition in T029 passes: the declaration remains. Skip to T033. If **any** precondition fails: follow the contingency procedure in `contracts/hpos-declaration.md` § "Removal procedure (contingency)". The preferred form is to delete the `add_action('before_woocommerce_init', ...)` block entirely and replace with a commented-out placeholder. Do **not** silently flip the third argument to `false` without also noting the reason in `readme.txt`
- [X] T032 [US3] Open `readme.txt` (at the repository root) and add a new changelog entry to the `== Changelog ==` section. The entry MUST honestly describe the outcome:

   - If the declaration is retained: add an entry such as `* HPOS: Migrated order-level email suppression to WooCommerce order CRUD. Verified compatible under HPOS enabled, disabled, and sync mode.`
   - If the declaration is removed (contingency): add an entry such as `* HPOS: Compatibility declaration temporarily removed pending completion of order-level suppression migration. See specs/02-woocommerce-hpos-compatibility for details.`

   Do **not** change the plugin version line in `disable-emails-per-product-for-woocommerce.php` or the `Stable tag:` line in `readme.txt`. Those are release-packaging concerns per constitution principle IX and are out of scope for the implementer
- [X] T033 [US3] On the test site, visit **WooCommerce → Status → Custom Order Tables**. Find the plugin's row. Confirm it reports "Compatible" (if the declaration was retained) or "Not compatible" (if the declaration was removed). Either outcome is acceptable per FR-007 provided it matches the source code

**Checkpoint**: US3 is complete. The declaration's source state, the runtime behavior, and the WooCommerce Status panel all agree.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Verify quality gates, exercise sync-mode QA, and re-validate the Constitution Check now that all implementation is complete.

- [X] T034 [P] Run `php -l includes/Admin.php` and `php -l includes/Core.php` one final time. Each must report `No syntax errors detected`. Also run `php -l disable-emails-per-product-for-woocommerce.php` to confirm the bootstrap is intact (it should be byte-identical to its Phase 1 state)
- [X] T035 [P] Run `vendor/bin/phpcs --standard=WordPress,WooCommerce includes/Admin.php includes/Core.php`. Compare the warning + error counts to the Phase 1 post-implementation baseline. Acceptance: counts on `Admin.php` and `Core.php` must **not exceed** Phase 1's counts. The `wp_unslash` fix in T010 may cause counts to **decrease** — that is an improvement and is acceptable
- [X] T036 On the test site, switch HPOS to **"High-performance order storage"** with the compatibility-mode checkbox **on** (sync mode). Allow the data sync to complete
- [X] T037 Execute the QA-1 scenario under sync mode. Confirm: order edit screen renders cleanly; no warnings
- [X] T038 Execute the QA-2 scenario under sync mode (save & reload). Confirm: checkbox persists; both `wp wc shop_order get <id> --field=meta_data` and `wp post meta get <order_id> _disable_order_emails` show the value (WooCommerce's sync writes to both stores)
- [X] T039 Execute the QA-3 scenario under sync mode (uncheck & reload). Confirm: checkbox unchecks; both stores reflect the deletion after sync
- [X] T040 Execute the QA-4 scenario under sync mode (status change on a suppressed order). Confirm: customer-facing email is **not** sent; no warnings
- [X] T041 Execute the QA-7 scenario (refunded order with suppression) under each HPOS mode that you have not yet tested it under. Confirm: refund processes cleanly; no fatals; refund email behavior follows the documented contract
- [X] T042 Execute the QA-8 scenario (guest checkout under HPOS enabled). Confirm: product-level suppression still applies (Phase 1's product-meta read path is HPOS-irrelevant and must keep working); no warnings
- [X] T043 Execute the QA-9 scenario (order containing a deleted product under HPOS enabled). This is a regression check that Phase 1's deleted-product guard continues to work under HPOS storage. Confirm: no fatal error; suppression on a surviving suppressed product still fires
- [X] T044 Execute the QA-10 scenario (debug.log audit). Inspect `wp-content/debug.log` and confirm zero plugin-originated warnings, notices, or fatals appear from any of the QA scenarios run during this phase
- [X] T045 Execute the QA-11 scenario (WooCommerce Status panel reflects declared state). Confirm the panel matches the bootstrap declaration's third argument
- [X] T046 Tick every checkbox in `specs/02-woocommerce-hpos-compatibility/quickstart.md` § "Acceptance checklist for the reviewer" that this phase's work satisfies. Anything left unchecked is a blocker for closing Phase 2
- [X] T047 Re-evaluate the Constitution Check table in `specs/02-woocommerce-hpos-compatibility/plan.md` § "Post-Design Re-check" against the *implemented* code. Pay particular attention to principle V (HPOS Safety, non-negotiable) — confirm declaration retention is justified by the QA matrix outcomes. If any row downgrades, document the cause and either fix or escalate per `.specify/memory/constitution.md` § Governance
- [X] T048 Confirm by inspection that no out-of-scope code was modified. Files that MUST be byte-identical to their Phase 1 post-implementation state: `disable-emails-per-product-for-woocommerce.php`, `includes/GlobalView.php`. Files that MUST contain only the Phase 2 changes plus the Phase 1 changes: `includes/Admin.php`, `includes/Core.php`. Files that MUST contain only a single changelog-line addition: `readme.txt`
- [X] T049 Confirm by inspection that no new public filters were introduced. Run `grep -n "apply_filters" includes/` and `grep -n "apply_filters" disable-emails-per-product-for-woocommerce.php`. The only match must be `apply_filters('dwepp_disable_global_view', false)` in the bootstrap (unchanged from Phase 1)

**Checkpoint**: All quality gates pass. Phase 2 is ready for review and release packaging.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies. Can start immediately.
- **Foundational (Phase 2)**: Depends on Setup completion AND on Phase 1 of the roadmap (`specs/01-runtime-safety-stabilization/`) being merged.
- **User Story 1 (Phase 3)**: Depends on Foundational completion. Edits `includes/Admin.php` and `includes/Core.php`.
- **User Story 2 (Phase 4)**: Depends on US1 completion (US2 is QA-only and validates US1's edits did not regress HPOS-disabled). Cannot run in parallel with US1.
- **User Story 3 (Phase 5)**: Depends on US1 completion AND US2 completion (the declaration's retention is conditional on QA outcomes). Cannot run in parallel with US1/US2.
- **Polish (Phase 6)**: Depends on all three user stories being complete.

### Within Each User Story

- US1 source edits (`includes/Admin.php`, `includes/Core.php`) come first.
- `php -l` lint check immediately after each source edit.
- Static greps (T014, T015) before any QA.
- Manual QA scenarios from `quickstart.md` come after the static greps pass.
- US2 and US3 are QA-driven and do not edit source.

### Parallel Opportunities

- T002, T003, T004 (Phase 1) can run in parallel — all are independent environment checks.
- T012 and T013 (Phase 3 lints) can run in parallel — different files, independent commands.
- T034 and T035 (Phase 6 lint + WPCS) can run in parallel — independent commands.
- Within Phase 3, T009 and T011 touch different files (`Admin.php` vs. `Core.php`) and could in principle be done in parallel by two implementers, but a single LLM should do them sequentially to avoid context confusion.
- US1 source edits (T008, T009, T010, T011) MUST be sequential within `Admin.php` (T008, T009, T010 touch the same file). T011 touches a different file and can be inserted anywhere in the sequence.

---

## Parallel Example: US1 split across two implementers

```text
# After Phase 2 (T006, T007) completes, the following can be split:

# Track A (Admin.php — US1 hook + render + save):
T008 → T009 → T010 → T012 → T014

# Track B (Core.php — US1 read migration):
T011 → T013 → T015

# Then converge for QA:
T016 → T017 → T018 → T019 → T020 → T021

# Then US2 (Phase 4):
T022 → T023 → T024 → T025 → T026 → T027 → T028

# Then US3 (Phase 5):
T029 → T030 → T031 → T032 → T033

# Then Phase 6 polish:
T034 → T035 → T036 → ... → T049
```

A single-developer (or single-LLM) execution follows strict T001 → T049 order.

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001–T005).
2. Complete Phase 2: Foundational (T006–T007).
3. Complete Phase 3: US1 (T008–T021).
4. **STOP and VALIDATE**: Confirm every HPOS-enabled QA row passes.
5. If only US1 ships, HPOS-enabled stores now have working order-level suppression. US2 (HPOS-disabled regression QA) and US3 (declaration verification) should still complete before release tagging — they are short and depend on US1's work being stable.

### Incremental Delivery

1. Phase 1 + Phase 2 → foundation ready.
2. Phase 3 (US1) → HPOS-enabled save and read work. Code-complete.
3. Phase 4 (US2) → HPOS-disabled regression confirmed clean. QA-complete.
4. Phase 5 (US3) → declaration honesty verified; `readme.txt` updated.
5. Phase 6 → lint + WPCS + sync-mode QA + constitution re-check → ready for release packaging.

### Single-Developer Strategy (Default for this Phase)

Phase 2 is small (≤40 LOC delta projected across two files) and is most safely executed by a single developer in strict task order T001 → T049. The "parallel" execution paths above exist so that two LLM passes or two developers can split the work safely along the `Admin.php` / `Core.php` file boundary.

---

## Notes for the implementer

- **No new runtime dependencies**. Do not run `composer require` for runtime packages. T004 may install dev-only `phpcs` + sniffs if not already present.
- **No new feature work**. This phase is purely a behavior-preserving migration. If you find yourself adding a new filter, a new admin screen, or a new setting — stop. That belongs to a later phase.
- **No HPOS-detection branching**. Do not add `if (OrderUtil::custom_orders_table_usage_is_enabled())` or similar. CRUD's whole point is that the plugin code is identical regardless of mode (R8 in research.md).
- **No product-meta migration**. The line `get_post_meta($product_id, '_disabled_emails', true)` in `Core::filter_woocommerce_email_recipient` is **intentionally** preserved (G5 in `contracts/order-crud-access.md`). Do not migrate it.
- **No GlobalView changes**. The direct `$wpdb` query in `includes/GlobalView.php::get_products_with_disabled_emails` is intentionally preserved (G6). Phase 3 of the roadmap owns admin settings hardening.
- **No bootstrap changes**. Do not touch `disable-emails-per-product-for-woocommerce.php` in this phase. The HPOS declaration block stays exactly as Phase 1 left it (unless T031's contingency removal applies).
- **Preserve security posture**. Every nonce action name, nonce field name, and capability check must match Phase 1 verbatim. The migration does not relax authentication or authorization.
- **Preserve translation strings**. The English label and description in `Admin::disable_order_emails` are byte-identical to the pre-migration values; only the `description` string gains an `__()` wrapper. Existing translations remain valid because the source string is unchanged.
- **Commit cadence**: commit after each phase completes (after T005, T007, T021, T028, T033, T049). Use the conventional commit prefix already established in this repo (see `git log` for prior style).
- **Do not bump the plugin version header**. Release packaging (version bump + readme.txt `Stable tag` update) is a separate manual step per constitution principle IX. The implementer is permitted to add the changelog *entry* (T032) but must not edit the version line.
- **If a task fails**, stop. Do not skip ahead. Re-read the referenced contract or research entry before retrying. `contracts/order-crud-access.md`, `contracts/external-hooks.md`, and `contracts/hpos-declaration.md` are the authoritative tie-breakers.
- **If a "before" snippet does not match the file character-for-character**, do not "approximate" the match. Re-read T006/T007 to confirm Phase 1 is fully applied. If Phase 1 is not applied, stop and apply Phase 1 first.
