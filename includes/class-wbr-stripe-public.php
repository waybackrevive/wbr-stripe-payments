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

		$payment_status = isset( $_GET['payment_status'] ) ? sanitize_text_field( $_GET['payment_status'] ) : '';
		if ( $payment_status === 'success' ) {
			// Security: Verify payment with Stripe API
			if ( $order['status'] !== 'paid' && ! empty( $order['stripe_session_id'] ) ) {
				$session = WBR_Stripe_API::retrieve_checkout_session( $order['stripe_session_id'] );
				
				if ( ! is_wp_error( $session ) && $session->payment_status === 'paid' ) {
					// Payment verified!
					$transaction_id = isset( $session->payment_intent ) ? $session->payment_intent : $session->id;
					
					$wpdb->update( 
						$table_name, 
						array( 
							'status' => 'paid',
							'transaction_id' => $transaction_id
						), 
						array( 'id' => $order['id'] ) 
					);
					
					$order['status'] = 'paid';
					$order['transaction_id'] = $transaction_id;
					
					// Send Receipt Email
					$this->send_payment_receipt( $order );
				} else {
					// Verification failed or pending
					// Maybe log error? For now, we don't mark as paid.
					// We can show a pending message or contact support.
				}
			} elseif ( $order['status'] !== 'paid' && empty( $order['stripe_session_id'] ) ) {
                // Edge case: User manipulated URL but no session exists?
            }
            
            // If verification passed (or was already paid), show success.
            // If verification failed, we probably shouldn't show success, but for UX if it's 'paid' in DB we do.
            if ( $order['status'] === 'paid' ) {
			    return $this->load_template( 'payment-success', $order );
            } else {
                return '<div class="wbr-notice wbr-error">Payment verification failed. Please contact support.</div>' . $this->load_template( 'payment-page', $order );
            }
		}

		return $this->load_template( 'payment-page', $order );
	}
    
    private function send_payment_receipt( $order ) {
		// Check if email receipts are enabled
		$enable_email = get_option( 'wbr_enable_email_receipt', 1 );
		if ( ! $enable_email ) {
			return;
		}

        $to = $order['client_email'];
		
		// Get Email Settings
		$sender_name = get_option( 'wbr_email_sender_name', get_bloginfo( 'name' ) );
		$sender_email = get_option( 'wbr_email_sender_address', get_option( 'admin_email' ) );
		$subject_template = get_option( 'wbr_email_subject', 'Payment Receipt - Order #{order_id}' );
		
		// Parse Subject
		$subject = str_replace(
			array( '{order_id}', '{service}', '{amount}' ),
			array( $order['id'], $order['service_type'], $order['amount'] ),
			$subject_template
		);

        $headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $sender_email . '>'
		);
        
        $brand_color = get_option( 'wbr_brand_color', '#6772e5' );
        $site_name = $sender_name; // Use sender name for footer/body too
        
        $message = '
        <html>
        <body style="font-family: Arial, sans-serif; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee;">
                <h2 style="color: ' . esc_attr($brand_color) . ';">Payment Received</h2>
                <p>Dear ' . esc_html($order['client_name']) . ',</p>
                <p>Thank you for your payment. Here are the details:</p>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Order ID:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">#' . esc_html($order['id']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Service:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html($order['service_type']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Amount:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html(number_format($order['amount'], 2) . ' ' . $order['currency']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Transaction ID:</strong></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html($order['transaction_id']) . '</td>
                    </tr>
                </table>
                <p>If you have any questions, please reply to this email.</p>
                <p>Best regards,<br>' . esc_html($site_name) . '</p>
            </div>
        </body>
        </html>
        ';
        
        wp_mail( $to, $subject, $message, $headers );
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
		
		// If already paid, just show success
		if ( $order['status'] === 'paid' ) {
			return $this->load_template( 'payment-success', $order );
		}

		// If not paid in DB but user is here, check Stripe (Sync)
		if ( ! empty( $order['stripe_session_id'] ) ) {
			$session = WBR_Stripe_API::retrieve_checkout_session( $order['stripe_session_id'] );
			
			if ( ! is_wp_error( $session ) && $session->payment_status === 'paid' ) {
				$transaction_id = isset( $session->payment_intent ) ? $session->payment_intent : $session->id;
				
				$wpdb->update( 
					$table_name, 
					array( 
						'status' => 'paid',
						'transaction_id' => $transaction_id
					), 
					array( 'id' => $order['id'] ) 
				);
				
				$order['status'] = 'paid';
				$order['transaction_id'] = $transaction_id;
				
				$this->send_payment_receipt( $order );
				return $this->load_template( 'payment-success', $order );
			}
		}

		// Fallback: If verification failed, don't just mark as paid blindly
		// But if this function was called, it means user hit a shortcode manually?
		// Actually, this function is shortcode [wbr_payment_success]
		// It should behave same as render_checkout success logic
		
		return '<p class="wbr-error">Payment not verified.</p>';
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