<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import/export review data via CSV.
 */
class Customer_Reviews_Import_Export {
	/**
	 * Capability required to use tools.
	 */
	const MENU_CAPABILITY = 'manage_options';
	const IMPORT_PAGE_SLUG = 'customer-reviews-import';
	const EXPORT_PAGE_SLUG = 'customer-reviews-export';

	/**
	 * Settings object.
	 *
	 * @var Customer_Reviews_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Customer_Reviews_Settings $settings settings accessor.
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
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_post_customer_reviews_export_csv', array( $this, 'handle_export' ) );
		add_action( 'admin_post_customer_reviews_import_csv', array( $this, 'handle_import' ) );
	}

	/**
	 * Add import/export submenu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		$import_label = Customer_Reviews_Settings::use_japanese_menu_labels() ? 'インポート' : __( 'Import', 'customer-reviews' );
		$export_label = Customer_Reviews_Settings::use_japanese_menu_labels() ? 'エクスポート' : __( 'Export', 'customer-reviews' );

		add_submenu_page(
			'edit.php?post_type=review',
			__( 'Import Reviews', 'customer-reviews' ),
			$import_label,
			self::MENU_CAPABILITY,
			self::IMPORT_PAGE_SLUG,
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			'edit.php?post_type=review',
			__( 'Export Reviews', 'customer-reviews' ),
			$export_label,
			self::MENU_CAPABILITY,
			self::EXPORT_PAGE_SLUG,
			array( $this, 'render_export_page' )
		);
	}

	/**
	 * Render export page.
	 *
	 * @return void
	 */
	public function render_export_page() {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Reviews', 'customer-reviews' ); ?></h1>
			<p><?php esc_html_e( 'Download all review data as CSV.', 'customer-reviews' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="customer_reviews_export_csv" />
				<?php wp_nonce_field( 'customer_reviews_export_csv', 'customer_reviews_export_nonce' ); ?>
				<?php submit_button( __( 'Export CSV', 'customer-reviews' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render import page.
	 *
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			return;
		}

		$csv_status = isset( $_GET['customer_reviews_csv_status'] ) ? sanitize_key( wp_unslash( $_GET['customer_reviews_csv_status'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Reviews', 'customer-reviews' ); ?></h1>
			<?php if ( '' !== $csv_status ) : ?>
				<?php $this->render_import_notice( $csv_status ); ?>
			<?php endif; ?>
			<p><?php esc_html_e( 'Upload CSV to create or update review data.', 'customer-reviews' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="customer_reviews_import_csv" />
				<?php wp_nonce_field( 'customer_reviews_import_csv', 'customer_reviews_import_nonce' ); ?>
				<input type="file" name="customer_reviews_csv_file" accept=".csv,text/csv" required />
				<?php submit_button( __( 'Import CSV', 'customer-reviews' ), 'primary', 'submit', false ); ?>
			</form>
			<p class="description">
				<?php esc_html_e( 'CSV must use UTF-8 encoding and include header row. Existing review_id values are updated; missing review_id values are created as new reviews.', 'customer-reviews' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle CSV export.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export reviews.', 'customer-reviews' ) );
		}

		check_admin_referer( 'customer_reviews_export_csv', 'customer_reviews_export_nonce' );

		$filename = 'customer-reviews-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to open output stream.', 'customer-reviews' ) );
		}

		fputcsv(
			$output,
			array(
				'review_id',
				'post_status',
				'reviewer_name',
				'reviewer_attribute',
				'rating',
				'review_title',
				'review_body',
				'confirm_real_user',
				'confirm_permission',
				'confirm_no_abuse',
				'profile_tag_slugs',
				'linked_post_ids',
			)
		);

		$review_posts = get_posts(
			array(
				'post_type'      => 'review',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $review_posts as $review_post ) {
			$linked_post_ids = get_post_meta( $review_post->ID, 'linked_post_ids', true );
			$linked_post_ids = is_array( $linked_post_ids ) ? array_map( 'absint', $linked_post_ids ) : array();

			$profile_terms = wp_get_object_terms(
				$review_post->ID,
				'review_profile_tag',
				array(
					'fields' => 'slugs',
				)
			);
			$profile_terms = is_wp_error( $profile_terms ) || ! is_array( $profile_terms ) ? array() : $profile_terms;

			fputcsv(
				$output,
				array(
					$review_post->ID,
					$review_post->post_status,
					(string) get_post_meta( $review_post->ID, 'reviewer_name', true ),
					(string) get_post_meta( $review_post->ID, 'reviewer_attribute', true ),
					(string) get_post_meta( $review_post->ID, 'rating', true ),
					(string) get_post_meta( $review_post->ID, 'review_title', true ),
					(string) get_post_meta( $review_post->ID, 'review_body', true ),
					(int) (bool) get_post_meta( $review_post->ID, 'confirm_real_user', true ),
					(int) (bool) get_post_meta( $review_post->ID, 'confirm_permission', true ),
					(int) (bool) get_post_meta( $review_post->ID, 'confirm_no_abuse', true ),
					implode( '|', array_map( 'sanitize_title', $profile_terms ) ),
					implode( '|', $linked_post_ids ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle CSV import.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to import reviews.', 'customer-reviews' ) );
		}

		check_admin_referer( 'customer_reviews_import_csv', 'customer_reviews_import_nonce' );

		if ( ! isset( $_FILES['customer_reviews_csv_file'] ) || ! is_array( $_FILES['customer_reviews_csv_file'] ) ) {
			$this->redirect_with_notice( 'import_failed', array( 'reason' => 'missing_file' ) );
		}

		$file = wp_unslash( $_FILES['customer_reviews_csv_file'] );
		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$error_code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error_code || '' === $tmp_name ) {
			$this->redirect_with_notice( 'import_failed', array( 'reason' => 'upload_error' ) );
		}

		$handle = fopen( $tmp_name, 'r' );
		if ( false === $handle ) {
			$this->redirect_with_notice( 'import_failed', array( 'reason' => 'open_failed' ) );
		}

		$header_row = fgetcsv( $handle );
		if ( ! is_array( $header_row ) || empty( $header_row ) ) {
			fclose( $handle );
			$this->redirect_with_notice( 'import_failed', array( 'reason' => 'invalid_header' ) );
		}

		$header = array_map( array( $this, 'normalize_header_key' ), $header_row );
		$required_columns = array( 'reviewer_name', 'review_title', 'review_body' );
		foreach ( $required_columns as $required_column ) {
			if ( ! in_array( $required_column, $header, true ) ) {
				fclose( $handle );
				$this->redirect_with_notice( 'import_failed', array( 'reason' => 'missing_required_columns' ) );
			}
		}

		$created_count = 0;
		$updated_count = 0;
		$skipped_count = 0;

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			if ( empty( array_filter( $row, 'strlen' ) ) ) {
				continue;
			}

			$record = $this->combine_header_and_row( $header, $row );
			if ( empty( $record ) ) {
				$skipped_count++;
				continue;
			}

			$result = $this->upsert_review_from_record( $record );
			if ( 'created' === $result ) {
				$created_count++;
			} elseif ( 'updated' === $result ) {
				$updated_count++;
			} else {
				$skipped_count++;
			}
		}

		fclose( $handle );

		$this->redirect_with_notice(
			'import_success',
			array(
				'created' => $created_count,
				'updated' => $updated_count,
				'skipped' => $skipped_count,
			)
		);
	}

	/**
	 * Normalize header key.
	 *
	 * @param string $header raw header.
	 * @return string
	 */
	private function normalize_header_key( $header ) {
		$header = (string) $header;
		$header = preg_replace( '/^\xEF\xBB\xBF/', '', $header );
		return sanitize_key( $header );
	}

	/**
	 * Combine header and row into associative record.
	 *
	 * @param string[] $header csv header.
	 * @param string[] $row csv row.
	 * @return array<string,string>
	 */
	private function combine_header_and_row( $header, $row ) {
		$record = array();
		$max = min( count( $header ), count( $row ) );

		for ( $i = 0; $i < $max; $i++ ) {
			$key = isset( $header[ $i ] ) ? (string) $header[ $i ] : '';
			if ( '' === $key ) {
				continue;
			}
			$record[ $key ] = isset( $row[ $i ] ) ? (string) $row[ $i ] : '';
		}

		return $record;
	}

	/**
	 * Create or update review from one CSV record.
	 *
	 * @param array<string,string> $record row data.
	 * @return string created|updated|skipped
	 */
	private function upsert_review_from_record( $record ) {
		$reviewer_name = isset( $record['reviewer_name'] ) ? sanitize_text_field( $record['reviewer_name'] ) : '';
		$review_title = isset( $record['review_title'] ) ? sanitize_textarea_field( $record['review_title'] ) : '';
		$review_body = isset( $record['review_body'] ) ? sanitize_textarea_field( $record['review_body'] ) : '';

		if ( '' === $reviewer_name && '' === $review_title && '' === $review_body ) {
			return 'skipped';
		}

		$reviewer_attribute = isset( $record['reviewer_attribute'] ) ? sanitize_text_field( $record['reviewer_attribute'] ) : '';
		$rating = isset( $record['rating'] ) ? $this->sanitize_rating( $record['rating'] ) : '0.0';
		$post_status = isset( $record['post_status'] ) ? sanitize_key( $record['post_status'] ) : 'publish';
		$allowed_statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
		if ( ! in_array( $post_status, $allowed_statuses, true ) ) {
			$post_status = 'publish';
		}

		$internal_title = trim( $reviewer_name . ' ' . $review_title );
		if ( '' === $internal_title ) {
			$internal_title = 'Imported Review';
		}

		$review_id = isset( $record['review_id'] ) ? absint( $record['review_id'] ) : 0;
		$existing_review = $review_id > 0 ? get_post( $review_id ) : null;

		$post_data = array(
			'post_type'   => 'review',
			'post_status' => $post_status,
			'post_title'  => wp_strip_all_tags( substr( $internal_title, 0, 190 ) ),
		);

		if ( $existing_review && 'review' === $existing_review->post_type ) {
			$post_data['ID'] = $review_id;
			$updated_id = wp_update_post( $post_data, true );
			if ( is_wp_error( $updated_id ) ) {
				return 'skipped';
			}
			$target_review_id = (int) $updated_id;
			$mode = 'updated';
		} else {
			$inserted_id = wp_insert_post( $post_data, true );
			if ( is_wp_error( $inserted_id ) ) {
				return 'skipped';
			}
			$target_review_id = (int) $inserted_id;
			$mode = 'created';
		}

		update_post_meta( $target_review_id, 'reviewer_name', $reviewer_name );
		update_post_meta( $target_review_id, 'reviewer_attribute', $reviewer_attribute );
		update_post_meta( $target_review_id, 'rating', $rating );
		update_post_meta( $target_review_id, 'review_title', $review_title );
		update_post_meta( $target_review_id, 'review_body', $review_body );
		update_post_meta( $target_review_id, 'confirm_real_user', $this->to_binary_flag( isset( $record['confirm_real_user'] ) ? $record['confirm_real_user'] : '' ) );
		update_post_meta( $target_review_id, 'confirm_permission', $this->to_binary_flag( isset( $record['confirm_permission'] ) ? $record['confirm_permission'] : '' ) );
		update_post_meta( $target_review_id, 'confirm_no_abuse', $this->to_binary_flag( isset( $record['confirm_no_abuse'] ) ? $record['confirm_no_abuse'] : '' ) );

		$linked_post_ids = isset( $record['linked_post_ids'] ) ? $this->parse_linked_post_ids( $record['linked_post_ids'] ) : array();
		update_post_meta( $target_review_id, 'linked_post_ids', $linked_post_ids );

		$profile_tag_ids = isset( $record['profile_tag_slugs'] ) ? $this->resolve_profile_tag_ids( $record['profile_tag_slugs'] ) : array();
		wp_set_object_terms( $target_review_id, $profile_tag_ids, 'review_profile_tag', false );

		return $mode;
	}

	/**
	 * Parse linked post ids from pipe-separated text.
	 *
	 * @param string $raw linked post IDs.
	 * @return int[]
	 */
	private function parse_linked_post_ids( $raw ) {
		$parts = array_filter(
			array_map( 'trim', explode( '|', (string) $raw ) ),
			'strlen'
		);

		if ( empty( $parts ) ) {
			return array();
		}

		$allowed_post_types = $this->settings->get_linkable_post_types();
		$ids = array();

		foreach ( $parts as $part ) {
			$post_id = absint( $part );
			if ( $post_id <= 0 ) {
				continue;
			}

			$post_type = get_post_type( $post_id );
			if ( $post_type && in_array( $post_type, $allowed_post_types, true ) ) {
				$ids[] = $post_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve profile tags from pipe-separated slugs.
	 *
	 * @param string $raw raw tag slugs.
	 * @return int[]
	 */
	private function resolve_profile_tag_ids( $raw ) {
		$slugs = array_filter(
			array_map(
				'sanitize_title',
				array_map( 'trim', explode( '|', (string) $raw ) )
			),
			'strlen'
		);

		if ( empty( $slugs ) || ! taxonomy_exists( 'review_profile_tag' ) ) {
			return array();
		}

		$term_ids = array();
		foreach ( $slugs as $slug ) {
			$term = term_exists( $slug, 'review_profile_tag' );
			if ( ! $term ) {
				$term = wp_insert_term(
					$slug,
					'review_profile_tag',
					array(
						'slug' => $slug,
					)
				);
			}

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$term_id = 0;
			if ( is_array( $term ) && isset( $term['term_id'] ) ) {
				$term_id = absint( $term['term_id'] );
			} elseif ( is_numeric( $term ) ) {
				$term_id = absint( $term );
			}

			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}

		return array_values( array_unique( $term_ids ) );
	}

	/**
	 * Normalize flag value to 0 or 1.
	 *
	 * @param string $value raw value.
	 * @return int
	 */
	private function to_binary_flag( $value ) {
		$normalized = strtolower( trim( (string) $value ) );
		return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
	}

	/**
	 * Sanitize rating to 0.0 - 5.0 with 0.5 increment.
	 *
	 * @param mixed $rating_raw raw rating value.
	 * @return string
	 */
	private function sanitize_rating( $rating_raw ) {
		$value = (float) $rating_raw;
		$value = max( 0, min( 5, $value ) );
		$value = round( $value * 2 ) / 2;

		return number_format( $value, 1, '.', '' );
	}

	/**
	 * Render import result notice.
	 *
	 * @param string $status notice status.
	 * @return void
	 */
	private function render_import_notice( $status ) {
		if ( 'import_success' === $status ) {
			$created = isset( $_GET['created'] ) ? absint( wp_unslash( $_GET['created'] ) ) : 0;
			$updated = isset( $_GET['updated'] ) ? absint( wp_unslash( $_GET['updated'] ) ) : 0;
			$skipped = isset( $_GET['skipped'] ) ? absint( wp_unslash( $_GET['skipped'] ) ) : 0;

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: created count, 2: updated count, 3: skipped count */
						__( 'Import completed. Created: %1$d / Updated: %2$d / Skipped: %3$d', 'customer-reviews' ),
						$created,
						$updated,
						$skipped
					)
				)
			);
			return;
		}

		if ( 'import_failed' === $status ) {
			$reason = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : 'unknown';
			$messages = array(
				'missing_file'             => __( 'Import failed: CSV file is missing.', 'customer-reviews' ),
				'upload_error'             => __( 'Import failed: CSV upload error.', 'customer-reviews' ),
				'open_failed'              => __( 'Import failed: Cannot open uploaded CSV.', 'customer-reviews' ),
				'invalid_header'           => __( 'Import failed: Invalid CSV header.', 'customer-reviews' ),
				'missing_required_columns' => __( 'Import failed: required columns (reviewer_name, review_title, review_body) are missing.', 'customer-reviews' ),
				'unknown'                  => __( 'Import failed due to an unknown error.', 'customer-reviews' ),
			);
			$message = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['unknown'];

			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
		}
	}

	/**
	 * Redirect to import page with result notice.
	 *
	 * @param string               $status status key.
	 * @param array<string,string|int> $args extra query args.
	 * @return void
	 */
	private function redirect_with_notice( $status, $args = array() ) {
		$query_args = array_merge(
			array(
				'page'                       => self::IMPORT_PAGE_SLUG,
				'post_type'                  => 'review',
				'customer_reviews_csv_status' => sanitize_key( $status ),
			),
			$args
		);

		$url = add_query_arg( $query_args, admin_url( 'edit.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
