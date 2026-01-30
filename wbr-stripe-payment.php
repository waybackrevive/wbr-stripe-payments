<?php
/**
 * Plugin Name: WBR Stripe Payment
 * Plugin URI: https://waybackrevive.com
 * Description: Custom Stripe payment portal for WBR.
 * Version: 1.0.0
 * Author: Wayback Revive
 * Author URI: https://waybackrevive.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WBR_STRIPE_VERSION', '1.0.0' );
define( 'WBR_STRIPE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WBR_STRIPE_URL', plugin_dir_url( __FILE__ ) );

// Autoload Composer dependencies
if ( file_exists( WBR_STRIPE_PATH . 'vendor/autoload.php' ) ) {
	require_once WBR_STRIPE_PATH . 'vendor/autoload.php';
}

// Include Classes
require_once WBR_STRIPE_PATH . 'includes/class-wbr-stripe-api.php';
require_once WBR_STRIPE_PATH . 'includes/class-wbr-stripe-public.php';

if ( is_admin() ) {
	require_once WBR_STRIPE_PATH . 'includes/class-wbr-stripe-admin.php';
}

// Activation Hook
register_activation_hook( __FILE__, 'wbr_stripe_activate' );

function wbr_stripe_activate() {
	global $wpdb;

	// 1. Create Database Table
	$table_name = $wpdb->prefix . 'wbr_orders';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		client_name varchar(255) NOT NULL,
		client_email varchar(255) NOT NULL,
		amount decimal(10,2) NOT NULL,
		currency varchar(3) NOT NULL,
		service_type varchar(255) NOT NULL,
		order_token varchar(64) NOT NULL,
		stripe_session_id varchar(255) DEFAULT '',
		status varchar(20) DEFAULT 'pending',
		notes text,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// 2. Create Payment Page
	$page_id = get_option( 'wbr_payment_page_id' );
	if ( ! $page_id || ! get_post( $page_id ) ) {
		$page_id = wp_insert_post( array(
			'post_title'     => 'Secure Payment',
			'post_content'   => '[wbr_payment_checkout]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		update_option( 'wbr_payment_page_id', $page_id );
	}

	// Flush rewrite rules
	wbr_stripe_rewrite_rules();
	flush_rewrite_rules();
}

// Deactivation Hook
register_deactivation_hook( __FILE__, 'wbr_stripe_deactivate' );

function wbr_stripe_deactivate() {
	flush_rewrite_rules();
}

// Initialize Plugin
function wbr_stripe_init() {
	new WBR_Stripe_Public();
	if ( is_admin() ) {
		new WBR_Stripe_Admin();
	}
}
add_action( 'plugins_loaded', 'wbr_stripe_init' );

// Rewrite Rules
function wbr_stripe_rewrite_rules() {
	$page_id = get_option( 'wbr_payment_page_id' );
	if ( $page_id ) {
		add_rewrite_rule( '^pay/order/([^/]+)/?', 'index.php?page_id=' . $page_id . '&order_token=$matches[1]', 'top' );
	}
}
add_action( 'init', 'wbr_stripe_rewrite_rules' );

// Query Vars
function wbr_stripe_query_vars( $vars ) {
	$vars[] = 'order_token';
	return $vars;
}
add_filter( 'query_vars', 'wbr_stripe_query_vars' );
