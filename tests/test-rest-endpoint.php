<?php
/**
 * Minimal REST endpoint test skeleton for customer reviews.
 *
 * @package CustomerReviews
 */

class Customer_Reviews_REST_Endpoint_Test extends WP_UnitTestCase {
	/**
	 * Ensure endpoint is registered.
	 *
	 * @return void
	 */
	public function test_review_endpoint_is_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/customer-reviews/v1/review', $routes );
	}
}
