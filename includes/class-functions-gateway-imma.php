<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'FunctionsGatewayIMMA', false ) ) {
	return new FunctionsGatewayIMMA();
}

class FunctionsGatewayIMMA {
    
	public function __construct () {
		add_action( 'restrict_manage_posts', array( $this, 'filter_orders_by_payment_gateway_imma' ), 10 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( $this, 'filter_order_list_by_payment_gateway_imma' ), 10, 2 );
		add_filter( 'pre_get_posts', array( $this, 'orders_by_payment_gateway_query_imma' ), 99, 1 );
		add_filter( 'woocommerce_shop_order_list_table_prepare_items_query_args', array( $this, 'order_list_by_payment_gateway_query_imma' ), 10, 1 );
	} //End __construct()

	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), WM_VERSION );
	} //End __clone()

	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), WM_VERSION );
	} //End __wakeup()
	
	public function filter_orders_by_payment_gateway_imma () {
		global $typenow;
		$gateway_filter_id_imma		= '';
		$all_options 				= '';
		$shop_order_view 			= 'woocommerce_page_wc-orders';

		if ( isset($_GET['gateway_filter_id_imma']) && $_GET['gateway_filter_id_imma'] != "" ){
			$gateway_filter_id_imma = wc_clean( wp_unslash( $_GET['gateway_filter_id_imma'] ) );
		}
		if ( function_exists('wc_get_page_screen_id') ) {
			$shop_order_view = wc_get_page_screen_id( 'shop-order' );
		}

		if ( 'shop_order' === $typenow || $shop_order_view === $typenow ) {
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			if ( !empty($gateways) ) {
				$own_payment_gateways = array();
		        foreach ($gateways as $id => $gateway) {
		        	$own_payment_gateways[] = $id;
		            $all_options = $all_options . '<option value="' . esc_attr($id) . '"';
		            if ( $gateway_filter_id_imma == $id ) {
		                $all_options = $all_options . ' selected="selected"';
		            }
		            $all_options = $all_options . '>' . esc_html( $gateway->get_title() ) . '</option>';
		        }
		        if ( !in_array('epayco', $own_payment_gateways) ) {
		            $all_options = $all_options . '<option value="epayco"';
		            if ( $gateway_filter_id_imma == 'epayco' ) {
		                $all_options = $all_options . ' selected="selected"';
		            }
		            $all_options = $all_options . '>ePayco</option>';
		        } else if ( !in_array('payu', $own_payment_gateways) ) {
		            $all_options = $all_options . '<option value="payu"';
		            if ( $gateway_filter_id_imma == 'payu' ) {
		                $all_options = $all_options . ' selected="selected"';
		            }
		            $all_options = $all_options . '>LATAM PayU</option>';
		        }
				?>
				<select name="gateway_filter_id_imma" id="gateway_filter_id_imma">
					<option value=""><?php echo __("Filter by Gateways", "imma"); ?></option>
					<?php echo $all_options; ?>
				</select>
				<?php
			}
		}
	} //End filter_orders_by_payment_gateway_imma()

	public function filter_order_list_by_payment_gateway_imma ( $order_type, $which ) {
		$gateway_filter_id_imma		= '';
		$all_options 				= '';
		$shop_order_view 			= 'woocommerce_page_wc-orders';

		if ( isset($_GET['gateway_filter_id_imma']) && $_GET['gateway_filter_id_imma'] != "" ){
			$gateway_filter_id_imma = wc_clean( wp_unslash( $_GET['gateway_filter_id_imma'] ) );
		}
		if ( function_exists('wc_get_page_screen_id') ) {
			$shop_order_view = wc_get_page_screen_id( 'shop-order' );
		}

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( !empty($gateways) ) {
			$own_payment_gateways = array();
	        foreach ($gateways as $id => $gateway) {
	        	$own_payment_gateways[] = $id;
	            $all_options = $all_options . '<option value="' . esc_attr($id) . '"';
	            if ( $gateway_filter_id_imma == $id ) {
	                $all_options = $all_options . ' selected="selected"';
	            }
	            $all_options = $all_options . '>' . esc_html( $gateway->get_title() ) . '</option>';
	        }
	        if ( !in_array('epayco', $own_payment_gateways) ) {
	            $all_options = $all_options . '<option value="epayco"';
	            if ( $gateway_filter_id_imma == 'epayco' ) {
	                $all_options = $all_options . ' selected="selected"';
	            }
	            $all_options = $all_options . '>ePayco</option>';
	        } else if ( !in_array('payu', $own_payment_gateways) ) {
	            $all_options = $all_options . '<option value="payu"';
	            if ( $gateway_filter_id_imma == 'payu' ) {
	                $all_options = $all_options . ' selected="selected"';
	            }
	            $all_options = $all_options . '>'.__("LATAM PayU", "imma").'</option>';
	        }
			?>
			<select name="gateway_filter_id_imma" id="gateway_filter_id_imma">
				<option value=""><?php echo __("Filter by Gateways", "imma"); ?></option>
				<?php echo $all_options; ?>
			</select>
			<?php
		}
	} //End filter_order_list_by_payment_gateway_imma()

	public function orders_by_payment_gateway_query_imma ( $wp ) {
		global $pagenow, $typenow;

    	$gateway_filter_id_imma		= '';
		$shop_order_view 			= 'woocommerce_page_wc-orders';
    	$qv 						= &$wp->query_vars;

		if ( isset($_GET['gateway_filter_id_imma']) && $_GET['gateway_filter_id_imma'] != "" ){
			$gateway_filter_id_imma = wc_clean( wp_unslash( $_GET['gateway_filter_id_imma'] ) );
		}
		if ( function_exists('wc_get_page_screen_id') ) {
			$shop_order_view = wc_get_page_screen_id( 'shop-order' );
		}
		if ( 'shop_order' === $typenow || $shop_order_view === $typenow ) {
    		if ( $pagenow == 'edit.php' && isset( $qv['post_type'] ) && $qv['post_type'] == 'shop_order' && $gateway_filter_id_imma != "" ) {
		        $qv['meta_key'] = '_payment_method';
		        $qv['meta_value'] = $gateway_filter_id_imma;
		        $qv['meta_compare'] = '=';
		    }
		}
	} //End orders_by_payment_gateway_query_imma()

	public function order_list_by_payment_gateway_query_imma ( $order_query_args ) {
    	$gateway_filter_id_imma		= '';

		if ( isset($_GET['gateway_filter_id_imma']) && $_GET['gateway_filter_id_imma'] != "" ){
			$gateway_filter_id_imma = wc_clean( wp_unslash( $_GET['gateway_filter_id_imma'] ) );
		}

		if ( $gateway_filter_id_imma != "" ) {
			$order_query_args['field_query'] = array(
	            array(
	                'field' => 'payment_method',
	                'value' => $gateway_filter_id_imma
	            )
	        );
		}
		return $order_query_args;
	}

}

return new FunctionsGatewayIMMA();