<?php
/**
 * Plugin Name: Customer Reviews
 * Description: Manage customer reviews separately from posts and expose them via REST API.
 * Version: 0.1.0
 * Author: Customer Reviews Team
 * Text Domain: customer-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CUSTOMER_REVIEWS_VERSION', '0.1.0' );
define( 'CUSTOMER_REVIEWS_PLUGIN_FILE', __FILE__ );
define( 'CUSTOMER_REVIEWS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUSTOMER_REVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-plugin.php';

Customer_Reviews_Plugin::init();
