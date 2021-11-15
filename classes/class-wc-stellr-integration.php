<?php

/**
 * Integration to Stellr API
 */

if ( ! class_exists( 'WC_Stellr_Integration' ) ) :
	class WC_Stellr_Integration extends WC_Integration {

		/**
		 * Constructor
		 */
		public function __construct() {
			global $woocommerce;
			$this->id                 = 'wc-stellr-integration';
			$this->method_title       = __( 'Stellr integration', 'woocommerce-stellr' );
			$this->method_description = __( 'Integration to the Stellr prepaid cards provider API.' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->api_key  = $this->get_option( 'api_key' );
			$this->base_url = $this->get_option( 'base_url' );
			$this->enable_cron = $this->get_option( 'enable_cron' );


			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		/**
		 * Initialize integration settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'api_key'  => array(
					'title'       => __( 'API Key' ),
					'type'        => 'text',
					'description' => __( 'You stellr API key' ),
					'desc_tip'    => true,
					'default'     => '',
				),
				'base_url' => array(
					'title'       => __( 'Base URL' ),
					'type'        => 'text',
					'description' => __( 'Stellr endpoint base url. eg.: https://api-prod.stellr-net.com/fusion-v1/' ),
					'desc_tip'    => true,
					'default'     => '',
				),
				'enable_cron' => array(
					'title'       => __( 'Update product automatically' ),
					'type'        => 'checkbox',
					'description' => __( 'Update product information automatically from Stellr API.' ),
					'desc_tip'    => true,
					'default'     => '',
				),

			);
		}

	}
endif;
