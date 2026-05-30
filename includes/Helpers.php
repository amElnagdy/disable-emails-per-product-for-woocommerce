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
		 * @since 1.1.0
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
		 * @since 1.1.0
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
		 * @since 1.1.0
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
