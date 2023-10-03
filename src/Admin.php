<?php

namespace DisableWoocommerceEmailsPerProduct;

class Admin
{

    public function __construct()
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tabs']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_disabled_emails']);
    }

    public function add_product_tabs($tabs)
    {
        $tabs[DWEPP_PREFIX . '_disable_emails'] = [
            'label'  => __('Disable Emails', 'dwepp'),
            'target' => 'dwepp_options',
        ];

        return $tabs;
    }

    public function add_product_tab_content()
    {
        $saved_emails = get_post_meta(get_the_ID(), '_disabled_emails', true) ?: [];

        echo '<div id="dwepp_options" class="panel woocommerce_options_panel">';

        $mailer = WC()->mailer()->get_emails();
        $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];

        foreach ($mailer as $email) {
            if ($email->is_enabled() && !in_array($email->id, $non_related_emails)) {
                woocommerce_wp_checkbox([
                    'id'          => 'dwepp_disabled_emails[' . $email->id . ']',
                    'label'       => $email->title,
                    'value'       => $saved_emails[$email->id] ?? 'no',
                    'cbvalue'     => 'yes',
                    'desc_tip'    => true,
                    'description' => sprintf(__('Check to disable %s email for this product.', 'dwepp'), $email->title),
                ]);
            }
        }

        echo '</div>';
    }

    public function save_disabled_emails($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['dwepp_disabled_emails']) && is_array($_POST['dwepp_disabled_emails'])) {
            $sanitized_data = array_map('sanitize_text_field', $_POST['dwepp_disabled_emails']);
            update_post_meta($post_id, '_disabled_emails', $sanitized_data);
        } else {
            delete_post_meta($post_id, '_disabled_emails');
        }
    }
}
