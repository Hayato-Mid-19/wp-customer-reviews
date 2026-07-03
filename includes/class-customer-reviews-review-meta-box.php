<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review edit form and save handlers.
 */
class Customer_Reviews_Review_Meta_Box {
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
		add_action( 'add_meta_boxes_review', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_review', array( $this, 'save_review_meta' ), 10, 2 );
		add_action( 'admin_head-post.php', array( $this, 'print_admin_styles' ) );
		add_action( 'admin_head-post-new.php', array( $this, 'print_admin_styles' ) );
	}

	/**
	 * Add meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'customer_reviews_content',
			__( '口コミ内容', 'customer-reviews' ),
			array( $this, 'render_content_meta_box' ),
			'review',
			'normal',
			'high'
		);

		add_meta_box(
			'customer_reviews_linked_posts',
			__( '表示する投稿', 'customer-reviews' ),
			array( $this, 'render_linked_posts_meta_box' ),
			'review',
			'normal',
			'default'
		);

		add_meta_box(
			'customer_reviews_compliance',
			__( '実在性チェック', 'customer-reviews' ),
			array( $this, 'render_compliance_meta_box' ),
			'review',
			'side',
			'default'
		);
	}

	/**
	 * Render edit form.
	 *
	 * @param WP_Post $post review post.
	 * @return void
	 */
	public function render_content_meta_box( $post ) {
		wp_nonce_field( 'customer_reviews_save_meta', 'customer_reviews_meta_nonce' );

		$reviewer_name      = (string) get_post_meta( $post->ID, 'reviewer_name', true );
		$reviewer_attribute = (string) get_post_meta( $post->ID, 'reviewer_attribute', true );
		$rating             = (string) get_post_meta( $post->ID, 'rating', true );
		$review_title       = (string) get_post_meta( $post->ID, 'review_title', true );
		$review_body        = (string) get_post_meta( $post->ID, 'review_body', true );
		$selected_profile_tag_ids = wp_get_object_terms(
			$post->ID,
			'review_profile_tag',
			array(
				'fields' => 'ids',
			)
		);
		if ( is_wp_error( $selected_profile_tag_ids ) || ! is_array( $selected_profile_tag_ids ) ) {
			$selected_profile_tag_ids = array();
		} else {
			$selected_profile_tag_ids = array_map( 'absint', $selected_profile_tag_ids );
		}

		$profile_tag_terms = $this->get_profile_tag_terms();
		$rating_options = array();
		for ( $i = 0; $i <= 10; $i++ ) {
			$rating_options[] = number_format( $i * 0.5, 1, '.', '' );
		}
		?>
		<div class="customer-reviews-grid">
			<div class="customer-reviews-field">
				<label for="customer_reviews_reviewer_name"><?php esc_html_e( 'レビュアー名', 'customer-reviews' ); ?></label>
				<input type="text" id="customer_reviews_reviewer_name" name="customer_reviews_reviewer_name" value="<?php echo esc_attr( $reviewer_name ); ?>" />
			</div>

			<div class="customer-reviews-field">
				<label for="customer_reviews_reviewer_attribute"><?php esc_html_e( '属性（年代・職種など）', 'customer-reviews' ); ?></label>
				<input type="text" id="customer_reviews_reviewer_attribute" name="customer_reviews_reviewer_attribute" value="<?php echo esc_attr( $reviewer_attribute ); ?>" />
			</div>
		</div>

		<div class="customer-reviews-grid">
			<div class="customer-reviews-field">
				<label for="customer_reviews_rating"><?php esc_html_e( '評価（★ 0.5〜5.0）', 'customer-reviews' ); ?></label>
				<select id="customer_reviews_rating" name="customer_reviews_rating">
					<?php foreach ( $rating_options as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $rating, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="customer-reviews-field">
				<label><?php esc_html_e( 'プロフィールタグ', 'customer-reviews' ); ?></label>
				<div class="customer-reviews-tag-list">
					<?php foreach ( $profile_tag_terms as $term ) : ?>
						<label class="customer-reviews-tag-item">
							<input
								type="checkbox"
								name="customer_reviews_profile_tag_ids[]"
								value="<?php echo esc_attr( (string) $term->term_id ); ?>"
								<?php checked( in_array( (int) $term->term_id, $selected_profile_tag_ids, true ) ); ?>
							/>
							<?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description">
					<?php esc_html_e( '※ 新規タグの追加は投稿画面ではできません。', 'customer-reviews' ); ?><br>
					<?php esc_html_e( '&nbsp;&nbsp;&nbsp;必要なタグは Review Profile Tags 管理画面で追加してください。', 'customer-reviews' ); ?>
				</p>
			</div>
		</div>

		<div class="customer-reviews-field">
			<label for="customer_reviews_review_title"><?php esc_html_e( 'タイトル', 'customer-reviews' ); ?></label>
			<textarea id="customer_reviews_review_title" name="customer_reviews_review_title" rows="3"><?php echo esc_textarea( $review_title ); ?></textarea>
		</div>

		<div class="customer-reviews-field">
			<label for="customer_reviews_review_body"><?php esc_html_e( '本文', 'customer-reviews' ); ?></label>
			<textarea id="customer_reviews_review_body" name="customer_reviews_review_body" rows="8"><?php echo esc_textarea( $review_body ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render linked posts box.
	 *
	 * @param WP_Post $post review post.
	 * @return void
	 */
	public function render_linked_posts_meta_box( $post ) {
		$linked_post_ids = get_post_meta( $post->ID, 'linked_post_ids', true );
		$linked_post_ids = is_array( $linked_post_ids ) ? array_map( 'absint', $linked_post_ids ) : array();
		?>
		<p class="description">
			<?php esc_html_e( 'この口コミを表示する投稿を選択します（複数可）。対象投稿タイプは Settings > Customer Reviews で設定できます。', 'customer-reviews' ); ?>
		</p>
		<div class="customer-reviews-post-list">
			<?php foreach ( $this->get_linkable_posts() as $post_item ) : ?>
				<label class="customer-reviews-post-item">
					<input
						type="checkbox"
						name="customer_reviews_linked_post_ids[]"
						value="<?php echo esc_attr( (string) $post_item->ID ); ?>"
						<?php checked( in_array( (int) $post_item->ID, $linked_post_ids, true ) ); ?>
					/>
					<span class="customer-reviews-post-title"><?php echo esc_html( $post_item->post_title ?: __( '(no title)', 'customer-reviews' ) ); ?></span>
					<span class="customer-reviews-post-slug"><?php echo esc_html( '/' . trim( (string) get_page_uri( $post_item->ID ), '/' ) . '/' ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render side compliance checklist.
	 *
	 * @param WP_Post $post review post.
	 * @return void
	 */
	public function render_compliance_meta_box( $post ) {
		$confirm_real_user = (bool) get_post_meta( $post->ID, 'confirm_real_user', true );
		$confirm_permission = (bool) get_post_meta( $post->ID, 'confirm_permission', true );
		$confirm_no_abuse = (bool) get_post_meta( $post->ID, 'confirm_no_abuse', true );
		?>
		<label style="display:block;margin-bottom:8px;">
			<input type="checkbox" name="customer_reviews_compliance[real_user]" value="1" <?php checked( $confirm_real_user ); ?> />
			<?php esc_html_e( '実在の利用者の声であることを確認', 'customer-reviews' ); ?>
		</label>
		<label style="display:block;margin-bottom:8px;">
			<input type="checkbox" name="customer_reviews_compliance[permission]" value="1" <?php checked( $confirm_permission ); ?> />
			<?php esc_html_e( '掲載許諾を取得済み', 'customer-reviews' ); ?>
		</label>
		<label style="display:block;">
			<input type="checkbox" name="customer_reviews_compliance[no_abuse]" value="1" <?php checked( $confirm_no_abuse ); ?> />
			<?php esc_html_e( '本文に暴言・誹謗表現がない', 'customer-reviews' ); ?>
		</label>
		<?php
	}

	/**
	 * Save meta fields.
	 *
	 * @param int     $post_id post ID.
	 * @param WP_Post $post post object.
	 * @return void
	 */
	public function save_review_meta( $post_id, $post ) {
		if ( ! isset( $_POST['customer_reviews_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['customer_reviews_meta_nonce'] ) ), 'customer_reviews_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( 'review' !== $post->post_type ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$reviewer_name      = isset( $_POST['customer_reviews_reviewer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_reviews_reviewer_name'] ) ) : '';
		$reviewer_attribute = isset( $_POST['customer_reviews_reviewer_attribute'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_reviews_reviewer_attribute'] ) ) : '';
		$review_title       = isset( $_POST['customer_reviews_review_title'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customer_reviews_review_title'] ) ) : '';
		$review_body        = isset( $_POST['customer_reviews_review_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['customer_reviews_review_body'] ) ) : '';
		$rating_raw         = isset( $_POST['customer_reviews_rating'] ) ? wp_unslash( $_POST['customer_reviews_rating'] ) : '0.0';
		$compliance         = isset( $_POST['customer_reviews_compliance'] ) && is_array( $_POST['customer_reviews_compliance'] )
			? wp_unslash( $_POST['customer_reviews_compliance'] )
			: array();

		$linked_post_ids = array();
		if ( isset( $_POST['customer_reviews_linked_post_ids'] ) && is_array( $_POST['customer_reviews_linked_post_ids'] ) ) {
			$allowed_post_types = $this->settings->get_linkable_post_types();
			$linked_post_ids = array_values(
				array_filter(
					array_map( 'absint', wp_unslash( $_POST['customer_reviews_linked_post_ids'] ) ),
					function ( $id ) use ( $allowed_post_types ) {
						if ( $id <= 0 ) {
							return false;
						}
						$post_type = get_post_type( $id );
						return $post_type && in_array( $post_type, $allowed_post_types, true );
					}
				)
			);
		}

		$profile_tag_ids = array();
		if ( isset( $_POST['customer_reviews_profile_tag_ids'] ) && is_array( $_POST['customer_reviews_profile_tag_ids'] ) ) {
			$allowed_ids = $this->get_profile_tag_ids();
			$profile_tag_ids = array_values(
				array_filter(
					array_map( 'absint', wp_unslash( $_POST['customer_reviews_profile_tag_ids'] ) ),
					function ( $term_id ) use ( $allowed_ids ) {
						return in_array( $term_id, $allowed_ids, true );
					}
				)
			);
		}

		$rating = $this->sanitize_rating( $rating_raw );

		update_post_meta( $post_id, 'reviewer_name', $reviewer_name );
		update_post_meta( $post_id, 'reviewer_attribute', $reviewer_attribute );
		update_post_meta( $post_id, 'rating', $rating );
		update_post_meta( $post_id, 'review_title', $review_title );
		update_post_meta( $post_id, 'review_body', $review_body );
		update_post_meta( $post_id, 'linked_post_ids', $linked_post_ids );
		update_post_meta( $post_id, 'confirm_real_user', isset( $compliance['real_user'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'confirm_permission', isset( $compliance['permission'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'confirm_no_abuse', isset( $compliance['no_abuse'] ) ? 1 : 0 );
		wp_set_object_terms( $post_id, $profile_tag_ids, 'review_profile_tag', false );

		$internal_title = trim( $reviewer_name . ' ' . $review_title );
		if ( '' === $internal_title ) {
			$internal_title = 'Review #' . $post_id;
		}

		remove_action( 'save_post_review', array( $this, 'save_review_meta' ), 10 );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => wp_strip_all_tags( substr( $internal_title, 0, 190 ) ),
			)
		);
		add_action( 'save_post_review', array( $this, 'save_review_meta' ), 10, 2 );
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
	 * Get selectable posts from allowed post types.
	 *
	 * @return WP_Post[]
	 */
	private function get_linkable_posts() {
		$post_types = $this->settings->get_linkable_post_types();
		if ( empty( $post_types ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Return available profile tag terms.
	 *
	 * @return WP_Term[]
	 */
	private function get_profile_tag_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'review_profile_tag',
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Return available profile tag ids.
	 *
	 * @return int[]
	 */
	private function get_profile_tag_ids() {
		return array_map(
			'absint',
			wp_list_pluck( $this->get_profile_tag_terms(), 'term_id' )
		);
	}

	/**
	 * Print admin-only styles for review editor screen.
	 *
	 * @return void
	 */
	public function print_admin_styles() {
		$screen = get_current_screen();
		if ( ! $screen || 'review' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			#post-body-content {
				display: none;
			}
			.customer-reviews-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 12px;
			}
			.customer-reviews-field {
				margin-bottom: 12px;
			}
			.customer-reviews-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 6px;
			}
			.customer-reviews-field textarea,
			.customer-reviews-field input[type="text"],
			.customer-reviews-field select {
				width: 100%;
				max-width: none;
				border-radius: 4px;
			}
			.customer-reviews-tag-list {
				min-height: 22px;
				height: 22px;
				resize: vertical;
				overflow-y: auto;
				border: 1px solid #949494;
				background: #fff;
				padding: 8px 10px;
				border-radius: 4px;
			}
			.customer-reviews-tag-item {
				display: block;
				margin-bottom: 6px;
				font-weight: 400;
			}
			.customer-reviews-post-list {
				margin-top: 8px;
			}
			.customer-reviews-post-item {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 10px;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				margin-bottom: 8px;
				background: #fff;
			}
			.customer-reviews-post-title {
				font-weight: 600;
				flex: 1;
			}
			.customer-reviews-post-slug {
				color: #646970;
				font-size: 12px;
			}
			@media (max-width: 960px) {
				.customer-reviews-grid {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}
}
