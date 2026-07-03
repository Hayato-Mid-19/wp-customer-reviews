<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers review post type and related taxonomy.
 */
class Customer_Reviews_Review_Post_Type {
	/**
	 * Default terms for review profile tag taxonomy.
	 *
	 * @return array<int,array{name:string,slug:string}>
	 */
	public static function get_default_profile_tags() {
		return array(
			array( 'name' => '20代前半', 'slug' => 'age-20-early' ),
			array( 'name' => '20代後半', 'slug' => 'age-20-late' ),
			array( 'name' => '30代前半', 'slug' => 'age-30-early' ),
			array( 'name' => '30代後半', 'slug' => 'age-30-late' ),
			array( 'name' => '40代前半', 'slug' => 'age-40-early' ),
			array( 'name' => '40代後半', 'slug' => 'age-40-late' ),
			array( 'name' => '50代前半', 'slug' => 'age-50-early' ),
			array( 'name' => '50代後半', 'slug' => 'age-50-late' ),
			array( 'name' => '男性', 'slug' => 'gender-male' ),
			array( 'name' => '女性', 'slug' => 'gender-female' ),
			array( 'name' => '月収 50万円', 'slug' => 'monthly-income-50' ),
			array( 'name' => '月収 55万円', 'slug' => 'monthly-income-55' ),
			array( 'name' => '月収 60万円', 'slug' => 'monthly-income-60' ),
			array( 'name' => '月収 65万円', 'slug' => 'monthly-income-65' ),
			array( 'name' => '月収 70万円', 'slug' => 'monthly-income-70' ),
			array( 'name' => '月収 75万円', 'slug' => 'monthly-income-75' ),
			array( 'name' => '月収 80万円', 'slug' => 'monthly-income-80' ),
			array( 'name' => '月収 85万円', 'slug' => 'monthly-income-85' ),
			array( 'name' => '月収 90万円', 'slug' => 'monthly-income-90' ),
			array( 'name' => '月収 95万円', 'slug' => 'monthly-income-95' ),
			array( 'name' => '月収 100万円', 'slug' => 'monthly-income-100' ),
			array( 'name' => '月収 105万円', 'slug' => 'monthly-income-105' ),
			array( 'name' => '月収 110万円', 'slug' => 'monthly-income-110' ),
			array( 'name' => '月収 115万円', 'slug' => 'monthly-income-115' ),
			array( 'name' => '月収 120万円以上', 'slug' => 'monthly-income-120-plus' ),
			array( 'name' => 'フリーランス歴 1年', 'slug' => 'freelance-years-1' ),
			array( 'name' => 'フリーランス歴 2年', 'slug' => 'freelance-years-2' ),
			array( 'name' => 'フリーランス歴 3年', 'slug' => 'freelance-years-3' ),
			array( 'name' => 'フリーランス歴 4年', 'slug' => 'freelance-years-4' ),
			array( 'name' => 'フリーランス歴 5年', 'slug' => 'freelance-years-5' ),
			array( 'name' => 'フリーランス歴 6年', 'slug' => 'freelance-years-6' ),
			array( 'name' => 'フリーランス歴 7年', 'slug' => 'freelance-years-7' ),
			array( 'name' => 'フリーランス歴 8年', 'slug' => 'freelance-years-8' ),
			array( 'name' => 'フリーランス歴 9年', 'slug' => 'freelance-years-9' ),
			array( 'name' => 'フリーランス歴 10年以上', 'slug' => 'freelance-years-10-plus' ),
		);
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'admin_init', array( __CLASS__, 'grant_caps_to_roles' ) );
		add_action( 'admin_init', array( $this, 'remove_default_editor_support' ) );
		add_action( 'admin_init', array( __CLASS__, 'seed_default_profile_tags' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_list_styles' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_block_editor_for_review' ), 10, 2 );
		add_filter( 'manage_edit-review_columns', array( $this, 'add_id_column' ) );
		add_action( 'manage_review_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
	}

	/**
	 * Register review post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$use_ja = class_exists( 'Customer_Reviews_Settings' ) && Customer_Reviews_Settings::use_japanese_menu_labels();

		register_post_type(
			'review',
			array(
				'labels'              => array(
					'name'               => $use_ja ? '口コミ' : __( 'Reviews', 'customer-reviews' ),
					'menu_name'          => $use_ja ? '口コミ' : __( 'Reviews', 'customer-reviews' ),
					'singular_name'      => $use_ja ? '口コミ' : __( 'Review', 'customer-reviews' ),
					'add_new'            => $use_ja ? '新規口コミ' : __( 'Add New Review', 'customer-reviews' ),
					'add_new_item'       => $use_ja ? '新規口コミを追加' : __( 'Add New Review', 'customer-reviews' ),
					'edit_item'          => $use_ja ? '口コミを編集' : __( 'Edit Review', 'customer-reviews' ),
					'new_item'           => $use_ja ? '新規口コミ' : __( 'New Review', 'customer-reviews' ),
					'view_item'          => $use_ja ? '口コミを表示' : __( 'View Review', 'customer-reviews' ),
					'search_items'       => $use_ja ? '口コミを検索' : __( 'Search Reviews', 'customer-reviews' ),
					'all_items'          => $use_ja ? '口コミ一覧' : __( 'Reviews', 'customer-reviews' ),
					'not_found'          => $use_ja ? '口コミが見つかりません。' : __( 'No reviews found.', 'customer-reviews' ),
					'not_found_in_trash' => $use_ja ? 'ゴミ箱に口コミはありません。' : __( 'No reviews found in Trash.', 'customer-reviews' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'review',
					'with_front' => false,
				),
				'supports'            => array(),
				'map_meta_cap'        => true,
				'capability_type'     => array( 'review', 'reviews' ),
				'capabilities'        => self::get_capabilities(),
				'menu_icon'           => 'dashicons-testimonial',
			)
		);
	}

	/**
	 * Register profile tag taxonomy for review.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$use_ja = class_exists( 'Customer_Reviews_Settings' ) && Customer_Reviews_Settings::use_japanese_menu_labels();

		register_taxonomy(
			'review_profile_tag',
			array( 'review' ),
			array(
				'labels'            => array(
					'name'          => $use_ja ? '口コミタグ' : __( 'Review Profile Tags', 'customer-reviews' ),
					'menu_name'     => $use_ja ? '口コミタグ' : __( 'Review Profile Tags', 'customer-reviews' ),
					'singular_name' => $use_ja ? '口コミタグ' : __( 'Review Profile Tag', 'customer-reviews' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Grants review capabilities to editor role.
	 *
	 * @return void
	 */
	public static function grant_caps_to_roles() {
		$roles = array( 'editor', 'administrator' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			foreach ( self::get_capabilities() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Seed default profile tags if they do not exist.
	 *
	 * @return void
	 */
	public static function seed_default_profile_tags() {
		if ( ! taxonomy_exists( 'review_profile_tag' ) ) {
			return;
		}

		foreach ( self::get_default_profile_tags() as $tag ) {
			$exists = term_exists( $tag['slug'], 'review_profile_tag' );
			if ( $exists ) {
				continue;
			}

			$exists_by_name = term_exists( $tag['name'], 'review_profile_tag' );
			if ( $exists_by_name ) {
				continue;
			}

			wp_insert_term(
				$tag['name'],
				'review_profile_tag',
				array(
					'slug' => $tag['slug'],
				)
			);
		}
	}

	/**
	 * Custom capabilities map.
	 *
	 * @return array<string,string>
	 */
	public static function get_capabilities() {
		return array(
			'edit_post'              => 'edit_review',
			'read_post'              => 'read_review',
			'delete_post'            => 'delete_review',
			'edit_posts'             => 'edit_reviews',
			'edit_others_posts'      => 'edit_others_reviews',
			'publish_posts'          => 'publish_reviews',
			'read_private_posts'     => 'read_private_reviews',
			'delete_posts'           => 'delete_reviews',
			'delete_private_posts'   => 'delete_private_reviews',
			'delete_published_posts' => 'delete_published_reviews',
			'delete_others_posts'    => 'delete_others_reviews',
			'edit_private_posts'     => 'edit_private_reviews',
			'edit_published_posts'   => 'edit_published_reviews',
			'create_posts'           => 'edit_reviews',
		);
	}

	/**
	 * Disable block editor for review post type.
	 *
	 * @param bool   $use_block_editor current value.
	 * @param string $post_type post type key.
	 * @return bool
	 */
	public function disable_block_editor_for_review( $use_block_editor, $post_type ) {
		if ( 'review' === $post_type ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Remove default title/editor UI from review screen.
	 *
	 * @return void
	 */
	public function remove_default_editor_support() {
		remove_post_type_support( 'review', 'editor' );
		remove_post_type_support( 'review', 'title' );
	}

	/**
	 * Add custom columns to review list table.
	 *
	 * @param array<string,string> $columns existing columns.
	 * @return array<string,string>
	 */
	public function add_id_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'cb' === $key ) {
				$new_columns['review_id'] = 'ID';
			}
		}

		if ( ! isset( $new_columns['review_id'] ) ) {
			$new_columns['review_id'] = 'ID';
		}

		return $new_columns;
	}

	/**
	 * Render custom column values.
	 *
	 * @param string $column_name column key.
	 * @param int    $post_id post ID.
	 * @return void
	 */
	public function render_custom_columns( $column_name, $post_id ) {
		if ( 'review_id' === $column_name ) {
			echo esc_html( (string) $post_id );
		}
	}

	/**
	 * Enqueue admin CSS for review list screen only.
	 *
	 * @param string $hook_suffix current admin screen hook.
	 * @return void
	 */
	public function enqueue_admin_list_styles( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'review' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'customer-reviews-admin-review-list',
			CUSTOMER_REVIEWS_PLUGIN_URL . 'assets/css/admin-review-list.css',
			array(),
			CUSTOMER_REVIEWS_VERSION
		);
	}
}
