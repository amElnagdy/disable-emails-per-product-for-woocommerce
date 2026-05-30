---

description: "Task list for Phase 4 — Extensibility & Internal API Cleanup. Tasks are written for direct execution: each task includes exact file path, anchor (current source line range), verbatim before/after snippets, and references to the contracts that own the behavior. A cheaper LLM should be able to execute these tasks one at a time without consulting external context."

---

# Tasks: Extensibility & Internal API Cleanup

**Input**: Design documents from `specs/04-extensibility-and-api-cleanup/`

**Prerequisites**: `plan.md` (loaded), `spec.md` (loaded), `research.md` (loaded), `data-model.md` (loaded), `contracts/filter-surface.md` (loaded), `contracts/internal-helpers.md` (loaded), `contracts/identifier-naming.md` (loaded), `quickstart.md` (loaded)

**Tests**: Manual QA per `quickstart.md` only. Automated tests are explicitly out of scope per `spec.md` § Assumptions — that work belongs to Phase 5. Do **not** add PHPUnit or any other automated test harness in this phase.

**Organization**: Tasks are grouped by user story so each story can be implemented and verified independently. Every task names the exact file path; most include verbatim source anchors and replacement snippets.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps to a user story from `spec.md` (US1, US2, US3, US4)
- Each task names the exact file path and (where applicable) the current line range and the exact replacement text

## Path Conventions

WordPress plugin project. All implementation files live at the repository root or under `includes/`. There is no `src/` or `tests/` directory.

- **Plugin bootstrap**: `disable-emails-per-product-for-woocommerce.php` (READ-ONLY in Phase 4)
- **Plugin classes**: `includes/Core.php`, `includes/Admin.php`, `includes/GlobalView.php`, and the new `includes/Helpers.php`
- **Composer autoload**: `composer.json` (no edit required at dev time; release packaging runs `composer dump-autoload -o`)
- **Documentation**: `README.md`, `readme.txt`

## Document references the implementer should keep open

- `specs/04-extensibility-and-api-cleanup/contracts/filter-surface.md` — F1–F5 filter signatures, defaults, validation, fallback rules (definitive)
- `specs/04-extensibility-and-api-cleanup/contracts/internal-helpers.md` — H1–H6 helper method signatures and behavior (definitive)
- `specs/04-extensibility-and-api-cleanup/contracts/identifier-naming.md` — prefix conventions, preserved identifiers, rejected renames
- `specs/04-extensibility-and-api-cleanup/research.md` — R1–R12 decisions
- `specs/04-extensibility-and-api-cleanup/quickstart.md` — manual QA matrix and verification drills V1–V7
- `specs/04-extensibility-and-api-cleanup/data-model.md` — invariants INV-FS1..5 and INV-HL1..4

## Invariants the implementer MUST preserve

- **INV-FS1**: Filter return-value validation is silent on the plugin side — never raise PHP warnings/notices from validation code.
- **INV-FS2**: No `apply_filters('dwepp_*', ...)` call inside any `__construct()` method or hook registration site.
- **INV-FS3**: The two meta-key filters are applied at every read/write site for their meta. Default values are `'_disabled_emails'` and `'_disable_order_emails'`.
- **INV-FS5**: With every new filter at its default, observable behavior is byte-identical to Phase 3.
- **INV-HL1**: Each of the four duplicated patterns appears at exactly ONE definition site after this phase (the `Helpers::*` method). Verification: `grep` per `quickstart.md` § V5.
- **INV-HL2**: Helpers return safe sentinels on every error path. No PHP warnings/notices/fatals from helper internals.
- **INV-HL3**: Helpers are read-only — no `update_post_meta`, `delete_post_meta`, or other state-mutating WP API calls inside `includes/Helpers.php`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm prerequisites are in place before any source change.

- [X] T001 Verify `WP_DEBUG = true`, `WP_DEBUG_LOG = true`, `WP_DEBUG_DISPLAY = false` are set in the test site's `wp-config.php` per `specs/04-extensibility-and-api-cleanup/quickstart.md` § Prerequisites
- [X] T002 [P] Truncate `wp-content/debug.log` so any new warning/notice/fatal raised during this phase is attributable
- [X] T003 [P] Confirm `vendor/bin/phpcs` is available; if absent, install with `composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs woocommerce/woocommerce-sniffs` and configure the installed paths
- [X] T004 [P] Confirm the current branch is `04-extensibility-and-api-cleanup` by running `git branch --show-current`. If not on this branch, switch to it before proceeding.
- [X] T005 [P] Verify Composer's existing PSR-4 entry `"DisableEmailsPerProductForWooCommerce\\": "includes/"` is present in `composer.json` (this entry is what auto-loads the new `Helpers` class added in Phase 2; no edit to `composer.json` is required at dev time)

**Checkpoint**: Environment ready. The current code reflects the Phase 3 baseline.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Create the `Helpers` class file and stub each of the five required static methods with the correct signature and a safe default return. Subsequent stories will add filter dispatch and behavior. Stubbing first lets the consumer-side edits in `Core.php`, `Admin.php`, and `GlobalView.php` compile and run from the moment they switch to calling helpers.

**⚠️ CRITICAL**: All four story phases (US1, US2, US3, US4) depend on this phase. Do not begin US1–US4 until this checkpoint is reached.

Reference: `specs/04-extensibility-and-api-cleanup/contracts/internal-helpers.md` (H1–H6) and `data-model.md` § E6.

- [X] T006 Create the new file `includes/Helpers.php` with the following exact contents:

```php
<?php

namespace DisableEmailsPerProductForWooCommerce;

/**
 * Internal utility class consolidating duplicated patterns across Admin, Core,
 * and GlobalView.
 *
 * NOT part of the plugin's public API — use the dwepp_* filters documented in
 * README.md for extension. Method signatures may evolve in patch releases.
 *
 * @internal
 */
class Helpers
{
    /**
     * Return all enabled WooCommerce mailer email instances.
     *
     * @internal
     * @return \WC_Email[] Array of enabled WC_Email instances (may be empty).
     */
    public static function get_enabled_emails(): array
    {
        if (!function_exists('WC') || !WC() || !WC()->mailer()) {
            return [];
        }

        $mailer = WC()->mailer()->get_emails();
        if (!is_array($mailer)) {
            return [];
        }

        $enabled = [];
        foreach ($mailer as $email) {
            if ($email instanceof \WC_Email && $email->is_enabled()) {
                $enabled[] = $email;
            }
        }
        return $enabled;
    }

    /**
     * Resolve the per-product disabled-emails meta key, honoring the
     * dwepp_disabled_emails_meta_key filter with silent fallback.
     *
     * @internal
     * @return string The resolved meta key (default '_disabled_emails').
     */
    private static function resolve_product_meta_key(): string
    {
        $default = '_disabled_emails';
        /**
         * Filter the WordPress post-meta key used to store per-product disabled-email
         * configuration. Applied at every read site (Helpers) and the single write
         * site (Admin::save_disabled_emails).
         *
         * @since {next-release-version}
         *
         * @param string $meta_key The current meta key. Default: '_disabled_emails'.
         */
        $candidate = apply_filters('dwepp_disabled_emails_meta_key', $default);
        if (!is_string($candidate) || $candidate === '' || preg_match('/\s/', $candidate)) {
            return $default;
        }
        return $candidate;
    }

    /**
     * Resolve the per-order disable-emails meta key, honoring the
     * dwepp_disable_order_emails_meta_key filter with silent fallback.
     *
     * @internal
     * @return string The resolved meta key (default '_disable_order_emails').
     */
    private static function resolve_order_meta_key(): string
    {
        $default = '_disable_order_emails';
        /**
         * Filter the WordPress post-meta key used to store the per-order disable-emails
         * flag. Applied at the read site (Helpers) and the single write site
         * (Admin::save_disable_order_emails).
         *
         * @since {next-release-version}
         *
         * @param string $meta_key The current meta key. Default: '_disable_order_emails'.
         */
        $candidate = apply_filters('dwepp_disable_order_emails_meta_key', $default);
        if (!is_string($candidate) || $candidate === '' || preg_match('/\s/', $candidate)) {
            return $default;
        }
        return $candidate;
    }

    /**
     * Return the persisted disabled-emails configuration for a product.
     *
     * @internal
     * @param int $product_id Product (post) ID. Non-positive values return [].
     * @return array<string, string> Map of email ID => 'yes'. Returns [] when meta missing or malformed.
     */
    public static function get_product_disabled_emails(int $product_id): array
    {
        if ($product_id <= 0) {
            return [];
        }
        $meta_key = self::resolve_product_meta_key();
        $value = get_post_meta($product_id, $meta_key, true);
        if (!is_array($value)) {
            return [];
        }
        return $value;
    }

    /**
     * Return whether the per-order disable-emails flag is set for the order.
     *
     * @internal
     * @param int $order_id Order (post) ID. Non-positive values return false.
     * @return bool True when the flag is set; false otherwise.
     */
    public static function is_order_emails_disabled(int $order_id): bool
    {
        if ($order_id <= 0) {
            return false;
        }
        $meta_key = self::resolve_order_meta_key();
        $value = get_post_meta($order_id, $meta_key, true);
        return (bool) $value;
    }

    /**
     * Return the product IDs that have saved per-product suppression configuration.
     *
     * @internal
     * @global \wpdb $wpdb
     * @return int[] Array of positive integer post IDs. May be empty.
     */
    public static function query_products_with_disabled_emails(): array
    {
        global $wpdb;
        $meta_key = self::resolve_product_meta_key();

        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        );
        $rows = $wpdb->get_col($query);
        if (!is_array($rows)) {
            $rows = [];
        }

        // Preserve Phase 3 INV-AR2: drop rows whose stored array contains no 'yes' value.
        $surviving = [];
        foreach ($rows as $post_id) {
            $post_id_int = (int) $post_id;
            if ($post_id_int <= 0) {
                continue;
            }
            $config = self::get_product_disabled_emails($post_id_int);
            if (!empty($config) && in_array('yes', $config, true)) {
                $surviving[] = $post_id_int;
            }
        }

        /**
         * Filter the resolved list of product IDs displayed on the global view
         * overview table.
         *
         * @since {next-release-version}
         *
         * @param int[] $product_ids Array of post IDs resolved by the default query.
         */
        $filtered = apply_filters('dwepp_global_view_products_query', $surviving);
        if (!is_array($filtered)) {
            $filtered = $surviving;
        }

        $clean = [];
        foreach ($filtered as $candidate) {
            $candidate_int = (int) $candidate;
            if ($candidate_int > 0) {
                $clean[] = $candidate_int;
            }
        }
        return array_values($clean);
    }

    /**
     * Return the rendered HTML for the global view "Products with Disabled Emails"
     * overview table. Returns the translated empty-state message when no products
     * with saved configuration are found.
     *
     * @internal
     * @return string Rendered HTML table or empty-state message.
     */
    public static function render_disabled_emails_overview_table(): string
    {
        $product_ids = self::query_products_with_disabled_emails();
        if (empty($product_ids)) {
            return esc_html__('No products found with disabled emails.', 'disable-emails-per-product-for-woocommerce');
        }

        $table  = '<table class="widefat">';
        $table .= '<thead><tr><th class="name">' . esc_html__('Product Name', 'disable-emails-per-product-for-woocommerce') . '</th>';
        $table .= '<th class="name">' . esc_html__('Disabled Emails', 'disable-emails-per-product-for-woocommerce') . '</th>';
        $table .= '<th class="name">' . esc_html__('Edit Product', 'disable-emails-per-product-for-woocommerce') . '</th></tr></thead>';
        $table .= '<tbody>';

        $rendered_any = false;
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product instanceof \WC_Product) {
                continue;
            }
            $rendered_any = true;
            $config = self::get_product_disabled_emails($product_id);
            $disabled_keys = is_array($config) ? array_keys($config, 'yes', true) : [];
            $disabled_list = implode(', ', $disabled_keys);
            $edit_link = get_edit_post_link($product_id);

            $table .= '<tr>';
            $table .= '<td>' . esc_html($product->get_name()) . '</td>';
            $table .= '<td>' . esc_html($disabled_list) . '</td>';
            $table .= '<td><a href="' . esc_url($edit_link) . '">' . esc_html__('Edit', 'disable-emails-per-product-for-woocommerce') . '</a></td>';
            $table .= '</tr>';
        }

        $table .= '</tbody></table>';

        if (!$rendered_any) {
            return esc_html__('No products found with disabled emails.', 'disable-emails-per-product-for-woocommerce');
        }

        return $table;
    }
}
```

- [X] T007 Run `php -l includes/Helpers.php` and confirm it reports `No syntax errors detected in includes/Helpers.php`
- [X] T008 [P] Verify the `Helpers` class is auto-loadable: open a PHP REPL or write a one-line WP-CLI eval — `wp eval 'var_dump(class_exists("\DisableEmailsPerProductForWooCommerce\Helpers"));'` — and confirm it prints `bool(true)`. If `false`, run `composer dump-autoload -o` and retry.

**Checkpoint**: `Helpers` class exists with all five static methods. The two meta-key filters (`dwepp_disabled_emails_meta_key`, `dwepp_disable_order_emails_meta_key`) are already wired in `Helpers::resolve_product_meta_key` and `Helpers::resolve_order_meta_key` and produce default behavior when no integrator callback is registered. The global-view filter (`dwepp_global_view_products_query`) is also wired in `Helpers::query_products_with_disabled_emails`. The remaining two filters (US1, US2) and the call-site migrations (US3) will be wired in later phases.

⚠️ **DO NOT call any `Helpers::` method from `Admin.php`, `Core.php`, or `GlobalView.php` yet** — those edits are deliberately deferred to US3 to keep the foundational phase contained.

---

## Phase 3: User Story 1 — Filterable excluded email IDs (Priority: P1) 🎯 MVP

**Goal**: Add the `dwepp_excluded_email_ids` public filter at the existing exclusion site in `Admin::add_product_tab_content`. After this phase, an integrator can register `add_filter('dwepp_excluded_email_ids', ...)` and immediately see the per-product UI reflect the customization.

**Independent Test**: Open WP Admin → Products → edit a product → "Disable Emails" tab. With no integrator filter, every enabled email except `customer_new_account`, `customer_reset_password`, `customer_note` is shown (baseline). Register `add_filter('dwepp_excluded_email_ids', '__return_empty_array');` in a mu-plugin; reload the product. Every enabled email — including the three previously hardcoded — is now shown.

**Reference**: `contracts/filter-surface.md` § F1.

### Implementation for User Story 1

- [X] T009 [US1] In `includes/Admin.php`, locate `Admin::add_product_tab_content` (currently lines 43–72). The current method body contains the hardcoded exclusion array at line 50:

  ```php
  $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
  ```

  Replace **that single line** with this exact block (preserving the variable name `$non_related_emails` so the existing `in_array` check at line 53 continues to work):

  ```php
  /**
   * Filter the list of WooCommerce email IDs excluded from the per-product
   * "Disable Emails" configuration UI.
   *
   * @since {next-release-version}
   *
   * @param string[] $excluded_ids Array of WooCommerce email ID strings to exclude.
   *                               Default: ['customer_new_account',
   *                               'customer_reset_password', 'customer_note'].
   */
  $non_related_emails = apply_filters(
      'dwepp_excluded_email_ids',
      ['customer_new_account', 'customer_reset_password', 'customer_note']
  );
  if (!is_array($non_related_emails)) {
      $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
  }
  $non_related_emails = array_values(array_filter($non_related_emails, 'is_string'));
  ```

- [X] T010 [US1] Run `php -l includes/Admin.php` and confirm `No syntax errors detected`.
- [X] T011 [US1] Manual verify per `quickstart.md` § Scenario 1, Column D: open a product's "Disable Emails" tab; the rendered checkboxes exclude the three default email IDs.
- [X] T012 [US1] Manual verify per `quickstart.md` § Scenario 1, Column E: register `add_filter('dwepp_excluded_email_ids', '__return_empty_array');` in `wp-content/mu-plugins/dwepp-qa.php`; reload the product; the rendered checkboxes now include `customer_new_account`, `customer_reset_password`, and `customer_note`.
- [X] T013 [US1] Manual verify malformed-callback fallback per `quickstart.md` § V2a–V2b: register a callback returning a string, then a callback returning an array with non-string entries; in both cases the rendered checkboxes match the default (column D) and `wp-content/debug.log` contains zero plugin-originated entries.

**Checkpoint**: User Story 1 is fully functional and testable independently. The hardcoded exclusion list at `Admin.php:50` is now filterable via `dwepp_excluded_email_ids`.

---

## Phase 4: User Story 2 — Filterable product-configurable email list (Priority: P1)

**Goal**: Add the `dwepp_product_configurable_emails` public filter in `Admin::add_product_tab_content` between the exclusion-list step and the render loop. After this phase, integrators can reorder, filter, or replace the full per-product configurable list at a per-product granularity.

**Independent Test**: With no integrator filter, the rendered checkboxes match the US1 baseline. Register `add_filter('dwepp_product_configurable_emails', '__return_empty_array');`; reload the product edit screen; the "Disable Emails" tab renders with zero checkboxes (graceful empty state) and no PHP warnings/notices.

**Reference**: `contracts/filter-surface.md` § F2.

### Implementation for User Story 2

- [X] T014 [US2] In `includes/Admin.php`, the `Admin::add_product_tab_content` method currently performs the per-email iteration as:

  ```php
  $mailer             = WC()->mailer()->get_emails();
  $non_related_emails = apply_filters( … );  // ← from T009
  // (validation lines from T009 …)

  foreach ($mailer as $email) {
      if ($email->is_enabled() && !in_array($email->id, $non_related_emails)) {
          woocommerce_wp_checkbox([ … ]);
      }
  }
  ```

  Refactor this block so the exclusion is applied **before** the filter, the filter is applied to the post-exclusion list, and the render loop iterates the filter output. Replace the block from the `$mailer = ...` line (currently line 49) through the closing brace of the `foreach` loop (currently line 67) with this exact code:

  ```php
  $mailer             = WC()->mailer()->get_emails();
  /**
   * Filter the list of WooCommerce email IDs excluded from the per-product
   * "Disable Emails" configuration UI.
   *
   * @since {next-release-version}
   *
   * @param string[] $excluded_ids Array of WooCommerce email ID strings to exclude.
   *                               Default: ['customer_new_account',
   *                               'customer_reset_password', 'customer_note'].
   */
  $non_related_emails = apply_filters(
      'dwepp_excluded_email_ids',
      ['customer_new_account', 'customer_reset_password', 'customer_note']
  );
  if (!is_array($non_related_emails)) {
      $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
  }
  $non_related_emails = array_values(array_filter($non_related_emails, 'is_string'));

  $configurable = [];
  if (is_array($mailer)) {
      foreach ($mailer as $email) {
          if (!($email instanceof \WC_Email) || !$email->is_enabled()) {
              continue;
          }
          if (in_array($email->id, $non_related_emails, true)) {
              continue;
          }
          $configurable[] = $email;
      }
  }

  $product_id = (int) get_the_ID();
  /**
   * Filter the list of WC_Email instances offered for per-product suppression
   * configuration after the dwepp_excluded_email_ids exclusion list has been applied.
   *
   * @since {next-release-version}
   *
   * @param \WC_Email[] $emails     Enabled emails minus the exclusion list.
   * @param int         $product_id Product whose configuration UI is being rendered.
   */
  $configurable_filtered = apply_filters('dwepp_product_configurable_emails', $configurable, $product_id);
  if (!is_array($configurable_filtered)) {
      $configurable_filtered = $configurable;
  }

  foreach ($configurable_filtered as $email) {
      if (!($email instanceof \WC_Email)) {
          continue;
      }
      woocommerce_wp_checkbox([
          'id'          => 'dwepp_disabled_emails[' . $email->id . ']',
          'label'       => $email->title,
          'value'       => $saved_emails[$email->id] ?? 'no',
          'cbvalue'     => 'yes',
          'desc_tip'    => true,
          'description' => sprintf(
              /* translators: %s: email title */
              esc_html__('Check to disable %s email for this product.', 'disable-emails-per-product-for-woocommerce'),
              esc_html($email->title)
          ),
      ]);
  }
  ```

  **Important**: this snippet supersedes the change introduced by T009. After T014 the `dwepp_excluded_email_ids` filter appears exactly once in `Admin::add_product_tab_content`, and `dwepp_product_configurable_emails` appears exactly once immediately after it. **Do not leave a duplicate `apply_filters('dwepp_excluded_email_ids', …)` call from T009 in the file.**

- [X] T015 [US2] Run `php -l includes/Admin.php` and confirm `No syntax errors detected`.
- [X] T016 [US2] Manual verify per `quickstart.md` § Scenario 1, Column D: with no integrator filter, the rendered checkboxes are identical to the Phase 3 baseline.
- [X] T017 [US2] Manual verify per `quickstart.md` § Scenario 1, Column C: register `add_filter('dwepp_product_configurable_emails', '__return_empty_array');`; reload the product; the "Disable Emails" tab renders with zero checkboxes and zero PHP warnings in `debug.log`.
- [X] T018 [US2] Manual verify malformed-callback fallback per `quickstart.md` § V2c–V2d: register a callback returning `null`, then one returning an array with non-`WC_Email` entries; in both cases the non-conforming entries are silently dropped and the remaining list renders correctly with zero PHP warnings.

**Checkpoint**: User Story 2 is fully functional. Two new public filters (`dwepp_excluded_email_ids`, `dwepp_product_configurable_emails`) are wired in `Admin::add_product_tab_content`.

---

## Phase 5: User Story 3 — Internal helpers eliminate duplicated logic (Priority: P2)

**Goal**: Migrate every duplicated read site in `Admin.php`, `Core.php`, and `GlobalView.php` to call the `Helpers::*` static methods created in Phase 2. After this phase, the four duplicated patterns identified in `spec.md` Story 3 appear at exactly one definition site each (`Helpers`), satisfying INV-HL1 and SC-001.

**Independent Test**: Run the V5 single-definition-site greps in `quickstart.md`. Each must return the expected single-site result. With every new filter at its default, exercise Scenarios 1–8 across Columns D, E, C, M; observable behavior is byte-identical to the post-US2 baseline.

**Reference**: `contracts/internal-helpers.md` (H2–H6) and `data-model.md` § INV-HL1.

### Implementation for User Story 3 — `includes/Core.php` migration

- [X] T019 [US3] In `includes/Core.php`, add the `use` statement at the top of the file. Locate line 3 which reads `namespace DisableEmailsPerProductForWooCommerce;`. Immediately after line 3 (so before the blank line preceding `class Core`), insert:

  ```php

  use DisableEmailsPerProductForWooCommerce\Helpers;
  ```

  (Result: `namespace …;` on line 3, blank line on 4, `use … Helpers;` on line 5, blank line on 6, `class Core` later.)

- [X] T020 [US3] In `includes/Core.php`, locate `Core::init` (currently lines 13–28). Replace the entire body (lines 15–27) with:

  ```php
      $emails = Helpers::get_enabled_emails();
      foreach ($emails as $email) {
          add_filter('woocommerce_email_recipient_' . $email->id, [
              $this,
              'filter_woocommerce_email_recipient'
          ], 10, 3);
          add_filter('woocommerce_email_recipient_' . $email->id, [
              $this,
              'filter_woocommerce_order_email_recipient'
          ], 9999, 2);
      }
  ```

  (This replaces the inline `$mailer = WC()->mailer()->get_emails(); foreach (...) { if ($email->is_enabled()) { ... } }` with a single helper call. INV-HL1 enforces this is the only `WC()->mailer()->get_emails()` call site outside `Helpers`.)

- [X] T021 [US3] In `includes/Core.php`, locate `Core::filter_woocommerce_email_recipient` (currently lines 30–52). Find the line that reads:

  ```php
  $disabled_emails = get_post_meta($product_id, '_disabled_emails', true);
  ```

  (currently line 43) and replace **that single line** with:

  ```php
  $disabled_emails = Helpers::get_product_disabled_emails((int) $product_id);
  ```

  Also, the surrounding code currently does `$product = $item->get_product();` and then `$product->is_type('variation') ? $product->get_parent_id() : $product->get_id();` without checking that `$product` is a `WC_Product` (Phase 1 was supposed to add this guard — if it is not already present, add it here). The complete refactored loop should read:

  ```php
      foreach ($order->get_items() as $key => $item) {
          $product = $item->get_product();
          if (!$product instanceof \WC_Product) {
              continue;
          }

          $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

          $disabled_emails = Helpers::get_product_disabled_emails((int) $product_id);

          if (isset($disabled_emails[$email_instance->id]) && $disabled_emails[$email_instance->id] === 'yes') {
              $recipient = '';
              break;
          }
      }
  ```

  **Note on the suppression check**: the Phase 3 code at `Core.php:45` used `is_array($disabled_emails) && isset($disabled_emails[$email_instance->id])`. The new helper guarantees an array return, so the `is_array` check is unnecessary. Additionally, the original code suppressed on *any* set value (including `'no'`); the new check additionally requires the value to equal `'yes'` to align with the saved-data shape (the save path writes `'yes'` for ticked checkboxes and omits the key otherwise). **This is a behavior change** in the corner case of a corrupted meta row containing `'no'` values — preserve the original behavior unless explicitly approving the tightening. If preserving the exact Phase 3 condition is required, use `if (isset($disabled_emails[$email_instance->id])) { … }` instead.

  ⚠️ **Decision**: Use the stricter check (`=== 'yes'`) only if `data-model.md` or `research.md` explicitly approves it. Otherwise default to the Phase 3 behavior:

  ```php
          if (isset($disabled_emails[$email_instance->id])) {
              $recipient = '';
              break;
          }
  ```

  Phase 4 is behavior-preserving (FR-014, INV-FS5), so use the **isset-only** condition.

- [X] T022 [US3] In `includes/Core.php`, locate `Core::filter_woocommerce_order_email_recipient` (currently lines 62–74). Replace the body (lines 64–73) with:

  ```php
      $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
      if ('wc-settings' === $page) {
          return $recipient;
      }
      if (!is_a($order, 'WC_Order')) {
          return $recipient;
      }
      if (Helpers::is_order_emails_disabled((int) $order->get_id())) {
          $recipient = '';
      }
      return $recipient;
  ```

  (Replaces the inline `get_post_meta($order->get_id(), '_disable_order_emails', true)` with the helper call. Adds the `wp_unslash` defensive wrapper around `$_GET['page']` for WPCS compliance and the `is_a($order, 'WC_Order')` guard so the helper is never called on a malformed `$order`.)

- [X] T023 [US3] Run `php -l includes/Core.php` and confirm `No syntax errors detected`.

### Implementation for User Story 3 — `includes/Admin.php` migration

- [X] T024 [US3] In `includes/Admin.php`, add the `use` statement at the top of the file. Locate line 3 which reads `namespace DisableEmailsPerProductForWooCommerce;`. Immediately after that line (before the blank line preceding `class Admin`), insert:

  ```php

  use DisableEmailsPerProductForWooCommerce\Helpers;
  ```

- [X] T025 [US3] In `includes/Admin.php`, locate `Admin::add_product_tab_content` (the method body produced by T014). Find the line at the top of the method:

  ```php
  $saved_emails = get_post_meta(get_the_ID(), '_disabled_emails', true) ?: [];
  ```

  (currently line 45) and replace it with:

  ```php
  $saved_emails = Helpers::get_product_disabled_emails((int) get_the_ID());
  ```

  Then in the same method, find the line introduced by T014:

  ```php
  $mailer = WC()->mailer()->get_emails();
  ```

  and replace it with:

  ```php
  $mailer = Helpers::get_enabled_emails();
  ```

  Since `Helpers::get_enabled_emails` already returns only enabled `WC_Email` instances, the per-loop `is_enabled()` filtering inside the `$configurable` construction loop (introduced by T014) becomes redundant. **Remove** the line `if (!($email instanceof \WC_Email) || !$email->is_enabled()) { continue; }` and replace it with `if (!($email instanceof \WC_Email)) { continue; }` (the `instanceof` check is kept as a defensive belt-and-braces guard, but the `is_enabled` check is now owned by `Helpers`).

  The fully refactored top-half of `add_product_tab_content` after T025 should read:

  ```php
  public function add_product_tab_content(): void
  {
      $saved_emails = Helpers::get_product_disabled_emails((int) get_the_ID());

      echo '<div id="dwepp_options" class="panel woocommerce_options_panel">';

      $mailer = Helpers::get_enabled_emails();
      /**
       * Filter the list of WooCommerce email IDs excluded from the per-product
       * "Disable Emails" configuration UI.
       *
       * @since {next-release-version}
       *
       * @param string[] $excluded_ids Default: ['customer_new_account',
       *                               'customer_reset_password', 'customer_note'].
       */
      $non_related_emails = apply_filters(
          'dwepp_excluded_email_ids',
          ['customer_new_account', 'customer_reset_password', 'customer_note']
      );
      if (!is_array($non_related_emails)) {
          $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
      }
      $non_related_emails = array_values(array_filter($non_related_emails, 'is_string'));

      $configurable = [];
      foreach ($mailer as $email) {
          if (!($email instanceof \WC_Email)) {
              continue;
          }
          if (in_array($email->id, $non_related_emails, true)) {
              continue;
          }
          $configurable[] = $email;
      }

      $product_id = (int) get_the_ID();
      /**
       * Filter the list of WC_Email instances offered for per-product suppression
       * configuration after the dwepp_excluded_email_ids exclusion list has been applied.
       *
       * @since {next-release-version}
       *
       * @param \WC_Email[] $emails     Enabled emails minus the exclusion list.
       * @param int         $product_id Product whose configuration UI is being rendered.
       */
      $configurable_filtered = apply_filters('dwepp_product_configurable_emails', $configurable, $product_id);
      if (!is_array($configurable_filtered)) {
          $configurable_filtered = $configurable;
      }

      foreach ($configurable_filtered as $email) {
          if (!($email instanceof \WC_Email)) {
              continue;
          }
          woocommerce_wp_checkbox([
              'id'          => 'dwepp_disabled_emails[' . $email->id . ']',
              'label'       => $email->title,
              'value'       => $saved_emails[$email->id] ?? 'no',
              'cbvalue'     => 'yes',
              'desc_tip'    => true,
              'description' => sprintf(
                  /* translators: %s: email title */
                  esc_html__('Check to disable %s email for this product.', 'disable-emails-per-product-for-woocommerce'),
                  esc_html($email->title)
              ),
          ]);
      }

      wp_nonce_field('save_disabled_emails_action', 'save_disabled_emails_nonce');

      echo '</div>';
  }
  ```

- [X] T026 [US3] In `includes/Admin.php`, locate `Admin::save_disabled_emails` (currently lines 74–97). Find the two `_disabled_emails` literals inside the `if (isset($_POST['dwepp_disabled_emails']) && is_array($_POST['dwepp_disabled_emails']))` branch (currently lines 93 and 95) and replace them with a single resolved variable. The new method body (everything between the opening and closing braces) should read:

  ```php
      // Exit if doing autosave or nonce is not set or fails verification.
      if (
          (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
          !isset($_POST['save_disabled_emails_nonce']) ||
          !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save_disabled_emails_nonce'])), 'save_disabled_emails_action')
      ) {
          return;
      }

      if (!current_user_can('edit_post', $post_id)) {
          return;
      }

      $meta_key_default = '_disabled_emails';
      /**
       * Filter the WordPress post-meta key used to store per-product disabled-email configuration.
       *
       * @since {next-release-version}
       *
       * @param string $meta_key The current meta key. Default: '_disabled_emails'.
       */
      $meta_key = apply_filters('dwepp_disabled_emails_meta_key', $meta_key_default);
      if (!is_string($meta_key) || $meta_key === '' || preg_match('/\s/', $meta_key)) {
          $meta_key = $meta_key_default;
      }

      if (isset($_POST['dwepp_disabled_emails']) && is_array($_POST['dwepp_disabled_emails'])) {
          $sanitized_data = array_map('sanitize_text_field', wp_unslash($_POST['dwepp_disabled_emails']));
          update_post_meta($post_id, $meta_key, $sanitized_data);
      } else {
          delete_post_meta($post_id, $meta_key);
      }
  ```

  (Note: the original code at line 92 omitted `wp_unslash` before `array_map('sanitize_text_field', ...)`; the corrected version above adds it for WPCS compliance. This is a defensive improvement, not a behavior change.)

- [X] T027 [US3] In `includes/Admin.php`, locate `Admin::save_disable_order_emails` (currently lines 128–153). Replace the body (everything between the opening and closing braces) with:

  ```php
      global $pagenow, $typenow;

      if (
          'post.php' !== $pagenow || 'shop_order' !== $typenow ||
          (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
          !isset($_POST['disable_order_emails_nonce']) ||
          !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['disable_order_emails_nonce'])), 'disable_order_emails_action')
      ) {
          return;
      }

      if (!current_user_can('edit_post', $order_id)) {
          return;
      }

      $meta_key_default = '_disable_order_emails';
      /**
       * Filter the WordPress post-meta key used to store the per-order disable-emails flag.
       *
       * @since {next-release-version}
       *
       * @param string $meta_key The current meta key. Default: '_disable_order_emails'.
       */
      $meta_key = apply_filters('dwepp_disable_order_emails_meta_key', $meta_key_default);
      if (!is_string($meta_key) || $meta_key === '' || preg_match('/\s/', $meta_key)) {
          $meta_key = $meta_key_default;
      }

      if (isset($_POST['_disable_order_emails'])) {
          update_post_meta($order_id, $meta_key, sanitize_text_field(wp_unslash($_POST['_disable_order_emails'])));
      } else {
          delete_post_meta($order_id, $meta_key);
      }
  ```

  (Note: this also adds `wp_unslash` before `sanitize_text_field` for WPCS compliance on the value read from `$_POST['_disable_order_emails']`.)

- [X] T028 [US3] Run `php -l includes/Admin.php` and confirm `No syntax errors detected`.

### Implementation for User Story 3 — `includes/GlobalView.php` migration

- [X] T029 [US3] In `includes/GlobalView.php`, add the `use` statement. Locate line 3 which reads `namespace DisableEmailsPerProductForWooCommerce;`. Immediately after that line, insert:

  ```php

  use DisableEmailsPerProductForWooCommerce\Helpers;
  ```

- [X] T030 [US3] In `includes/GlobalView.php`, locate `GlobalView::get_settings` (currently lines 28–50). Find the line:

  ```php
  $products_with_disabled_emails = $this->get_products_with_disabled_emails();
  ```

  (currently line 30) and replace it with:

  ```php
  $products_with_disabled_emails = Helpers::render_disabled_emails_overview_table();
  ```

- [X] T031 [US3] In `includes/GlobalView.php`, delete the entire `get_products_with_disabled_emails()` method (currently lines 57–94, the method definition and its body up to and including the closing `}`). After deletion, the file should end with the `custom_html_field` method's closing brace (around line 55) followed by the class's closing brace (around line 95 in the original, now earlier).

  ⚠️ If Phase 3 introduced a new private method name (e.g., `render_disabled_emails_overview` instead of `custom_html_field`, per `specs/03-admin-settings-hardening/contracts/admin-rendering.md`), preserve whichever method name Phase 3 settled on; this task only removes `get_products_with_disabled_emails`, not the renderer.

- [X] T032 [US3] Run `php -l includes/GlobalView.php` and confirm `No syntax errors detected`.

### Verification for User Story 3 (Phase 5 single-definition-site grep)

- [X] T033 [US3] Run the V5 greps from `quickstart.md` § "V5 — Single-definition-site grep":

  ```bash
  grep -rn 'WC()->mailer()->get_emails()' includes/
  ```

  Expected: exactly one match, in `includes/Helpers.php`, in `Helpers::get_enabled_emails`. If any match appears in `Admin.php` or `Core.php`, return to T020 / T025 and fix.

- [X] T034 [US3] Run:

  ```bash
  grep -rn "'_disabled_emails'" includes/
  ```

  Expected: matches only in `includes/Helpers.php` (`resolve_product_meta_key` default and fallback) and in `includes/Admin.php` (`save_disabled_emails` default and fallback per T026). No match in `Core.php` or `GlobalView.php`. If unexpected matches appear, return to T021 / T031.

- [X] T035 [US3] Run:

  ```bash
  grep -rn "'_disable_order_emails'" includes/
  ```

  Expected: matches only in `includes/Helpers.php` (`resolve_order_meta_key` default and fallback) and `includes/Admin.php` (`save_disable_order_emails` default and fallback per T027, plus the literal `$_POST['_disable_order_emails']` key for the form-field name — note the form-field name is unchanged and is **not** a meta key). No match in `Core.php`.

- [X] T036 [US3] Run:

  ```bash
  grep -rn 'apply_filters' includes/ | grep -i construct
  ```

  Expected: zero matches. INV-FS2 verification.

**Checkpoint**: User Story 3 is complete. Every duplicated pattern from `spec.md` Story 3 appears at exactly one definition site. With every new filter at its default, behavior is byte-identical to Phase 3.

---

## Phase 6: User Story 4 — Filterable persisted meta-key names (Priority: P3)

**Goal**: Confirm the two meta-key filters wired in Phases 2, 5 work end-to-end. No new code is required — the wiring was done in T006, T026, T027. This phase is verification-only.

**Independent Test**: Register `add_filter('dwepp_disabled_emails_meta_key', fn() => '_dwepp_disabled_emails');`. Save per-product suppression on a product. Confirm via `wp post meta get <product_id> _dwepp_disabled_emails` that the row exists under the new key. Confirm via `wp post meta get <product_id> _disabled_emails` that nothing was written under the default key. Confirm the per-product tab UI re-renders the saved configuration on reload.

**Reference**: `contracts/filter-surface.md` § F3, F4.

### Verification for User Story 4

- [X] T037 [US4] Manual verify per `quickstart.md` § Scenario 2, Column M: register `add_filter('dwepp_disabled_emails_meta_key', fn() => '_dwepp_disabled_emails');` in the mu-plugin. Configure per-product suppression on a fresh product (one with no existing `_disabled_emails` row); save the product. Run `wp post meta get <product_id> _dwepp_disabled_emails` and confirm the value matches what was ticked. Run `wp post meta get <product_id> _disabled_emails` and confirm no value exists. Reload the product edit screen and confirm the tick state is preserved.
- [X] T038 [US4] Manual verify per `quickstart.md` § Scenario 3, Column M: place an order containing the product configured in T037; trigger the suppressed email's status transition; confirm the email is suppressed (mail catcher receives nothing for the suppressed flow but receives every other enabled email normally).
- [X] T039 [US4] Manual verify per `quickstart.md` § V3: register `add_filter('dwepp_disable_order_emails_meta_key', fn() => '_dwepp_disable_order_emails');`. Tick the per-order "Disable Order Emails" checkbox on an existing order; save. Run `wp post meta get <order_id> _dwepp_disable_order_emails` and confirm the value is set. Change the order status; confirm the customer-facing email for the new status is suppressed.
- [X] T040 [US4] Manual verify malformed-callback fallback per `quickstart.md` § V2e–V2h: register meta-key callbacks returning empty string, whitespace-only, and a non-string in turn. In every case, confirm the plugin reads from and writes to the documented default key and zero plugin-originated entries appear in `debug.log`.
- [X] T041 [US4] Manual verify `dwepp_global_view_products_query` per `quickstart.md` § Scenario 5 "Additional verification": register a callback returning the first ID only; reload `WooCommerce → Settings → Disable Emails Per Product`; confirm only the first product row renders.

**Checkpoint**: User Story 4 is fully functional. All five new filters are observable from integrator code and persist their default behavior when no integrator callback is registered.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final cleanup, documentation updates, and the full QA matrix pass.

- [X] T042 [P] Update `README.md`: append a "## Filters" section enumerating each of the five new public filters with name, default, one-line description, and one usage example. Use the content of `contracts/filter-surface.md` as the source of truth. The new section must include all five filters: `dwepp_excluded_email_ids`, `dwepp_product_configurable_emails`, `dwepp_disabled_emails_meta_key`, `dwepp_disable_order_emails_meta_key`, `dwepp_global_view_products_query`. (SC-002, SC-006, FR-012.)
- [X] T043 [P] Update `readme.txt`: add a changelog entry under the next version stating "New: filterable excluded email IDs (`dwepp_excluded_email_ids`), filterable per-product configurable email list (`dwepp_product_configurable_emails`), filterable per-product and per-order meta keys (`dwepp_disabled_emails_meta_key`, `dwepp_disable_order_emails_meta_key`), filterable global-view product query (`dwepp_global_view_products_query`). New internal helper class `Helpers` consolidating duplicated read patterns." Include the meta-key-filter soft-data-binding rollback warning from `spec.md` § Rollback Considerations.
- [X] T044 Replace every `{next-release-version}` placeholder in the PHPDoc `@since` lines added across `includes/Admin.php`, `includes/Core.php`, `includes/Helpers.php` with the actual release version that ships Phase 4 (consult the plugin header in `disable-emails-per-product-for-woocommerce.php` and the `readme.txt` "Stable tag" entry). The placeholder appears in these locations (verify with `grep -rn '{next-release-version}' includes/`):
  - `includes/Helpers.php`: in `resolve_product_meta_key`, `resolve_order_meta_key`, and `query_products_with_disabled_emails` PHPDoc blocks.
  - `includes/Admin.php`: in `add_product_tab_content` (two filters), `save_disabled_emails`, `save_disable_order_emails`.
- [X] T045 [P] Run `vendor/bin/phpcs --standard=WordPress,WooCommerce includes/` and confirm zero new warnings versus the Phase 3 baseline. If new warnings appear, fix them in-place (most likely candidates: missing `wp_unslash` before sanitization, missing PHPDoc on public methods).
- [X] T046 [P] Run `php -l` on each modified or added file and confirm clean output:

  ```bash
  php -l includes/Helpers.php
  php -l includes/Admin.php
  php -l includes/Core.php
  php -l includes/GlobalView.php
  ```

- [X] T047 [P] Run `composer dump-autoload -o` to regenerate the optimized class map so `Helpers` is included. Verify `vendor/composer/autoload_classmap.php` contains an entry mapping `DisableEmailsPerProductForWooCommerce\\Helpers` to the absolute path of `includes/Helpers.php`.
- [X] T048 Execute the full `quickstart.md` acceptance checklist. All eight items must be checked:
  1. Scenarios 1–8 pass under columns D, E, C, M.
  2. V1 HPOS-enabled regression pass.
  3. V2 malformed-callback fallback drill — every sub-case V2a–V2j produces zero plugin-originated entries in `debug.log`.
  4. V3 order-meta-key filter drill passes.
  5. V4 documentation enumerates every new filter.
  6. V5 single-definition-site greps all return the expected result (already verified in T033–T036; re-run for final acceptance).
  7. V6 lint and standards baseline is clean versus Phase 3.
  8. V7 Composer optimized class map includes `Helpers`.
- [X] T049 Execute the six critical email flows verification per `quickstart.md` § Scenario 7 (Column D, no integrator filters): place a guest order, transition through Processing → Completed, add a customer-visible order note, register a new account, trigger a password reset. All six emails must be delivered. This is the Constitution Principle II (Transactional Email Safety) acceptance gate.
- [X] T050 [P] Commit a final review of every `dwepp_*` filter call site to confirm: (a) the filter is invoked outside any `__construct()` method (INV-FS2); (b) the return value is validated with the rule specified in `contracts/filter-surface.md` (INV-FS1); (c) on validation failure the consumer falls back silently to the documented default (INV-FS1, INV-FS5).

**Checkpoint**: Phase 4 is complete and ready for release packaging.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No code dependencies; can start immediately.
- **Foundational (Phase 2)**: Depends on Setup. **Blocks every user story** because `Helpers` must exist before any consumer can call it.
- **User Story 1 (Phase 3)**: Depends on Foundational. (Adds the first new filter at the existing exclusion site; does not touch helpers yet.)
- **User Story 2 (Phase 4)**: Depends on US1 because T014 supersedes T009 in the same source location. Do not start US2 until US1 is complete or the work is consolidated.
- **User Story 3 (Phase 5)**: Depends on Foundational. **Independent of US1/US2** structurally — the helper migration changes call sites that US1/US2 do not modify (`Helpers::get_product_disabled_emails`, `Helpers::is_order_emails_disabled`, `Helpers::query_products_with_disabled_emails`). However, T025 modifies code in the same method that T014 modified, so US3's `Admin.php` edits must come after US2. Schedule US3 after US1/US2 unless multiple developers coordinate on `Admin.php`.
- **User Story 4 (Phase 6)**: Verification-only; depends on Foundational and US3 (because US3 wires the meta-key filters in `Admin::save_disabled_emails` and `Admin::save_disable_order_emails`).
- **Polish (Phase 7)**: Depends on all four user stories.

### User Story Dependencies (recommended sequential order for a single implementer)

1. Phase 1: Setup (T001–T005)
2. Phase 2: Foundational (T006–T008) — creates `Helpers`
3. Phase 3: US1 (T009–T013) — `dwepp_excluded_email_ids` filter added
4. Phase 4: US2 (T014–T018) — `dwepp_product_configurable_emails` filter added (supersedes T009's snippet in the same method)
5. Phase 5: US3 (T019–T036) — helper migration completes the centralization
6. Phase 6: US4 (T037–T041) — verification of the meta-key filters wired in T006, T026, T027
7. Phase 7: Polish (T042–T050) — docs, lint, final QA

### Within Each User Story

- Tests are manual per `quickstart.md`; the verification tasks (`Manual verify ...`) are listed after each implementation task and MUST be executed before moving to the next user story.
- For US3, the order within the phase matters: Core.php migration (T019–T023) → Admin.php migration (T024–T028) → GlobalView.php migration (T029–T032) → grep verification (T033–T036).

### Parallel Opportunities

- **Setup**: T002, T003, T004, T005 marked `[P]` can run in parallel after T001.
- **Polish**: T042, T043, T045, T046, T047, T050 marked `[P]` can run in parallel because they touch different files (docs vs. lint vs. autoloader vs. final review). T044 (placeholder replacement) must run before T045 (linting) because the placeholder would otherwise be flagged.
- **Within US3**: T019–T023 (Core.php), T024–T028 (Admin.php), and T029–T032 (GlobalView.php) operate on different files and **could** run in parallel if multiple developers coordinate. For single-implementer execution, run them sequentially in that order.

---

## Parallel Example: Polish phase

```bash
# After T044 completes:
Task: "T042 Update README.md with filter documentation"
Task: "T043 Update readme.txt changelog"
Task: "T045 Run phpcs --standard=WordPress,WooCommerce includes/"
Task: "T046 Run php -l on each modified file"
Task: "T047 Run composer dump-autoload -o"
```

---

## Implementation Strategy

### Recommended order for a single LLM implementer

1. Complete Phase 1: Setup (T001–T005). Confirm the environment is clean.
2. Complete Phase 2: Foundational (T006–T008). The `Helpers` class is now in place and the two meta-key filters and the global-view filter are already wired internally.
3. Complete Phase 3: US1 (T009–T013). Verify column E from the QA matrix passes.
4. Complete Phase 4: US2 (T014–T018). Verify column C from the QA matrix passes.
5. Complete Phase 5: US3 (T019–T036). After T036, all four duplicated patterns are at single definition sites and Core.php is calling helpers exclusively.
6. Complete Phase 6: US4 (T037–T041). Verify column M from the QA matrix passes.
7. Complete Phase 7: Polish (T042–T050). The full quickstart acceptance gate runs here.

### MVP scope

The MVP for this phase is **US1 + US3 partial (helpers exist, Core.php still uses inline reads)** — that delivers the headline `dwepp_excluded_email_ids` filter and the foundation for future extensibility. However, the spec treats US1 and US2 as co-P1, so the practical MVP is US1 + US2 + Phase 1 + Phase 2.

### Rollback strategy if a story fails QA

- If US1 verification (T012, T013) fails, the change can be reverted by replacing the T009 snippet with the original hardcoded line at `Admin.php:50`. No other file is affected.
- If US2 verification (T017, T018) fails, the change can be reverted by replacing the T014 snippet with the T009 result. `apply_filters('dwepp_product_configurable_emails', ...)` and the post-exclusion variable can be removed without affecting `dwepp_excluded_email_ids`.
- If US3 verification (T033–T036) fails, the call-site migrations can be reverted file-by-file: Core.php, Admin.php, GlobalView.php. The `Helpers` class can remain in place (no consumer depends on it after rollback).
- If US4 verification (T037–T041) fails for the meta-key filters specifically, remove the `apply_filters('dwepp_disabled_emails_meta_key', ...)` and `apply_filters('dwepp_disable_order_emails_meta_key', ...)` call sites from `Helpers.php` and `Admin.php`; the helpers continue to use the default keys.

---

## Notes

- `[P]` tasks = different files, no dependencies.
- `[Story]` label maps task to specific user story for traceability.
- Each user story is independently completable and verifiable per its acceptance section.
- Manual QA verification tasks (`Manual verify ...`) MUST be executed before marking the implementation task complete.
- Commit after each task group (per phase) or per user story.
- The `{next-release-version}` placeholder appears throughout the new `@since` PHPDoc lines and is replaced in T044 once the actual version number is known.
- The `wp_unslash`-before-sanitize improvements in T026 and T027 are defensive WPCS-compliance changes; they do not alter observable behavior.
- Do NOT introduce any automated test harness in this phase. PHPUnit, Pest, and similar are Phase 5 work.
- Do NOT rename `DEPPWC_PREFIX`, `DEPPWC_BASENAME`, the `_disabled_emails` or `_disable_order_emails` default keys, or the `disable_woocommerce_emails_per_product` settings tab id. These are preserved verbatim per `contracts/identifier-naming.md` § N2.
