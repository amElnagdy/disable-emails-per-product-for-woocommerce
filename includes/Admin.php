<?php

namespace DisableEmailsPerProductForWooCommerce;

use DisableEmailsPerProductForWooCommerce\Helpers;

class Admin
{

	public function __construct()
	{
		add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tabs']);
		add_action('woocommerce_product_data_panels', [$this, 'add_product_tab_content']);
		add_action('woocommerce_process_product_meta', [$this, 'save_disabled_emails']);

		add_action('woocommerce_admin_order_data_after_order_details', [$this, 'disable_order_emails'], 9999);
		add_action('woocommerce_process_shop_order_meta', [$this, 'save_disable_order_emails']);
		add_action('init', [$this, 'load_text_domain']);
		add_filter('plugin_action_links_' . DEPPWC_BASENAME, array($this, 'donate_link'));
	}

	public function add_product_tabs($tabs)
	{
		$tabs[DEPPWC_PREFIX . '_disable_emails'] = [
			'label'  => __('Disable Emails', 'disable-emails-per-product-for-woocommerce'),
			'target' => 'dwepp_options',
		];

		return $tabs;
	}

	public function add_product_tab_content(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		$saved_emails = Helpers::get_product_disabled_emails((int) get_the_ID());

		echo '<div id="dwepp_options" class="panel woocommerce_options_panel">';

		$mailer = Helpers::get_enabled_emails();
		/**
		 * Filter the list of WooCommerce email IDs excluded from the per-product
		 * "Disable Emails" configuration UI.
		 *
		 * @since 1.1.0
		 *
		 * @param string[] $excluded_ids Array of WooCommerce email ID strings to exclude.
		 *                               Default: ['customer_new_account',
		 *                               'customer_reset_password', 'customer_note'].
		 */
		$non_related_emails = apply_filters(
			'dwepp_excluded_email_ids',
			['customer_new_account', 'customer_reset_password', 'customer_note']
		);
		if (!is_array($non_related_emails)) {
			$non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
		}
		$non_related_emails = array_values(array_filter($non_related_emails, 'is_string'));

		$configurable = [];
		if (is_array($mailer)) {
			foreach ($mailer as $email) {
				if (!($email instanceof \WC_Email)) {
					continue;
				}
				if (in_array($email->id, $non_related_emails, true)) {
					continue;
				}
				$configurable[] = $email;
			}
		}

		$product_id = (int) get_the_ID();
		/**
		 * Filter the list of WC_Email instances offered for per-product suppression
		 * configuration after the dwepp_excluded_email_ids exclusion list has been applied.
		 *
		 * @since 1.1.0
		 *
		 * @param \WC_Email[] $emails     Enabled emails minus the exclusion list.
		 * @param int         $product_id Product whose configuration UI is being rendered.
		 */
		$configurable_filtered = apply_filters('dwepp_product_configurable_emails', $configurable, $product_id);
		if (!is_array($configurable_filtered)) {
			$configurable_filtered = $configurable;
		}

		$rendered = 0;
		foreach ($configurable_filtered as $email) {
			if (!($email instanceof \WC_Email)) {
				continue;
			}
			woocommerce_wp_checkbox([
				'id'          => 'dwepp_disabled_emails[' . $email->id . ']',
				'label'       => $email->title,
				'value'       => $saved_emails[$email->id] ?? 'no',
				'cbvalue'     => 'yes',
				'desc_tip'    => true,
				'description' => sprintf(
					/* translators: %s: email title */
					esc_html__('Check to disable %s email for this product.', 'disable-emails-per-product-for-woocommerce'),
					esc_html($email->title)
				),
			]);
			++$rendered;
		}
		if (0 === $rendered) {
			echo '<p>' . esc_html__('No emails are currently available for per-product configuration.', 'disable-emails-per-product-for-woocommerce') . '</p>';
		}

		wp_nonce_field('save_disabled_emails_action', 'save_disabled_emails_nonce');

		echo '</div>';
	}

	public function save_disabled_emails($post_id): void
	{
		// Exit if doing autosave or nonce is not set or fails verification.
		if (
			(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
			!isset($_POST['save_disabled_emails_nonce']) ||
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['save_disabled_emails_nonce'])), 'save_disabled_emails_action')
		) {
			return;
		}

		// Ensure the current user can edit the post
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$meta_key_default = '_disabled_emails';
		/**
		 * Filter the WordPress post-meta key used to store per-product disabled-email configuration.
		 *
		 * @since 1.1.0
		 *
		 * @param string $meta_key The current meta key. Default: '_disabled_emails'.
		 */
		$meta_key = apply_filters('dwepp_disabled_emails_meta_key', $meta_key_default);
		if (!is_string($meta_key) || $meta_key === '' || preg_match('/\s/', $meta_key)) {
			$meta_key = $meta_key_default;
		}

		if (isset($_POST['dwepp_disabled_emails']) && is_array($_POST['dwepp_disabled_emails'])) {
			$sanitized_data = array_map('sanitize_text_field', wp_unslash($_POST['dwepp_disabled_emails']));
			update_post_meta($post_id, $meta_key, $sanitized_data);
		} else {
			delete_post_meta($post_id, $meta_key);
		}
	}

	/**
	 * Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/
	 *
	 * @param $order
	 *
	 * @return void
	 */
	public function disable_order_emails($order): void
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		$value = '';
		if ($order instanceof \WC_Order) {
			$value = $order->get_meta('_disable_order_emails');
		}

		woocommerce_wp_checkbox(
			array(
				'id'            => '_disable_order_emails',
				'value'         => $value,
				'cbvalue'       => 'yes',
				'label'         => __('Disable Order Emails', 'disable-emails-per-product-for-woocommerce'),
				'description'   => __('Check this if you wish to disable emails when order status changes. Make sure to update the order after checking this box and before changing the status.', 'disable-emails-per-product-for-woocommerce'),
				'wrapper_class' => 'form-field-wide',
				'style'         => 'width:auto',
			)
		);
		wp_nonce_field('disable_order_emails_action', 'disable_order_emails_nonce');
	}

	/**
	 * Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/
	 *
	 * @param $order_id
	 *
	 * @return void
	 */

	public function save_disable_order_emails($order_id): void
	{
		// Combine checks for autosave and nonce verification.
		if (
			(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
			!isset($_POST['disable_order_emails_nonce']) ||
			!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['disable_order_emails_nonce'])), 'disable_order_emails_action')
		) {
			return;
		}

		// Ensure the current user has the capability to edit the order
		if (!current_user_can('edit_post', $order_id)) {
			return;
		}

		// Load the order through WooCommerce CRUD so the write is HPOS-safe
		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}

		$meta_key_default = '_disable_order_emails';
		/**
		 * Filter the WordPress post-meta key used to store the per-order disable-emails flag.
		 *
		 * @since 1.1.0
		 *
		 * @param string $meta_key The current meta key. Default: '_disable_order_emails'.
		 */
		$meta_key = apply_filters('dwepp_disable_order_emails_meta_key', $meta_key_default);
		if (!is_string($meta_key) || $meta_key === '' || preg_match('/\s/', $meta_key)) {
			$meta_key = $meta_key_default;
		}

		// Update or delete the meta based on whether _disable_order_emails is set
		if (isset($_POST['_disable_order_emails'])) {
			$order->update_meta_data($meta_key, sanitize_text_field(wp_unslash($_POST['_disable_order_emails'])));
		} else {
			$order->delete_meta_data($meta_key);
		}
		$order->save_meta_data();
	}


	public function load_text_domain(): void
	{
		load_plugin_textdomain(
			'disable-emails-per-product-for-woocommerce',
			false,
			dirname(plugin_basename(DEPPWC_PLUGIN_FILE)) . '/languages'
		);
	}

	public function donate_link($links)
	{
		$donate_link = '<a href="https://ko-fi.com/nagdy" target="_blank" rel="noopener noreferrer" style="color: green;">' . __('Donate', 'disable-emails-per-product-for-woocommerce') . '</a>';
		array_unshift($links, $donate_link);
		return $links;
	}
}
