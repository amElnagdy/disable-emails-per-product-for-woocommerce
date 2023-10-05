<?php

namespace DisableWoocommerceEmailsPerProduct;

class GlobalView
{

    public function __construct()
    {
        add_action('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_disable_woocommerce_emails_per_product', [$this, 'settings_tab']);
        add_action('woocommerce_admin_field_custom_html', [$this, 'custom_html_field']);
    }

    public function add_settings_tab($settings_tab)
    {
        $settings_tab['disable_woocommerce_emails_per_product'] = __('Disable Emails Per Product', 'dwepp');
        return $settings_tab;
    }

    public function settings_tab(): void {

        woocommerce_admin_fields($this->get_settings());
    }

    public function get_settings(): array {
        $products_with_disabled_emails = $this->get_products_with_disabled_emails();
        return [
            'section_title' => [
                'name' => __('Products with Disabled Emails', 'woocommerce'),
                'type' => 'title',
                'desc' => esc_html__('This is a general overview of all product with disabled emails.', 'dwepp'),
                'id' => 'wc_disabled_emails_section_title',
            ],
            'products_list' => [
                'name' => __('Products', 'woocommerce'),
                'type' => 'custom_html',
                'desc' => $products_with_disabled_emails,
                'id' => 'wc_disabled_emails_products_list',
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'wc_disabled_emails_section_end',
            ],
        ];
    }

    public function custom_html_field($value)
    {
        echo $value['desc'];
    }
    
    public function get_products_with_disabled_emails()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'postmeta';
        $query = $wpdb->prepare("SELECT post_id FROM $table_name WHERE meta_key = %s AND meta_value != %s", '_disabled_emails', '');
        $product_ids = $wpdb->get_col($query);
        
        if (empty($product_ids)) {
            return __('No products found with disabled emails.', 'dwepp');
        }
        
        $table = '<table class="widefat">';
        $table .= '<thead><tr><th class="name">' . __('Product Name', 'dwepp') . '</th><th class="name">' . __('Disabled Emails', 'dwepp') . '</th><th class="name">' . __('Edit Product', 'dwepp') . '</th></tr></thead>';
        $table .= '<tbody>';
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $disabled_emails = get_post_meta($product_id, '_disabled_emails', true);
                $disabled_email_keys = is_array($disabled_emails) ? array_keys($disabled_emails, 'yes', true) : [];
                $disabled_email_list = implode(', ', $disabled_email_keys);
                $edit_link = get_edit_post_link($product_id);
                $table .= "<tr>";
                $table .= "<td>{$product->get_name()}</td>";
                $table .=  "<td>{$disabled_email_list}</td>";
                $table .= "<td><a href=\"{$edit_link}\">" . __('Edit', 'dwepp') . "</a></td>";
                $table .= "</tr>";
            }
        }
        
        $table .= '</tbody></table>';
        
        return $table;
    }
    
    
}
