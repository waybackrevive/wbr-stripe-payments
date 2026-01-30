<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBR_Stripe_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wbr_create_order', array( $this, 'handle_create_order' ) );
		add_action( 'wp_ajax_wbr_create_order_ajax', array( $this, 'ajax_create_order' ) );
		add_action( 'wp_ajax_wbr_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			'WBR Payments',
			'WBR Payments',
			'manage_options',
			'wbr-payments',
			array( $this, 'render_orders_page' ),
			'dashicons-money',
			56
		);

		add_submenu_page(
			'wbr-payments',
			'Settings',
			'Settings',
			'manage_options',
			'wbr-payments-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'wbr_stripe_settings', 'wbr_payment_mode' );
		register_setting( 'wbr_stripe_settings', 'wbr_stripe_publishable_key' );
		register_setting( 'wbr_stripe_settings', 'wbr_stripe_secret_key' );
		register_setting( 'wbr_stripe_settings', 'wbr_stripe_test_publishable_key' );
		register_setting( 'wbr_stripe_settings', 'wbr_stripe_test_secret_key' );
		register_setting( 'wbr_stripe_settings', 'wbr_collect_billing' );
		register_setting( 'wbr_stripe_settings', 'wbr_collect_phone' );
		register_setting( 'wbr_stripe_settings', 'wbr_brand_color' );
		register_setting( 'wbr_stripe_settings', 'wbr_logo_url' );
		register_setting( 'wbr_stripe_settings', 'wbr_custom_title' );
		register_setting( 'wbr_stripe_settings', 'wbr_custom_description' );
	}

	public function render_settings_page() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';
		?>
<div class="wrap">
    <h1>Stripe Settings</h1>
    <h2 class="nav-tab-wrapper">
        <a href="?page=wbr-payments-settings&tab=general"
            class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General</a>
        <a href="?page=wbr-payments-settings&tab=checkout"
            class="nav-tab <?php echo $active_tab == 'checkout' ? 'nav-tab-active' : ''; ?>">Checkout Options</a>
        <a href="?page=wbr-payments-settings&tab=appearance"
            class="nav-tab <?php echo $active_tab == 'appearance' ? 'nav-tab-active' : ''; ?>">Appearance</a>
    </h2>
    <form id="wbr-settings-form">
        <?php
				// We still use settings_fields to generate nonces and hidden fields, though we'll use our own nonce for AJAX
				settings_fields( 'wbr_stripe_settings' );
				wp_nonce_field( 'wbr_save_settings_nonce', 'wbr_settings_nonce' );
				?>
        <input type="hidden" name="wbr_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
        <?php
				if ( $active_tab == 'general' ) {
					$mode = get_option( 'wbr_payment_mode', 'test' );
					?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Payment Mode</th>
                <td>
                    <select name="wbr_payment_mode" id="wbr_payment_mode">
                        <option value="test" <?php selected( $mode, 'test' ); ?>>Test Mode</option>
                        <option value="live" <?php selected( $mode, 'live' ); ?>>Live Mode</option>
                    </select>
                    <p class="description">Switch between Test and Live modes. Test mode uses Stripe's test keys for
                        development.</p>
                </td>
            </tr>

            <!-- Test Keys -->
            <tr valign="top" class="wbr-test-keys">
                <th scope="row">Test Publishable Key</th>
                <td><input type="text" name="wbr_stripe_test_publishable_key"
                        value="<?php echo esc_attr( get_option( 'wbr_stripe_test_publishable_key' ) ); ?>"
                        class="regular-text" placeholder="pk_test_..." /></td>
            </tr>
            <tr valign="top" class="wbr-test-keys">
                <th scope="row">Test Secret Key</th>
                <td><input type="password" name="wbr_stripe_test_secret_key"
                        value="<?php echo esc_attr( get_option( 'wbr_stripe_test_secret_key' ) ); ?>"
                        class="regular-text" placeholder="sk_test_..." /></td>
            </tr>

            <!-- Live Keys -->
            <tr valign="top" class="wbr-live-keys">
                <th scope="row">Live Publishable Key</th>
                <td><input type="text" name="wbr_stripe_publishable_key"
                        value="<?php echo esc_attr( get_option( 'wbr_stripe_publishable_key' ) ); ?>"
                        class="regular-text" placeholder="pk_live_..." /></td>
            </tr>
            <tr valign="top" class="wbr-live-keys">
                <th scope="row">Live Secret Key</th>
                <td><input type="password" name="wbr_stripe_secret_key"
                        value="<?php echo esc_attr( get_option( 'wbr_stripe_secret_key' ) ); ?>" class="regular-text"
                        placeholder="sk_live_..." /></td>
            </tr>
        </table>

        <script>
        (function() {
            var modeSelect = document.getElementById('wbr_payment_mode');
            var testRows = document.querySelectorAll('.wbr-test-keys');
            var liveRows = document.querySelectorAll('.wbr-live-keys');

            function toggleKeys() {
                var mode = modeSelect.value;
                testRows.forEach(function(row) {
                    row.style.display = (mode === 'test') ? 'table-row' : 'none';
                });
                liveRows.forEach(function(row) {
                    row.style.display = (mode === 'live') ? 'table-row' : 'none';
                });
            }

            if (modeSelect) {
                modeSelect.addEventListener('change', toggleKeys);
                toggleKeys(); // Initial state
            }
        })();
        </script>
        <?php
				} elseif ( $active_tab == 'checkout' ) {
					?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Collect Billing Address</th>
                <td>
                    <select name="wbr_collect_billing">
                        <option value="required" <?php selected( get_option( 'wbr_collect_billing' ), 'required' ); ?>>
                            Required</option>
                        <option value="auto" <?php selected( get_option( 'wbr_collect_billing' ), 'auto' ); ?>>Auto
                            (Optional)</option>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Collect Phone Number</th>
                <td>
                    <input type="checkbox" name="wbr_collect_phone" value="1"
                        <?php checked( get_option( 'wbr_collect_phone' ), 1 ); ?> />
                    <label>Enable phone number collection</label>
                </td>
            </tr>
        </table>
        <?php
				} elseif ( $active_tab == 'appearance' ) {
					$site_logo_url = '';
					if ( has_custom_logo() ) {
						$custom_logo_id = get_theme_mod( 'custom_logo' );
						$logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
						if ( $logo ) {
							$site_logo_url = $logo[0];
						}
					}
					?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Brand Color</th>
                <td>
                    <input type="color" name="wbr_brand_color"
                        value="<?php echo esc_attr( get_option( 'wbr_brand_color', '#6772e5' ) ); ?>" />
                    <p class="description">Used for buttons and accents.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Logo URL</th>
                <td>
                    <input type="text" name="wbr_logo_url"
                        value="<?php echo esc_attr( get_option( 'wbr_logo_url' ) ); ?>" class="regular-text"
                        placeholder="<?php echo esc_attr( $site_logo_url ); ?>" />
                    <p class="description">Enter the full URL of your logo image. Leave empty to use your site's custom
                        logo (if set).</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Custom Title</th>
                <td>
                    <input type="text" name="wbr_custom_title"
                        value="<?php echo esc_attr( get_option( 'wbr_custom_title' ) ); ?>" class="regular-text"
                        placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                    <p class="description">Leave empty to use your site title.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Custom Description</th>
                <td>
                    <textarea name="wbr_custom_description" class="large-text" rows="3"
                        placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"><?php echo esc_textarea( get_option( 'wbr_custom_description' ) ); ?></textarea>
                    <p class="description">Leave empty to use your site tagline/description.</p>
                </td>
            </tr>
        </table>
        <?php
				}
				?>
        <p class="submit">
            <button type="submit" id="wbr-save-settings-btn" class="button button-primary">Save Settings</button>
            <span id="wbr-settings-spinner" class="spinner" style="float: none; margin-left: 10px;"></span>
        </p>
        <div id="wbr-settings-message" style="margin-top: 10px; display: none;"></div>
    </form>
</div>
<script>
document.getElementById('wbr-settings-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var btn = document.getElementById('wbr-save-settings-btn');
    var spinner = document.getElementById('wbr-settings-spinner');
    var msg = document.getElementById('wbr-settings-message');

    btn.disabled = true;
    spinner.classList.add('is-active');
    msg.style.display = 'none';

    var formData = new FormData(form);
    formData.append('action', 'wbr_save_settings');

    fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            spinner.classList.remove('is-active');

            if (data.success) {
                msg.textContent = 'Settings saved successfully.';
                msg.style.color = '#27ae60';
                msg.style.display = 'block';
                setTimeout(function() {
                    msg.style.display = 'none';
                }, 3000);
            } else {
                msg.textContent = data.data.message || 'Error saving settings';
                msg.style.color = '#c0392b';
                msg.style.display = 'block';
            }
        })
        .catch(error => {
            btn.disabled = false;
            spinner.classList.remove('is-active');
            msg.textContent = 'Network error. Please try again.';
            msg.style.color = '#c0392b';
            msg.style.display = 'block';
        });
});
</script>
<?php
	}

	public function render_orders_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';
		$orders = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY created_at DESC" );
		?>
<div class="wrap">
    <h1 class="wp-heading-inline">Payment Orders</h1>
    <a href="#" id="wbr-add-new-order-btn" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <div id="wbr-add-order-form"
        style="display:none; margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2>Create New Payment Order</h2>
        <form id="wbr-create-order-form">
            <?php wp_nonce_field( 'wbr_create_order_nonce', 'wbr_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="client_name">Client Name</label></th>
                    <td><input type="text" name="client_name" id="client_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="client_email">Client Email</label></th>
                    <td><input type="email" name="client_email" id="client_email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="amount">Amount</label></th>
                    <td><input type="number" step="0.01" name="amount" id="amount" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="currency">Currency</label></th>
                    <td>
                        <select name="currency" id="currency">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="AUD">AUD</option>
                            <option value="CAD">CAD</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="service_type">Service Type</label></th>
                    <td><input type="text" name="service_type" id="service_type" class="regular-text"
                            placeholder="e.g. Web Design, SEO" required></td>
                </tr>
                <tr>
                    <th><label for="notes">Internal Notes</label></th>
                    <td><textarea name="notes" id="notes" class="large-text" rows="3"></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" id="wbr-submit-order" class="button button-primary">Generate Payment Link</button>
                <button type="button" class="button"
                    onclick="document.getElementById('wbr-add-order-form').style.display='none';">Cancel</button>
                <span id="wbr-order-spinner" class="spinner" style="float: none; margin-left: 10px;"></span>
            </p>
            <div id="wbr-form-message" style="margin-top: 10px; display: none;"></div>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped" id="wbr-orders-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Service</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Link</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $orders ) ) : ?>
            <?php foreach ( $orders as $order ) : ?>
            <?php $payment_url = home_url( 'pay/order/' . $order->order_token ); ?>
            <tr>
                <td><?php echo esc_html( $order->created_at ); ?></td>
                <td>
                    <?php echo esc_html( $order->client_name ); ?><br>
                    <a
                        href="mailto:<?php echo esc_attr( $order->client_email ); ?>"><?php echo esc_html( $order->client_email ); ?></a>
                </td>
                <td><?php echo esc_html( $order->service_type ); ?></td>
                <td><?php echo esc_html( $order->amount . ' ' . $order->currency ); ?></td>
                <td>
                    <?php
									$status_colors = array( 'pending' => '#f39c12', 'paid' => '#27ae60', 'cancelled' => '#c0392b' );
									$color = isset( $status_colors[ $order->status ] ) ? $status_colors[ $order->status ] : '#7f8c8d';
									?>
                    <span
                        style="color: white; background: <?php echo $color; ?>; padding: 3px 8px; border-radius: 3px; font-weight: bold;">
                        <?php echo ucfirst( esc_html( $order->status ) ); ?>
                    </span>
                </td>
                <td>
                    <button type="button" class="button button-small wbr-copy-btn"
                        data-link="<?php echo esc_url( $payment_url ); ?>" title="Copy Link">
                        <span class="dashicons dashicons-admin-links" style="line-height: 1.3;"></span> Copy
                    </button>
                    <a href="<?php echo esc_url( $payment_url ); ?>" target="_blank" class="button button-small"
                        title="Open Link">
                        <span class="dashicons dashicons-external" style="line-height: 1.3;"></span>
                    </a>
                    <span class="wbr-copy-msg"
                        style="display:none; color: #27ae60; font-size: 12px; margin-left: 5px;">Copied!</span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else : ?>
            <tr class="no-items">
                <td colspan="6">No orders found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Add Form
    document.getElementById('wbr-add-new-order-btn').addEventListener('click', function(e) {
        e.preventDefault();
        var form = document.getElementById('wbr-add-order-form');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    // Copy Link Functionality
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.wbr-copy-btn')) {
            e.preventDefault();
            var btn = e.target.closest('.wbr-copy-btn');
            var link = btn.getAttribute('data-link');
            var msg = btn.parentNode.querySelector('.wbr-copy-msg');

            // Robust Copy Function
            var copyToClipboard = function(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                } else {
                    // Fallback for non-secure contexts or older browsers
                    var textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-9999px";
                    textArea.style.top = "0";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    return new Promise((resolve, reject) => {
                        try {
                            var successful = document.execCommand('copy');
                            document.body.removeChild(textArea);
                            if (successful) resolve();
                            else reject(new Error('Copy command failed'));
                        } catch (err) {
                            document.body.removeChild(textArea);
                            reject(err);
                        }
                    });
                }
            };

            copyToClipboard(link).then(function() {
                msg.style.display = 'inline-block';
                msg.style.opacity = '1';
                setTimeout(function() {
                    msg.style.opacity = '0';
                    setTimeout(function() {
                        msg.style.display = 'none';
                    }, 300);
                }, 2000);
            }, function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy link. Please copy manually: ' + link);
            });
        }
    });

    // AJAX Order Creation
    document.getElementById('wbr-create-order-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        var btn = document.getElementById('wbr-submit-order');
        var spinner = document.getElementById('wbr-order-spinner');
        var msg = document.getElementById('wbr-form-message');

        btn.disabled = true;
        spinner.classList.add('is-active');
        msg.style.display = 'none';

        var formData = new FormData(form);
        formData.append('action', 'wbr_create_order_ajax');

        fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                spinner.classList.remove('is-active');

                if (data.success) {
                    // Reset form
                    form.reset();
                    document.getElementById('wbr-add-order-form').style.display = 'none';

                    // Add new row to table
                    var tbody = document.querySelector('#wbr-orders-table tbody');
                    var noItems = tbody.querySelector('.no-items');
                    if (noItems) noItems.remove();

                    var order = data.data.order;
                    var row = document.createElement('tr');
                    row.style.backgroundColor = '#e8f5e9'; // Highlight new row
                    row.innerHTML = `
								<td>${order.created_at}</td>
								<td>${order.client_name}<br><a href="mailto:${order.client_email}">${order.client_email}</a></td>
								<td>${order.service_type}</td>
								<td>${order.amount}</td>
								<td><span style="color: white; background: #f39c12; padding: 3px 8px; border-radius: 3px; font-weight: bold;">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
								<td>
									<button type="button" class="button button-small wbr-copy-btn" data-link="${order.payment_url}" title="Copy Link">
										<span class="dashicons dashicons-admin-links" style="line-height: 1.3;"></span> Copy
									</button>
									<a href="${order.payment_url}" target="_blank" class="button button-small" title="Open Link">
										<span class="dashicons dashicons-external" style="line-height: 1.3;"></span>
									</a>
									<span class="wbr-copy-msg" style="display:none; color: #27ae60; font-size: 12px; margin-left: 5px;">Copied!</span>
								</td>
							`;
                    tbody.insertBefore(row, tbody.firstChild);

                    setTimeout(function() {
                        row.style.transition = 'background-color 1s';
                        row.style.backgroundColor = 'transparent';
                    }, 2000);
                } else {
                    msg.textContent = data.data.message || 'Error creating order';
                    msg.style.color = '#c0392b';
                    msg.style.display = 'block';
                }
            })
            .catch(error => {
                btn.disabled = false;
                spinner.classList.remove('is-active');
                msg.textContent = 'Network error. Please try again.';
                msg.style.color = '#c0392b';
                msg.style.display = 'block';
            });
    });
});
</script>
<?php
	}

	public function handle_create_order() {
		if ( ! isset( $_POST['wbr_nonce'] ) || ! wp_verify_nonce( $_POST['wbr_nonce'], 'wbr_create_order_nonce' ) ) {
			wp_die( 'Security check failed' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';

		$client_name  = sanitize_text_field( $_POST['client_name'] );
		$client_email = sanitize_email( $_POST['client_email'] );
		$amount       = floatval( $_POST['amount'] );
		$currency     = sanitize_text_field( $_POST['currency'] );
		$service_type = sanitize_text_field( $_POST['service_type'] );
		$notes        = sanitize_textarea_field( $_POST['notes'] );

		$order_token = bin2hex( random_bytes( 16 ) );

		$wpdb->insert(
			$table_name,
			array(
				'created_at'   => current_time( 'mysql' ),
				'client_name'  => $client_name,
				'client_email' => $client_email,
				'amount'       => $amount,
				'currency'     => $currency,
				'service_type' => $service_type,
				'order_token'  => $order_token,
				'status'       => 'pending',
				'notes'        => $notes
			)
		);

		wp_redirect( admin_url( 'admin.php?page=wbr-payments' ) );
		exit;
	}

	public function ajax_create_order() {
		if ( ! isset( $_POST['wbr_nonce'] ) || ! wp_verify_nonce( $_POST['wbr_nonce'], 'wbr_create_order_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wbr_orders';

		$client_name  = sanitize_text_field( $_POST['client_name'] );
		$client_email = sanitize_email( $_POST['client_email'] );
		$amount       = floatval( $_POST['amount'] );
		$currency     = sanitize_text_field( $_POST['currency'] );
		$service_type = sanitize_text_field( $_POST['service_type'] );
		$notes        = sanitize_textarea_field( $_POST['notes'] );

		if ( empty( $client_name ) || empty( $client_email ) || empty( $amount ) || empty( $service_type ) ) {
			wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
		}

		$order_token = bin2hex( random_bytes( 16 ) );
		$created_at  = current_time( 'mysql' );

		$result = $wpdb->insert(
			$table_name,
			array(
				'created_at'   => $created_at,
				'client_name'  => $client_name,
				'client_email' => $client_email,
				'amount'       => $amount,
				'currency'     => $currency,
				'service_type' => $service_type,
				'order_token'  => $order_token,
				'status'       => 'pending',
				'notes'        => $notes
			)
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
		}

		$order_data = array(
			'created_at'   => $created_at,
			'client_name'  => $client_name,
			'client_email' => $client_email,
			'amount'       => $amount . ' ' . $currency,
			'currency'     => $currency,
			'service_type' => $service_type,
			'status'       => 'pending',
			'payment_url'  => home_url( 'pay/order/' . $order_token )
		);

		wp_send_json_success( array( 'order' => $order_data ) );
	}

	public function ajax_save_settings() {
		if ( ! isset( $_POST['wbr_settings_nonce'] ) || ! wp_verify_nonce( $_POST['wbr_settings_nonce'], 'wbr_save_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$active_tab = isset( $_POST['wbr_active_tab'] ) ? $_POST['wbr_active_tab'] : 'general';

		if ( $active_tab == 'general' ) {
			if ( isset( $_POST['wbr_payment_mode'] ) ) {
				update_option( 'wbr_payment_mode', sanitize_text_field( $_POST['wbr_payment_mode'] ) );
			}
			if ( isset( $_POST['wbr_stripe_publishable_key'] ) ) {
				update_option( 'wbr_stripe_publishable_key', sanitize_text_field( $_POST['wbr_stripe_publishable_key'] ) );
			}
			if ( isset( $_POST['wbr_stripe_secret_key'] ) ) {
				update_option( 'wbr_stripe_secret_key', sanitize_text_field( $_POST['wbr_stripe_secret_key'] ) );
			}
			if ( isset( $_POST['wbr_stripe_test_publishable_key'] ) ) {
				update_option( 'wbr_stripe_test_publishable_key', sanitize_text_field( $_POST['wbr_stripe_test_publishable_key'] ) );
			}
			if ( isset( $_POST['wbr_stripe_test_secret_key'] ) ) {
				update_option( 'wbr_stripe_test_secret_key', sanitize_text_field( $_POST['wbr_stripe_test_secret_key'] ) );
			}
		} elseif ( $active_tab == 'checkout' ) {
			if ( isset( $_POST['wbr_collect_billing'] ) ) {
				update_option( 'wbr_collect_billing', sanitize_text_field( $_POST['wbr_collect_billing'] ) );
			}
			// Checkbox handling: if not set, it's 0
			$collect_phone = isset( $_POST['wbr_collect_phone'] ) ? 1 : 0;
			update_option( 'wbr_collect_phone', $collect_phone );
		} elseif ( $active_tab == 'appearance' ) {
			if ( isset( $_POST['wbr_brand_color'] ) ) {
				update_option( 'wbr_brand_color', sanitize_hex_color( $_POST['wbr_brand_color'] ) );
			}
			if ( isset( $_POST['wbr_logo_url'] ) ) {
				update_option( 'wbr_logo_url', esc_url_raw( $_POST['wbr_logo_url'] ) );
			}
			if ( isset( $_POST['wbr_custom_title'] ) ) {
				update_option( 'wbr_custom_title', sanitize_text_field( $_POST['wbr_custom_title'] ) );
			}
			if ( isset( $_POST['wbr_custom_description'] ) ) {
				update_option( 'wbr_custom_description', sanitize_textarea_field( $_POST['wbr_custom_description'] ) );
			}
		}

		wp_send_json_success( array( 'message' => 'Settings saved.' ) );
	}
}