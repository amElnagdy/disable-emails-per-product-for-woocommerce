<?php

namespace DisableEmailsPerProductForWooCommerce;

use DisableEmailsPerProductForWooCommerce\Helpers;

class Core
{
	public function __construct()
	{
		add_action('woocommerce_init', [$this, 'init']);
	}


	public function init(): void
	{
		$emails = Helpers::get_enabled_emails();
		foreach ($emails as $email) {
			add_filter('woocommerce_email_recipient_' . $email->id, [
				$this,
				'filter_woocommerce_email_recipient'
			], 10, 3);
			add_filter('woocommerce_email_recipient_' . $email->id, [
				$this,
				'filter_woocommerce_order_email_recipient'
			], 9999, 2);
		}
	}

	public function filter_woocommerce_email_recipient($recipient, $order, $email_instance)
	{
		if (!is_a($order, 'WC_Order') || !is_a($email_instance, 'WC_Email')) {
			return $recipient;
		}

		$items = $order->get_items();
		if (!is_array($items) && !($items instanceof \Traversable)) {
			return $recipient;
		}

		// Loop through order items
		foreach ($items as $key => $item) {
			if (!($item instanceof \WC_Order_Item_Product)) {
				continue;
			}

			$product = $item->get_product();
			if (!is_a($product, 'WC_Product')) {
				continue;
			}

			if ($product->is_type('variation')) {
				$product_id = $product->get_parent_id();
				if ($product_id <= 0) {
					continue;
				}
			} else {
				$product_id = $product->get_id();
			}

			if ($product_id <= 0) {
				continue;
			}

			$disabled_emails = Helpers::get_product_disabled_emails((int) $product_id);

			if (isset($disabled_emails[$email_instance->id])) {
				$recipient = '';
				break;
			}
		}

		return $recipient;
	}

	/**
	 * Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/
	 *
	 * @param $recipient
	 * @param $order
	 *
	 * @return mixed|string
	 */
	public function filter_woocommerce_order_email_recipient($recipient, $order)
	{
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ('wc-settings' === $page) {
			return $recipient;
		}
		if (!is_a($order, 'WC_Order')) {
			return $recipient;
		}
		if (Helpers::is_order_emails_disabled((int) $order->get_id())) {
			$recipient = '';
		}
		return $recipient;
	}
}
