<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

/**
 * Class WC_Customer_Cancel_Order
 */
class WC_Stellr_Send_Order_Voucher extends WC_Email {

	/**
	 * Create an instance of the class.
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {
		// Email slug we can use to filter other data.
		$this->id          = 'class_wc_stellr_send_order_voucher';
		$this->title       = __( 'Your Voucher Pin', 'woocommerce-stellr' );
		$this->description = __( 'An email sent to the customer with the voucher to a digital product.', 'woocommerce-stellr' );
		// For admin area to let the user know we are sending this email to customers.
		$this->customer_email = true;
		$this->heading        = __( 'Digital Order Voucher code', 'woocommerce-stellr' );
		// translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
		$this->subject = sprintf( _x( 'Your voucher pin from [%s]', 'default email subject for voucher code sent to customer', 'woocommerce-stellr' ), '{blogname}' );

		// Template paths.
		$this->template_html  = 'wc-stellr-order-voucher.php';
		$this->template_plain = 'plain/wc-stellr-order-voucher.php';
		$this->template_base  = CUSTOM_WC_EMAIL_PATH . 'emails/';

		// Action to which we hook onto to send the email.
		add_action( 'woocommerce_order_status_pending_to_processing_notification', array( $this, 'trigger' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ), 10, 2 );

		/**
		 * Add a custom field (in an order) to the emails
		 */
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'wc_stellr_woocommerce_email_order_meta_fields' ), 10, 3 );

		parent::__construct();
	}

	/**
	 * Trigger Function that will send this email to the customer.
	 *
	 * @access public
	 * @return void
	 */
	function trigger( $order_id ) {

		$voucher_code = get_post_meta( $order_id, 'voucher_code', true );

		if ( ! $voucher_code ) {
			return;
		}

		$this->object = wc_get_order( $order_id );

		if ( version_compare( '3.0.0', WC()->version, '>' ) ) {
			$order_email = $this->object->billing_email;
		} else {
			$order_email = $this->object->get_billing_email();
		}

		$this->recipient = $order_email;

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 *  Add a custom field (in an order) to the emails
	 * 
	 * @access public
	 * 
	 * @param array $fields
	 * @param bool $sent_to_admin
	 * @param WC_Order $order
	 * @return array
	 */
	function wc_stellr_woocommerce_email_order_meta_fields( $fields, $sent_to_admin, $order ) {
		$fields['voucher_code'] = array(
			'label' => __( 'Voucher PIN(s)' ),
			'value' => get_post_meta( $order->get_id(), 'voucher_code', true ),
		);
		return $fields;
	}

	 /**
	  * Get content html.
	  *
	  * @access public
	  * @return string
	  */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'email_heading' => $this->get_heading(),
				'sent_to_admin' => false,
				'plain_text'    => true,
				'email'         => $this,
			),
			'',
			$this->template_base
		);
	}
}
