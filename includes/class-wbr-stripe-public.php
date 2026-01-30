<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBR_Stripe_Public {
	public function __construct() {
		add_shortcode( 'wbr_payment_checkout', array( $this, 'render_checkout' ) );
		add_shortcode( 'wbr_payment_success', array( $this, 'render_success' ) );
		add_shortcode( 'wbr_payment_cancel', array( $this, 'render_cancel' ) );
		add_action( 'admin_post_wbr_process_payment', array( $this, 'process_payment' ) );
		add_action( 'admin_post_nopriv_wbr_process_payment', array( $this, 'process_payment' ) );
		add_action( 'wp_ajax_wbr_create_checkout', array( $this, 'ajax_create_checkout' ) );
		add_action( 'wp_ajax_nopriv_wbr_create_checkout', array( $this, 'ajax_create_checkout' ) );
	}

	public function render_checkout( $atts ) {
		$order_token = get_query_var( 'order_token' );
		if ( empty( $order_token ) ) {
			$order_token = isset( $_GET['order_token'] ) ? sanitize_text_field( $_GET['order_token'] ) : '';
		}
		if ( empty( $order_token ) ) {
			return '<p class="wbr-error">Invalid payment link.</p>';
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE order_token = %s", $order_token ), ARRAY_A );
		if ( ! $order ) {
			return '<p class="wbr-error">Order not found.</p>';
		}

		if ( $order['status'] === 'paid' ) {
			return $this->load_template( 'payment-success', $order );
		}

		return $this->load_template( 'payment-page', $order );
	}

	public function render_success() {
		$order_token = isset( $_GET['order_token'] ) ? sanitize_text_field( $_GET['order_token'] ) : '';
		if ( empty( $order_token ) ) {
			return '<p class="wbr-error">Invalid payment link.</p>';
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE order_token = %s", $order_token ), ARRAY_A );
		if ( ! $order ) {
			return '<p class="wbr-error">Order not found.</p>';
		}
		$wpdb->update( $table_name, array( 'status' => 'paid' ), array( 'id' => $order['id'] ) );
		$order['status'] = 'paid';
		return $this->load_template( 'payment-success', $order );
	}

	public function render_cancel() {
		$order_token = isset( $_GET['order_token'] ) ? sanitize_text_field( $_GET['order_token'] ) : '';
		if ( empty( $order_token ) ) {
			return '<p class="wbr-error">Invalid payment link.</p>';
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE order_token = %s", $order_token ), ARRAY_A );
		if ( ! $order ) {
			return '<p class="wbr-error">Order not found.</p>';
		}
		echo '<div class="wbr-notice wbr-warning">Payment was cancelled. You can try again below.</div>';
		return $this->load_template( 'payment-page', $order );
	}

	private function load_template( $template_name, $order ) {
		ob_start();
		$template_path = WBR_STRIPE_PATH . 'templates/' . $template_name . '.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo 'Template not found.';
		}
		return ob_get_clean();
	}

	public function process_payment() {
		if ( ! isset( $_POST['wbr_payment_nonce'] ) || ! wp_verify_nonce( $_POST['wbr_payment_nonce'], 'wbr_process_payment' ) ) {
			wp_die( 'Security check failed' );
		}

		$order_token = sanitize_text_field( $_POST['order_token'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE order_token = %s", $order_token ), ARRAY_A );
		if ( ! $order ) {
			wp_die( 'Order not found' );
		}

		$session = WBR_Stripe_API::create_checkout_session( $order );
		if ( is_wp_error( $session ) ) {
			wp_die( $session->get_error_message() );
		}

		$wpdb->update( $table_name, array( 'stripe_session_id' => $session->id ), array( 'id' => $order['id'] ) );

		wp_redirect( $session->url );
		exit;
	}

	public function ajax_create_checkout() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wbr_checkout' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 400 );
		}
		$order_token = isset( $_POST['order_token'] ) ? sanitize_text_field( $_POST['order_token'] ) : '';
		if ( empty( $order_token ) ) {
			wp_send_json_error( array( 'message' => 'Missing order token.' ), 400 );
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE order_token = %s", $order_token ), ARRAY_A );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => 'Order not found.' ), 404 );
		}
		if ( isset( $order['status'] ) && $order['status'] === 'paid' ) {
			wp_send_json_error( array( 'message' => 'Order already paid.' ), 409 );
		}
		$session = WBR_Stripe_API::create_checkout_session( $order );
		if ( is_wp_error( $session ) ) {
			wp_send_json_error( array( 'message' => $session->get_error_message() ), 500 );
		}
		$wpdb->update( $table_name, array( 'stripe_session_id' => $session->id ), array( 'id' => $order['id'] ) );
		wp_send_json_success( array( 'redirect' => $session->url ) );
	}
}
