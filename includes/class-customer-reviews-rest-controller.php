<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoint provider.
 */
class Customer_Reviews_REST_Controller {
	/**
	 * Settings object.
	 *
	 * @var Customer_Reviews_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Customer_Reviews_Settings $settings settings service.
	 */
	public function __construct( Customer_Reviews_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'customer-reviews/v1',
			'/review',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reviews' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			)
		);

		register_rest_route(
			'customer-reviews/v1',
			'/review/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_single_review' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Returns collection parameter schema.
	 *
	 * @return array<string,mixed>
	 */
	private function get_collection_params() {
		return array(
			'post_ids' => array(
				'description' => __( 'Comma-separated target post IDs', 'customer-reviews' ),
				'required'    => false,
			),
			'sort'     => array(
				'type'              => 'string',
				'default'           => 'newest',
				'sanitize_callback' => function ( $value ) {
					$value = sanitize_key( (string) $value );
					return in_array( $value, array( 'newest', 'rating' ), true ) ? $value : 'newest';
				},
			),
			'order'    => array(
				'type'              => 'string',
				'default'           => 'desc',
				'sanitize_callback' => function ( $value ) {
					$value = strtolower( sanitize_text_field( (string) $value ) );
					return in_array( $value, array( 'asc', 'desc' ), true ) ? $value : 'desc';
				},
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'required'          => false,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * GET /review collection.
	 *
	 * @param WP_REST_Request $request request object.
	 * @return WP_REST_Response
	 */
	public function get_reviews( WP_REST_Request $request ) {
		$options = $this->settings->get_options();

		$default_per_page = isset( $options['default_per_page'] ) ? max( 1, absint( $options['default_per_page'] ) ) : 10;
		$max_per_page = isset( $options['max_per_page'] ) ? max( 1, absint( $options['max_per_page'] ) ) : 50;

		$page = max( 1, (int) $request->get_param( 'page' ) );
		$per_page_param = absint( (int) $request->get_param( 'per_page' ) );
		$per_page = $per_page_param > 0 ? $per_page_param : $default_per_page;
		$per_page = min( $per_page, $max_per_page );

		$sort = (string) $request->get_param( 'sort' );
		$order = strtoupper( (string) $request->get_param( 'order' ) );

		$query_args = array(
			'post_type'      => 'review',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		if ( 'rating' === $sort ) {
			$query_args['meta_key'] = 'rating';
			$query_args['orderby'] = array(
				'meta_value_num' => $order,
				'date'           => 'DESC',
			);
		} else {
			$query_args['orderby'] = 'date';
			$query_args['order'] = $order;
		}

		$reviews = get_posts( $query_args );
		if ( ! is_array( $reviews ) ) {
			$reviews = array();
		}

		$target_post_ids = $this->parse_post_ids( $request->get_param( 'post_ids' ) );
		if ( ! empty( $target_post_ids ) ) {
			$reviews = array_values(
				array_filter(
					$reviews,
					function ( $review ) use ( $target_post_ids ) {
						$linked_post_ids = get_post_meta( $review->ID, 'linked_post_ids', true );
						$linked_post_ids = is_array( $linked_post_ids ) ? array_map( 'absint', $linked_post_ids ) : array();
						return ! empty( array_intersect( $target_post_ids, $linked_post_ids ) );
					}
				)
			);
		}

		$total = count( $reviews );
		$total_pages = (int) ceil( $total / $per_page );
		$offset = ( $page - 1 ) * $per_page;

		$reviews_page = array_slice( $reviews, $offset, $per_page );
		$items = array_map( array( $this, 'format_review' ), $reviews_page );

		$response = new WP_REST_Response(
			array(
				'items'      => $items,
				'pagination' => array(
					'total'       => $total,
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => $total_pages,
				),
			)
		);
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * GET /review/{id}.
	 *
	 * @param WP_REST_Request $request request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_review( WP_REST_Request $request ) {
		$id = absint( $request->get_param( 'id' ) );
		$post = get_post( $id );

		if ( ! $post || 'review' !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'customer_reviews_not_found', __( 'Review not found.', 'customer-reviews' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->format_review( $post ) );
	}

	/**
	 * Convert review post to API response item.
	 *
	 * @param WP_Post $post review post.
	 * @return array<string,mixed>
	 */
	private function format_review( WP_Post $post ) {
		$linked_post_ids = get_post_meta( $post->ID, 'linked_post_ids', true );
		$linked_post_ids = is_array( $linked_post_ids ) ? array_values( array_map( 'absint', $linked_post_ids ) ) : array();

		$profile_terms = get_the_terms( $post, 'review_profile_tag' );
		if ( ! is_array( $profile_terms ) ) {
			$profile_terms = array();
		}

		return array(
			'id'                => $post->ID,
			'reviewer_name'     => (string) get_post_meta( $post->ID, 'reviewer_name', true ),
			'reviewer_attribute'=> (string) get_post_meta( $post->ID, 'reviewer_attribute', true ),
			'rating'            => (string) get_post_meta( $post->ID, 'rating', true ),
			'title'             => (string) get_post_meta( $post->ID, 'review_title', true ),
			'body'              => (string) get_post_meta( $post->ID, 'review_body', true ),
			'profile_tags'      => array_values(
				array_map(
					function ( WP_Term $term ) {
						return array(
							'id'   => (int) $term->term_id,
							'name' => (string) $term->name,
							'slug' => (string) $term->slug,
						);
					},
					$profile_terms
				)
			),
			'linked_post_ids'   => $linked_post_ids,
			'date_published'    => mysql_to_rfc3339( $post->post_date_gmt ),
			'date_updated'      => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * Parse comma-separated post_ids query string.
	 *
	 * @param mixed $raw raw param.
	 * @return int[]
	 */
	private function parse_post_ids( $raw ) {
		$values = array();
		if ( is_array( $raw ) ) {
			$values = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$values = explode( ',', $raw );
		}

		if ( empty( $values ) ) {
			return array();
		}

		$ids = array_map(
			'absint',
			array_map(
				'trim',
				$values
			)
		);

		return array_values(
			array_filter(
				$ids,
				function ( $id ) {
					return $id > 0;
				}
			)
		);
	}
}
