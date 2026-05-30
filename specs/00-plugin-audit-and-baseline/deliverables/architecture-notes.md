# Architecture Notes — Disable Emails Per Product for WooCommerce

**Audit date**: 2026-05-19
**Commit audited**: d161952642f9200c30e9cc5b59a4ba24cf0ca60c

## 1. Bootstrap

The plugin bootstrap is `disable-emails-per-product-for-woocommerce.php`.

### Plugin header values

| Field | Value |
|-------|-------|
| Plugin Name | Disable Emails Per Product for WooCommerce |
| Version | 1.0.1 |
| Requires PHP | 7.4 (from `readme.txt`) |
| Requires Plugins | *(missing)* |
| Tested up to | 6.7 |
| WC tested up to | *(missing in `readme.txt`)* |

### Constants and globals defined

- `DEPPWC_PREFIX = 'deppwc'` (line 25)
- `DEPPWC_BASENAME` (line 26) — `plugin_basename(__FILE__)`

### Component instantiation order

```php
new Admin();
new Core();
add_action('after_setup_theme', function () {
    if (!apply_filters('dwepp_disable_global_view', false)) {
        new GlobalView();
    }
});
```

`Admin` and `Core` are instantiated unconditionally at load time. `GlobalView` is deferred to `after_setup_theme` and is gated by the filter `dwepp_disable_global_view` (default false, so it runs by default).

### HPOS compatibility declaration

Verbatim call site (lines 40–46):

```php
add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
);
```

The last argument is `true`, declaring full compatibility with custom order tables (HPOS).

---

## 2. Components

### 2.1 Core

- **Files**: `includes/Core.php`
- **Responsibility**: Attaches recipient filters to every enabled WooCommerce transactional email and evaluates both product-level and order-level suppression rules.
- **Public hooks**:
  - `woocommerce_init` ([hook-inventory.md row 11](../hook-inventory.md))
  - `woocommerce_email_recipient_<email_id>` — product-level filter ([hook-inventory.md row 12](../hook-inventory.md))
  - `woocommerce_email_recipient_<email_id>` — order-level filter ([hook-inventory.md row 13](../hook-inventory.md))
- **Consumed WC APIs**: `WC()->mailer()->get_emails()`, `WC_Email::is_enabled()`, `WC_Email::$id`, `WC_Order::get_items()`, `WC_Order_Item_Product::get_product()`, `WC_Product::is_type()`, `WC_Product::get_parent_id()`, `WC_Product::get_id()`, `WC_Order::get_id()`
- **Consumed WP APIs**: `get_post_meta`
- **Inter-component deps**: *(none)*
- **HPOS assumptions**: Product meta reads via `get_post_meta` are safe because products remain in `wp_posts`. Order meta reads via `get_post_meta($order->get_id(), '_disable_order_emails', …)` assume orders live in `wp_posts/wp_postmeta`; this is **not** HPOS-safe.

### 2.2 Admin

- **Files**: `includes/Admin.php`
- **Responsibility**: Adds the "Disable Emails" product tab, renders order-level suppression checkbox, saves product and order metadata, enqueues admin CSS/JS, and appends a donate link to plugin actions.
- **Public hooks**:
  - `woocommerce_product_data_tabs` ([hook-inventory.md row 3](../hook-inventory.md))
  - `woocommerce_product_data_panels` ([hook-inventory.md row 4](../hook-inventory.md))
  - `woocommerce_process_product_meta` ([hook-inventory.md row 5](../hook-inventory.md))
  - `admin_head` ([hook-inventory.md row 6](../hook-inventory.md))
  - `woocommerce_admin_order_data_after_order_details` ([hook-inventory.md row 7](../hook-inventory.md))
  - `save_post_shop_order` ([hook-inventory.md row 8](../hook-inventory.md))
  - `init` — text domain ([hook-inventory.md row 9](../hook-inventory.md))
  - `plugin_action_links_*` ([hook-inventory.md row 10](../hook-inventory.md))
- **Consumed WC APIs**: `woocommerce_wp_checkbox()`, `WC()->mailer()->get_emails()`, `WC_Email::is_enabled()`, `WC_Email::$id`, `WC_Email::$title`
- **Consumed WP APIs**: `get_post_meta`, `update_post_meta`, `delete_post_meta`, `wp_nonce_field`, `wp_verify_nonce`, `sanitize_text_field`, `wp_unslash`, `current_user_can`, `load_plugin_textdomain`
- **Inter-component deps**: *(none)*
- **HPOS assumptions**: Product meta reads/writes are safe. Order meta writes via `update_post_meta` / `delete_post_meta` are **not** HPOS-safe.

### 2.3 GlobalView

- **Files**: `includes/GlobalView.php`
- **Responsibility**: Registers a WooCommerce settings tab that renders a read-only table listing all products with disabled emails.
- **Public hooks**:
  - `woocommerce_settings_tabs_array` ([hook-inventory.md row 14](../hook-inventory.md))
  - `woocommerce_settings_tabs_disable_woocommerce_emails_per_product` ([hook-inventory.md row 15](../hook-inventory.md))
  - `woocommerce_admin_field_custom_html` ([hook-inventory.md row 16](../hook-inventory.md))
- **Consumed WC APIs**: `woocommerce_admin_fields()`, `wc_get_product()`, `WC_Product::get_name()`
- **Consumed WP APIs**: `$wpdb->prepare`, `$wpdb->get_col`, `get_post_meta`, `get_edit_post_link`, `sanitize_text_field`, `esc_url`, `esc_html`, `wp_kses_post`
- **Inter-component deps**: *(none)*
- **HPOS assumptions**: `"none"` — only queries product meta.

---

## 3. End-to-end suppression flow

### 3.1 New order email

1. **WC trigger hook**: WooCommerce calls `WC_Email_New_Order::trigger()` which eventually invokes `apply_filters( 'woocommerce_email_recipient_new_order', $recipient, $order, $email_instance )`.
2. **Plugin filter callbacks**:
   - `Core::filter_woocommerce_email_recipient` (priority **10**, `includes/Core.php:18`) executes first.
   - `Core::filter_woocommerce_order_email_recipient` (priority **9999**, `includes/Core.php:22`) executes second.
3. **Plugin meta read**:
   - Product-level filter reads `_disabled_emails` post meta from every product in the order (`includes/Core.php:43`).
   - Order-level filter reads `_disable_order_emails` post meta from the order (`includes/Core.php:69`).
4. **Plugin decision**:
   - If any product in the order has `disabled_emails['new_order'] = true`, the product-level filter sets `$recipient = ''`.
   - If the order has `_disable_order_emails` meta set, the order-level filter also sets `$recipient = ''`.
   - Either condition suppresses the email.
5. **WC sends or skips**: WooCommerce proceeds with `send()` only if `$recipient` is non-empty after all filters.

### 3.2 Processing order email

1. **WC trigger hook**: `WC_Email_Customer_Processing_Order::trigger()` → `apply_filters( 'woocommerce_email_recipient_customer_processing_order', … )`.
2. **Plugin filter callbacks**:
   - `Core::filter_woocommerce_email_recipient` (priority **10**, `includes/Core.php:18`).
   - `Core::filter_woocommerce_order_email_recipient` (priority **9999**, `includes/Core.php:22`).
3. **Plugin meta read**:
   - `_disabled_emails` from each product (`includes/Core.php:43`).
   - `_disable_order_emails` from the order (`includes/Core.php:69`).
4. **Plugin decision**: Same as 3.1 — product-level or order-level rule can set recipient to empty string.
5. **WC sends or skips**: Email sent only if recipient remains non-empty.

### 3.3 Completed order email

1. **WC trigger hook**: `WC_Email_Customer_Completed_Order::trigger()` → `apply_filters( 'woocommerce_email_recipient_customer_completed_order', … )`.
2. **Plugin filter callbacks**: Same pair as 3.1 and 3.2.
3. **Plugin meta read**: Same as above.
4. **Plugin decision**: Same as above.
5. **WC sends or skips**: Email sent only if recipient remains non-empty.

### 3.4 Customer note email

1. **WC trigger hook**: `WC_Email_Customer_Note::trigger($order_id)` → `apply_filters( 'woocommerce_email_recipient_customer_note', … )`.
2. **Plugin filter callbacks**: Same pair as above.
3. **Plugin meta read**: Same as above.
4. **Plugin decision**: Same as above.
5. **WC sends or skips**: Email sent only if recipient remains non-empty.

### 3.5 New account email

1. **WC trigger hook**: `WC_Email_Customer_New_Account::trigger()` → `apply_filters( 'woocommerce_email_recipient_customer_new_account', … )`.
2. **Plugin filter callbacks**:
   - `Core::filter_woocommerce_email_recipient` (priority **10**, `includes/Core.php:18`). The guard `if (!is_a($order, 'WC_Order') …)` fails because the second argument is a user/customer object, **not** an order. The filter returns the original recipient unchanged.
   - `Core::filter_woocommerce_order_email_recipient` (priority **9999**, `includes/Core.php:22`) **does not** have a type guard. It attempts to call `$order->get_id()` on the user/customer object and read `_disable_order_emails` meta from that ID. For a `WP_User` object this will fatal (no `get_id()` method). For a `WC_Customer` object it may read unintended meta.
3. **Plugin meta read**: N/A — the product-level filter exits early; the order-level filter operates on the wrong entity type.
4. **Plugin decision**: The plugin **should not** intervene in this flow, but the order-level filter creates a runtime safety risk because it is attached without a type guard.
5. **WC sends or skips**: Under normal circumstances WC sends the email, but the unguarded order-level filter may fatal before `send()` is reached.

### 3.6 Password reset email

1. **WC trigger hook**: `WC_Email_Customer_Reset_Password::trigger()` → `apply_filters( 'woocommerce_email_recipient_customer_reset_password', … )`.
2. **Plugin filter callbacks**: Same behavior as 3.5 — product-level filter exits early; order-level filter lacks a type guard and operates on a non-order object.
3. **Plugin meta read**: N/A — same risk as 3.5.
4. **Plugin decision**: The plugin does not legitimately intervene in this flow, but the unguarded filter introduces a fatal risk.
5. **WC sends or skips**: Under normal circumstances WC sends the email, but the unguarded filter may fatal.

---

## 4. Settings registration

### WooCommerce settings tab

- **Hook**: `woocommerce_settings_tabs_array`
- **File:line**: `includes/GlobalView.php:10`
- **Priority**: `50`
- **Ordering dependencies**: Priority 50 places the tab after WooCommerce core tabs (which typically use lower priorities).
- **Tab slug owned**: `disable_woocommerce_emails_per_product`
- **Nonce**: No custom nonce is used for this tab; the tab renders read-only custom HTML and has no write handler.

### Product data tab

- **Hook**: `woocommerce_product_data_tabs`
- **File:line**: `includes/Admin.php:10`
- **Priority**: `10` (default)
- **Tab slug owned**: `deppwc_disable_emails`
- **Nonce**: `save_disabled_emails_nonce` is rendered in `includes/Admin.php:69` and verified in `includes/Admin.php:80` (`save_disabled_emails`).

### Order meta box

- **Hook**: `woocommerce_admin_order_data_after_order_details`
- **File:line**: `includes/Admin.php:14`
- **Priority**: `9999`
- **Nonce**: `disable_order_emails_nonce` is rendered in `includes/Admin.php:117` and verified in `includes/Admin.php:137` (`save_disable_order_emails`).

---

## 5. Order & product metadata access

| file:line | meta_key | read/write | subject_entity | hpos_safe? | notes |
|-----------|----------|------------|----------------|------------|-------|
| `includes/Core.php:43` | `_disabled_emails` | read | product | yes | Reads from product ID (parent if variation). |
| `includes/Core.php:69` | `_disable_order_emails` | read | order | **no** | Uses `get_post_meta($order->get_id(), …)` instead of `$order->get_meta()`. |
| `includes/Admin.php:45` | `_disabled_emails` | read | product | yes | Reads current product meta for the tab UI. |
| `includes/Admin.php:93` | `_disabled_emails` | write | product | yes | Updates on product save. |
| `includes/Admin.php:95` | `_disabled_emails` | write | product | yes | Deletes on product save when no selections. |
| `includes/Admin.php:149` | `_disable_order_emails` | write | order | **no** | Uses `update_post_meta($order_id, …)` instead of `$order->update_meta_data()`. |
| `includes/Admin.php:151` | `_disable_order_emails` | write | order | **no** | Uses `delete_post_meta($order_id, …)` instead of `$order->delete_meta_data()`. |
| `includes/GlobalView.php:60` | `_disabled_emails` | read | product | yes | Queries `$wpdb->postmeta` directly; only product meta. |
| `includes/GlobalView.php:74` | `_disabled_emails` | read | product | yes | Reads product meta for the overview table. |

---

## 6. Unrouted findings

- **Unguarded `$product` access in product-level filter**: `includes/Core.php:38–41` calls `$item->get_product()` then immediately invokes `$product->is_type()` without checking if `$product` is a valid object. If a product has been deleted since the order was placed, `get_product()` returns `false`, causing a fatal error. This is a **high-severity** candidate for Phase 1 (Runtime Safety).
- **Order-level filter attached to non-order email hooks**: `includes/Core.php:22` registers `filter_woocommerce_order_email_recipient` for **every** enabled WooCommerce email, including `customer_new_account` and `customer_reset_password`, which pass a user object rather than an order. The callback lacks a type guard and will fatal on `WP_User` objects. This is a **high-severity** candidate for Phase 1.
- **Missing `Requires Plugins` header**: The bootstrap does not declare `Requires Plugins: woocommerce`, which is required by modern WordPress for plugin dependency enforcement. This is a **medium-severity** candidate for Phase 4 (Extensibility / cleanup).
- **Text-domain load path mismatch risk**: `includes/Admin.php:158` uses `basename(dirname(__FILE__)) . '/languages'`. Because `__FILE__` points to `includes/Admin.php`, `dirname(__FILE__)` is `includes/`, so the path becomes `includes/languages` rather than the plugin root `languages/`. On case-sensitive filesystems the basename may still resolve to the plugin slug, but the extra `includes/` segment means the text domain will not load from the expected location unless `basename(dirname(__FILE__))` coincidentally equals the plugin folder name AND the path is interpreted relative to `WP_PLUGIN_DIR`. This is a **low-severity** candidate for Phase 4.

---

**Contract validation**: All required completeness checks pass as of 2026-05-19, verified against `specs/00-plugin-audit-and-baseline/contracts/architecture-notes.schema.md`.
