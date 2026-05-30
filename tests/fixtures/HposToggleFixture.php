<?php

namespace Tests\Fixtures;

/**
 * Toggle WooCommerce HPOS (High-Performance Order Storage) mode
 * for the duration of a single test.
 */
final class HposToggleFixture
{

	/**
	 * @var string
	 */
	private string $original_mode;

	/**
	 * @var int[]
	 */
	private array $tracked_order_ids = [];

	public function __construct()
	{
		$this->original_mode = get_option('woocommerce_feature_custom_order_tables_enabled', 'no');
	}

	/**
	 * Set the WooCommerce HPOS storage mode for the duration of the current test.
	 *
	 * @param string $mode Either 'enabled' or 'disabled'.
	 *
	 * @throws \InvalidArgumentException If $mode is not one of the two enumerated values.
	 */
	public function set_storage_mode(string $mode): void
	{
		if (!in_array($mode, ['enabled', 'disabled'], true)) {
			throw new \InvalidArgumentException("mode must be 'enabled' or 'disabled', got '{$mode}'");
		}

		$value = ($mode === 'enabled') ? 'yes' : 'no';
		update_option('woocommerce_feature_custom_order_tables_enabled', $value);

		$controller_class = 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController';
		if (class_exists($controller_class)) {
			$controller = \WC_Data_Store::load('order');
			if (is_callable([$controller_class, 'set_table_usage_enabled'])) {
				call_user_func([$controller_class, 'set_table_usage_enabled'], $mode === 'enabled');
			}
		}

		// Reset data store caches
		if (method_exists(\WC_Data_Store::class, '__set_state')) {
			$reflection = new \ReflectionClass(\WC_Data_Store::class);
			$property = $reflection->getProperty('stores');
			$property->setAccessible(true);
			$property->setValue(null, []);
		}
	}

	/**
	 * Create a WC_Order under the currently configured storage mode.
	 *
	 * @return \WC_Order The order, persisted to whichever storage table the current mode selects.
	 */
	public function create_order_under_current_mode(): \WC_Order
	{
		$order = \WC_Helper_Order::create_order();
		$this->tracked_order_ids[] = (int) $order->get_id();
		return $order;
	}

	/**
	 * Restore the storage mode active at fixture construction.
	 * Called by AbstractRegressionTest::tearDown().
	 */
	public function tear_down(): void
	{
		foreach ($this->tracked_order_ids as $order_id) {
			$order = wc_get_order($order_id);
			if ($order instanceof \WC_Order) {
				$order->delete(true);
			} else {
				wp_delete_post($order_id, true);
			}
		}
		$this->tracked_order_ids = [];

		update_option('woocommerce_feature_custom_order_tables_enabled', $this->original_mode);

		$controller_class = 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController';
		if (class_exists($controller_class) && is_callable([$controller_class, 'set_table_usage_enabled'])) {
			call_user_func([$controller_class, 'set_table_usage_enabled'], $this->original_mode === 'yes');
		}
	}
}
