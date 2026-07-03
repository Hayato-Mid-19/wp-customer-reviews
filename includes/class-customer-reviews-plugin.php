<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-settings.php';
require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-review-post-type.php';
require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-review-meta-box.php';
require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-rest-controller.php';
require_once CUSTOMER_REVIEWS_PLUGIN_PATH . 'includes/class-customer-reviews-single-blocker.php';

/**
 * Main plugin orchestrator.
 */
class Customer_Reviews_Plugin {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		register_activation_hook( CUSTOMER_REVIEWS_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( CUSTOMER_REVIEWS_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		$settings = new Customer_Reviews_Settings();
		$post_type = new Customer_Reviews_Review_Post_Type();
		$meta_box = new Customer_Reviews_Review_Meta_Box( $settings );
		$rest = new Customer_Reviews_REST_Controller( $settings );
		$single_blocker = new Customer_Reviews_Single_Blocker();

		$settings->register();
		$post_type->register();
		$meta_box->register();
		$rest->register();
		$single_blocker->register();
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		$post_type = new Customer_Reviews_Review_Post_Type();
		$post_type->register_post_type();
		$post_type->register_taxonomy();

		Customer_Reviews_Review_Post_Type::grant_caps_to_roles();
		Customer_Reviews_Review_Post_Type::seed_default_profile_tags();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
