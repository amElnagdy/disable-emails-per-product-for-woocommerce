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

Add this filter to your theme's `functions.php` file to disable the global view functionality
`add_filter('dwepp_disable_global_view', '__return_true');`

## Support

For support, feature requests, or bug reporting, please open an issue on the GitHub repository.

## License

This plugin is licensed under the GPL-3.0 License.
