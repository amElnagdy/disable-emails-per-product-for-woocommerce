<?php

namespace DisableWoocommerceEmailsPerProduct;

class Core
{
    public function __construct()
    {
        add_action('woocommerce_init', [$this, 'init']);
    }

    public function init()
    {
        $mailer = WC()->mailer()->get_emails();
        foreach ($mailer as $email) {
            if ($email->is_enabled()) {
                add_filter('woocommerce_email_recipient_' . $email->id, [$this, 'filter_woocommerce_email_recipient'], 10, 3);
            }
        }
    }

    public function filter_woocommerce_email_recipient($recipient, $order, $email_instance)
    {
        if (!is_a($order, 'WC_Order') || !is_a($email_instance, 'WC_Email')) return $recipient;

        // Loop through order items
        foreach ($order->get_items() as $key => $item) {
            $product_id = $item->get_variation_id() > 0 ? $item->get_variation_id() : $item->get_product_id();

            // Get the disabled emails for this product
            $disabled_emails = get_post_meta($product_id, '_disabled_emails', true);

            if (is_array($disabled_emails) && isset($disabled_emails[$email_instance->id])) {
                $recipient = '';
                break;
            }
        }
        
        return $recipient;
    }
}
