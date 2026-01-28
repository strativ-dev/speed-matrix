<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Speed_Matrix_Admin {
	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Enqueue admin CSS
	 */
	public function enqueue_styles() {
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
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->id, 'speed-matrix' ) !== false ) {

				$settings = get_option( 'speed_matrix_settings', array() );
				wp_enqueue_script(
					$this->plugin_name,
					SPEED_MATRIX_PLUGIN_URL . 'assets/js/admin.js',
					array( 'jquery' ),
					$this->version,
					false
				);



				wp_localize_script(
					$this->plugin_name,
					'speedMatrixData',
					array(
						'settings' => $settings,
						'i18n' => array(
							'import_success' => __( 'Settings imported successfully!', 'speed-matrix' ),
							'import_error' => __( 'Error importing settings', 'speed-matrix' ),
						),
					)
				);

			}
		}
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
}


