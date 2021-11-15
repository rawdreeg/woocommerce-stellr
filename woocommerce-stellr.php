<?php
/**
 * Plugin Name:     Woocommerce Stellr
 * Plugin URI:      https://github.com/rawdreeg/woocommerce-stellr
 * Description:     Integration to the Stellr prepaid cards provider API.
 * Author:          Tusse, Rodrigue
 * Author URI:      https://rodrigue.xyz
 * Text Domain:     woocommerce-stellr
 * Version:         0.1.0
 *
 * @package         Woocommerce_Stellr
 */

if ( ! class_exists( 'WC_Stellr' ) ) :
	class WC_Stellr {
		/**
		 * Settings
		 */
		public $settings;

		/**
		 * Initialize the plugin.
		 */
		public function __construct() {
			$this->settings = get_option( 'woocommerce_wc-stellr-integration_settings' );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Initialize the plugin.
		 */
		public function init() {
			// Checks if WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) ) {
				// Include our classes.
				include_once 'classes/class-wc-stellr-integration.php';
				include_once 'classes/class-wc-stellr-integration-api.php';
				include_once 'classes/class-wc-stellr-integration-product-import.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

				// Set the plugin slug
				define( 'WC_STELLR_SLUG', 'wc-settings' );

				// Setting action for plugin
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'WC_Stellr_Integration_plugin_action_links' );

				// Schedule the import.
				if ($this->settings['enable_cron'] === 'yes') {
					// Importing the products
					add_action( 'wc_stellr_get_products_cron', array( $this, 'wc_stellr_get_products' ) );

					if ( ! wp_next_scheduled( 'wc_stellr_get_products_cron' ) ) {
						wp_schedule_event( time(), 'daily', 'wc_stellr_get_products_cron' );
					}
				} else {
					wp_clear_scheduled_hook( 'wc_stellr_get_products_cron' );
				}

				// Get voucher code after payment.
				add_action( 'woocommerce_order_status_changed', array( $this, 'wc_stellr_get_voucher_code' ) );

				// Filtering the emails and adding our own email.
				add_filter( 'woocommerce_email_classes', array( $this, 'register_email' ), 90, 1 );
				// Absolute path to the plugin folder.
				define( 'CUSTOM_WC_EMAIL_PATH', plugin_dir_path( __FILE__ ) );
			}
		}

		/**
		 * Voucher code.
		 */
		public function wc_stellr_get_voucher_code( $order_id ) {
			$order       = wc_get_order( $order_id );
			$order_items = $order->get_items();

			// order status.
			if ( $order->get_date_paid() ) {
				$voucher = array();
				foreach ( $order_items as $item ) {
					$product_id = $item->get_product_id();

					if ( ! has_term( array( 'Digital Vouchers' ), 'product_cat', $product_id ) ) {
						continue;
					}
					$price = $item->get_total();
					$sku   = $item->get_product()->get_sku();
					// API transaction.
					$data     = array(
						'amount'     => array(
							'currency' => 'ZAR',
							'value'    => (float) $price,
						),
						'storeRef'   => '00001',
						'productRef' => $sku,
					);
					$response = [];
					for ( $x = 0;  $x < $item->get_quantity(); $x++ ) {
						$data['ref'] = $sku . '-' . $product_id . '-' . $x . '-' . $order_id;
						$response    = WC_Stellr_Integration_API::stellr_api_request( $this->settings['base_url'] . 'transaction', $this->settings['api_key'], 'POST', $data );
						if ( $response && isset( $response['pin'] ) ) {
							$voucher[] = $item->get_name() . ': ' . $response['pin'];
						}
					}
				}

				if ( ! empty( $voucher ) ) {
					$order->update_meta_data( 'voucher_code', implode( '<br/>', $voucher ) );
					$order->save();
				} else {
					// add note
					$order->add_order_note( __( 'Error generating the voucher code. Please contact support.', 'woocommerce-stellr' ) );
				}
			}

		}

		/**
		 * Get the products from stellr API
		 */
		public function wc_stellr_get_products() {
			$products = WC_Stellr_Integration_API::stellr_api_request( $this->woocommerce-stellr['base_url'] . 'product', $this->woocommerce-stellr['api_key'], 'GET' );
			if ( $products ) {
				foreach ( $products as $product ) {
					if ( $product['country'] === 'ZA' && $product['status'] == 'Active' ) {
						WC_Stellar_Integration_Product_Import::wc_stellr_upsert_product( $product );
					}
				}
			}
		}

		/**
		 * Send voucher code to the customer.
		 */
		public function wc_stellr_send_voucher_code( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order->get_date_paid() ) {
				$voucher_code = $order->get_meta( 'voucher_code' );
				if ( ! empty( $voucher_code ) ) {
					// Send email to customer.
					$email = WC_Stellar_Integration_Product_Import::wc_stellr_get_email_template( $voucher_code );
					$order->add_order_note( $voucher_code );
				}
			}
		}

		/**
		 * Add integration to WooCommerce.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Stellr_Integration';
			return $integrations;
		}

		/**
		 * @param array $emails
		 *
		 * @return array
		 */
		public function register_email( $emails ) {

			require_once 'classes/class-wc-stellr-send-order-voucher.php';
			$emails['WC_Stellr_Send_Order_Voucher'] = new WC_Stellr_Send_Order_Voucher();

			return $emails;
		}

	}

	/**
	 * Action links.
	 *
	 * @param array $links
	 *
	 * return array
	 */
	function WC_Stellr_Integration_plugin_action_links( $links ) {

		$links[] = '<a href="' . menu_page_url( WC_STELLR_SLUG, false ) . '&tab=integration">Settings</a>';
		return $links;
	}

	// Instantiate the plugin.
	$WC_Stellr = new WC_Stellr( __FILE__ );
endif;
