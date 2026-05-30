=== Disable Emails Per Product for WooCommerce ===
Contributors: nagdy
Tags: disable emails, WooCommerce, products
Requires PHP: 7.4
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Allows WooCommerce users to disable transactional emails per product. Additionally, it provides an option to disable emails for specific orders manually.

== Description ==

Running a WooCommerce store means dealing with a barrage of emails for various events like new orders, order updates, and customer notices. Disable Emails Per Product for WooCommerce offers you the ability to selectively disable emails per product. You can also manually override this setting for individual orders.

### Features

- Disable specific WooCommerce emails per product.
- Manual override to disable emails for individual orders.
- Global view to see which emails are disabled for each product.

## Usage

### Automated Email Disabling

1. Edit a product and navigate to the "Disable Emails" meta box.
2. Check the emails you wish to disable for this product.
3. Save changes.

### Manual Override

1. Edit an order.
2. Check the "Disable Order Emails" checkbox.
3. Update the order.

### Global View

- Go to WooCommerce → Settings → Disable Emails Per Product.
- See which emails are disabled for each product.

== Installation ==
1. Upload `disable-emails-per-product-for-woocommerce` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Edit products or orders to start disabling emails.

== Frequently Asked Questions ==
= The plugin isn't working or have a bug? =
Post detailed information about the issue in the support forum and we will work to fix it.

== Screenshots ==
1. Disable emails per product.
2. Disable emails for individual orders.
3. Global View: See which emails are disabled for each product.

== Changelog ==

= 1.1.0 =
* New: filterable excluded email IDs (`dwepp_excluded_email_ids`).
* New: filterable per-product configurable email list (`dwepp_product_configurable_emails`).
* New: filterable per-product and per-order meta keys (`dwepp_disabled_emails_meta_key`, `dwepp_disable_order_emails_meta_key`).
* New: filterable global-view product query (`dwepp_global_view_products_query`).
* New: internal helper class `Helpers` consolidating duplicated read patterns across Admin, Core, and GlobalView.
* Update: WordPress 7.0 readiness validated — metadata, CI matrix, and deprecated API audit complete.
* Warning: adopting the meta-key filters creates a soft data-binding to this version or newer. Rolling back to a pre-1.1.0 version after using a non-default meta key will cause the plugin to read from the default keys again; customized-key rows remain in the database and can be recovered by reactivating the filter on 1.1.0+.

= 1.0.1 =
* WordPress 6.7 compatibility.

= 1.0.0 =
* Initial Release
