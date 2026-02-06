<?php
// Smart Defaults Logic
$brand_color_option = get_option( 'wbr_brand_color' );
$brand_color = ! empty( $brand_color_option ) ? $brand_color_option : '#6772e5';

$logo_url_option = get_option( 'wbr_logo_url' );
$logo_url = $logo_url_option;
if ( empty( $logo_url ) && has_custom_logo() ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	$logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
	if ( $logo ) {
		$logo_url = $logo[0];
	}
}

$title_option = get_option( 'wbr_custom_title' );
$title = ! empty( $title_option ) ? $title_option : get_bloginfo( 'name' );

$description_option = get_option( 'wbr_custom_description' );
$description = ! empty( $description_option ) ? $description_option : get_bloginfo( 'description' );

if ( empty( $description ) ) {
	$description = 'Secure Payment Portal';
}
?>
<div class="wbr-payment-container" style="max-width: 500px; margin: 50px auto; padding: 30px; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #fff;">
	
	<div class="wbr-header" style="text-align: center; margin-bottom: 30px;">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="max-width: 80px; height: auto; margin-bottom: 15px;">
		<?php endif; ?>
		<h2 style="margin: 0; color: #333;"><?php echo esc_html( $title ); ?></h2>
		<p style="color: #666; margin-top: 5px;"><?php echo esc_html( $description ); ?></p>
	</div>

	<div class="wbr-order-summary" style="background: #f9f9f9; padding: 20px; border-radius: 6px; margin-bottom: 30px;">
		<div class="wbr-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
			<span style="color: #666;">Service:</span>
			<span style="font-weight: 600; color: #333;"><?php echo esc_html( $order['service_type'] ); ?></span>
		</div>
		<div class="wbr-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
			<span style="color: #666;">Client:</span>
			<span style="font-weight: 600; color: #333;"><?php echo esc_html( $order['client_name'] ); ?></span>
		</div>
		<div class="wbr-divider" style="height: 1px; background: #e0e0e0; margin: 15px 0;"></div>
		<div class="wbr-row" style="display: flex; justify-content: space-between; align-items: center;">
			<span style="color: #333; font-size: 1.1em;">Total Amount:</span>
			<span style="font-weight: 700; font-size: 1.5em; color: <?php echo esc_attr( $brand_color ); ?>;">
				<?php echo esc_html( number_format( $order['amount'], 2 ) . ' ' . $order['currency'] ); ?>
			</span>
		</div>
	</div>

	<div class="wbr-actions" style="text-align: center;">
		<style>
			@keyframes wbr-spin { to { transform: rotate(360deg); } }
			.wbr-spinner {
				display: inline-block;
				width: 16px;
				height: 16px;
				border: 2px solid rgba(255,255,255,0.3);
				border-radius: 50%;
				border-top-color: #fff;
				animation: wbr-spin 0.8s linear infinite;
				margin-right: 8px;
				vertical-align: middle;
			}
		</style>
		<button id="wbr-pay-btn" style="background: <?php echo esc_attr( $brand_color ); ?>; color: white; border: none; padding: 12px 30px; font-size: 16px; border-radius: 4px; cursor: pointer; transition: opacity 0.2s; width: 100%; font-weight: 600; display: flex; align-items: center; justify-content: center;">
			<span>Pay Securely via Stripe</span>
		</button>
		<div id="wbr-error" style="display:none; margin-top: 12px; color:#c0392b;"></div>
		<p style="margin-top: 15px; font-size: 12px; color: #999;">
			<svg style="vertical-align: middle; width: 12px; height: 12px; fill: #999;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
			Your payment information is encrypted and processed securely by Stripe.
		</p>
		<script>
			(function(){
				var btn = document.getElementById('wbr-pay-btn');
				var btnText = btn.querySelector('span');
				var err = document.getElementById('wbr-error');
				var busy = false;
				
				function setBusy(b) {
					busy = b;
					btn.disabled = b;
					btn.style.opacity = b ? '0.7' : '1';
					if (b) {
						var countdown = 0;
						btnText.innerHTML = '<span class="wbr-spinner"></span> Redirecting...';
						
						// Simple visual cue interval
						var dots = '';
						var interval = setInterval(function(){
							if(!busy) { clearInterval(interval); return; }
							countdown++;
							dots = '.'.repeat((countdown % 3) + 1);
							btnText.innerHTML = '<span class="wbr-spinner"></span> Redirecting' + dots;
						}, 500);
						
					} else {
						btnText.textContent = 'Pay Securely via Stripe';
					}
				}
				
				btn.addEventListener('click', function(e){
					e.preventDefault();
					if (busy) return;
					setBusy(true);
					err.style.display = 'none';
					
					var data = new FormData();
					data.append('action', 'wbr_create_checkout');
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wbr_checkout' ) ); ?>');
					data.append('order_token', '<?php echo esc_js( $order['order_token'] ); ?>');
					
					fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: data,
						credentials: 'same-origin'
					}).then(function(res){
						if (!res.ok) {
							throw new Error('Server error: ' + res.status);
						}
						return res.text().then(function(text) {
							try {
								return JSON.parse(text);
							} catch (e) {
								throw new Error('Invalid server response');
							}
						});
					})
					.then(function(json){
						if (json && json.success && json.data && json.data.redirect) {
							window.location.href = json.data.redirect;
						} else {
							setBusy(false);
							err.textContent = (json && json.data && json.data.message) ? json.data.message : 'Something went wrong. Please try again.';
							err.style.display = 'block';
						}
					}).catch(function(e){
						setBusy(false);
						console.error(e);
						err.textContent = 'Unable to connect to payment server. Please try again later.';
						err.style.display = 'block';
					});
				});
			})();
		</script>
	</div>

</div>
