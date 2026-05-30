# Hook Inventory — Disable Emails Per Product for WooCommerce

**Audit date**: 2026-05-19
**Commit audited**: d161952642f9200c30e9cc5b59a4ba24cf0ca60c
**Files scanned**: `disable-emails-per-product-for-woocommerce.php`, `includes/Core.php`, `includes/Admin.php`, `includes/GlobalView.php`

## Summary

- Total registrations: 16
- Action registrations: 12
- Filter registrations: 4
- Conditional registrations: 3
- Registrations exercised under default config: 16
- Registrations linked to a critical email flow: 2

## Table

| # | hook_name | hook_type | callback | source_file | source_line | priority | accepted_args | registration_precondition | exercised_under_default_config | relates_to_critical_email_flow | notes |
|---|-----------|-----------|----------|-------------|-------------|----------|---------------|---------------------------|--------------------------------|--------------------------------|-------|
| 1 | after_setup_theme | action | `\DisableEmailsPerProductForWooCommerce\GlobalView::__construct` (closure) | disable-emails-per-product-for-woocommerce.php | 31 | 10 | 1 | `apply_filters('dwepp_disable_global_view', false) === false` | yes | none | GlobalView instantiated only when filter returns false; default is false. |
| 2 | before_woocommerce_init | action | `\DisableEmailsPerProductForWooCommerce\{closure}` | disable-emails-per-product-for-woocommerce.php | 40 | 10 | 1 | always | yes | none | Declares HPOS compatibility via `FeaturesUtil::declare_compatibility`. Guards inside callback with `class_exists`. |
| 3 | woocommerce_product_data_tabs | filter | `Admin::add_product_tabs` | includes/Admin.php | 10 | 10 | 1 | always | yes | none | Adds "Disable Emails" tab to product data. |
| 4 | woocommerce_product_data_panels | action | `Admin::add_product_tab_content` | includes/Admin.php | 11 | 10 | 1 | always | yes | none | Renders checkbox panel for disabling emails per product. |
| 5 | woocommerce_process_product_meta | action | `Admin::save_disabled_emails` | includes/Admin.php | 12 | 10 | 1 | always | yes | none | Saves `_disabled_emails` post meta on product save. |
| 6 | admin_head | action | `Admin::enqueue_custom_css_js` | includes/Admin.php | 13 | 10 | 1 | always | yes | none | Conditionally injects CSS/JS on the plugin settings page only (page/tab check inside callback). |
| 7 | woocommerce_admin_order_data_after_order_details | action | `Admin::disable_order_emails` | includes/Admin.php | 14 | 9999 | 1 | always | yes | none | Renders "Disable Order Emails" checkbox in order admin. Very high priority 9999. |
| 8 | save_post_shop_order | action | `Admin::save_disable_order_emails` | includes/Admin.php | 15 | 10 | 1 | always | yes | none | Persists `_disable_order_emails` meta when an order is saved. |
| 9 | init | action | `Admin::load_text_domain` | includes/Admin.php | 16 | 10 | 1 | always | yes | none | Loads plugin text domain. |
| 10 | plugin_action_links_* | filter | `Admin::donate_link` | includes/Admin.php | 17 | 10 | 1 | always | yes | none | Dynamic hook name built from `DEPPWC_BASENAME`. Prepends Donate link. |
| 11 | woocommerce_init | action | `Core::init` | includes/Core.php | 9 | 10 | 1 | always | yes | none | Triggers registration of dynamic recipient filters. |
| 12 | woocommerce_email_recipient_<email_id> | filter | `Core::filter_woocommerce_email_recipient` | includes/Core.php | 18 | 10 | 3 | WooCommerce initialized and email is enabled (`$email->is_enabled()`) | yes | multiple | Dynamically registered once per enabled WooCommerce email inside `woocommerce_init` loop. |
| 13 | woocommerce_email_recipient_<email_id> | filter | `Core::filter_woocommerce_order_email_recipient` | includes/Core.php | 22 | 9999 | 2 | WooCommerce initialized and email is enabled (`$email->is_enabled()`) | yes | multiple | Dynamically registered once per enabled WooCommerce email. Priority 9999 runs after product-level filter. |
| 14 | woocommerce_settings_tabs_array | action | `GlobalView::add_settings_tab` | includes/GlobalView.php | 10 | 50 | 1 | always | yes | none | Registers the plugin's settings tab at priority 50. |
| 15 | woocommerce_settings_tabs_disable_woocommerce_emails_per_product | action | `GlobalView::settings_tab` | includes/GlobalView.php | 11 | 10 | 1 | always | yes | none | Renders the settings tab content. |
| 16 | woocommerce_admin_field_custom_html | action | `GlobalView::custom_html_field` | includes/GlobalView.php | 12 | 10 | 1 | always | yes | none | Renders custom HTML field used by the settings tab. |

## Per-file breakdown

### `disable-emails-per-product-for-woocommerce.php`

- Row 1 — `after_setup_theme` action (line 31): instantiates `GlobalView` conditionally.
- Row 2 — `before_woocommerce_init` action (line 40): declares HPOS compatibility.

### `includes/Admin.php`

- Row 3 — `woocommerce_product_data_tabs` filter (line 10): adds product tab.
- Row 4 — `woocommerce_product_data_panels` action (line 11): renders tab content.
- Row 5 — `woocommerce_process_product_meta` action (line 12): saves product meta.
- Row 6 — `admin_head` action (line 13): enqueues CSS/JS.
- Row 7 — `woocommerce_admin_order_data_after_order_details` action (line 14): order checkbox.
- Row 8 — `save_post_shop_order` action (line 15): saves order meta.
- Row 9 — `init` action (line 16): text domain.
- Row 10 — `plugin_action_links_*` filter (line 17): donate link.

### `includes/Core.php`

- Row 11 — `woocommerce_init` action (line 9): bootstraps recipient filters.
- Row 12 — Dynamic `woocommerce_email_recipient_<email_id>` filter (line 18): product-level suppression.
- Row 13 — Dynamic `woocommerce_email_recipient_<email_id>` filter (line 22): order-level suppression.

### `includes/GlobalView.php`

- Row 14 — `woocommerce_settings_tabs_array` action (line 10): adds settings tab.
- Row 15 — `woocommerce_settings_tabs_disable_woocommerce_emails_per_product` action (line 11): tab content.
- Row 16 — `woocommerce_admin_field_custom_html` action (line 12): custom HTML field.

---

**Contract validation**: All required completeness checks pass as of 2026-05-19, verified against `specs/00-plugin-audit-and-baseline/contracts/hook-inventory.schema.md`.
