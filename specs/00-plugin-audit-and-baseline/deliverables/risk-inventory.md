# Risk Inventory — Disable Emails Per Product for WooCommerce

**Audit date**: 2026-05-19
**Commit audited**: d161952642f9200c30e9cc5b59a4ba24cf0ca60c

## Summary by severity

| Severity | Count | Owning phases |
|----------|-------|---------------|
| high     | 8     | 1, 2          |
| medium   | 5     | 1, 4          |
| low      | 5     | 1, 4          |

## Summary by owning phase

| Phase | Count | High | Medium | Low |
|-------|-------|------|--------|-----|
| 1 (Runtime Safety) | 6 | 4 | 1 | 1 |
| 2 (HPOS)           | 4 | 4 | 0 | 0 |
| 3 (Admin)          | 0 | 0 | 0 | 0 |
| 4 (Extensibility)  | 8 | 0 | 4 | 4 |
| 5 (Testing/QA)     | 0 | 0 | 0 | 0 |
| 6 (Features)       | 0 | 0 | 0 | 0 |

## Entries

### R-001 — HPOS-unsafe order meta read in Core recipient filter

- **Severity**: high
- **Likelihood**: medium
- **Owning phase**: 2
- **Related principles**: ["II", "V"]
- **Source refs**: `includes/Core.php:69`
- **Discovered during**: static-review
- **Description**: `Core::filter_woocommerce_order_email_recipient` reads `_disable_order_emails` using `get_post_meta($order->get_id(), …)`. When HPOS is enabled, order metadata is stored in the dedicated orders table, not `wp_postmeta`. The read silently returns an empty value, causing order-level email suppression to fail (emails are delivered when they should be suppressed).
- **Mitigation summary**: Replace `get_post_meta` with `$order->get_meta('_disable_order_emails')` to ensure the read works under both HPOS enabled and disabled.

### R-002 — HPOS-unsafe order meta write in Admin order save handler

- **Severity**: high
- **Likelihood**: medium
- **Owning phase**: 2
- **Related principles**: ["II", "V"]
- **Source refs**: `includes/Admin.php:149`
- **Discovered during**: static-review
- **Description**: `Admin::save_disable_order_emails` persists the `_disable_order_emails` flag using `update_post_meta($order_id, …)`. Under HPOS, this writes to the legacy postmeta table instead of the orders meta table, so the value is not visible to HPOS-aware reads and the suppression checkbox appears to work but has no effect.
- **Mitigation summary**: Use `$order->update_meta_data('_disable_order_emails', $value)` followed by `$order->save()` to write to the correct storage layer.

### R-003 — HPOS-unsafe order meta delete in Admin order save handler

- **Severity**: high
- **Likelihood**: medium
- **Owning phase**: 2
- **Related principles**: ["II", "V"]
- **Source refs**: `includes/Admin.php:151`
- **Discovered during**: static-review
- **Description**: When the order checkbox is unchecked, `Admin::save_disable_order_emails` calls `delete_post_meta($order_id, '_disable_order_emails')`. Under HPOS this operates on the wrong table, leaving stale meta in the orders table.
- **Mitigation summary**: Use `$order->delete_meta_data('_disable_order_emails')` and `$order->save()`.

### R-004 — FR-014: HPOS compatibility declared true while order meta access remains HPOS-unsafe

- **Severity**: high
- **Likelihood**: medium
- **Owning phase**: 2
- **Related principles**: ["V"]
- **Source refs**: `disable-emails-per-product-for-woocommerce.php:44`, `includes/Core.php:69`, `includes/Admin.php:149`, `includes/Admin.php:151`
- **Discovered during**: static-review
- **Description**: The plugin declares `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` in the bootstrap, yet three call sites continue to use `get_post_meta` / `update_post_meta` / `delete_post_meta` for order metadata. This creates a mismatch: WooCommerce advertises the plugin as HPOS-compatible, but order-level email suppression silently fails when HPOS is active.
- **Mitigation summary**: Either remove the compatibility declaration until all order meta access is migrated to CRUD APIs, or migrate the three call sites to `$order->get_meta()` / `$order->update_meta_data()` / `$order->delete_meta_data()` before the next release.

### R-005 — Missing WooCommerce dependency header

- **Severity**: medium
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["IV"]
- **Source refs**: `disable-emails-per-product-for-woocommerce.php:1-15`
- **Discovered during**: static-review
- **Description**: The plugin bootstrap lacks the `Requires Plugins: woocommerce` header. WordPress 6.5+ supports this header for plugin dependency enforcement. Without it, the plugin can be activated without WooCommerce present, leading to fatal errors when `WC()` or WooCommerce-specific functions are invoked.
- **Mitigation summary**: Add `Requires Plugins: woocommerce` to the plugin header.

### R-006 — Text domain path resolution may fail on case-sensitive filesystems

- **Severity**: low
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["IV"]
- **Source refs**: `includes/Admin.php:158`
- **Discovered during**: static-review
- **Description**: `Admin::load_text_domain` uses `basename(dirname(__FILE__)) . '/languages'`. Because `__FILE__` points to `includes/Admin.php`, the resolved path becomes `<plugin-slug>/includes/languages` instead of `<plugin-slug>/languages`. On case-sensitive or strict path-resolution hosts, WordPress will fail to locate the `.mo` files.
- **Mitigation summary**: Use the plugin root directory (e.g., `dirname(plugin_basename(__FILE__))` or a dedicated constant) to construct the relative languages path.

### R-007 — Null product in order item causes fatal in recipient filter

- **Severity**: high
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["II", "III"]
- **Source refs**: `includes/Core.php:38-41`
- **Discovered during**: static-review
- **Description**: `Core::filter_woocommerce_email_recipient` calls `$item->get_product()` then immediately accesses `$product->is_type()` without checking if `$product` is a valid object. If a product has been deleted since the order was placed, `get_product()` returns `false`, producing a fatal error (`Call to a member function is_type() on bool`) and preventing the email from being sent.
- **Mitigation summary**: Guard the product access with `if ( ! $product instanceof \WC_Product ) { continue; }` before inspecting the product type.

### R-008 — Deleted product in order item triggers fatal error

- **Severity**: high
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["II", "III"]
- **Source refs**: `includes/Core.php:38-41`
- **Discovered during**: static-review
- **Description**: When a product referenced by an order item no longer exists, the email recipient filter fatals before delivery can proceed. Because the filter runs for every transactional email, this affects new order, processing, completed, and customer note emails.
- **Mitigation summary**: Add an explicit null/validity check after `$item->get_product()` and skip the item if the product is missing.

### R-009 — Order-level recipient filter attached to non-order emails without type guard

- **Severity**: high
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["II", "III"]
- **Source refs**: `includes/Core.php:22`
- **Discovered during**: static-review
- **Description**: `Core::filter_woocommerce_order_email_recipient` is dynamically registered for **every** enabled WooCommerce email, including `customer_new_account` and `customer_reset_password`, which pass a user/customer object instead of an order. The callback lacks a type guard and calls `$order->get_id()` on a non-order object. For `WP_User` this produces a fatal error (`Call to undefined method WP_User::get_id()`), silently breaking the new-account and password-reset flows.
- **Mitigation summary**: Add `if ( ! is_a( $order, 'WC_Order' ) ) { return $recipient; }` at the top of `filter_woocommerce_order_email_recipient`, or restrict the dynamic registration to email IDs that are known to operate on orders.

### R-010 — Insufficient defensive validation of WooCommerce objects in email filters

- **Severity**: high
- **Likelihood**: high
- **Owning phase**: 1
- **Related principles**: ["II", "III"]
- **Source refs**: `includes/Core.php:30-32`, `includes/Core.php:62-68`
- **Discovered during**: static-review
- **Description**: While `filter_woocommerce_email_recipient` validates `$order` and `$email_instance` types, `filter_woocommerce_order_email_recipient` does not validate `$order`. Additionally, neither filter validates the `$recipient` string before returning it, and the product-level filter does not validate the product object before calling methods on it. These gaps allow null, deleted, or unexpected objects to propagate through the suppression logic.
- **Mitigation summary**: Standardize guard clauses at the top of every recipient filter: verify `is_a($order, 'WC_Order')`, verify `is_a($email_instance, 'WC_Email')`, verify the product object is a valid `WC_Product`, and sanitize/validate the recipient string before returning.

### R-011 — WPCS file naming and documentation convention violations

- **Severity**: medium
- **Likelihood**: low
- **Owning phase**: 4
- **Related principles**: ["IV", "VII"]
- **Source refs**: `includes/Admin.php:1`, `includes/Core.php:1`, `includes/GlobalView.php:1`
- **Discovered during**: wpcs
- **Description**: WPCS reports missing file doc comments (3 occurrences), missing class doc comments (3 occurrences), missing function doc comments (~19 occurrences), missing parameter types (~4 occurrences), missing @package tag (1 occurrence), and file naming violations (lowercase with hyphens + class- prefix, 6 occurrences). These gaps reduce maintainability and make automated code review less effective.
- **Mitigation summary**: Add file, class, and function doc blocks per WordPress PHPDoc standards; rename files to match WPCS naming conventions (or exclude the sniff if PSR-4 naming is intentional and justified in Complexity Tracking).

### R-012 — WPCS whitespace and formatting style violations

- **Severity**: medium
- **Likelihood**: low
- **Owning phase**: 4
- **Related principles**: ["VII"]
- **Source refs**: Multiple files
- **Discovered during**: wpcs
- **Description**: The WPCS baseline contains ~230 occurrences of spacing and formatting violations across all four source files, including: CRLF line endings (4), incorrect spacing around parentheses/operators (! / ( ) ), short array syntax usage (~25), array spacing issues (~30), multi-line function call formatting (10), inline comment punctuation (~10), double-quote string usage (~10), equals alignment (2), short ternaries (1), and opening brace placement (~15). PHPCBF can automatically fix the majority of these.
- **Mitigation summary**: Run `phpcbf --standard=WordPress` across the plugin source and commit the auto-fixed changes. Remaining manual fixes should be addressed in Phase 4.

### R-013 — WPCS security and input validation warnings

- **Severity**: medium
- **Likelihood**: low
- **Owning phase**: 4
- **Related principles**: ["III", "VI"]
- **Source refs**: `includes/Admin.php:23`, `includes/Admin.php:24`, `includes/Admin.php:25`, `includes/Core.php:65`, `includes/Admin.php:92`, `includes/Admin.php:149`, `includes/Admin.php:53`
- **Discovered during**: wpcs
- **Description**: Seven security-related WPCS warnings were identified: processing form data without nonce verification in `enqueue_custom_css_js` and `filter_woocommerce_order_email_recipient` (4 occurrences); `$_POST` values not unslashed before sanitization in `save_disabled_emails` and `save_disable_order_emails` (2 occurrences); and non-strict `in_array` check in `add_product_tab_content` (1 occurrence).
- **Mitigation summary**: Add nonce verification where missing; wrap `$_POST` accesses with `wp_unslash()` before sanitization; pass `true` as the third argument to `in_array()` for strict comparison.

### R-014 — WPCS code quality and best-practice warnings

- **Severity**: medium
- **Likelihood**: low
- **Owning phase**: 4
- **Related principles**: ["VII"]
- **Source refs**: `includes/Admin.php:78`, `includes/Admin.php:135`, `includes/Admin.php:106`, `includes/Admin.php:158`, `includes/GlobalView.php:61`
- **Discovered during**: wpcs
- **Description**: Nine code-quality warnings: mixing binary boolean operators without clarifying parentheses (4 occurrences); unused method parameter `$order` in `disable_order_emails` (1 occurrence); using `dirname(__FILE__)` instead of `__DIR__` (1 occurrence); direct database call and missing caching in `GlobalView::get_products_with_disabled_emails` (2 occurrences); and a false positive `Use placeholders and $wpdb->prepare` warning (1 occurrence).
- **Mitigation summary**: Add parentheses around mixed boolean expressions; remove or document unused parameters; replace `dirname(__FILE__)` with `__DIR__`; evaluate whether the direct `$wpdb` query can be replaced with a cached product meta query; ignore or suppress the false-positive prepare sniff after verification.

### R-015 — PHPStan unable to resolve WordPress/WooCommerce global functions

- **Severity**: low
- **Likelihood**: high
- **Owning phase**: 4
- **Related principles**: ["VIII"]
- **Source refs**: Multiple files
- **Discovered during**: static-analysis
- **Description**: PHPStan reports 68 `function.notFound` errors for standard WordPress and WooCommerce global functions (e.g., `add_action`, `add_filter`, `get_post_meta`, `sanitize_text_field`, `wc_get_product`, `__`, etc.). These are false positives in the runtime environment but indicate that the PHPStan configuration is not fully loading the WordPress stubs provided by `phpstan-wordpress`.
- **Mitigation summary**: Create a `phpstan.neon` configuration that correctly includes the `phpstan-wordpress` extension stubs and defines the `ABSPATH` constant. Re-run PHPStan after configuration to confirm symbol resolution.

### R-016 — PHPStan unable to resolve WooCommerce classes

- **Severity**: low
- **Likelihood**: high
- **Owning phase**: 4
- **Related principles**: ["VIII"]
- **Source refs**: `includes/Core.php:37`, `includes/Core.php:45`
- **Discovered during**: static-analysis
- **Description**: PHPStan reports 2 `class.notFound` errors for `WC_Order` and `WC_Email`. Like R-015, these are false positives at runtime but indicate incomplete stub coverage in the static analysis configuration.
- **Mitigation summary**: Ensure `phpstan.neon` loads the WooCommerce class stubs from `szepeviktor/phpstan-wordpress` or a custom stub file.

### R-017 — PHPStan unable to resolve plugin constant

- **Severity**: low
- **Likelihood**: high
- **Owning phase**: 4
- **Related principles**: ["VIII"]
- **Source refs**: `includes/Admin.php:17`
- **Discovered during**: static-analysis
- **Description**: PHPStan reports 1 `constant.notFound` error for `DEPPWC_BASENAME`. The constant is defined in the bootstrap file, but PHPStan does not see it because the bootstrap file is not included in the analysis scope or the definition is conditional.
- **Mitigation summary**: Add the bootstrap file to PHPStan's `scanFiles` or `scanDirectories`, or define the constant in `phpstan.neon` parameters.

### R-018 — PHPStan `argument.type` on `array_map` with string callable

- **Severity**: low
- **Likelihood**: high
- **Owning phase**: 4
- **Related principles**: ["VIII"]
- **Source refs**: `includes/Admin.php:92`
- **Discovered during**: static-analysis
- **Description**: PHPStan reports 1 `argument.type` error: `Parameter #1 $callback of function array_map expects (callable(mixed): mixed)|null, 'sanitize_text_field' given`. `sanitize_text_field` is a valid callable string at runtime, but PHPStan does not recognize it as callable without a stub.
- **Mitigation summary**: Resolve by fixing PHPStan stub loading (same as R-015) or by using an explicit callable closure instead of a string.

---

**Contract validation**: All required completeness checks pass as of 2026-05-19, verified against `specs/00-plugin-audit-and-baseline/contracts/risk-inventory.schema.md`.
