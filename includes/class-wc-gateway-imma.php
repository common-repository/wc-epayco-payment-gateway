<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateways_IMMA class.
 *
 * @extends WC_Payment_Gateway
 */

if ( !class_exists( 'WC_Gateways_IMMA' ) ) {
	abstract class WC_Gateways_IMMA extends WC_Payment_Gateway {
		/**
		 * Process Payment.
		 *
		 * Process the payment. Override this in your gateway. When implemented, this should.
		 * return the success and redirect in an array. e.g:
		 *
		 *        return array(
		 *            'result'   => 'success',
		 *            'redirect' => $this->get_return_url( $order )
		 *        );
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( $order->get_total() > 0 ) {
				$reduce_stock_completed = true;
				if ( isset($this->reduce_stock) ) {
					$reduce_stock_completed = $this->get_reduce_stock_completed();
				}
				if ( $reduce_stock_completed == true ) {
					$order->update_status( 'on-hold' );
				}
			} else {
				$order->payment_complete();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		/**
		 * If There are no payment fields show the description if set.
		 * Override this in your gateway if you have some.
		 */
		public function payment_fields() {
			$description = $this->get_description();

			if ( $this->sandbox == "yes" ) {
				$description .= "\n\n" . '<strong>' . __( "IMPORTANT: SANDBOX mode is active for this payment gateway.", "imma" ) . '</strong>';
			}

			if ( isset($this->redirect_logo) ) {
				$description .= '<span class="imma-redirect-text" style="align-items: center!important;align-self: stretch!important;background: #f5f5f5!important;border-radius: 6px!important;display: flex!important;flex: none!important;flex-direction: row!important;flex-grow: 0!important;gap: 16px!important;height: 100%!important;justify-content: center!important;order: 2!important;padding: 12px 20px!important; margin-top: 12px!important;">' . sprintf( __("By continuing, we will take you to %s to safely complete your purchase", "imma"), $this->get_method_title() ) . '.<img class="imma-redirect-logo" src="'.$this->redirect_logo.'"></span>';
			}

			if ( $description ) {
				echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
			}

			if ( $this->supports( 'default_credit_card_form' ) ) {
				$cc_form           = new WC_Payment_Gateway_CC();
				$cc_form->id       = $this->id;
				$cc_form->supports = $this->supports;
				$cc_form->form();
			}
		}

		public function gw_clean( $value ) {
			$value = wc_clean( wp_unslash( $value ) );
			$value = preg_replace( '/\s+/','',$value );
			return $value;
		}	

		public function get_user_agent () {
			$user_agent = '';
			$user_agent_temp = wc_get_user_agent();
			if ( $user_agent_temp != "" ) {
				$user_agent_array = explode( ' ', $user_agent_temp );
				if ( isset($user_agent_array[0]) && $user_agent_array[0] != "" ) {
					$user_agent = $user_agent_array[0];
				}
			}
			if ( $user_agent == "" ) {
				$user_agent = 'Mozilla/5.0';
			}
			return $user_agent;
		}

		public function get_user_date_card ( $date, $format = 'string' ) {
			$_return = '';
			$date_temp = explode( '/', $date );
			if ( isset($date_temp[0]) && isset($date_temp[1]) ) {
				if ( strlen($date_temp[1]) < 3 ) {
					$date_temp[1] = '20' . $date_temp[1];	
				}
				if ( $format == 'string' ) {
					$_return = $date_temp[1] . '/' . $date_temp[0];
				} else {
					$_return = $date_temp;
				}
			}
			return $_return;
		}

		public function payment_scripts() {
		}

		public function get_checkout_order_received_url( $order, $args = array() ) {
			$args['key'] = $order->get_order_key();
			$order_received_url = wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() );
			$order_received_url = add_query_arg( $args, $order_received_url );

			return apply_filters( 'woocommerce_get_checkout_order_received_url', $order_received_url, $order );
		}

		public function get_device_session_id () {
			//session_start();    
			return md5( session_id() . microtime() );
		}

		public function get_client_ip () {
			/*$ip = '';
			if ( isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP'] != "" ) {
	    		$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != "" ) {
	    		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif ( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != "" ) {
	    		$ip = $_SERVER['REMOTE_ADDR'];
			}
			return $ip;*/
			return WC_Geolocation::get_ip_address();
		}
	}
}