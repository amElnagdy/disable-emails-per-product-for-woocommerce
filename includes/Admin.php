<?php

namespace DisableEmailsPerProductForWooCommerce;

class Admin {
    
    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_tabs' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'add_product_tab_content' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_disabled_emails' ] );
        add_action( 'admin_head', [ $this, 'enqueue_custom_css_js' ] );
        add_action( 'woocommerce_admin_order_data_after_order_details', [ $this, 'disable_order_emails' ], 9999 );
        add_action( 'save_post_shop_order', [ $this, 'save_disable_order_emails' ] );
        
    }
    
    public function enqueue_custom_css_js(): void {
        if ( isset( $_GET['page'] ) && $_GET['page'] == 'wc-settings' && isset( $_GET['tab'] ) && $_GET['tab'] == 'disable_woocommerce_emails_per_product' ) {
            echo '<style>.woocommerce-save-button { display: none !important; }
.name {font-weight: bold !important;}</style>';
        }
    }
    
    
    public function add_product_tabs( $tabs ) {
        $tabs[ DWEPP_PREFIX . '_disable_emails' ] = [
            'label'  => __( 'Disable Emails', 'dwepp' ),
            'target' => 'dwepp_options',
        ];
        
        return $tabs;
    }
    
    public function add_product_tab_content(): void {
        $saved_emails = get_post_meta( get_the_ID(), '_disabled_emails', true ) ?: [];
        
        echo '<div id="dwepp_options" class="panel woocommerce_options_panel">';
        
        $mailer             = WC()->mailer()->get_emails();
        $non_related_emails = [ 'customer_new_account', 'customer_reset_password', 'customer_note' ];
        
        foreach ( $mailer as $email ) {
            if ( $email->is_enabled() && ! in_array( $email->id, $non_related_emails ) ) {
                woocommerce_wp_checkbox( [
                    'id'          => 'dwepp_disabled_emails[' . $email->id . ']',
                    'label'       => $email->title,
                    'value'       => $saved_emails[ $email->id ] ?? 'no',
                    'cbvalue'     => 'yes',
                    'desc_tip'    => true,
                    'description' => sprintf( __( 'Check to disable %s email for this product.', 'dwepp' ), $email->title ),
                ] );
            }
        }
        
        echo '</div>';
    }
    
    public function save_disabled_emails( $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( isset( $_POST['dwepp_disabled_emails'] ) && is_array( $_POST['dwepp_disabled_emails'] ) ) {
            $sanitized_data = array_map( 'sanitize_text_field', $_POST['dwepp_disabled_emails'] );
            update_post_meta( $post_id, '_disabled_emails', $sanitized_data );
        } else {
            delete_post_meta( $post_id, '_disabled_emails' );
        }
    }
    
    /**
     * Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/
     *
     * @param $order
     *
     * @return void
     */
    public function disable_order_emails( $order ): void {
        woocommerce_wp_checkbox(
            array(
                'id'            => '_disable_order_emails',
                'label'         => __( 'Disable Order Emails', 'dwepp' ),
                'description'   => 'Check this if you wish to disable emails when order status changes. Make sure to update the order after checking this box and before changing the status.',
                'wrapper_class' => 'form-field-wide',
                'style'         => 'width:auto',
            )
        );
    }
    
    /**
     * Credit: https://www.businessbloomer.com/woocommerce-disable-emails-single-order/
     *
     * @param $order_id
     *
     * @return void
     */
    
    public function save_disable_order_emails( $order_id ): void {
        
        global $pagenow, $typenow;
        
        if ( 'post.php' !== $pagenow || 'shop_order' !== $typenow ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( isset( $_POST['_disable_order_emails'] ) ) {
            update_post_meta( $order_id, '_disable_order_emails', $_POST['_disable_order_emails'] );
        } else {
            delete_post_meta( $order_id, '_disable_order_emails' );
        }
        
    }
}
