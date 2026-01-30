<?php
$brand_color = get_option( 'wbr_brand_color', '#6772e5' );
?>
<div class="wbr-payment-container" style="max-width: 500px; margin: 50px auto; padding: 40px; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #fff;">
	
	<div style="color: #2ecc71; margin-bottom: 20px;">
		<svg style="width: 64px; height: 64px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
	</div>

	<h2 style="color: #333; margin-bottom: 10px;">Payment Successful!</h2>
	
	<p style="color: #666; line-height: 1.6;">
		Thank you, <strong><?php echo esc_html( $order['client_name'] ); ?></strong>.<br>
		Your payment of <strong><?php echo esc_html( number_format( $order['amount'], 2 ) . ' ' . $order['currency'] ); ?></strong> has been received.
	</p>

	<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
		<p style="font-size: 14px; color: #999;">Order ID: #<?php echo esc_html( $order['id'] ); ?></p>
		<a href="<?php echo esc_url( home_url() ); ?>" style="display: inline-block; margin-top: 10px; color: <?php echo esc_attr( $brand_color ); ?>; text-decoration: none; font-weight: 600;">Return to Home</a>
	</div>

</div>

