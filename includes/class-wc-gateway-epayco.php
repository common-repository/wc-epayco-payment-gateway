<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_ePayco class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_ePayco extends WC_Gateways_IMMA {

	public static $log_enabled = false;
	public $enabled;
	public $title;
	public $description;
	public $icon;
	public $maintenance_task;
	public $customer_id;
	public $test_p_key;
	public $p_key;
	public $test_secret_key;
	public $secret_key;
	public $test_public_key;
	public $public_key;
	public $sandbox;
	public $debug;
	public $token;
	public $reduce_stock;	
	public $redirect_logo;
	public $paymentaction;
	public $status_completed;
	public $lang;
	
	public static $log = false;

	public function __construct() {
		$this->id             			= 'epayco';
		$this->method_title   			= 'ePayco';
		$this->method_description 		= __( 'The WC ePayco payment gateway works by sending the customer\'s data to ePayco to later process the purchase from that platform.', 'imma' );
		$this->has_fields         		= false;
		$this->supports           		= array( 'products');
										//tokenization, default_credit_card_form, refunds

		$this->init_form_fields();
		$this->init_settings();

		// Get setting values.
		$this->enabled              	= $this->get_option( 'enabled' );
		$this->title               		= $this->get_option( 'title' );
		$this->description          	= $this->get_option( 'description' );
		$this->icon 					= $this->get_option( 'logo' );
		$this->maintenance_task         = $this->get_option( 'maintenance_task' );
		$this->customer_id        		= $this->get_option( 'customer_id' );
		$this->test_p_key          		= $this->get_option( 'test_p_key' );
		$this->p_key          			= $this->get_option( 'p_key' );
		$this->test_secret_key          = $this->get_option( 'test_secret_key' );
		$this->secret_key          		= $this->get_option( 'secret_key' );
		$this->test_public_key          = $this->get_option( 'test_public_key' );
		$this->public_key          		= $this->get_option( 'public_key' );
		$this->sandbox         			= $this->get_option( 'sandbox', 'no' );
		$this->debug          			= $this->get_option( 'debug', 'no' );
		$this->token          			= $this->get_option( 'token' );
		$this->reduce_stock				= $this->get_option( 'reduce_stock', 'yes' );
		self::$log_enabled    			= $this->debug;
		$this->redirect_logo 			= WCGW_EPAYCO_ASSETS_PATH.'images/redirect-v1.png';
		$this->paymentaction 			= $this->get_option( 'paymentaction', 'checkout' );
		$this->status_completed 		= $this->get_option( 'status_completed', 'wc-completed' );
		$this->lang 					= $this->get_option( 'lang', 'ES' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'wc_gw_epayco_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'wc_gw_epayco_check_response' ), 10, 1 );
		add_filter( 'woocommerce_thankyou_order_id', array( $this, 'wc_gw_epayco_pre_thankyou_page' ), 10, 1 );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'wc_gw_epayco_thankyou_page' ), 10, 1 );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'wc_gw_epayco_email_paybutton' ), 10, 3 );
		add_filter( 'woocommerce_thankyou_order_key', array( $this, 'wc_gw_epayco_remove_ref' ), 10, 1 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'wc_gw_epayco_complete_order_status' ), 10, 3 );
		add_filter( 'do_epayco_check_response', array( $this, 'wc_gw_do_epayco_check_response' ), 10, 2 );
		add_filter( 'woocommerce_can_restore_order_stock', array( $this, 'wc_gw_epayco_can_restore_order_stock' ), 10, 2 );
	}

	public function needs_setup() {
		return true;
	} //End needs_setup()

	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ( $this->public_key == "" || $this->customer_id == "" || $this->secret_key == "" ) && $this->sandbox == 'no' ) {
				return false;
			}
			return true;
		}

		return parent::is_available();
	} //End is_available()

	public function get_paymentaction() {
		return $this->paymentaction;
	} //End get_paymentaction()

	public function get_status_completed( $format = false ) {
		if ( $format === true ) {
			return str_replace( "wc-", "", $this->status_completed );
		}
		return $this->status_completed;
	} //End get_status_completed()

	public function get_customer_id() {
		return intval( $this->customer_id );
	} //End get_customer_id()

	public function get_p_key() {
		$p_key = "";
		if ( $this->sandbox == 'no' ) {
			$p_key = $this->p_key;
		} else {
			$p_key = $this->test_p_key;
		}
		return $p_key; 
	} //End get_p_key()

	public function get_public_key() {
		$public_key = "";
		if ( $this->sandbox == 'no' ) {
			$public_key = $this->public_key;
		} else {
			$public_key = $this->test_public_key;
		}
		return $public_key; 
	} //End get_public_key()

	public function get_secret_key() {
		$secret_key = "";
		if ( $this->sandbox == 'no' ) {
			$secret_key = $this->secret_key;
		} else {
			$secret_key = $this->test_secret_key;
		}
		return $secret_key; 
	} //End get_secret_key()

	public function get_lang() {
		return $this->lang;
	} //End get_lang()

	public function get_url( $key = 'wcapi', $value = null ) {
		$_return = "";
		switch ( $key ) {
			case 'checkout':
				$_return = 'https://'.'checkout.epayco.co'.'/checkout.js';
			break;
			case 'validation':
				$_return = 'https://'.'secure.epayco.co/validation/v1/reference/'.$value;
			break;			
			default:
				$_return = trailingslashit( get_home_url() . '/wc-api/WC_Gateway_ePayco' );
				
			break;
		}

		return $_return;
	} //End get_url()

	public function get_token() {
		return $this->token;
	} //End get_token()

	public function set_debug( $value ) {
		if ( $this->debug == 'yes' )
			WC_Gateway_ePayco::log( $value );
	} //End set_debug()

	public function get_reduce_stock_completed() {
		return wc_string_to_bool( $this->reduce_stock );
	} //End get_reduce_stock_completed()

	public function get_maintenance_task() {
		return $this->maintenance_task;
	} //End get_maintenance_task()

	public function admin_options() {
		$token = $this->get_option( 'token', '' );
		if ( $token == "" ) $token = __("Inactive", "imma");
		else $token = __("Active", "imma");
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'imma' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		$add_method_description = '<div style="border-style: dashed !important; background-color: #F1FAFF !important; padding: 15px !important; border: 1px solid #009EF7;">
									<ul style="color: #181C32 !important; font-weight: 600 !important;">
										<li>- ' . __("The ePayco gateway only works with the following currencies: Colombian Pesos (COP) or American Dollars (USD).", "imma") . '</li>
										<li>- ' . __("It is recommended to include the IP of the ePayco gateway 54.145.154.191 in the whitelist of the server.", "imma") . '</li>
										<li>- ' . __("The minimum amount of payment is 5.000 COP and the maximum is 3.000,000 COP. If the account is not validated by ePayco, it has a maximum of 200.000 COP (only reference values).", "imma") . '</li>
										<li>- ' . sprintf( __("Token: %s", "imma"), $token ) . '</li>
									</ul>
								</div>';
		echo wp_kses_post( wpautop( $this->get_method_description() . $add_method_description ) );
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
	} //End admin_options()

	public function init_form_fields() {
		$this->form_fields = require( dirname( __FILE__ ) . '/admin/epayco-settings.php' );
	} //End init_form_fields()

	public function wc_gw_epayco_admin_options() {
		$token = $this->get_option( 'token' );
		$saved = parent::process_admin_options();
		$username = sanitize_text_field( $this->get_public_key() );
		$password = sanitize_text_field( $this->get_secret_key() );
		if ( 'yes' !== $this->get_option( 'debug', 'no' ) ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->clear( 'epayco' );
		}
	    if ( $token == "" && $username != "" && $password != "" ) {
	        $response = wp_remote_post( 'https://apify.epayco.co/login', array(
	            'headers' => array(
	                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
	            ),
	        ) );
	        $data = json_decode( wp_remote_retrieve_body( $response ) );
	        if ( is_object($data) && isset($data->token) && $data->token != "" ) {
	        	$token = $data->token;
	        }
	    }
		$maintenance_task = $this->get_option( 'maintenance_task' );
	    if ( $maintenance_task == 'yes' ) {
	    	if ( !wp_next_scheduled ( 'woocommerce_maintenance_task_event_' . $this->id ) ) {
	    		wp_schedule_event( time(), 'hourly', 'woocommerce_maintenance_task_event_' . $this->id );
	    	}
	    } else {
	    	if ( wp_next_scheduled ( 'woocommerce_maintenance_task_event_' . $this->id ) ) {
	    		wp_clear_scheduled_hook( 'woocommerce_maintenance_task_event_' . $this->id );
	    	}
	    }
	    $this->update_option( 'token', $token );
		return $saved;
	} //End wc_gw_epayco_admin_options()

	public function wc_gw_epayco_get_payment_args( $order ) {
		if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( 'wc_gw_epayco_get_payment_args' );

		$description = "";
        $descripcionParts = array();
        foreach ( $order->get_items() as $value ) {
            $description = $value['name'];
            $strip = array( "~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]","}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;","â€”", "â€“", ",", "<", ".", ">", "/", "?" );
            $clean = trim( str_replace( $strip, "", strip_tags( $description ) ) );
            $clean = preg_replace( '/\s+/', "_", $clean );
            $clearData = str_replace( '_', ' ', $clean );
            $descripcionParts[] = $clearData;
        }
        $description = implode( ' - ', $descripcionParts );
        unset( $descripcionParts );
        $webhook_url = $this->get_url();
		$response_url = $order->get_checkout_order_received_url();
		$acepted_url = $this->get_checkout_order_received_url( $order, array( 'xrpayco' => 1 ) );
		$rejected_url = $this->get_checkout_order_received_url( $order, array( 'xrpayco' => 2 ) );
		$pending_url = $this->get_checkout_order_received_url( $order , array( 'xrpayco' => 3 ) );
		$base_tax = 0;
		$tax =  $order->get_total_tax();
		$total = $order->get_total();
        if ( $tax > 0 ) {
            $base_tax = $total - $tax;
            $base_tax = wc_format_decimal( $base_tax, 2 );
        } else {
            $base_tax = 0;
            $tax = 0;
        }
		$referenceCode = time();

		$hpos_is_enabled = false;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') ) {
			$orderUtil = new \Automattic\WooCommerce\Utilities\OrderUtil();
			if ( $orderUtil::custom_orders_table_usage_is_enabled() ) {
				$hpos_is_enabled = true;
			}
		}
		if ( $hpos_is_enabled == true ) {
			if ( 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) ) {
				update_post_meta( $order->get_id(), 'epayco_reference_code', $referenceCode );
			}
			global $wpdb;
			$exists = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s", $order->get_id(), 'epayco_reference_code') );
		    if ( $exists ) {
		        $sql = "UPDATE {$wpdb->prefix}wc_orders_meta SET meta_value = %s WHERE order_id = %d AND meta_key = %s";
		        $params = array( $referenceCode, $order->get_id(), 'epayco_reference_code' );
		        $sql = $wpdb->prepare( $sql, $params );
		        $result = $wpdb->query( $sql );
		    } else {
		        $result = $wpdb->insert(
		            "{$wpdb->prefix}wc_orders_meta",
		            array(
		                'order_id' => $order->get_id(),
		                'meta_key' => 'epayco_reference_code',
		                'meta_value' => $referenceCode
		            ),
		            array('%d', '%s', '%s')
		        );
		    }
		} else {
			update_post_meta( $order->get_id(), 'epayco_reference_code', $referenceCode );
		}

        $total = wc_format_decimal( $total, 2 );
		$args = array(
			'epayco-key' 					=> $this->get_public_key(),
			'epayco-amount' 				=> $total,
			'epayco-tax' 					=> $tax,
			'epayco-tax-base' 				=> $base_tax,
			'epayco-name' 					=> wp_kses_post( wc_strtoupper( sprintf( esc_html__( '[Order #%1$s] (%2$s)', 'imma' ), $order->get_order_number(), wc_format_datetime( $order->get_date_created() ) ) ) ),
			'epayco-description' 			=> $description,
			'epayco-currency' 				=> $order->get_currency(),
			'epayco-country' 				=> $order->get_billing_country(),
			'epayco-lang' 					=> $this->get_lang(),
			'epayco-test' 					=> ( ($this->sandbox=="yes")? "true" : "false" ),
			'epayco-external' 				=> ( ($this->get_paymentaction()=="onepage")? "false" : "true" ),
			'epayco-invoice' 				=> $referenceCode,
			'epayco-extra1' 				=> "woocommerce", //payment via
			'epayco-extra2' 				=> $order->get_id(), //payment via id
			//epayco-extra3
			'epayco-button' 				=> WCGW_EPAYCO_ASSETS_PATH.'images/btnpay.png',
			'epayco-confirmation' 			=> $webhook_url,
			'epayco-response' 				=> $response_url,
			'epayco-acepted' 				=> $acepted_url,
			'epayco-rejected' 				=> $rejected_url,
			'epayco-pending' 				=> $pending_url,
			//'epayco-autoclick' 			=> "true",
			'epayco-email-billing' 			=> $order->get_billing_email(),
			'epayco-name-billing' 			=> $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
			'epayco-address-billing' 		=> $order->get_billing_address_1() . " " . $order->get_billing_city() . " " . $order->get_billing_state(), //WC()->countries->get_states( $country )[$state];
			//'epayco-type-doc-billing'
			//'epayco-number-doc-billing'
		);
		$billing_phone = intval( $order->get_billing_phone() );
		if ( $billing_phone > 111111 ) {
			$args['epayco-mobilephone-billing'] = $billing_phone;
		}
		
		return $args;
	} //End wc_gw_epayco_get_payment_args()

	public function wc_gw_epayco_check_response() {
		@ob_clean();
		header( 'HTTP/1.1 200 OK' ); //http_response_code();

		if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( "wc_gw_epayco_check_response" . serialize($_REQUEST) );

		if ( ! empty( $_REQUEST ) ) {
			if ( isset($_REQUEST['x_cust_id_cliente']) && $_REQUEST['x_cust_id_cliente'] != "" ) {
				$order = apply_filters( 'do_epayco_check_response', $_REQUEST, null );
				wp_die( __( 'Checking IPN response is valid', 'imma' ), 'Checking IPN response', array( 'response' => 200 ) );
				exit;				
			}
		}
		wp_die( __( 'Unauthorized Access', 'imma' ), 'Unauthorized Access', array( 'response' => 500 ) );
		exit;
	} // End wc_gw_epayco_check_response()

	public function wc_gw_epayco_validate_ipn( $ref_payco ) {
		$params = array(
			'timeout'     => 60,
			'user-agent'  => 'WooCommerce/' . WC()->version,
		);
        $url = $this->get_url( 'validation', $ref_payco );
		$response = wp_safe_remote_get( $url, $params );
		$body = wp_remote_retrieve_body( $response );
        $jsonData = @json_decode( $body, true );
        if ( isset($jsonData['data']) ) {
	        $validationData = $jsonData['data'];
	        if ( !empty($validationData) ) {
	        	return $validationData;
	        }
        }
		return false;
	} //End wc_gw_epayco_validate_ipn()

	public function wc_gw_epayco_pre_thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		$ref_payco = "";
		if ( is_object($order) && $this->id == $order->get_payment_method() ) {
			
			if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( 'wc_gw_epayco_pre_thankyou_page' );
			
			$x_xrpayco = 0;
			if ( isset($_GET['xrpayco']) ) {
				$x_xrpayco = intval( wc_clean( wp_unslash( $_GET['xrpayco'] ) ) );
			}
			if ( isset($_GET['ref_payco']) ) {
				$ref_payco = wc_clean( wp_unslash( $_GET['ref_payco'] ) );
			}
			if ( $ref_payco != "" && $ref_payco != "undefined" ) {
				$data_request = $this->wc_gw_epayco_validate_ipn( $ref_payco );
				if ( $data_request !== false ) {
					$order = apply_filters( 'do_epayco_check_response', $data_request, $order );
				}
			}
			if ( $order->get_status() == 'on-hold' || $x_xrpayco == 0 ) {
				$order->update_status( 'pending', __( 'The status is changed to pending payment (always) so that the buyer can use other payment methods if he wants it that way.', 'imma' ), true );
			}
		}

		return $order_id;
	} //End wc_gw_epayco_pre_thankyou_page()

	public function wc_gw_epayco_thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		$ref_payco = "";
		$out = "";
		$wm_continue = true;
		if ( is_object($order) && $this->id == $order->get_payment_method() ) {

			if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( 'wc_gw_epayco_thankyou_page' );

			$x_xrpayco = 0;
			if ( isset($_GET['xrpayco']) ) {
				$x_xrpayco = intval( wc_clean( wp_unslash( $_GET['xrpayco'] ) ) );
			}
			if ( isset($_GET['ref_payco']) ) {
				$ref_payco = wc_clean( wp_unslash( $_GET['ref_payco'] ) );
			}
			if ( !in_array( $order->get_status(), array( 'processing', 'cancelled', 'completed', 'failed', 'refunded' ) ) ) {
				if ( $x_xrpayco > 0 ) {
					if ( in_array( $x_xrpayco, array( 3, 7, 8, 1, 6 ) ) ) {
						$wm_continue = false;
					}
				}
				if ( $wm_continue == true ) {
					$another_payment_text = __('Do you want to use another payment method?', 'imma');
					$another_payment_text = '<a class="button imma-new-gateway" style="width: 100%;margin-top: 10px;text-align: center;" href="' . $order->get_checkout_payment_url() . '">' . $another_payment_text . '</a>';
					$another_payment_text = apply_filters( 'wc_gw_epayco_another_payment_text', $another_payment_text, $this->id, $order, $this );
					if ( $ref_payco != "undefined" ) {
						$out .= '<p>' . sprintf( __( 'Thank you for your order, please click the button below to pay with ePayco service. %s', 'imma' ), $another_payment_text ) . '</p>';
					} else {
						$out .= '<p>' . $another_payment_text . '</p>';
					}
					if ( !in_array( $x_xrpayco, array( 2, 4, 11 ) ) ) {
						$args_array = array();
						$epayco_args = array();
						$script = "";
						if ( $ref_payco != "undefined" ) {
							$epayco_args = $this->wc_gw_epayco_get_payment_args( $order );
							foreach ( $epayco_args as $key => $value ) {
								$args_array[] = 'data-' . $key . '="' . $value . '"';
							}
						}
						if ( $this->get_paymentaction() == "checkout" && $x_xrpayco == 0 && $ref_payco != "undefined" ) {
							$script .= 'jQuery( "body" ).block({
								message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to ePayco to make payment.', 'imma' ) ) . '",
								baseZ: 99999,
								overlayCSS: {
									background: "#fff",
									opacity: 0.6
								},
								css: {
									padding:        "20px",
								    zindex:         "9999999",
								    textAlign:      "center",
								    color:          "#555",
								    border:         "3px solid #aaa",
								    backgroundColor:"#fff",
								    cursor:         "wait",
								    lineHeight:		"24px",
								}
							});';
							$script .= 'jQuery( ".epayco-button-render" ).click();';
						} else if ( $this->get_paymentaction() == "onepage" && $x_xrpayco == 0 && $ref_payco != "undefined" ) {
							$script .= 'jQuery( ".epayco-button-render" ).click();';
						}
						if ( $script != "" ) {
							wc_enqueue_js( $script );
						}
						if ( !empty($args_array) ) {
							$out .= '<form style="display: grid;">
								<script src="'.$this->get_url("checkout").'" class="epayco-button"' . implode( '', $args_array ) . '></script>
							</form>';
						}
					}
				}
			}
			echo $out;
		}
	} //End wc_gw_epayco_thankyou_page()

	public function wc_gw_epayco_email_paybutton( $order, $sent_to_admin, $plain_text ) {
		if ( is_object($order) && $this->id == $order->get_payment_method() && $plain_text == false && $sent_to_admin == false ) {
			if ( !in_array( $order->get_status(), array('completed', 'processing') ) ) {
				$url = $order->get_checkout_payment_url();
				$pay_text = __( 'Pay now via ePayco', 'imma' );
				$paymentlink = '<a class="button gatewaypay" href="'. $order->get_checkout_payment_url() .'">' . $pay_text . '</a>';
				$paymentlink = apply_filters( 'woocommerce_email_after_order_table_paylink', $paymentlink, $url, $pay_text, $order, $sent_to_admin, $plain_text );
				echo wp_kses_post( wpautop( wptexturize( $paymentlink ) ) . PHP_EOL );
			}
		}
	} // End wc_gw_epayco_email_paybutton()

	public function wc_gw_epayco_remove_ref( $order_key ) {
		if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( 'wc_gw_epayco_remove_ref' );
	
		$order_key = remove_query_arg( 'ref_payco', $order_key );
		
		return $order_key;
	} //End wc_gw_epayco_remove_ref()

	public function wc_gw_epayco_complete_order_status( $status, $order_id, $order ) {
		if ( $this->id == $order->get_payment_method() ) {
			
			if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( 'wc_gw_epayco_complete_order_status' );
			
			$status = 'completed';
		}
		return $status;
	} //End wc_gw_epayco_complete_order_status()

	public function wc_gw_do_epayco_check_response( $data_request, $order = null ) {
		if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( "wc_gw_do_epayco_check_response" . serialize($data_request) );

		$p_cust_id_cliente 		= $this->get_customer_id();
		$p_key 					= $this->get_p_key();
		$epayco_order_id 		= "";
		
		if ( $order == null ) {
			if ( $p_cust_id_cliente > 0 && $p_key != "" && ( isset($data_request['x_id_invoice']) || isset($data_request['x_extra2']) )  ) {
				if ( isset($data_request['x_extra2']) ) {
					$x_extra2 = wc_clean( wp_unslash( $data_request['x_extra2'] ) );
					if ( $x_extra2 != "" ) {
						$epayco_order_id = $x_extra2;
					}
				}
				/*if ( $epayco_order_id == "" ) {
					if ( isset($data_request['x_id_invoice']) ) {
						$x_id_invoice = wc_clean( wp_unslash( $data_request['x_id_invoice'] ) );
						if ( $x_id_invoice != "" ) {
							$epayco_order_id = $x_id_invoice;
						}
					}
				}*/
				if ( $epayco_order_id != "" ) {
					$order = wc_get_order( $epayco_order_id );
				} else {
					if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error order_id %s", serialize($data_request) ) );
				}				
			} else {
				if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error cust_id %s, p_key %s, order_id %s", $p_cust_id_cliente, $p_key, serialize($data_request) ) );
			}
		}
		if ( $order != null && is_object($order) ) {
			$this->wc_gw_epayco_save_thistory( $data_request, $order );
			$order_status = $order->get_status();
			if ( $order_status != "trash" ) {
				if ( isset( $data_request['x_ref_payco'], $data_request['x_transaction_id'], $data_request['x_amount'], $data_request['x_currency_code'], $data_request['x_signature'], $data_request['x_cod_response'] ) ) {
					$x_ref_payco = wc_clean( wp_unslash( $data_request['x_ref_payco'] ) );
					$x_transaction_id = wc_clean( wp_unslash( $data_request['x_transaction_id'] ) );
					$x_amount = wc_clean( wp_unslash( $data_request['x_amount'] ) );
					$x_currency_code = wc_clean( wp_unslash( $data_request['x_currency_code'] ) );
					$x_signature = wc_clean( wp_unslash( $data_request['x_signature'] ) );
					$x_cod_response = intval( wc_clean( wp_unslash( $data_request['x_cod_response'] ) ) );
					$x_cust_id_cliente = intval( wc_clean( wp_unslash( $data_request['x_cust_id_cliente'] ) ) );
					$x_franchise = "";
					if ( isset($data_request['x_franchise']) && $data_request['x_franchise'] != "" ) {
						$x_franchise = wc_clean( wp_unslash( $data_request['x_franchise'] ) );
					}
					$x_response_reason_text = "";
					if ( isset($data_request['x_response_reason_text']) ) {
						$x_response_reason_text = wc_clean( wp_unslash( $data_request['x_response_reason_text'] ) );
					}
					if ( $p_cust_id_cliente == $x_cust_id_cliente ) {
						if ( $x_ref_payco != "" && $x_transaction_id != "" && $x_amount != "" && $x_currency_code != "" && $x_signature != "" && $x_cod_response > 0 ) {
							$signature = hash( 'sha256', $p_cust_id_cliente . '^' . $p_key . '^' . $x_ref_payco . '^' . $x_transaction_id . '^' . $x_amount . '^' . $x_currency_code );
							if ( $x_signature == $signature ) {
								switch ( $x_cod_response ) {
									case 1:
										$status = $this->get_status_completed( true );
										if ( $status == 'completed' ) {
											$order->payment_complete( $x_ref_payco );
										} else {
											$order->set_transaction_id( $x_ref_payco );
											$order->update_status( $status, __('Transaction approved successfully', 'imma') . '–', true );
										}
									break;
									case 2:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									case 3:
										$status = 'pending';
										if ( in_array($x_franchise, array("SP", "RS", "PR", "GA", "EF", "BA", "PSE")) ) {}
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( $status, $x_response_reason_text . '–', true );
									break;
									case 4:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									case 6:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'refunded', $x_response_reason_text . '–', true );
									break;
									case 7:
										$status = 'pending';
										if ( in_array($x_franchise, array("SP", "RS", "PR", "GA", "EF", "BA", "PSE")) ) {}
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( $status, $x_response_reason_text . '–', true );
									break;
									case 8:
										$status = 'pending';
										if ( in_array($x_franchise, array("SP", "RS", "PR", "GA", "EF", "BA", "PSE")) ) {}
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( $status, $x_response_reason_text . '–', true );
									break;
									case 9:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									case 10:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									case 11:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									case 12:
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', $x_response_reason_text . '–', true );
									break;
									default:
										if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error x_cod_response %s", $x_cod_response ) );
										$order->set_transaction_id( $x_ref_payco );
										$order->update_status( 'failed', sprintf( "Error x_cod_response %s", $x_cod_response ), true );
									break;
								}
							} else {
								if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error x_signature %s, signature %s", $x_signature, $signature ) );
							}
						} else {
							if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error x_ref_payco %s, x_transaction_id %s, x_amount %s, x_currency_code %s, x_signature %s, x_cod_response %s", $x_ref_payco, $x_transaction_id, $x_amount, $x_currency_code, $x_signature,$x_cod_response ) );
						}
					} else {
						if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error clientid %s - %s ", $p_cust_id_cliente, $x_cust_id_cliente ) );
					}
				} else {
					if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error epayco data response %s", serialize($data_request) ) );								
				}
			} else {
				if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error order_status %s", $order_status ) );
			}
			do_action( 'after_wc_gw_epayco_check_response', $order, $data_request );
		} else {
			if ( $this->debug == 'yes' ) WC_Gateway_ePayco::log( sprintf( "Error order %s", serialize($order) ) );
		}

		return $order;
	} //End wc_gw_do_epayco_check_response()

	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = wc_get_logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'epayco' ) );
		}	
	} //End log()

	public function wc_gw_epayco_can_restore_order_stock( $value, $order ) {
		if ( $order->get_status() != 'cancelled' ) {
			$value = false;
		}
		return $value;
	} //End wc_gw_epayco_can_restore_order_stock()

	public function wc_gw_epayco_save_thistory( $data_request, $order ) {
		$wpdb = null;
		$epaycoh = array();
		$hpos_is_enabled = false;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') ) {
			$orderUtil = new \Automattic\WooCommerce\Utilities\OrderUtil();
			if ( $orderUtil::custom_orders_table_usage_is_enabled() ) {
				$hpos_is_enabled = true;
			}
		}
		if ( $hpos_is_enabled == true ) {
			global $wpdb;
		    $sql = "SELECT meta_key, meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d";
		    $params = array( $order->get_id() );
	        $sql .= " AND meta_key = %s";
	        $params[] = 'htransaction';
			$sql = $wpdb->prepare( $sql, $params );
			$result = $wpdb->get_results( $sql, ARRAY_A );
			if ( is_countable($result) && count($result) > 0 ) {
				$epaycoh = maybe_unserialize( $result[0]['meta_value'] );
			}
		} else {
			$epaycoh = get_post_meta( $order->get_id(), 'htransaction', true );
		}
		if ( !is_array($epaycoh) ) {
			unset( $epaycoh );
			$epaycoh = array();
		}
		$save_htransaction = true;
		if ( !empty($epaycoh) ) {
			foreach ( $epaycoh as $value ) {
				if ( isset($value['x_ref_payco'], $data_request['x_ref_payco']) && $value['x_ref_payco'] == $data_request['x_ref_payco'] ) {
					$save_htransaction = false;
					break;
				}
			}
		}
		if ( $save_htransaction == true ) {
			$epaycoh[] = $data_request;
			if ( $hpos_is_enabled == true ) {
				if ( 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) ) {
					update_post_meta( $order->get_id(), 'htransaction', $epaycoh );
				}				
				if ( is_array( $epaycoh ) || is_object( $epaycoh ) ) {
					$epaycoh = serialize( $epaycoh );
				}
				if ( is_null($wpdb) ) global $wpdb;
				$exists = $wpdb->get_var($wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s", $order->get_id(), 'htransaction') );
			    if ( $exists ) {
			        $sql = "UPDATE {$wpdb->prefix}wc_orders_meta SET meta_value = %s WHERE order_id = %d AND meta_key = %s";
			        $params = array( $epaycoh, $order->get_id(), 'htransaction' );
			        $sql = $wpdb->prepare( $sql, $params );
			        $result = $wpdb->query( $sql );
			    } else {
			        $result = $wpdb->insert(
			            "{$wpdb->prefix}wc_orders_meta",
			            array(
			                'order_id' => $order->get_id(),
			                'meta_key' => 'htransaction',
			                'meta_value' => $epaycoh
			            ),
			            array('%d', '%s', '%s')
			        );
			    }
			} else {
				update_post_meta( $order->get_id(), 'htransaction', $epaycoh );
			}
		}
	} //End wc_gw_epayco_save_thistory()
}