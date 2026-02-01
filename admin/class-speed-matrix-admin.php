<?php
/**
 * Speed Matrix Admin Class
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Admin Class
 */
class Speed_Matrix_Admin {
	/**
	 * Plugin name
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Register AJAX handler
		add_action( 'wp_ajax_speed_matrix_import_settings', array( $this, 'ajax_import_settings' ) );
	}

	/**
	 * Enqueue admin CSS
	 */
	public function enqueue_styles() {
		// Only load on our plugin page
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'speed-matrix' ) === false ) {
			return;
		}

		wp_enqueue_style(
			$this->plugin_name,
			SPEED_MATRIX_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue admin JS
	 */
	public function enqueue_scripts() {
		// Only load on our plugin page
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'speed-matrix' ) === false ) {
			return;
		}

		// Get current settings
		$settings = get_option( 'speed_matrix_settings', array() );

		// Enqueue admin script
		wp_enqueue_script(
			'speed-matrix-admin',
			SPEED_MATRIX_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		// Localize script with data
		wp_localize_script(
			'speed-matrix-admin',
			'speedMatrixData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'speed_matrix_admin_nonce' ),
				'settings' => $settings,
				'i18n' => array(
					'import_success' => __( 'Settings imported successfully!', 'speed-matrix' ),
					'import_error' => __( 'Failed to import settings. Please check the file format.', 'speed-matrix' ),
					'export_success' => __( 'Settings exported successfully!', 'speed-matrix' ),
				),
			)
		);
	}

	/**
	 * Add plugin menu
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Speed Matrix Settings', 'speed-matrix' ),
			__( 'Speed Matrix', 'speed-matrix' ),
			'manage_options',
			'speed-matrix',
			array( $this, 'display_plugin_admin_page' ),
			SPEED_MATRIX_PLUGIN_URL . 'assets/images/speed-matrix-logo.png'
		);
	}

	/**
	 * Add admin bar menu
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id' => 'speed-matrix',
				'title' => __( 'Speed Matrix', 'speed-matrix' ),
				'href' => esc_url( admin_url( 'admin.php?page=speed-matrix' ) ),
			)
		);
	}

	/**
	 * Display plugin settings page
	 */
	public function display_plugin_admin_page() {
		$settings_file = SPEED_MATRIX_PLUGIN_DIR . 'admin/admin-settings.php';
		if ( file_exists( $settings_file ) ) {
			include_once $settings_file;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Settings file not found.', 'speed-matrix' ) . '</p></div>';
		}
	}

	/**
	 * Add "Settings" link on Plugins page
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified action links.
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=speed-matrix' ) ),
			esc_html__( 'Settings', 'speed-matrix' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Handle settings import via AJAX
	 */
	public function ajax_import_settings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'speed_matrix_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'speed-matrix' ) ) );
		}

		// Check user permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to import settings.', 'speed-matrix' ) ) );
		}

		// Get and validate settings data
		if ( ! isset( $_POST['settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No settings data provided.', 'speed-matrix' ) ) );
		}

		// Decode JSON settings
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string is validated after decoding, then each field is sanitized individually below
		$imported_settings = json_decode( wp_unslash( $_POST['settings'] ), true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $imported_settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings format.', 'speed-matrix' ) ) );
		}

		// Get default settings for validation
		$default_settings = array(
			'optimization_preset' => 'recommended',
			'enable_page_cache' => '0',
			'cache_mobile_separate' => '0',
			'enable_browser_cache' => '0',
			'cache_expiry' => '31536000',
			'minify_html' => '0',
			'minify_inline_css' => '0',
			'minify_inline_js' => '0',
			'minify_css' => '0',
			'combine_css' => '0',
			'async_css' => '0',
			'remove_unused_css' => '0',
			'inline_critical_css' => '0',
			'critical_css' => '',
			'exclude_css' => '',
			'minify_js' => '0',
			'combine_js' => '0',
			'defer_js' => '0',
			'exclude_jquery' => '1',
			'delay_js_execution' => '0',
			'delay_js_timeout' => '5',
			'delay_js_patterns' => "google-analytics\ngoogletagmanager\nfacebook.net\ngtag\nfbevents",
			'exclude_js' => "jquery.min.js\njquery.js",
			'lazy_load' => '0',
			'lazy_load_iframes' => '0',
			'exclude_first_images' => '2',
			'enable_webp' => '0',
			'preload_key_requests' => '0',
			'lcp_image_url' => '',
			'preload_fonts' => '0',
			'font_urls' => '',
			'optimize_google_fonts' => '0',
			'google_fonts_method' => 'combine',
			'font_display_swap' => '0',
			'dns_prefetch' => '0',
			'dns_prefetch_urls' => "//fonts.googleapis.com\n//fonts.gstatic.com",
			'remove_query_strings' => '0',
			'disable_emojis' => '0',
			'disable_embeds' => '0',
			'disable_dashicons' => '0',
			'disable_jquery_migrate' => '0',
			'cdn_url' => '',
			'exclude_urls' => "/cart\n/checkout\n/my-account",
			'auto_cleanup' => '0',
			'cleanup_frequency' => 'weekly',
		);

		// Sanitize imported settings
		$sanitized_settings = array();

		// Text fields
		$text_fields = array( 'optimization_preset', 'cache_expiry', 'delay_js_timeout', 'exclude_first_images', 'google_fonts_method', 'cleanup_frequency' );
		foreach ( $text_fields as $field ) {
			if ( isset( $imported_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = sanitize_text_field( $imported_settings[ $field ] );
			} elseif ( isset( $default_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = $default_settings[ $field ];
			}
		}

		// URL fields
		$url_fields = array( 'lcp_image_url', 'cdn_url' );
		foreach ( $url_fields as $field ) {
			if ( isset( $imported_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = esc_url_raw( $imported_settings[ $field ] );
			} elseif ( isset( $default_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = $default_settings[ $field ];
			}
		}

		// Textarea fields
		$textarea_fields = array( 'delay_js_patterns', 'exclude_js', 'exclude_css', 'exclude_urls', 'dns_prefetch_urls', 'font_urls', 'critical_css' );
		foreach ( $textarea_fields as $field ) {
			if ( isset( $imported_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = sanitize_textarea_field( $imported_settings[ $field ] );
			} elseif ( isset( $default_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = $default_settings[ $field ];
			}
		}

		// Checkbox fields (all remaining keys from default settings)
		$checkbox_fields = array_diff( array_keys( $default_settings ), $text_fields, $url_fields, $textarea_fields );
		foreach ( $checkbox_fields as $field ) {
			if ( isset( $imported_settings[ $field ] ) ) {
				// Convert to '1' or '0'
				$sanitized_settings[ $field ] = ( '1' === $imported_settings[ $field ] || 1 === $imported_settings[ $field ] || true === $imported_settings[ $field ] ) ? '1' : '0';
			} elseif ( isset( $default_settings[ $field ] ) ) {
				$sanitized_settings[ $field ] = $default_settings[ $field ];
			}
		}

		// Update settings
		$updated = update_option( 'speed_matrix_settings', $sanitized_settings );

		if ( $updated ) {
			// Clear cache after importing settings
			if ( class_exists( 'Speed_Matrix_Cache' ) ) {
				Speed_Matrix_Cache::clear_all_static();
			}

			wp_send_json_success(
				array(
					'message' => __( 'Settings imported and saved successfully!', 'speed-matrix' ),
				)
			);
		} else {
			// Settings are the same or update failed
			wp_send_json_success(
				array(
					'message' => __( 'Settings imported (no changes detected).', 'speed-matrix' ),
				)
			);
		}
	}
}