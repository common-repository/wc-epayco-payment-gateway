<?php
/**
 * Plugin Name: IMMAGIT ePayco Payment Gateway for WooCommerce
 * Description: Receive payments by more than 22 means (credit card, digital wallet, bank transfer, cash and more payments) through the ePayco Colombia service in your WooCommerce + WordPress store.
 * Plugin URI: https://immagit.com/producto/epayco-gateway-para-woocommerce/
 * Version: 1.1.8
 * Author: IMMAGIT
 * Author URI: https://immagit.com/
 * Requires at least: 5.6
 * Tested up to: 6.4.2
 * WC requires at least: 3.6.0
 * WC tested up to: 8.4.0
 * Requires PHP: 7.0
 * 
 * Text Domain: imma
 * Domain Path: /i18n/languages/
 * Function slug: wc_gw_epayco_ 
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wc_gw_epayco_missing_wc_notice' ) ) {
	function wc_gw_epayco_missing_wc_notice() {
		echo '<div class="error"><p><strong>' . sprintf( __( 'WC ePayco Gateway requires WooCommerce to be installed and active. You can download %s here.', 'imma' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
	}
} //End wc_gw_epayco_missing_wc_notice()

if ( ! function_exists( 'wc_gw_epayco_missing_curl_notice' ) ) {
	function wc_gw_epayco_missing_curl_notice() {
		echo '<div class="error"><p><strong>' . __( 'WC ePayco Gateway requires cURL extension to be installed and active.', 'imma' ) . '</strong></p></div>';
	}
} //End wc_gw_epayco_missing_curl_notice()

if ( ! function_exists( 'wc_gw_epayco_woomelly_ads' ) ) {
	function wc_gw_epayco_woomelly_ads() {
		$imma_wm_ads_key = get_site_transient( 'imma_wm_ads_key' );
		if ( $imma_wm_ads_key === false )  {
			set_site_transient( 'imma_wm_ads_key', 'wc_gw_epayco', 3600 );
			$imma_wm_ads_key = 'wc_gw_epayco';
		}
		if ( $imma_wm_ads_key === 'wc_gw_epayco' ) {
			$now = time();
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$timeads = intval( get_user_meta( $user_id, 'imma_wm_ads', true ) );
				if ( $now >= $timeads ) {
					$out = '<div id="immawmads" class="notice notice-success" style="padding-right: 38px; position: relative;"><p><a href="https://woomelly.com/?utm_source=epaycoplugin&utm_medium=wpadmin&utm_content=ads1&utm_campaign=woomelly" target="_blank"><img style="width: 100%;" src="'.WCGW_EPAYCO_ASSETS_PATH.'images/woomellyads1.gif"></a></p><a id="immaclosewmads" class="notice-dismiss" style="text-decoration: none;"><span class="screen-reader-text">Dismiss this notice.</span></a></div>';
					$out .= '<script>jQuery("#immaclosewmads").click(function(e){
			   				e.preventDefault();
			   				jQuery("#immawmads").remove();
					        var data = {
					            "action" : "imma_close_admin_notices",
					        };
					        jQuery.post(ajaxurl, data, function(response) {
					        }).error(function(data){
					        });
			   			});</script>';
			   		echo $out;			
				}
			}
		}
	}
} //End wc_gw_epayco_woomelly_ads()

if ( ! function_exists( 'wc_gw_epayco_before_woocommerce_init' ) ) {
	add_action( 'before_woocommerce_init', 'wc_gw_epayco_before_woocommerce_init', 10 );
	function wc_gw_epayco_before_woocommerce_init() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
		}
	}
} //End FeaturesUtil

if ( ! function_exists( 'wc_gw_epayco_init' ) ) {
	add_action( 'plugins_loaded', 'wc_gw_epayco_init', 10 );
	function wc_gw_epayco_init() {
	    $domain = 'imma';
	    $locale = apply_filters( 'wc_gw_epayco_plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/i18n/languages/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages/' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'wc_gw_epayco_missing_wc_notice' );
			return;
		}

		if ( ! extension_loaded( 'curl' ) ) {
			add_action( 'admin_notices', 'wc_gw_epayco_missing_curl_notice' );
			return;
		}

		if ( ! class_exists( 'WCGW_ePayco' ) ) :
			if ( !defined( 'WCGW_EPAYCO_VERSION' ) )
				define( 'WCGW_EPAYCO_VERSION', '1.1.8' );
			if ( !defined( 'WCGW_EPAYCO_MAIN_FILE' ) )
				define( 'WCGW_EPAYCO_MAIN_FILE', __FILE__ );
			if ( !defined( 'WCGW_EPAYCO_PLUGIN_URL' ) )
				define( 'WCGW_EPAYCO_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
			if ( !defined( 'WCGW_EPAYCO_PLUGIN_PATH' ) )
				define( 'WCGW_EPAYCO_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
			if ( !defined( 'WCGW_EPAYCO_ASSETS_PATH' ) )
				define( 'WCGW_EPAYCO_ASSETS_PATH', esc_url( trailingslashit( plugins_url( '/assets', __FILE__ ) ) ) );			
			class WCGW_ePayco {
				private static $instance;
				
				public static function get_instance() {
					if ( null === self::$instance ) {
						self::$instance = new self();
					}
					return self::$instance;
				} //End get_instance()
				
				public function __clone() {}
				
				public function __wakeup() {}
				
				public function __construct() {
					add_action( 'admin_init', array( $this, 'install' ) );
					$this->init();
				}
				
				public function init() {
					if ( is_admin() ) {}
					require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-imma.php';
					require_once dirname( __FILE__ ) . '/includes/class-wc-gateway-epayco.php';
					require_once dirname( __FILE__ ) . '/includes/admin/epayco-admin.php';
					register_deactivation_hook( plugin_basename( __FILE__ ), array( $this, 'plugin_epayco_deactivation_hook' ) );
					add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
					add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
					add_action( 'woocommerce_maintenance_task_event_epayco', array( $this, 'wc_gw_epayco_maintenance_task_event' ), 10 );
					if ( !class_exists( 'FunctionsGatewayIMMA') ) { require_once dirname( __FILE__ ) . '/includes/class-functions-gateway-imma.php'; }
				} //End init()

				public function install() {
					if ( ! is_plugin_active( plugin_basename( __FILE__ ) ) ) {
						return;
					}
				} //End install()

				public function plugin_action_links( $links ) {
					$plugin_links = array(
						'<a href="admin.php?page=wc-settings&tab=checkout&section=epayco">' . __( 'Settings', 'imma' ) . '</a>',
					);
					return array_merge( $plugin_links, $links );
				} //End plugin_action_links()

				public function plugin_epayco_deactivation_hook() {
					wp_clear_scheduled_hook( 'woocommerce_maintenance_task_event_epayco' );
				} //End plugin_epayco_deactivation_hook()

				public function add_gateways( $methods ) {
					$methods[] = 'WC_Gateway_ePayco';
					return $methods;
				} //End add_gateways()

				public function wc_gw_epayco_maintenance_task_event() {
					$all_orders_query = array();
					$wc_gateway_epayco = new WC_Gateway_ePayco();
					$temp_token = $wc_gateway_epayco->get_token();
					if ( $temp_token != "" && $wc_gateway_epayco->get_maintenance_task() == "yes" ) {
						$wc_gateway_epayco->set_debug( "========== BEGIN wc_gw_epayco_maintenance_task_event BEGIN ==========" );
						global $wpdb;
						$today = date( 'Y-m-d', strtotime('-1 day') );
						$tomorrow = date( 'Y-m-d', strtotime('tomorrow') );
						$hpos_is_enabled = false;
						if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil') ) {
							$orderUtil = new \Automattic\WooCommerce\Utilities\OrderUtil();
							if ( $orderUtil::custom_orders_table_usage_is_enabled() ) {
								$hpos_is_enabled = true;
							}
						}
						if ( $hpos_is_enabled == true ) {
							$all_orders_query = $wpdb->get_results( "SELECT DISTINCT A.id, A.transaction_id FROM {$wpdb->prefix}wc_orders AS A WHERE (A.status='wc-on-hold' OR A.status='wc-pending') AND A.payment_method='epayco' AND ( A.date_created_gmt BETWEEN '{$today}' AND '{$tomorrow}' );", OBJECT );
						} else {
							$all_orders_query = $wpdb->get_results( "SELECT DISTINCT A.ID FROM {$wpdb->posts} AS A INNER JOIN {$wpdb->postmeta} AS B ON A.ID=B.post_id WHERE A.post_type='shop_order' AND (A.post_status='wc-on-hold' OR A.post_status='wc-pending') AND B.meta_key='_payment_method' AND B.meta_value='epayco' AND ( A.post_date BETWEEN '{$today}' AND '{$tomorrow}' );", OBJECT );
						}
						if ( !empty($all_orders_query) ) {
							foreach ( $all_orders_query as $value ) {
								$dcod = "";
								$dstatus = "";
								$transaction_id = "";
								$dmethod = "";
								$order_id = 0;
								$isOkData = null;
								if ( $hpos_is_enabled == true ) {
									$transaction_id = $value->transaction_id;
									$order_id = $value->id;
								} else {
									$transaction_id = get_post_meta( $value->ID, '_transaction_id', true );
									$order_id = $value->ID;
								}
								if ( $transaction_id != "" && !is_null($transaction_id) ) {
							        $response = wp_remote_post( 'https://apify.epayco.co/transaction/detail', array(
							            'timeout' => 60,
							            'headers' => array(
							                'Authorization' => 'Bearer ' . $temp_token,
							            ),
							            'body' => array(
							                'filter' => array( 'referencePayco' => $transaction_id ),
							            ),
							        ) );
							        $data = json_decode( wp_remote_retrieve_body( $response ) );
							        if ( is_object($data) && isset($data->titleResponse) && strtolower($data->titleResponse) == "unauthorized." ) {
										$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event: unauthorized" );
							        	unset( $data );
							        	unset( $response );
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
								        	$temp_token = $data->token;
								        	$wc_gateway_epayco->update_option( 'token', $temp_token );
								        	unset( $data );
									        $response = wp_remote_post( 'https://apify.epayco.co/transaction/detail', array(
									            'timeout' => 60,
									            'headers' => array(
									                'Authorization' => 'Bearer ' . $temp_token,
									            ),
									            'body' => array(
									                'filter' => array('referencePayco' => $transaction_id),
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
					    				$dcod = 0;
					    				if ( isset($isOkData->data->log, $isOkData->data->log->x_cod_response) ) {
					    					$dcod = intval( $isOkData->data->log->x_cod_response );
					    				}
					    				$dmethod = "CREDIT_CARD";
				        				if ( isset($isOkData->data->log, $isOkData->data->log->x_franchise) ) {
				        					$dmethod = $isOkData->data->log->x_franchise;
											if ( $dmethod != "" && in_array($dmethod, array("SP", "RS", "PR", "GA", "EF", "BA", "PSE")) ) {
												$dmethod = "OFFLINE";
											}
				        				}
					    				if ( in_array( $dcod, array(1, 2, 3, 4, 6, 9, 10, 11, 12) ) ) {
											$wc_order = wc_get_order( $order_id );
											if ( is_object($wc_order) && $wc_order->get_payment_method() == 'epayco' ) {
												switch ( $dcod ) {
													case 1:
														$status = $wc_gateway_epayco->get_status_completed( true );
														$wc_order->update_status( $status, __('Automatic process by IMMAGIT ePayco', 'imma'), true );
														$wc_gateway_epayco->set_debug( "wc_gw_payu_maintenance_task_event1: " . $wc_order->get_id() );
													break;
													case 2:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma'), true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event2: " . $wc_order->get_id() );
													break;
													case 3:
														$status = 'pending';
														if ( $dmethod == "OFFLINE" ) {}
														$wc_order->update_status( $status, __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event3: " . $wc_order->get_id() );
													break;
													case 4:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event4: " . $wc_order->get_id() );
													break;
													case 6:
														$wc_order->update_status( 'refunded', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event6: " . $wc_order->get_id() );
													break;
													case 7:
														$status = 'pending';
														if ( $dmethod == "OFFLINE" ) {}
														$wc_order->update_status( $status, __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event7: " . $wc_order->get_id() );
													break;
													case 8:
														$status = 'pending';
														if ( $dmethod == "OFFLINE" ) {}
														$wc_order->update_status( $status, __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event8: " . $wc_order->get_id() );
													break;
													case 9:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event9: " . $wc_order->get_id() );
													break;
													case 10:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event10: " . $wc_order->get_id() );
													break;
													case 11:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event11: " . $wc_order->get_id() );
													break;
													case 12:
														$wc_order->update_status( 'failed', __('Automatic process by IMMAGIT ePayco', 'imma') . '–', true );
														$wc_gateway_epayco->set_debug( "wc_gw_epayco_maintenance_task_event12: " . $wc_order->get_id() );
													break;
												}
											}
											unset( $wc_order );
					    				}
							        }
							        unset( $data );
							        unset( $response );
								}
								unset( $isOkData );
							}
						}
						$wc_gateway_epayco->set_debug( "========== END wc_gw_epayco_maintenance_task_event END ==========" );
					}
				}
			}
			WCGW_ePayco::get_instance();
		endif;

		if ( ! class_exists( 'Woomelly' ) ) {
			add_action( 'admin_notices', 'wc_gw_epayco_woomelly_ads' );
			return;
		}
	}
} //End wc_gw_epayco_init()