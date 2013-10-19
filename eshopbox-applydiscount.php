<?php
/*
Plugin Name: Apply discount on checkout based on payment gateway
Plugin URI: http://www.eshopbox.com
Description: Add discount based on payment gateway
Version: 1.0
Author: vaibhav sharma
Author URI: http://www.eshopbox.com
*/

/**
 * Copyright (c) `date "+%Y"` Vaibhav Sharma. All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class eshopboxapplydiscount{
    public function __construct(){
        $this -> current_gateway_title = '';
        $this -> current_gateway_extra_charges = '';
        add_action('admin_head', array($this, 'add_form_fields'));
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 100, 1 );
        wp_enqueue_script( 'wc-add-extra-charges', $this->plugin_url() . '/assets/app.js', array('wc-checkout'), false, true );
    }

    

    function add_form_fields(){
        global $woocommerce;
         // Get current tab/section
        $current_tab        = ( empty( $_GET['tab'] ) ) ? '' : sanitize_text_field( urldecode( $_GET['tab'] ) );
        $current_section    = ( empty( $_REQUEST['section'] ) ) ? '' : sanitize_text_field( urldecode( $_REQUEST['section'] ) );
        if($current_tab == 'payment_gateways' && $current_section!=''){
            $gateways = $woocommerce->payment_gateways->payment_gateways();
            foreach($gateways as $gateway){
                if(get_class($gateway)==$current_section){
                    $current_gateway = $gateway -> id;
                    $extra_charges_id = 'woocommerce_'.$current_gateway.'_discount';
                    $extra_charges_type = $extra_charges_id.'_discount_type';
                    if(isset($_REQUEST['save'])){
                        update_option( $extra_charges_id, $_REQUEST[$extra_charges_id] );
                        update_option( $extra_charges_type, $_REQUEST[$extra_charges_type] );
                    }
                    $extra_charges = get_option( $extra_charges_id);
                    $extra_charges_type_value = get_option($extra_charges_type);
                }
            }

            ?>
            <script>
            jQuery(document).ready(function($){
                $data = '<h4>Discount on checkout</h4><table class="form-table">';
                $data += '<tr valign="top">';
                $data += '<th scope="row" class="titledesc">Discount Amount</th>';
                $data += '<td class="forminp">';
                $data += '<fieldset>';
                $data += '<input style="" name="<?php echo $extra_charges_id?>" id="<?php echo $extra_charges_id?>" type="text" value="<?php echo $extra_charges?>"/>';
                $data += '<br /></fieldset></td></tr>';
                $data += '<tr valign="top">';
                $data += '<th scope="row" class="titledesc">Discount Type</th>';
                $data += '<td class="forminp">';
                $data += '<fieldset>';
                $data += '<select name="<?php echo $extra_charges_type?>"><option <?php if($extra_charges_type_value=="add") echo "selected=selected"?> value="add">Total Add</option>';
                $data += '<option <?php if($extra_charges_type_value=="percentage") echo "selected=selected"?> value="percentage">Total % Add</option>';
                $data += '<br /></fieldset></td></tr></table>';
                $('.form-table:last').after($data);

            });
            </script>
<?php
}
}

public function calculate_totals( $totals ) {
    global $woocommerce;
    $available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
 
    $current_gateway = '';
    if ( ! empty( $available_gateways ) ) {
           // Chosen Method
        if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
            $current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
        } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
            $current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
        } else {
            $current_gateway =  current( $available_gateways );

        }
    }
    if($current_gateway!=''){
        $current_gateway_id = $current_gateway -> id;
        $extra_charges_id = 'woocommerce_'.$current_gateway_id.'_discount';
        $extra_charges_type = $extra_charges_id.'_discount_type';
        $extra_charges = (float)get_option( $extra_charges_id);
        $extra_charges_type_value = get_option( $extra_charges_type); 
        if($extra_charges){
            if($extra_charges_type_value=="percentage"){
                $totals -> cart_contents_total = $totals -> cart_contents_total - round(($totals -> cart_contents_total*$extra_charges)/100);
            }else{
                $totals -> cart_contents_total = $totals -> cart_contents_total - $extra_charges;
            }

            $this -> current_gateway_title = $current_gateway -> title;
            $this -> current_gateway_extra_charges = $extra_charges;
            $this -> current_gateway_extra_charges_type_value = $extra_charges_type_value;
            add_action( 'woocommerce_review_order_before_order_total',  array( $this, 'add_payment_gateway_extra_charges_row'));

        }

    }
  return $totals;
}

function add_payment_gateway_extra_charges_row(){
    ?>
    <tr class="payment-extra-charge">
        <th>Discount on checkout</th>
        <td><?php if($this->current_gateway_extra_charges_type_value=="percentage"){
            echo $this -> current_gateway_extra_charges.'%';
        }else{
         echo woocommerce_price($this -> current_gateway_extra_charges);
	echo '<input type="hidden" name="checkoutdiscountvalue" value="'.$this -> current_gateway_extra_charges.'" />';
	echo '<input type="hidden" name="checkoutdiscounttitle" value="'.$this->current_gateway_title.' Extra Charges" />';
     }?></td>
 </tr>
 <?php
}

/**
     * Get the plugin url.
     *
     * @access public
     * @return string
     */
    public function plugin_url() {
        if ( $this->plugin_url ) return $this->plugin_url;
        return $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
    }


    /**
     * Get the plugin path.
     *
     * @access public
     * @return string
     */
    public function plugin_path() {
        if ( $this->plugin_path ) return $this->plugin_path;

        return $this->plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );
    }

}
new eshopboxapplydiscount();
