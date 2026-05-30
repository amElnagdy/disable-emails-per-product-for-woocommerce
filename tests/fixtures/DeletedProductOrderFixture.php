<?php

namespace Tests\Fixtures;

/**
 * Construct a WooCommerce order containing one or more line items,
 * exactly one of which references a product that has been permanently deleted.
 */
final class DeletedProductOrderFixture
{

	/**
	 * @var \WC_Order|null
	 */
	private $order;

	/**
	 * @var int[]
	 */
	private array $surviving_product_ids = [];

	/**
	 * Construct an order with a deleted line item.
	 *
	 * @param int $line_item_count Total number of line items on the produced order. Default: 2.
	 * @param int $deleted_index   Zero-based index of the line item whose product is deleted. Default: 0.
	 *
	 * @return \WC_Order The persisted order. The product at $deleted_index has been deleted; the others exist.
	 *
	 * @throws \InvalidArgumentException If $line_item_count < 1 or $deleted_index out of range.
	 */
	public function create_with_deleted_line_item(int $line_item_count = 2, int $deleted_index = 0): \WC_Order
	{
		if ($line_item_count < 1) {
			throw new \InvalidArgumentException("line_item_count must be >= 1, got {$line_item_count}");
		}
		if ($deleted_index < 0 || $deleted_index >= $line_item_count) {
			throw new \InvalidArgumentException("deleted_index must be between 0 and " . ($line_item_count - 1) . ", got {$deleted_index}");
		}

		$products = [];
		for ($i = 0; $i < $line_item_count; $i++) {
			$products[] = \WC_Helper_Product::create_simple_product();
		}

		$order = \WC_Helper_Order::create_order();

		// Add line items
		foreach ($products as $product) {
			$item = new \WC_Order_Item_Product();
			$item->set_product_id($product->get_id());
			$item->set_order_id($order->get_id());
			$item->set_name($product->get_name());
			$item->set_quantity(1);
			$item->set_total($product->get_price());
			$item->save();
			$order->add_item($item);
		}
		$order->save();

		// Delete the target product
		$deleted_product = $products[$deleted_index];
		$deleted_id = $deleted_product->get_id();
		wp_delete_post($deleted_id, true);

		// Flush caches so wc_get_product() observes the deletion
		\WC_Cache_Helper::invalidate_cache_group('products');
		wp_cache_delete($deleted_id, 'posts');

		// Track surviving products and the order
		foreach ($products as $idx => $product) {
			if ($idx !== $deleted_index) {
				$this->surviving_product_ids[] = (int) $product->get_id();
			}
		}

		$this->order = $order;

		return $order;
	}

	/**
	 * Tear down all entities created by this fixture during the current test.
	 * Called by AbstractRegressionTest::tearDown().
	 */
	public function tear_down(): void
	{
		if ($this->order !== null) {
			wp_delete_post($this->order->get_id(), true);
			$this->order = null;
		}

		foreach ($this->surviving_product_ids as $product_id) {
			wp_delete_post($product_id, true);
		}
		$this->surviving_product_ids = [];

		// Flush any lingering product cache
		\WC_Cache_Helper::invalidate_cache_group('products');
	}
}
