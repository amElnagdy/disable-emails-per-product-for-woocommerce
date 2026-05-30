<?php

namespace DisableEmailsPerProductForWooCommerce;

use DisableEmailsPerProductForWooCommerce\Helpers;

class GlobalView
{

	public function __construct()
	{
		add_action('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
		add_action('woocommerce_settings_tabs_disable_woocommerce_emails_per_product', [$this, 'settings_tab']);
		add_action('woocommerce_admin_field_dwepp_disabled_emails_overview', [$this, 'render_disabled_emails_overview']);
	}

	public function add_settings_tab($settings_tab)
	{
		$settings_tab['disable_woocommerce_emails_per_product'] = __('Disable Emails Per Product', 'disable-emails-per-product-for-woocommerce');

		return $settings_tab;
	}

	public function settings_tab(): void
	{
		if (! current_user_can('manage_woocommerce')) {
			return;
		}
		echo '<style>.woocommerce-save-button { display: none !important; } .name { font-weight: bold !important; }</style>';
		woocommerce_admin_fields($this->get_settings());
	}

	public function get_settings(): array
	{
		$products_with_disabled_emails = Helpers::render_disabled_emails_overview_table();

		return [
			'section_title' => [
				'name' => __('Products with Disabled Emails', 'disable-emails-per-product-for-woocommerce'),
				'type' => 'title',
				'desc' => esc_html__('This is a general overview of all product with disabled emails.', 'disable-emails-per-product-for-woocommerce'),
				'id'   => 'wc_disabled_emails_section_title',
			],
			'products_list' => [
				'name' => __('Products', 'disable-emails-per-product-for-woocommerce'),
				'type' => 'dwepp_disabled_emails_overview',
				'desc' => $products_with_disabled_emails,
				'id'   => 'wc_disabled_emails_products_list',
			],
			'section_end'   => [
				'type' => 'sectionend',
				'id'   => 'wc_disabled_emails_section_end',
			],
		];
	}

	public function render_disabled_emails_overview($value): void
	{
		if (! isset($value['desc'])) {
			return;
		}
		echo wp_kses_post($value['desc']);
	}


}
