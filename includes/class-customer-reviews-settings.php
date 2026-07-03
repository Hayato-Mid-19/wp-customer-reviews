<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin settings page and option helpers.
 */
class Customer_Reviews_Settings {
	const OPTION_KEY = 'customer_reviews_options';
	const PAGE_SLUG = 'customer-reviews-settings';
	const MENU_CAPABILITY = 'manage_options';
	const MENU_LANGUAGE_EN = 'en';
	const MENU_LANGUAGE_JA = 'ja';

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default option values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'target_post_type'    => 'mw_columns',
			'menu_language'       => self::MENU_LANGUAGE_EN,
			'default_per_page'    => 10,
			'max_per_page'        => 50,
		);
	}

	/**
	 * Get current menu language.
	 *
	 * @return string
	 */
	public static function get_menu_language() {
		$defaults = self::defaults();
		$stored = get_option( self::OPTION_KEY, array() );
		$value = is_array( $stored ) && isset( $stored['menu_language'] ) ? sanitize_key( (string) $stored['menu_language'] ) : $defaults['menu_language'];
		$allowed = array( self::MENU_LANGUAGE_EN, self::MENU_LANGUAGE_JA );

		return in_array( $value, $allowed, true ) ? $value : $defaults['menu_language'];
	}

	/**
	 * Whether menu labels should be in Japanese.
	 *
	 * @return bool
	 */
	public static function use_japanese_menu_labels() {
		return self::MENU_LANGUAGE_JA === self::get_menu_language();
	}

	/**
	 * Get all options.
	 *
	 * @return array<string,mixed>
	 */
	public function get_options() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Return selectable post types for linking.
	 *
	 * @return string[]
	 */
	public function get_linkable_post_types() {
		return array( $this->get_target_post_type() );
	}

	/**
	 * Return selected target post type for linking.
	 *
	 * @return string
	 */
	public function get_target_post_type() {
		$options = $this->get_options();
		$target_post_type = isset( $options['target_post_type'] ) ? sanitize_key( (string) $options['target_post_type'] ) : '';

		if ( '' !== $target_post_type && post_type_exists( $target_post_type ) ) {
			return $target_post_type;
		}

		if ( post_type_exists( 'mw_columns' ) ) {
			return 'mw_columns';
		}

		$available = $this->get_available_target_post_types();
		if ( ! empty( $available ) ) {
			return (string) $available[0]->name;
		}

		return 'post';
	}

	/**
	 * Return selectable post types for target selection.
	 *
	 * @return WP_Post_Type[]
	 */
	private function get_available_target_post_types() {
		$post_types = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		$post_types = array_filter(
			$post_types,
			function ( $post_type ) {
				return isset( $post_type->name ) && 'review' !== $post_type->name;
			}
		);

		uasort(
			$post_types,
			function ( $a, $b ) {
				if ( 'mw_columns' === $a->name ) {
					return -1;
				}
				if ( 'mw_columns' === $b->name ) {
					return 1;
				}
				return strcmp( (string) $a->label, (string) $b->label );
			}
		);

		return array_values( $post_types );
	}

	/**
	 * Register option schema and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'customer_reviews_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize_options' )
		);

		add_settings_section(
			'customer_reviews_linking_section',
			__( 'Review Linking Settings', 'customer-reviews' ),
			function () {
				echo '<p>' . esc_html__( 'Select a single target post type for review linking. Only posts in this type can be selected on the review editor.', 'customer-reviews' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'customer_reviews_target_post_type',
			__( 'Target Post Type', 'customer-reviews' ),
			array( $this, 'render_target_post_type_field' ),
			self::PAGE_SLUG,
			'customer_reviews_linking_section'
		);

		add_settings_field(
			'customer_reviews_menu_language',
			__( 'Menu Label Language', 'customer-reviews' ),
			array( $this, 'render_menu_language_field' ),
			self::PAGE_SLUG,
			'customer_reviews_linking_section'
		);

		add_settings_field(
			'customer_reviews_default_per_page',
			__( 'API Default Per Page', 'customer-reviews' ),
			array( $this, 'render_default_per_page_field' ),
			self::PAGE_SLUG,
			'customer_reviews_linking_section'
		);

		add_settings_field(
			'customer_reviews_max_per_page',
			__( 'API Max Per Page', 'customer-reviews' ),
			array( $this, 'render_max_per_page_field' ),
			self::PAGE_SLUG,
			'customer_reviews_linking_section'
		);
	}

	/**
	 * Sanitize options.
	 *
	 * @param mixed $input raw option value.
	 * @return array<string,mixed>
	 */
	public function sanitize_options( $input ) {
		$defaults = self::defaults();

		$sanitized = array(
			'target_post_type'    => $defaults['target_post_type'],
			'menu_language'       => $defaults['menu_language'],
			'default_per_page'    => $defaults['default_per_page'],
			'max_per_page'        => $defaults['max_per_page'],
		);

		if ( is_array( $input ) ) {
			if ( isset( $input['target_post_type'] ) ) {
				$target = sanitize_key( (string) $input['target_post_type'] );
				if ( post_type_exists( $target ) && 'review' !== $target ) {
					$sanitized['target_post_type'] = $target;
				}
			} elseif ( isset( $input['linkable_post_types'] ) && is_array( $input['linkable_post_types'] ) ) {
				// Backward compatibility for older option payload.
				$legacy_types = array_values(
					array_filter(
						array_map( 'sanitize_key', $input['linkable_post_types'] ),
						function ( $post_type ) {
							return post_type_exists( $post_type ) && 'review' !== $post_type;
						}
					)
				);
				if ( ! empty( $legacy_types ) ) {
					$sanitized['target_post_type'] = (string) $legacy_types[0];
				}
			}

			if ( isset( $input['menu_language'] ) ) {
				$menu_language = sanitize_key( (string) $input['menu_language'] );
				if ( in_array( $menu_language, array( self::MENU_LANGUAGE_EN, self::MENU_LANGUAGE_JA ), true ) ) {
					$sanitized['menu_language'] = $menu_language;
				}
			}

			if ( isset( $input['default_per_page'] ) ) {
				$default_per_page = absint( $input['default_per_page'] );
				$sanitized['default_per_page'] = max( 1, $default_per_page );
			}

			if ( isset( $input['max_per_page'] ) ) {
				$max_per_page = absint( $input['max_per_page'] );
				$sanitized['max_per_page'] = max( 1, $max_per_page );
			}
		}

		if ( $sanitized['default_per_page'] > $sanitized['max_per_page'] ) {
			$sanitized['default_per_page'] = $sanitized['max_per_page'];
		}

		return $sanitized;
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		$settings_label = self::use_japanese_menu_labels() ? '設定' : __( 'Settings', 'customer-reviews' );

		add_submenu_page(
			'edit.php?post_type=review',
			__( 'Reviews Settings', 'customer-reviews' ),
			$settings_label,
			self::MENU_CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Customer Reviews Settings', 'customer-reviews' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'customer_reviews_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render field for target post type.
	 *
	 * @return void
	 */
	public function render_target_post_type_field() {
		$selected = $this->get_target_post_type();
		$post_types = $this->get_available_target_post_types();
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[target_post_type]">
			<?php foreach ( $post_types as $post_type ) : ?>
				<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( $selected, $post_type->name ); ?>>
					<?php echo esc_html( $post_type->labels->singular_name ); ?> (<?php echo esc_html( $post_type->name ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Theme post type "mw_columns" is prioritized when detected. Reviews can only link to posts of the selected type.', 'customer-reviews' ); ?>
		</p>
		<?php
	}

	/**
	 * Render menu language selector.
	 *
	 * @return void
	 */
	public function render_menu_language_field() {
		$options = $this->get_options();
		$current = isset( $options['menu_language'] ) ? sanitize_key( (string) $options['menu_language'] ) : self::defaults()['menu_language'];
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[menu_language]">
			<option value="<?php echo esc_attr( self::MENU_LANGUAGE_EN ); ?>" <?php selected( $current, self::MENU_LANGUAGE_EN ); ?>>
				English
			</option>
			<option value="<?php echo esc_attr( self::MENU_LANGUAGE_JA ); ?>" <?php selected( $current, self::MENU_LANGUAGE_JA ); ?>>
				日本語
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Switch review-related admin menu labels between English and Japanese.', 'customer-reviews' ); ?>
		</p>
		<?php
	}

	/**
	 * Render field for API default per page.
	 *
	 * @return void
	 */
	public function render_default_per_page_field() {
		$options = $this->get_options();
		?>
		<input
			type="number"
			min="1"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_per_page]"
			value="<?php echo esc_attr( (string) $options['default_per_page'] ); ?>"
		/>
		<?php
	}

	/**
	 * Render field for API max per page.
	 *
	 * @return void
	 */
	public function render_max_per_page_field() {
		$options = $this->get_options();
		?>
		<input
			type="number"
			min="1"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_per_page]"
			value="<?php echo esc_attr( (string) $options['max_per_page'] ); ?>"
		/>
		<?php
	}
}
