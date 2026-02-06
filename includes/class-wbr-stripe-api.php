<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBR_Stripe_API {
	public static function get_payment_mode() {
		return get_option( 'wbr_payment_mode', 'test' );
	}

	public static function get_secret_key() {
		$mode = self::get_payment_mode();
		if ( 'test' === $mode ) {
			return get_option( 'wbr_stripe_test_secret_key' );
		}
		return get_option( 'wbr_stripe_secret_key' );
	}

	public static function get_publishable_key() {
		$mode = self::get_payment_mode();
		if ( 'test' === $mode ) {
			return get_option( 'wbr_stripe_test_publishable_key' );
		}
		return get_option( 'wbr_stripe_publishable_key' );
	}

	public static function create_checkout_session( $order_data ) {
		$secret_key = self::get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'stripe_error', 'Payment configuration error: Secret Key is missing.' );
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			return new WP_Error( 'stripe_error', 'Payment system error: Stripe library missing.' );
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		try {
			$order_token = $order_data['order_token'];
			$success_page = get_option( 'wbr_payment_success_page_id' );
			$cancel_page  = get_option( 'wbr_payment_cancel_page_id' );
			$success_url  = $success_page ? add_query_arg( array( 'order_token' => $order_token ), get_permalink( $success_page ) ) : add_query_arg( array( 'payment_status' => 'success', 'order_token' => $order_token ), get_permalink( get_option( 'wbr_payment_page_id' ) ) );
			$cancel_url   = $cancel_page ? add_query_arg( array( 'order_token' => $order_token ), get_permalink( $cancel_page ) ) : add_query_arg( array( 'payment_status' => 'cancelled', 'order_token' => $order_token ), get_permalink( get_option( 'wbr_payment_page_id' ) ) );
			$session_args = array(
				'payment_method_types' => array( 'card' ),
				'line_items' => array(
					array(
						'price_data' => array(
							'currency' => strtolower( $order_data['currency'] ),
							'product_data' => array(
								'name' => $order_data['service_type'],
								'description' => 'Order #' . $order_data['id'] . ' - ' . $order_data['client_name'],
							),
							'unit_amount' => intval( $order_data['amount'] * 100 ),
						),
						'quantity' => 1,
					),
				),
				'mode' => 'payment',
				'customer_email' => $order_data['client_email'],
				'billing_address_collection' => get_option( 'wbr_collect_billing', 'required' ),
				'metadata' => array(
					'order_id' => $order_data['id'],
					'order_token' => $order_data['order_token'],
					'client_email' => $order_data['client_email'],
					'service_type' => $order_data['service_type'],
					'deal_source' => 'custom_quote',
					'site' => get_bloginfo( 'name' ),
				),
				'success_url' => $success_url,
				'cancel_url' => $cancel_url,
			);

			if ( get_option( 'wbr_collect_phone' ) ) {
				$session_args['phone_number_collection'] = array( 'enabled' => true );
			}

			$session = \Stripe\Checkout\Session::create( $session_args );
			return $session;
		} catch ( \Stripe\Exception\CardException $e ) {
			// Card errors are safe to show to the user
			return new WP_Error( 'stripe_error', $e->getMessage() );
		} catch ( \Exception $e ) {
			// Other errors (authentication, API, etc.) should be hidden
			// We can log the actual error if we had a logger, but for now just return a generic one.
			// Error: "This API call cannot be made with a publishable API key" falls here.
			return new WP_Error( 'stripe_error', 'Unable to initialize payment. Please contact support. (Ref: ' . substr(md5($e->getMessage()), 0, 8) . ')' );
		}
	}

	public static function retrieve_checkout_session( $session_id ) {
		$secret_key = self::get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'stripe_error', 'Payment configuration error: Secret Key is missing.' );
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			return new WP_Error( 'stripe_error', 'Payment system error: Stripe library missing.' );
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		try {
			$session = \Stripe\Checkout\Session::retrieve( $session_id );
			return $session;
		} catch ( \Exception $e ) {
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}
}