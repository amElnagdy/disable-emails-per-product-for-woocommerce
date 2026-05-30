<?php

namespace Tests\Fixtures;

/**
 * Persist per-product and per-order email-suppression configuration,
 * honoring the Phase 4 dwepp_*_meta_key filter surface.
 */
final class SuppressionConfigurationFixture
{

	/**
	 * @var array<int, array{product_id: int, resolved_key: string}>
	 */
	private array $product_tracked = [];

	/**
	 * @var array<int, array{order_id: int, resolved_key: string}>
	 */
	private array $order_tracked = [];

	/**
	 * Persist per-product suppression configuration for a given product.
	 *
	 * @param int   $product_id     Existing product post ID.
	 * @param array $email_ids_map  Map of WC email_id => 'yes' (or 'no' / absent for "not suppressed").
	 *
	 * @throws \InvalidArgumentException If $product_id <= 0, product does not exist, or $email_ids_map shape is invalid.
	 */
	public function set_product_suppression(int $product_id, array $email_ids_map): void
	{
		if ($product_id <= 0) {
			throw new \InvalidArgumentException("product_id must be positive, got {$product_id}");
		}
		$product = wc_get_product($product_id);
		if (!($product instanceof \WC_Product)) {
			throw new \InvalidArgumentException("Product {$product_id} does not exist");
		}
		if (!is_array($email_ids_map)) {
			throw new \InvalidArgumentException("email_ids_map must be an array");
		}

		foreach ($email_ids_map as $key => $value) {
			if (!is_string($key) || $key === '') {
				throw new \InvalidArgumentException("email_ids_map keys must be non-empty strings");
			}
			if (!in_array($value, ['yes', 'no'], true)) {
				throw new \InvalidArgumentException("email_ids_map values must be 'yes' or 'no', got '{$value}'");
			}
		}

		$resolved_key = apply_filters('dwepp_disabled_emails_meta_key', '_disabled_emails');
		update_post_meta($product_id, $resolved_key, $email_ids_map);
		$this->product_tracked[] = [
			'product_id'    => $product_id,
			'resolved_key'  => $resolved_key,
		];
	}

	/**
	 * Persist per-order email-suppression flag for a given order.
	 *
	 * @param int    $order_id Existing order ID.
	 * @param string $value    Either 'yes' (suppress all order-status-change emails for this order)
	 *                         or '' (empty string to clear). Default: 'yes'.
	 *
	 * @throws \InvalidArgumentException If $order_id <= 0, order does not exist, or $value not in {'yes', ''}.
	 */
	public function set_order_suppression(int $order_id, string $value = 'yes'): void
	{
		if ($order_id <= 0) {
			throw new \InvalidArgumentException("order_id must be positive, got {$order_id}");
		}
		$order = wc_get_order($order_id);
		if (!($order instanceof \WC_Order)) {
			throw new \InvalidArgumentException("Order {$order_id} does not exist");
		}
		if (!in_array($value, ['yes', ''], true)) {
			throw new \InvalidArgumentException("value must be 'yes' or '', got '{$value}'");
		}

		$resolved_key = apply_filters('dwepp_disable_order_emails_meta_key', '_disable_order_emails');

		if ($value === '') {
			$order->delete_meta_data($resolved_key);
		} else {
			$order->update_meta_data($resolved_key, $value);
		}
		$order->save();

		$this->order_tracked[] = [
			'order_id'      => $order_id,
			'resolved_key'  => $resolved_key,
		];
	}

	/**
	 * Tear down all per-product and per-order meta this fixture wrote during the current test.
	 */
	public function tear_down(): void
	{
		foreach ($this->product_tracked as $track) {
			delete_post_meta($track['product_id'], $track['resolved_key']);
		}
		$this->product_tracked = [];

		foreach ($this->order_tracked as $track) {
			$order = wc_get_order($track['order_id']);
			if ($order instanceof \WC_Order) {
				$order->delete_meta_data($track['resolved_key']);
				$order->save();
			}
		}
		$this->order_tracked = [];
	}
}
