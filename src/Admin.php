<?php

namespace DisableWoocommerceEmailsPerProduct;
class Admin {
    
    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tabs' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'add_product_tab_content' ) );
    }
    
    public function add_product_tabs( $tabs ) {
        $tabs[ DWEPP_PREFIX . '_disable_emails' ] = array(
            'label'  => __( 'Disable Emails', 'dwepp' ),
            'target' => 'dwepp_options',
        );
        
        return $tabs;
    }
    
    public function add_product_tab_content() {
        global $woocommerce, $post;
        ?>
		<div id="dwepp_options" class="panel woocommerce_options_panel"><?php
    
    
    }
    
}
