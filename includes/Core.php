<?php

namespace DisableWoocommerceEmailsPerProduct;

class Core
{
    public function __construct()
    {
        add_action('woocommerce_init', [$this, 'init']);
    }

    public function init(): void
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
            $product = $item->get_product();

            // If it is a variation, get the parent product ID
            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

            $disabled_emails = get_post_meta($product_id, '_disabled_emails', true);

            if (is_array($disabled_emails) && isset($disabled_emails[$email_instance->id])) {
                $recipient = '';
                break;
            }
        }

        return $recipient;
    }
}
