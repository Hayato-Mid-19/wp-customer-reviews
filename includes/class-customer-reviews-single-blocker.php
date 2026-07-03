<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forces single review pages to 404.
 */
class Customer_Reviews_Single_Blocker {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_block_single_review' ) );
	}

	/**
	 * Make single review URL return 404.
	 *
	 * @return void
	 */
	public function maybe_block_single_review() {
		if ( ! is_singular( 'review' ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		$template = get_query_template( '404' );
		if ( is_string( $template ) && '' !== $template && file_exists( $template ) ) {
			include $template;
		} else {
			wp_die(
				esc_html__( 'Not Found', 'customer-reviews' ),
				esc_html__( '404 Not Found', 'customer-reviews' ),
				array(
					'response' => 404,
				)
			);
		}
		exit;
	}
}
