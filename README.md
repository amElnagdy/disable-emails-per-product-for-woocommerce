# Disable Emails Per Product for WooCommerce

## Description

This WordPress plugin allows WooCommerce store owners to disable specific transactional emails per product. Additionally, the plugin provides an option to disable emails for specific orders manually.

## Features

- Disable specific WooCommerce emails per product.
- Manual override to disable emails for individual orders.
- Global view to see which emails are disabled for each product.

## Installation

1. Install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Development

Contributors: see [the testing & QA quickstart](specs/05-testing-and-quality-assurance/quickstart.md)
for instructions on setting up the local test environment, running the regression
suite, and reproducing CI outcomes locally.

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


## Filters

- `dwepp_disable_global_view`: Disable the global view functionality.

Add this filter to your theme's `functions.php` file to disable the global view functionality:
```php
add_filter('dwepp_disable_global_view', '__return_true');
```

### `dwepp_excluded_email_ids`

Controls which WooCommerce email IDs are excluded from the per-product "Disable Emails" configuration UI.

**Default**: `['customer_new_account', 'customer_reset_password', 'customer_note']`

**Example** — allow per-product suppression of the customer note email:
```php
add_filter('dwepp_excluded_email_ids', function ($excluded) {
    return array_values(array_diff($excluded, ['customer_note']));
});
```

### `dwepp_product_configurable_emails`

Controls the full list of `WC_Email` instances offered for per-product suppression after the exclusion list has been applied.

**Arguments**: `$emails` (array of `WC_Email`), `$product_id` (int)

**Example** — hide a third-party email from the per-product UI:
```php
add_filter('dwepp_product_configurable_emails', function (array $emails, int $product_id) {
    $emails = array_filter($emails, fn($e) => $e->id !== 'my_custom_email');
    return array_values($emails);
}, 10, 2);
```

### `dwepp_disabled_emails_meta_key`

Controls the WordPress post-meta key used to persist per-product suppression configuration.

**Default**: `'_disabled_emails'`

**Example** — relocate storage to avoid a meta-key collision:
```php
add_filter('dwepp_disabled_emails_meta_key', fn() => '_dwepp_disabled_emails');
```

### `dwepp_disable_order_emails_meta_key`

Controls the WordPress post-meta key used to persist the per-order disable-emails flag.

**Default**: `'_disable_order_emails'`

**Example**:
```php
add_filter('dwepp_disable_order_emails_meta_key', fn() => '_dwepp_disable_order_emails');
```

### `dwepp_global_view_products_query`

Controls the list of product IDs displayed on the global view "Products with Disabled Emails" overview table.

**Default**: The resolved product ID list from the default query.

**Example** — restrict the overview to the first 50 products:
```php
add_filter('dwepp_global_view_products_query', fn(array $ids) => array_slice($ids, 0, 50));
```

## Support

For support, feature requests, or bug reporting, please open an issue on the GitHub repository.

## License

This plugin is licensed under the GPL-3.0 License.
