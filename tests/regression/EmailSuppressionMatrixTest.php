<?php

namespace Tests\Regression;

use WC_Helper_Order;
use WC_Helper_Product;

/**
 * Regression test suite covering the critical email-suppression matrix.
 *
 * Every row of the ten-row critical QA matrix has at least one covering
 * test method. Tests assert on the recipient string at the wp_mail boundary
 * using the RecipientCapture utility.
 *
 * @see contracts/test-suite-shape.md for naming and assertion conventions.
 */
class EmailSuppressionMatrixTest extends AbstractRegressionTest
{

	/**
	 * Trigger a WooCommerce email for the given order and email ID.
	 *
	 * @param \WC_Order $order    Order object.
	 * @param string    $email_id WooCommerce email ID (e.g. 'new_order').
	 */
	private function send_wc_email_for_order(\WC_Order $order, string $email_id): void
	{
		$mailer = WC()->mailer()->get_emails();
		if (!is_array($mailer)) {
			return;
		}

		foreach ($mailer as $email) {
			if (!($email instanceof \WC_Email)) {
				continue;
			}
			if ($email->id !== $email_id) {
				continue;
			}

			if ($email_id === 'new_order') {
				$email->trigger($order->get_id(), $order);
			} elseif ($email_id === 'customer_note') {
				// Customer note email is dispatched via add_order_note.
				$order->add_order_note('Test customer note', true);
			} else {
				$email->trigger($order->get_id());
			}
			return;
		}
	}

	/**
	 * Return the customer's billing email for an order.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string Customer email or empty string.
	 */
	private function customer_email_for_order(\WC_Order $order): string
	{
		return (string) $order->get_billing_email();
	}

	// =====================================================================
	// new_order email tests
	// =====================================================================

	public function test_new_order_email_with_no_suppression_under_hpos_disabled__email_is_delivered(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'new_order');

		$this->assertSame(
			get_option('admin_email'),
			$this->capture->latest_recipient_for('new_order'),
			'new_order email should be delivered to admin when no suppression is configured.'
		);
	}

	public function test_new_order_email_with_product_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['new_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		// Add the suppressed product to the order
		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'new_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('new_order'),
			'new_order email should be suppressed when the order contains a product with per-product suppression.'
		);
	}

	public function test_new_order_email_with_product_level_suppression_under_hpos_enabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('enabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['new_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'new_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('new_order'),
			'new_order email should be suppressed under HPOS-enabled when the order contains a product with per-product suppression.'
		);
	}

	public function test_new_order_email_with_order_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();
		$this->suppression_fixture->set_order_suppression($order->get_id(), 'yes');

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'new_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('new_order'),
			'new_order email should be suppressed when order-level suppression is enabled.'
		);
	}

	public function test_new_order_email_with_deleted_product_under_hpos_disabled__email_is_delivered_and_no_php_warning_is_raised(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->deleted_product_fixture->create_with_deleted_line_item(2, 0);

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'new_order');

		$this->assertSame(
			get_option('admin_email'),
			$this->capture->latest_recipient_for('new_order'),
			'new_order email should still be delivered when one line item references a deleted product.'
		);
		$this->assertNoPhpDiagnostic('Deleted-product line item must not raise PHP warnings.');
	}

	// =====================================================================
	// processing email tests
	// =====================================================================

	public function test_processing_email_with_no_suppression_under_hpos_disabled__email_is_delivered(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			$this->customer_email_for_order($order),
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should be delivered to the customer when no suppression is configured.'
		);
	}

	public function test_processing_email_with_product_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_processing_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should be suppressed when the order contains a product with per-product suppression.'
		);
	}

	public function test_processing_email_with_product_level_suppression_under_hpos_enabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('enabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_processing_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should be suppressed under HPOS-enabled when the order contains a product with per-product suppression.'
		);
	}

	public function test_processing_email_with_order_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();
		$this->suppression_fixture->set_order_suppression($order->get_id(), 'yes');

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should be suppressed when order-level suppression is enabled.'
		);
	}

	public function test_processing_email_with_order_level_suppression_under_hpos_enabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('enabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();
		$this->suppression_fixture->set_order_suppression($order->get_id(), 'yes');

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should be suppressed under HPOS-enabled when order-level suppression is enabled.'
		);
	}

	public function test_processing_email_with_deleted_product_under_hpos_enabled__email_is_delivered_and_no_php_warning_is_raised(): void
	{
		$this->hpos_fixture->set_storage_mode('enabled');
		$order = $this->deleted_product_fixture->create_with_deleted_line_item(2, 0);

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_processing_order');

		$this->assertSame(
			$this->customer_email_for_order($order),
			$this->capture->latest_recipient_for('customer_processing_order'),
			'processing email should still be delivered when one line item references a deleted product.'
		);
		$this->assertNoPhpDiagnostic('Deleted-product line item must not raise PHP warnings.');
	}

	// =====================================================================
	// completed email tests
	// =====================================================================

	public function test_completed_email_with_product_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_completed_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_completed_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_completed_order'),
			'completed email should be suppressed when the order contains a product with per-product suppression.'
		);
	}

	public function test_completed_email_with_product_level_suppression_under_hpos_enabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('enabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_completed_order' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_completed_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_completed_order'),
			'completed email should be suppressed under HPOS-enabled when the order contains a product with per-product suppression.'
		);
	}

	public function test_completed_email_with_order_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();
		$this->suppression_fixture->set_order_suppression($order->get_id(), 'yes');

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_completed_order');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_completed_order'),
			'completed email should be suppressed when order-level suppression is enabled.'
		);
	}

	public function test_completed_email_with_no_suppression_under_hpos_disabled__email_is_delivered(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_completed_order');

		$this->assertSame(
			$this->customer_email_for_order($order),
			$this->capture->latest_recipient_for('customer_completed_order'),
			'completed email should be delivered to the customer when no suppression is configured.'
		);
	}

	// =====================================================================
	// customer_note email tests
	// =====================================================================

	public function test_customer_note_email_with_product_level_suppression_under_hpos_disabled__email_is_delivered_because_customer_note_is_excluded_by_default(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$product = WC_Helper_Product::create_simple_product();
		// Even if legacy data has customer_note in the meta, it should be ignored
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_note' => 'yes']
		);
		$order = $this->hpos_fixture->create_order_under_current_mode();

		$item = new \WC_Order_Item_Product();
		$item->set_product_id($product->get_id());
		$item->set_order_id($order->get_id());
		$item->set_name($product->get_name());
		$item->set_quantity(1);
		$item->set_total($product->get_price());
		$item->save();
		$order->add_item($item);
		$order->save();

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_note');

		$this->assertSame(
			$this->customer_email_for_order($order),
			$this->capture->latest_recipient_for('customer_note'),
			'customer_note email should still be delivered because it is excluded from per-product suppression by default.'
		);
	}

	public function test_customer_note_email_with_order_level_suppression_under_hpos_disabled__email_is_suppressed(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$order = $this->hpos_fixture->create_order_under_current_mode();
		$this->suppression_fixture->set_order_suppression($order->get_id(), 'yes');

		$this->capture->reset();
		$this->send_wc_email_for_order($order, 'customer_note');

		$this->assertSame(
			'',
			$this->capture->latest_recipient_for('customer_note'),
			'customer_note email should be suppressed when order-level suppression is enabled.'
		);
	}

	// =====================================================================
	// new_account email tests
	// =====================================================================

	public function test_new_account_email_with_no_suppression_under_hpos_disabled__email_is_delivered(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$user_id = $this->factory->user->create(['role' => 'customer']);
		$user = get_user_by('id', $user_id);

		$this->capture->reset();
		$emails = WC()->mailer()->get_emails();
		if (is_array($emails) && isset($emails['customer_new_account']) && $emails['customer_new_account'] instanceof \WC_Email) {
			$emails['customer_new_account']->trigger($user_id, 'password');
		}

		$this->assertSame(
			$user->user_email,
			$this->capture->latest_recipient_for('customer_new_account'),
			'new_account email should be delivered to the newly registered customer.'
		);
	}

	public function test_new_account_email_with_product_level_suppression_under_hpos_disabled__email_is_delivered_because_new_account_is_not_order_associated(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$product = WC_Helper_Product::create_simple_product();
		$this->suppression_fixture->set_product_suppression(
			$product->get_id(),
			['customer_new_account' => 'yes']
		);

		$user_id = $this->factory->user->create(['role' => 'customer']);
		$user = get_user_by('id', $user_id);

		$this->capture->reset();
		$emails = WC()->mailer()->get_emails();
		if (is_array($emails) && isset($emails['customer_new_account']) && $emails['customer_new_account'] instanceof \WC_Email) {
			$emails['customer_new_account']->trigger($user_id, 'password');
		}

		$this->assertSame(
			$user->user_email,
			$this->capture->latest_recipient_for('customer_new_account'),
			'new_account email should still be delivered because it is not order-associated and is excluded from per-product suppression.'
		);
	}

	// =====================================================================
	// reset_password email tests
	// =====================================================================

	public function test_reset_password_email_with_no_suppression_under_hpos_disabled__email_is_delivered(): void
	{
		$this->hpos_fixture->set_storage_mode('disabled');
		$user_id = $this->factory->user->create(['role' => 'customer']);
		$user = get_user_by('id', $user_id);
		$key = get_password_reset_key($user);

		$this->capture->reset();
		$emails = WC()->mailer()->get_emails();
		if (is_array($emails) && isset($emails['customer_reset_password']) && $emails['customer_reset_password'] instanceof \WC_Email) {
			$emails['customer_reset_password']->trigger($user->user_login, $key);
		}

		$this->assertSame(
			$user->user_email,
			$this->capture->latest_recipient_for('customer_reset_password'),
			'reset_password email should be delivered to the customer.'
		);
	}
}
