<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( class_exists( 'WMimmaMenuePayco', false ) ) {
	return new WMimmaMenuePayco();
}

/**
 * WMimmaMenuePayco Class.
 */
if ( !class_exists( 'WMimmaMenuePayco' ) ) {
	class WMimmaMenuePayco {

		public function __construct () {
			add_action( 'add_meta_boxes', array( $this, 'wc_add_order_history_box' ), 10 );
			add_action( 'wp_ajax_imma_close_admin_notices', array( $this, 'imma_do_close_admin_notices' ), 10 );
			add_action( 'wp_ajax_imma_replicate_epayco_transaction', array( $this, 'imma_do_replicate_epayco_transaction' ), 10 );
			add_action( 'add_meta_boxes', array( $this, 'wc_add_order_epayco_box' ), 10 );
		} //End __construct()

		public function __clone () {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), WCGW_EPAYCO_VERSION );
		} //End __clone()

		public function __wakeup () {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), WCGW_EPAYCO_VERSION );
		} //End __wakeup()

		public function wc_add_order_history_box () {
			$screen = 'shop_order';
			$hpos_is_enabled = false;
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') ) {
				$orderUtil = new \Automattic\WooCommerce\Utilities\OrderUtil();
				if ( $orderUtil::custom_orders_table_usage_is_enabled() ) {
					$hpos_is_enabled = true;
				}
			}
			if ( $hpos_is_enabled == true ) {
				if ( function_exists('wc_get_page_screen_id') ) {
					$screen = wc_get_page_screen_id( 'shop-order' );
				} else {
					$screen = 'woocommerce_page_wc-orders';
				}				
			}
	    	add_meta_box( 'transactions-history-epayco-id', __( 'ePayco transactions history', 'imma' ),  array( $this, 'wc_add_order_thistory_box_function' ), $screen, 'normal', 'low' );
		} //End wc_add_order_history_box()

		public function wc_add_order_thistory_box_function ( $post ) {
			$thistory = array();
			$order_id = 0;
			$_order = null;
			$out = '<div style="background-color: #3699FF;border-color: #3699FF;color: #FFFFFF;padding: 1rem;">'.__("This order does not have any recorded history", "imma").'.</div>';
			if ( is_a($post, 'WC_Order') ) {
				$_order = $post;
			} else {
				$_order = wc_get_order( $post->ID );
			}
			if ( is_object($_order) ) {
				$found_data = false;
				$note_content_style = "";
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
				    $params = array( $_order->get_id() );
			        $sql .= " AND meta_key = %s";
			        $params[] = 'htransaction';
					$sql = $wpdb->prepare( $sql, $params );
					$result = $wpdb->get_results( $sql, ARRAY_A );
					if ( is_countable($result) && count($result) > 0 ) {
						$thistory = maybe_unserialize( $result[0]['meta_value'] );
					}
				} else {
					$thistory = get_post_meta( $_order->get_id(), 'htransaction', true );
				}
				if ( is_array($thistory) && !empty($thistory) ) {
					$out = '<ul class="order_notes">';
					foreach ( $thistory as $value ) {
						if ( isset($value['x_ref_payco']) ) {
							$data_value = "";
							foreach ( $value as $key => $datavalue ) {
								if ( 'x_franchise' == $key ) {
									switch ( $datavalue ) {
										case 'AM': $datavalue = 'AMEX'; break;
										case 'BA': $datavalue = 'Baloto'; break;
										case 'CR': $datavalue = 'Credencial'; break;
										case 'DC': $datavalue = 'Diners Club'; break;
										case 'EF': $datavalue = 'Efecty'; break;
										case 'GA': $datavalue = 'Gana'; break;
										case 'PR': $datavalue = 'Punto Red'; break;
										case 'RS': $datavalue = 'Red Servi'; break;
										case 'MC': $datavalue = 'MASTERCARD'; break;
										case 'PSE': $datavalue = 'PSE'; break;
										case 'SP': $datavalue = 'SafetyPay'; break;
										case 'VS': $datavalue = 'VISA'; break;
										default: break;
									}
								}
								$data_value .= '<strong>' . $key . '</strong>: ' . $datavalue . '<br>';
							}
							$note_content_style = "background: #1C0E49; color: #FFFFFF";
							$out .= '<li class="note" style="margin-bottom: 10px;"><div class="note_content" style="'.$note_content_style.'"><p style="column-count: 4;">'.$data_value.'</p></div></li>';
							$found_data = true;
						}
					}
					$out .= '</ul>';
				}
				if ( $found_data == false ) $out = '<div style="background-color: #3699FF;border-color: #3699FF;color: #FFFFFF;padding: 1rem;">'.__("This order does not have an history associated ePayco payment method", "imma").'.</div>';
			}
			echo $out;
		} //End wc_add_order_thistory_box_function()

		public function imma_do_close_admin_notices () {
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				update_user_meta( $user_id, 'imma_wm_ads', strtotime( date('Y-m-d', strtotime('+3 day')) ) );
			}
			wp_die();
		} //End imma_do_close_admin_notices()

		public function imma_do_replicate_epayco_transaction () {
			if ( isset($_POST['action'], $_POST['dcod'], $_POST['dpostid']) && $_POST['action'] == "imma_replicate_epayco_transaction" && $_POST['dcod'] != "" && $_POST['dpostid'] != "" ) {
				$x_cod_response = intval( wc_clean( wp_unslash( $_POST['dcod'] ) ) );
				$post_id = intval( wc_clean( wp_unslash( $_POST['dpostid'] ) ) );
				$dmethod = "";
				if ( isset($_POST['dmethod']) && $_POST['dmethod'] != "" ) {
					$dmethod = wc_clean( wp_unslash( $_POST['dcod'] ) );
				}
				$wc_order = wc_get_order( $post_id );
				if ( is_object($wc_order) && $wc_order->get_payment_method() == 'epayco' ) {
					switch ( $x_cod_response ) {
						case 1:
							$wc_gateway_epayco = new WC_Gateway_ePayco();
							$status = $wc_gateway_epayco->get_status_completed( true );
							$wc_order->update_status( $status, __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 2:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 3:
							$status = 'pending';
							if ( $dmethod == "OFFLINE" ) {}
							$wc_order->update_status( $status, __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 4:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 6:
							$wc_order->update_status( 'refunded', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 7:
							$status = 'pending';
							if ( $dmethod == "OFFLINE" ) {}
							$wc_order->update_status( $status, __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 8:
							$status = 'pending';
							if ( $dmethod == "OFFLINE" ) {}
							$wc_order->update_status( $status, __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 9:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 10:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 11:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						case 12:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
						default:
							$wc_order->update_status( 'failed', __('Manual process by IMMAGIT ePayco.', 'imma'), true );
						break;
					}
				}
			}

			wp_die();
		} //End imma_do_replicate_epayco_transaction()

		public function wc_add_order_epayco_box () {
			$screen = 'shop_order';
			$hpos_is_enabled = false;
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') ) {
				$orderUtil = new \Automattic\WooCommerce\Utilities\OrderUtil();
				if ( $orderUtil::custom_orders_table_usage_is_enabled() ) {
					$hpos_is_enabled = true;
				}
			}
			if ( $hpos_is_enabled == true ) {
				if ( function_exists('wc_get_page_screen_id') ) {
					$screen = wc_get_page_screen_id( 'shop-order' );
				} else {
					$screen = 'woocommerce_page_wc-orders';
				}
			}			
	    	add_meta_box( 'data-epayco-box', __( 'ePayco Data', 'imma' ),  array( $this, 'wc_add_data_epayco_box_function' ), $screen, 'side', 'low' );
		} //End wc_add_order_epayco_box()

		public function wc_add_data_epayco_box_function ( $post ) {
			$token = "";
			$dcod = "";
			$dmethod = "CREDIT_CARD";
			$order_id = 0;
			$wc_order = null;
			if ( is_a($post, 'WC_Order') ) {
				$wc_order = $post;
			} else {
				$wc_order = wc_get_order( $post->ID );
			}
			if ( is_object($wc_order) && $wc_order->get_payment_method() == 'epayco' ) {
				$transaction = $wc_order->get_transaction_id();
				if ( $transaction != "" ) {
					$isOkData = null;
					$wc_gateway_epayco = new WC_Gateway_ePayco();
					if ( $wc_gateway_epayco->get_token() == "" ) {
						echo '<div style="background-color: #3699FF;border-color: #3699FF;color: #FFFFFF;padding: 1rem;">'.sprintf( __("You do not have an active token. Go to %s again and save the changes. If the problem persists, contact technical support.", "imma"), '<a style="color: #591d23;" href="'.admin_url( "admin.php?page=wc-settings&tab=checkout&section=epayco" ).'">'.__("settings", "imma").'</a>' ).'</div>';
					} else {
				        $response = wp_remote_post( 'https://apify.epayco.co/transaction/detail', array(
				            'timeout' => 60,
				            'headers' => array(
				                'Authorization' => 'Bearer ' . $wc_gateway_epayco->get_token(),
				            ),
				            'body' => array(
				                'filter' => array('referencePayco' => $transaction),
				            ),
				        ) );
				        $data = json_decode( wp_remote_retrieve_body( $response ) );
				        if ( is_object($data) && isset($data->titleResponse) && strtolower($data->titleResponse) == "unauthorized." ) {
				        	unset( $data );
							$username = sanitize_text_field( $wc_gateway_epayco->get_public_key() );
							$password = sanitize_text_field( $wc_gateway_epayco->get_secret_key() );
					        $response = wp_remote_post( 'https://apify.epayco.co/login', array(
					            'timeout' => 60,
					            'headers' => array(
					                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
					            ),
					        ) );
			        		$data = json_decode( wp_remote_retrieve_body( $response ) );
					        if ( is_object($data) && isset($data->token) && $data->token != "" ) {
					        	$token = $data->token;
					        	$wc_gateway_epayco->update_option( 'token', $token );
					        	unset( $data );
						        $response = wp_remote_post( 'https://apify.epayco.co/transaction/detail', array(
						            'timeout' => 60,
						            'headers' => array(
						                'Authorization' => 'Bearer ' . $token,
						            ),
						            'body' => array(
						                'filter' => array('referencePayco' => $transaction),
						            ),
						        ) );
						        $data = json_decode( wp_remote_retrieve_body( $response ) );
						        if ( is_object($data) && isset($data->success) && $data->success == true ) {
						        	$isOkData = $data;
						        }
					        }			       
				        } else if ( is_object($data) && isset($data->success) && $data->success == true ) {
				        	$isOkData = $data;
				        }
				        if ( !is_null($isOkData) && is_object($isOkData) && isset($isOkData->data) && is_object($isOkData->data) ) {
				        	$txt = '<strong style="font-size: 14px;">'.__("Status of the transaction", "imma").'</strong><br>';
				        	if ( isset($isOkData->data->response) && $isOkData->data->response != "" )
				        		$txt = $txt . $isOkData->data->response;
				        	else
				        		$txt = $txt . 'N/A'.'<br><br>';
				        	if ( isset($isOkData->data->status) && $isOkData->data->status != "" )
								$txt = sprintf( __('%s (%s)', 'imma'), $txt, $isOkData->data->status );
				        	$txt = $txt . '<br><br>';
				        	$txt = $txt . '<strong style="font-size: 14px;">'.__("Customer data", "imma").'</strong><br>';
				        	if ( isset($isOkData->data->firstName) ) $txt = $txt . sprintf(__("FirstName: %s", "imma"), $isOkData->data->firstName.'<br>');
				        	if ( isset($isOkData->data->lastName) ) $txt = $txt . sprintf(__("LastName: %s", "imma"), $isOkData->data->lastName.'<br>');
				        	if ( isset($isOkData->data->log, $isOkData->data->log->x_customer_doctype) ) $txt = $txt . sprintf(__("Document type: %s", "imma"), $isOkData->data->log->x_customer_doctype.'<br>');
				        	if ( isset($isOkData->data->log, $isOkData->data->log->x_cod_response) ) $dcod = $isOkData->data->log->x_cod_response;
				        	if ( isset($isOkData->data->log, $isOkData->data->log->x_franchise) ) $dmethod = $isOkData->data->log->x_franchise;
							if ( $dmethod != "" && in_array($dmethod, array("SP", "RS", "PR", "GA", "EF", "BA", "PSE")) ) {
								$dmethod = "OFFLINE";
							}
				        	if ( isset($isOkData->data->document) ) $txt = $txt . sprintf(__("Document: %s", "imma"), $isOkData->data->document.'<br>');
				        	if ( isset($isOkData->data->address) ) $txt = $txt . sprintf(__("Address: %s", "imma"), $isOkData->data->address.'<br>');
				        	if ( isset($isOkData->data->city) ) $txt = $txt . sprintf(__("City: %s", "imma"), $isOkData->data->city.'<br>');
				        	if ( isset($isOkData->data->department) ) $txt = $txt . sprintf(__("Department: %s", "imma"), $isOkData->data->department.'<br>');
				        	if ( isset($isOkData->data->country) ) $txt = $txt . sprintf(__("Country: %s", "imma"), $isOkData->data->country.'<br>');
				        	if ( isset($isOkData->data->telephone) ) $txt = $txt . sprintf(__("Telephone: %s", "imma"), $isOkData->data->telephone.'<br>');
				        	if ( isset($isOkData->data->mobilePhone) ) $txt = $txt . sprintf(__("MobilePhone: %s", "imma"), $isOkData->data->mobilePhone.'<br>');
				        	if ( isset($isOkData->data->company) ) $txt = $txt . sprintf(__("Company: %s", "imma"), $isOkData->data->company.'<br>');
				        	if ( isset($isOkData->data->email) ) $txt = $txt . sprintf(__("Email: %s", "imma"), $isOkData->data->email.'<br>');
				        	if ( isset($isOkData->data->ip) ) $txt = $txt . sprintf(__("IP: %s", "imma"), $isOkData->data->ip.'<br>');
				        	if ( isset($isOkData->data->amount) ) $txt = $txt . sprintf(__("Amount (COP): %s", "imma"), $isOkData->data->amount.'<br>');
				        	if ( isset($isOkData->data->dollars) ) $txt = $txt . sprintf(__("Amount (USD): %s", "imma"), $isOkData->data->dollars);
				        	$disabled = 'disabled="disabled"';
				        	if ( $dcod != "" ) $disabled = "";
							$txt = $txt . '<button id="replicate-epayco-transaction-btn" class="button" style="width: 100%;margin-top: 12px;" '.$dcod.'>'.__("Replicate transaction status", "imma").'</button>';
				      		echo '<div style="background: #1C0E49; color: #FFFFFF;padding: 1rem; word-wrap: break-word;">'.$txt.'</div>';
				        	?>
				        	<script>
							    <?php if ( $dcod != "" ) { ?>
								    jQuery('#replicate-epayco-transaction-btn').click(function(e) {
										e.preventDefault();
										var widgetContainer = jQuery('#data-epayco-box');
										var widgetTextLoading = "<?php echo __('Replicating information...', 'imma'); ?>";
										var widgetBtnCod = parseInt( "<?php echo $dcod; ?>", 10 );
										var widgetDmethod = "<?php echo $dmethod; ?>";
										var wcOrder = "<?php echo $wc_order->get_id(); ?>";
										var widgetTextError = "<?php echo __('Sorry, there was an error trying to replicate the information. Please try again in a few minutes or contact technical support for assistance.', 'imma'); ?>";
										widgetContainer.block({
											message: null,
											overlayCSS: {
												background: '#fff',
												opacity: 0.6
											}
										});
								        /*widgetContainer.waitMe({
								          effect: 'pulse',
								          text: widgetTextLoading,
								          color: '#fff',
								          bg: 'rgba(0, 0, 0, 0.7)',
								          textPos: 'vertical',
								        });*/
								        var data = {
								            "action" : "imma_replicate_epayco_transaction",
								            "dcod" : widgetBtnCod,
								            "dpostid" : wcOrder,
								            "dmethod" : widgetDmethod
								        };
								        jQuery.post(ajaxurl, data, function(response) {
											/*widgetContainer.fadeOut( '300', function () {
												widgetContainer.remove();
											});*/
								        	location.reload();
								        }).error(function(data){
								        	alert(widgetTextError);
								        });
								    });
								<?php } ?>
				        	</script>
				        	<?php
				        } else {
							echo '<div style="background-color: #F64E60;border-color: #F64E60;color: #ffffff;padding: 1rem; word-wrap: break-word;">'.sprintf( __("We are sorry, the data for this transaction could not be loaded due to the following reason:%sIf the problem persists, contact technical support.", "imma"), '<br><br>' . serialize($isOkData) . '<br><br>' ).'</div>';
				        }
					}
				} else {
					echo '<div style="background-color: #3699FF;border-color: #3699FF;color: #FFFFFF;padding: 1rem;">'.__("This order does not have a valid reference to be consulted in ePayco", "imma").'.</div>';
				}
			} else {
				echo '<div style="background-color: #3699FF;border-color: #3699FF;color: #FFFFFF;padding: 1rem;">'.__("This order does not have an associated ePayco payment method", "imma").'.</div>';
			}
		} //End wc_add_data_epayco_box_function()
	}
}

return new WMimmaMenuePayco();