<?php

namespace DisableWoocommerceEmailsPerProduct;

class Admin {
    
    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_tabs' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'add_product_tab_content' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_disabled_emails' ] );
    }

    public function add_product_tabs( $tabs ) {
        $tabs[ DWEPP_PREFIX . '_disable_emails' ] = [
            'label'  => __( 'Disable Emails', 'dwepp' ),
            'target' => 'dwepp_options',
        ];
        
        return $tabs;
    }

    public function add_product_tab_content() {
        global $post;
    
        $saved_emails = get_post_meta( $post->ID, '_disabled_emails', true );
        
        ?>
        <div id="dwepp_options" class="panel woocommerce_options_panel">
            <?php
            $mailer = WC()->mailer()->get_emails();
            $non_related_emails = ['customer_new_account', 'customer_reset_password', 'customer_note'];
            
            foreach ( $mailer as $email ) {
                if ( $email->is_enabled() && ! in_array( $email->id, $non_related_emails ) ) {
                    $checked = isset( $saved_emails[ $email->id ] ) ? 'yes' : 'no';
                    
                    woocommerce_wp_checkbox(
                        [
                            'id'            => 'dwepp_disabled_emails[' . $email->id . ']',
                            'label'         => $email->title,
                            'value'         => $checked,
                            'cbvalue'       => $email->id,
                            'desc_tip'      => true,
                            'description'   => sprintf( __( 'Check to disable %s email for this product.', 'dwepp' ), $email->title )
                        ]
                    );
                }
            }
            ?>
        </div>
        <?php
    }
    

    public function save_disabled_emails( $post_id ) {
        if ( ! wp_verify_nonce( $_POST[ '_wpnonce' ], 'woocommerce_save_data' ) ) {
            return;
        }

        if ( isset( $_POST['dwepp_disabled_emails'] ) ) {
            update_post_meta( $post_id, '_disabled_emails', $_POST['dwepp_disabled_emails'] );
        } else {
            delete_post_meta( $post_id, '_disabled_emails' );
        }
    }
}
