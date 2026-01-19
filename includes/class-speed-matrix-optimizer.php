<?php
/**
 * Speed Matrix Optimizer Class
 *
 * Handles all optimization functionality for the Speed Matrix plugin.
 *
 * @package Speed_Matrix
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Speed_Matrix_Optimizer {
	private $speed_matrix_settings;
	private $excluded_urls = array();
	private $excluded_js = array();
	private $excluded_css = array();
	private $delayed_scripts = array();

	public function __construct() {
		$this->load_settings();
		add_filter( 'cron_schedules', [ $this, 'speed_matrix_add_cron_schedules' ] );
		add_action( 'admin_init', [ $this, 'speed_matrix_maybe_schedule_cleanup' ] );
		add_action( 'speed_matrix_auto_cleanup_event', [ $this, 'speed_matrix_run_cleanup' ] );

	}


	/**
	 * Load settings and exclusions
	 */
	private function load_settings() {

		$this->settings = get_option( 'speed_matrix_settings', array() );




		$this->excluded_urls = ! empty( $this->settings['exclude_urls'] )
			? array_map( 'trim', explode( "\n", $this->settings['exclude_urls'] ) )
			: array();

		$this->excluded_js = ! empty( $this->settings['exclude_js'] )
			? array_map( 'trim', explode( "\n", $this->settings['exclude_js'] ) )
			: array();

		$this->excluded_css = ! empty( $this->settings['exclude_css'] )
			? array_map( 'trim', explode( "\n", $this->settings['exclude_css'] ) )
			: array();

		$this->delayed_scripts = ! empty( $this->settings['delay_js_patterns'] )
			? array_map( 'trim', explode( "\n", $this->settings['delay_js_patterns'] ) )
			: array();
	}

	/**
	 * Add custom cron schedules
	 */
	public function speed_matrix_add_cron_schedules( $schedules ) {

		$schedules['weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display' => __( 'Once Weekly', 'speed-matrix' ),
		);

		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display' => __( 'Once Monthly', 'speed-matrix' ),
		);

		return $schedules;
	}


	/**
	 * Schedule or clear cleanup event based on settings
	 */
	public function speed_matrix_maybe_schedule_cleanup() {

		$speed_matrix_settings = get_option( 'speed_matrix_settings', array() );

		$enabled = ! empty( $speed_matrix_settings['auto_cleanup'] );
		$frequency = isset( $speed_matrix_settings['cleanup_frequency'] ) ? $speed_matrix_settings['cleanup_frequency'] : 'weekly';

		$hook = 'speed_matrix_auto_cleanup_event';

		// Clear existing schedule
		if ( wp_next_scheduled( $hook ) ) {
			wp_clear_scheduled_hook( $hook );
		}

		// Schedule only if enabled
		if ( $enabled ) {
			wp_schedule_event( time(), $frequency, $hook );
		}
	}


	/**
	 * Run the cleanup tasks
	 */


	public function speed_matrix_run_cleanup() {

		// Prevent multiple simultaneous runs
		if ( get_transient( 'speed_matrix_cleanup_running' ) ) {
			return;
		}
		set_transient( 'speed_matrix_cleanup_running', true, 300 ); // 5 min lock

		// --- Delete post revisions ---
		$revisions = get_posts( array(
			'post_type' => 'revision',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'post_status' => 'any',
		) );

		foreach ( $revisions as $rev_id ) {
			wp_delete_post( $rev_id, true );
		}

		// --- Delete spam & trashed comments ---
		$comments = get_comments( array(
			'status' => 'spam,trash',
			'number' => 0,
			'fields' => 'ids',
		) );

		foreach ( $comments as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}

		// --- Delete known transients safely ---
		$speed_matrix_all_options = wp_load_alloptions(); // gets all autoloaded options
		foreach ( $speed_matrix_all_options as $speed_matrix_option_name => $speed_matrix_value ) {
			if ( strpos( $speed_matrix_option_name, '_transient_timeout_' ) === 0 && $speed_matrix_value < time() ) {
				$transient = str_replace( '_transient_timeout_', '', $speed_matrix_option_name );
				delete_transient( $transient );
			}
		}

		// --- Save last cleanup time ---
		update_option( 'speed_matrix_last_cleanup', current_time( 'mysql' ) );

	}







	/**
	 * Check if current URL should be excluded from optimization
	 *
	 * @return bool
	 */
	private function is_excluded() {
		if ( empty( $this->excluded_urls ) ) {
			return false;
		}
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		foreach ( $this->excluded_urls as $excluded ) {
			if ( ! empty( $excluded ) && strpos( $current_url, $excluded ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Initialize all optimizations
	 */
	public function init() {
		// Don't run on admin pages or AJAX requests
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}



		// Check if current URL is excluded
		if ( $this->is_excluded() ) {
			return;
		}

		// HTML Optimization
		if ( ! empty( $this->settings['minify_html'] ) ) {
			add_action( 'template_redirect', array( $this, 'start_html_minification' ), 0 );
		}

		// CSS Optimization
		if ( ! empty( $this->settings['minify_css'] ) ) {
			if ( ! empty( $this->settings['combine_css'] ) ) {
				add_action( 'wp_print_styles', array( $this, 'combine_css_files' ), 999 );
			}
			if ( ! empty( $this->settings['async_css'] ) ) {
				add_filter( 'style_loader_tag', array( $this, 'async_css_loading' ), 10, 2 );
			}
		}

		// Unused CSS Removal
		if ( ! empty( $this->settings['remove_unused_css'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'remove_unused_css' ), 999 );
			add_action( 'wp_enqueue_scripts', array( $this, 'removed_unused_css_script' ), 999 );
		}

		// Critical CSS
		if ( ! empty( $this->settings['inline_critical_css'] ) && ! empty( $this->settings['critical_css'] ) ) {
			add_action( 'wp_head', array( $this, 'inline_critical_css' ), 1 );
		}

		// JS Optimization
		if ( ! empty( $this->settings['minify_js'] ) && ! empty( $this->settings['combine_js'] ) ) {
			add_action( 'wp_print_scripts', array( $this, 'combine_js_files' ), 999 );
		}
		if ( ! empty( $this->settings['defer_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );
		}
		if ( ! empty( $this->settings['delay_js_execution'] ) ) {
			add_action( 'wp_footer', array( $this, 'add_delay_js_script' ), 999 );
			add_filter( 'script_loader_tag', array( $this, 'delay_javascript_execution' ), 20, 3 );
		}

		if ( ! empty( $this->settings['minify_inline_js'] ) ) {
			add_filter( 'script_loader_tag', array( $this, 'minify_inline_script_tags' ), 10, 2 );
		}

		// Lazy Loading
		if ( ! empty( $this->settings['lazy_load'] ) ) {
			add_filter( 'the_content', array( $this, 'lazy_load_images' ), 20 );
			add_filter( 'post_thumbnail_html', array( $this, 'lazy_load_images' ), 20 );
		}

		if ( ! empty( $this->settings['lazy_load_iframes'] ) ) {
			add_filter( 'the_content', array( $this, 'lazy_load_iframes' ), 20 );
		}

		// Performance tweaks
		if ( ! empty( $this->settings['remove_query_strings'] ) ) {
			add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ), 15 );
			add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ), 15 );
		}
		if ( ! empty( $this->settings['disable_emojis'] ) ) {
			$this->disable_emojis();
		}
		if ( ! empty( $this->settings['disable_embeds'] ) ) {
			$this->disable_embeds();
		}
		if ( ! empty( $this->settings['disable_dashicons'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_dashicons' ) );
		}
		if ( ! empty( $this->settings['disable_jquery_migrate'] ) ) {
			add_action( 'wp_default_scripts', array( $this, 'disable_jquery_migrate' ) );
		}

		// WebP Image Conversion
		if ( ! empty( $this->settings['enable_webp'] ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'convert_images_to_webp' ), 10, 2 );
			add_filter( 'wp_content_img_tag', array( $this, 'serve_webp_images' ), 10, 3 );
		}

		// Google Fonts Optimization
		if ( ! empty( $this->settings['google_fonts_method'] ) ) {
			$this->handle_google_fonts_method();
		}

		// DNS Prefetch
		if ( ! empty( $this->settings['dns_prefetch'] ) ) {
			add_action( 'wp_head', array( $this, 'add_dns_prefetch' ), 1 );
		}

		// Preload Key Requests
		if ( ! empty( $this->settings['preload_key_requests'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_key_requests' ), 1 );
		}

		// CDN
		if ( ! empty( $this->settings['cdn_url'] ) ) {
			add_filter( 'script_loader_src', array( $this, 'speed_matrix_check_url' ), 10, 2 );
			add_filter( 'style_loader_src', array( $this, 'speed_matrix_check_url' ), 10, 2 );
		}

		// LCP image
		if ( ! empty( $this->settings['lcp_image_url'] ) ) {
			add_action( 'wp_head', array( $this, 'speed_matrix_get_lcp_image_url' ), 1 );
		}

		// Preload Fonts
		if ( ! empty( $this->settings['preload_fonts'] ) && ! empty( $this->settings['font_urls'] ) ) {
			add_action( 'wp_head', array( $this, 'speed_matrix_preload_fonts' ), 1 );
		}
	}

	/**
	 * Start HTML minification
	 */
	public function start_html_minification() {
		ob_start( array( $this, 'minify_html' ) );
	}

	/**
	 * Minify HTML output
	 *
	 * @param string $html HTML content.
	 * @return string Minified HTML.
	 */
	public function minify_html( $html ) {
		// Don't minify if HTML contains pre or textarea tags
		if ( stripos( $html, '<pre' ) !== false || stripos( $html, '<textarea' ) !== false ) {
			return $html;
		}

		// Remove HTML comments (except IE conditional comments)
		$html = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html );

		// Remove whitespace between tags
		$html = preg_replace( '/>\s+</', '><', $html );

		// Remove multiple spaces
		$html = preg_replace( '/\s+/', ' ', $html );

		return trim( $html );
	}

	/**
	 * Make CSS load asynchronously
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle The handle name.
	 * @return string Modified tag.
	 */
	public function async_css_loading( $tag, $handle ) {
		// Don't async load admin bar styles
		if ( in_array( $handle, array( 'admin-bar' ), true ) ) {
			return $tag;
		}

		// Convert to preload with onload fallback
		$tag = str_replace( "rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
		$tag = str_replace( 'rel="stylesheet"', 'rel="preload" as="style" onload="this.onload=null;this.rel=\'stylesheet\'"', $tag );

		// Add noscript fallback
		$noscript = str_replace( 'rel="preload" as="style"', 'rel="stylesheet"', $tag );
		$noscript = str_replace( "rel='preload' as='style'", "rel='stylesheet'", $noscript );
		$noscript = preg_replace( '/\s*onload="[^"]*"/', '', $noscript );
		$tag .= '<noscript>' . $noscript . '</noscript>';

		return $tag;
	}

	/**
	 * Combine CSS files
	 */
	public function combine_css_files() {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) ) {
			return;
		}

		$styles_to_combine = array();
		foreach ( $wp_styles->queue as $handle ) {
			// Skip admin and excluded styles
			if ( in_array( $handle, array( 'admin-bar', 'dashicons' ), true ) ) {
				continue;
			}

			// Check if excluded
			if ( $this->is_resource_excluded( $handle, 'css' ) ) {
				continue;
			}

			if ( isset( $wp_styles->registered[ $handle ] ) ) {
				$style = $wp_styles->registered[ $handle ];
				if ( $this->is_local_file( $style->src ) ) {
					$styles_to_combine[] = $handle;
				}
			}
		}

		if ( empty( $styles_to_combine ) ) {
			return;
		}

		$cache_key = md5( serialize( $styles_to_combine ) . SPEED_MATRIX_VERSION );
		$cache_file = SPEED_MATRIX_CACHE_DIR . 'css/combined-' . $cache_key . '.css';
		$cache_url = SPEED_MATRIX_CACHE_URL . 'css/combined-' . $cache_key . '.css';

		if ( ! file_exists( $cache_file ) ) {
			$combined_css = '';
			foreach ( $styles_to_combine as $handle ) {
				$style = $wp_styles->registered[ $handle ];
				$css_path = $this->get_file_path( $style->src );
				if ( file_exists( $css_path ) && is_readable( $css_path ) ) {
					$css_content = file_get_contents( $css_path );
					$css_content = $this->fix_css_urls( $css_content, dirname( $style->src ) );
					$combined_css .= $this->minify_css( $css_content ) . "\n";
				}
			}

			wp_mkdir_p( dirname( $cache_file ) );
			// Use WP_Filesystem for file operations
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents( $cache_file, $combined_css, FS_CHMOD_FILE );
		}

		foreach ( $styles_to_combine as $handle ) {
			wp_dequeue_style( $handle );
		}

		wp_enqueue_style( 'speed-matrix-combined-css', $cache_url, array(), filemtime( $cache_file ) );
	}

	/**
	 * Fix relative URLs in CSS
	 *
	 * @param string $css     CSS content.
	 * @param string $css_dir CSS file directory.
	 * @return string Fixed CSS.
	 */
	private function fix_css_urls( $css, $css_dir ) {
		return preg_replace_callback(
			'/url\s*\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			function ( $matches ) use ( $css_dir ) {
				$url = $matches[2];
				// Skip absolute URLs, data URIs, and already absolute paths
				if ( preg_match( '/^(https?:|\/\/|data:|\/)/i', $url ) ) {
					return $matches[0];
				}
				// Make relative URL absolute
				$new_url = trailingslashit( $css_dir ) . $url;
				return 'url(' . $matches[1] . $new_url . $matches[1] . ')';
			},
			$css
		);
	}

	/**
	 * Minify CSS
	 *
	 * @param string $css CSS content.
	 * @return string Minified CSS.
	 */
	private function minify_css( $css ) {
		// Remove comments
		$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
		// Remove whitespace
		$css = str_replace( array( "\r\n", "\r", "\n", "\t" ), '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $css );


		if ( ! empty( $this->settings['minify_inline_css'] ) ) {
			$css = preg_replace( '/\/\*.*?\*\//s', '', $css );
		}
		return trim( $css );
	}

	/**
	 * Remove unused CSS
	 */
	public function remove_unused_css() {

		add_filter( 'style_loader_tag', array( $this, 'mark_css_for_analysis' ), 10, 2 );
	}

	/**
	 * Mark CSS for unused CSS analysis
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle The handle name.
	 * @return string Modified tag.
	 */
	public function mark_css_for_analysis( $tag, $handle ) {
		// Add data attribute for potential client-side analysis
		return str_replace( '<link ', '<link data-css-handle="' . esc_attr( $handle ) . '" ', $tag );
	}


	/**
	 * Inline critical CSS
	 */
	public function inline_critical_css() {
		if ( empty( $this->settings['critical_css'] ) ) {
			return;
		}

		$critical_css = wp_strip_all_tags( $this->settings['critical_css'] );
		$critical_css = $this->minify_css( $critical_css );

		echo '<style id="speed-matrix-critical-css">'
			. esc_html( $critical_css )
			. "</style>\n";
	}


	/**
	 * Combine JavaScript files
	 */
	public function combine_js_files() {
		global $wp_scripts;
		if ( ! is_object( $wp_scripts ) ) {
			return;
		}

		$scripts_to_combine = array();
		foreach ( $wp_scripts->queue as $handle ) {
			// Skip jQuery and excluded scripts
			if ( in_array( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) ) {
				continue;
			}

			// Check if excluded
			if ( $this->is_resource_excluded( $handle, 'js' ) ) {
				continue;
			}

			if ( isset( $wp_scripts->registered[ $handle ] ) ) {
				$script = $wp_scripts->registered[ $handle ];
				if ( $this->is_local_file( $script->src ) ) {
					$scripts_to_combine[] = $handle;
				}
			}
		}

		if ( empty( $scripts_to_combine ) ) {
			return;
		}

		$cache_key = md5( serialize( $scripts_to_combine ) . SPEED_MATRIX_VERSION );
		$cache_file = SPEED_MATRIX_CACHE_DIR . 'js/combined-' . $cache_key . '.js';
		$cache_url = SPEED_MATRIX_CACHE_URL . 'js/combined-' . $cache_key . '.js';

		if ( ! file_exists( $cache_file ) ) {
			$combined_js = '';
			foreach ( $scripts_to_combine as $handle ) {
				$script = $wp_scripts->registered[ $handle ];
				$js_path = $this->get_file_path( $script->src );
				if ( file_exists( $js_path ) && is_readable( $js_path ) ) {
					$js_content = file_get_contents( $js_path );
					$combined_js .= $this->minify_js( $js_content ) . ";\n";
				}
			}

			wp_mkdir_p( dirname( $cache_file ) );
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$wp_filesystem->put_contents( $cache_file, $combined_js, FS_CHMOD_FILE );
		}

		foreach ( $scripts_to_combine as $handle ) {
			wp_dequeue_script( $handle );
		}

		wp_enqueue_script( 'speed-matrix-combined-js', $cache_url, array( 'jquery' ), filemtime( $cache_file ), true );
	}

	/**
	 * Minify JavaScript
	 *
	 * @param string $js JavaScript content.
	 * @return string Minified JavaScript.
	 */
	private function minify_js( $js ) {
		// Remove single-line comments (but preserve URLs)
		$js = preg_replace( '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/', '', $js );
		// Remove whitespace
		$js = preg_replace( '/\s+/', ' ', $js );
		return trim( $js );
	}

	/**
	 * Minify Inline Js
	 */

	public function minify_inline_script_tags( $tag, $handle ) {

		if ( strpos( $tag, '<script' ) === false ) {
			return $tag;
		}

		$tag = preg_replace(
			'/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/',
			'',
			$tag
		);

		$tag = preg_replace( '/\s+/', ' ', $tag );

		return $tag;
	}

	/**
	 * Defer script loading
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The handle name.
	 * @return string Modified tag.
	 */
	public function defer_scripts( $tag, $handle ) {
		// Don't defer jQuery or excluded scripts
		if ( in_array( $handle, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) ) {
			return $tag;
		}

		// Check if excluded
		if ( $this->is_resource_excluded( $handle, 'js' ) ) {
			return $tag;
		}

		// Don't add defer if already present
		if ( strpos( $tag, 'defer' ) !== false || strpos( $tag, 'async' ) !== false ) {
			return $tag;
		}

		return str_replace( ' src', ' defer src', $tag );
	}

	/**
	 * Delay JavaScript execution
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The handle name.
	 * @param string $src    The script source.
	 * @return string Modified tag.
	 */
	public function delay_javascript_execution( $tag, $handle, $src ) {
		if ( empty( $this->delayed_scripts ) ) {
			return $tag;
		}

		$should_delay = false;
		foreach ( $this->delayed_scripts as $pattern ) {
			if ( ! empty( $pattern ) && ( strpos( $src, $pattern ) !== false || strpos( $tag, $pattern ) !== false ) ) {
				$should_delay = true;
				break;
			}
		}

		if ( $should_delay ) {
			$tag = str_replace( '<script', '<script type="speed-matrix-delayed"', $tag );
			$tag = str_replace( 'type="text/javascript"', 'type="speed-matrix-delayed"', $tag );
			$tag = str_replace( "type='text/javascript'", "type='speed-matrix-delayed'", $tag );
		}

		return $tag;
	}

	/**
	 * Add delay JavaScript loader script
	 */
	public function add_delay_js_script() {
		$timeout_ms = ( ! empty( $this->settings['delay_js_timeout'] ) ? intval( $this->settings['delay_js_timeout'] ) : 5 ) * 1000;
		?>
		<script id="speed-matrix-delay-js">
			!function () { var e = [], t = !1; function n() { if (!t) { t = !0; for (var n = 0; n < e.length; n++) { var a = document.createElement("script"); e[n].hasAttribute("src") && a.setAttribute("src", e[n].getAttribute("src")), e[n].hasAttribute("id") && a.setAttribute("id", e[n].getAttribute("id")), e[n].hasAttribute("class") && a.setAttribute("class", e[n].getAttribute("class")), a.type = "text/javascript", e[n].textContent && (a.textContent = e[n].textContent), e[n].parentNode.replaceChild(a, e[n]) } } } ["mouseover", "keydown", "touchstart", "touchmove", "wheel"].forEach(function (e) { window.addEventListener(e, n, { passive: !0 }) }), document.addEventListener("DOMContentLoaded", function () { var t = document.querySelectorAll('script[type="speed-matrix-delayed"]'); Array.prototype.forEach.call(t, function (t) { e.push(t) }), setTimeout(n, <?php echo absint( $timeout_ms ); ?>) }) }();
		</script>
		<?php
	}

	/**
	 * Lazy load images
	 *
	 * @param string $content Content with images.
	 * @return string Modified content.
	 */
	public function lazy_load_images( $content ) {
		if ( is_admin() || is_feed() || wp_is_json_request() ) {
			return $content;
		}

		$exclude_first = ! empty( $this->settings['exclude_first_images'] ) ? intval( $this->settings['exclude_first_images'] ) : 2;
		$image_count = 0;

		$content = preg_replace_callback(
			'/<img([^>]+?)>/i',
			function ( $matches ) use ( &$image_count, $exclude_first ) {
				$img = $matches[0];
				$image_count++;

				// Set fetchpriority="high" for first N images (LCP optimization)
				if ( $image_count <= $exclude_first ) {
					if ( strpos( $img, 'fetchpriority=' ) === false ) {
						$img = str_replace( '<img', '<img fetchpriority="high"', $img );
					}
					return $img;
				}

				// Add lazy loading for remaining images
				if ( strpos( $img, 'loading=' ) === false ) {
					$img = str_replace( '<img', '<img loading="lazy"', $img );
				}

				return $img;
			},
			$content
		);

		return $content;
	}

	/**
	 * Lazy load iframes
	 *
	 * @param string $content Content with iframes.
	 * @return string Modified content.
	 */
	public function lazy_load_iframes( $content ) {
		if ( is_admin() || is_feed() || wp_is_json_request() ) {
			return $content;
		}

		$content = preg_replace_callback(
			'/<iframe([^>]+?)>/i',
			function ( $matches ) {
				$iframe = $matches[0];
				// Add lazy loading if not present
				if ( strpos( $iframe, 'loading=' ) === false ) {
					$iframe = str_replace( '<iframe', '<iframe loading="lazy"', $iframe );
				}
				return $iframe;
			},
			$content
		);

		return $content;
	}

	/**
	 * Remove query strings from static resources
	 *
	 * @param string $src Resource URL.
	 * @return string Modified URL.
	 */
	public function remove_query_strings( $src ) {
		if ( strpos( $src, '?ver=' ) !== false ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Disable WordPress emojis
	 */
	private function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_dns_prefetch' ), 10, 2 );
	}

	/**
	 * Remove emoji TinyMCE plugin
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array Modified plugins.
	 */
	public function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		}
		return array();
	}

	/**
	 * Remove emoji DNS prefetch
	 *
	 * @param array  $urls          URLs.
	 * @param string $relation_type Relation type.
	 * @return array Modified URLs.
	 */
	public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$speed_matrix_emoji_svg_url = apply_filters( 'speed_matrix_emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
			$urls = array_diff( $urls, array( $speed_matrix_emoji_svg_url ) );
		}
		return $urls;
	}

	/**
	 * Disable WordPress embeds
	 */
	private function disable_embeds() {
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		add_filter( 'embed_oembed_discover', '__return_false' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_embeds_tinymce' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'disable_embeds_rewrites' ) );
	}

	/**
	 * Remove embed TinyMCE plugin
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array Modified plugins.
	 */
	public function disable_embeds_tinymce( $plugins ) {
		return array_diff( $plugins, array( 'wpembed' ) );
	}

	/**
	 * Remove embed rewrite rules
	 *
	 * @param array $rules Rewrite rules.
	 * @return array Modified rules.
	 */
	public function disable_embeds_rewrites( $rules ) {
		foreach ( $rules as $rule => $rewrite ) {
			if ( strpos( $rewrite, 'embed=true' ) !== false ) {
				unset( $rules[ $rule ] );
			}
		}
		return $rules;
	}

	/**
	 * Disable Dashicons on frontend for non-logged-in users
	 */
	public function disable_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_deregister_style( 'dashicons' );
		}
	}

	/**
	 * Remove jQuery Migrate
	 *
	 * @param WP_Scripts $scripts WP_Scripts object.
	 */
	public function disable_jquery_migrate( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];
			if ( $script->deps ) {
				$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
			}
		}
	}

	/**
	 * Convert uploaded images to WebP format
	 *
	 * @param array $metadata      Image metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public function convert_images_to_webp( $metadata, $attachment_id ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return $metadata;
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Convert original image
		$this->create_webp_image( $file_path );

		// Convert image sizes
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				$size_path = path_join( dirname( $file_path ), $size_data['file'] );
				if ( file_exists( $size_path ) ) {
					$this->create_webp_image( $size_path );
				}
			}
		}

		return $metadata;
	}

	/**
	 * Create WebP version of an image
	 *
	 * @param string $speed_matrix_file_path Path to image file.
	 * @return bool Success status.
	 */
	private function create_webp_image( $file_path ) {
	// Validate path is within uploads directory
	$upload_dir = wp_upload_dir();
	$real_path  = realpath( $file_path );
	
	if ( false === $real_path || 0 !== strpos( $real_path, $upload_dir['basedir'] ) ) {
		return false;
	}

	// Check file size
	$file_size = filesize( $real_path );
	if ( $file_size > 5 * 1024 * 1024 ) { // 5MB limit
		return false;
	}

	$image_type = wp_check_filetype( $real_path );
	$webp_path  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $real_path );

	// Skip if WebP already exists
	if ( file_exists( $webp_path ) ) {
		return true;
	}

	$quality = ! empty( $this->settings['webp_quality'] )
		? absint( $this->settings['webp_quality'] )
		: 80;

	$image = null;

	switch ( $image_type['type'] ) {
		case 'image/jpeg':
			$image = @imagecreatefromjpeg( $real_path );
			break;

		case 'image/png':
			$image = @imagecreatefrompng( $real_path );
			if ( $image ) {
				// Preserve transparency
				if ( function_exists( 'imagepalettetotruecolor' ) ) {
					imagepalettetotruecolor( $image );
				}
				imagealphablending( $image, true );
				imagesavealpha( $image, true );
			}
			break;

		default:
			return false;
	}

	if ( ! $image || ! is_resource( $image ) ) {
		return false;
	}

	$result = @imagewebp( $image, $webp_path, $quality );
	imagedestroy( $image );

	if ( $result && file_exists( $webp_path ) ) {
		// Use WP_Filesystem for chmod
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem ) {
			$wp_filesystem->chmod( $webp_path, 0644 );
		}
	}

	return (bool) $result;
}


	/**
	 * Serve WebP images when available
	 *
	 * @param string $filtered_image Full img tag with attributes.
	 * @param string $context        Additional context.
	 * @param int    $attachment_id  Image attachment ID.
	 * @return string Modified img tag.
	 */
	public function serve_webp_images( $filtered_image, $context, $attachment_id ) {
		// Only proceed if browser supports WebP
		if ( ! $this->browser_supports_webp() ) {
			return $filtered_image;
		}

		// Extract src attribute
		if ( ! preg_match( '/src=["\']([^"\']+)["\']/', $filtered_image, $matches ) ) {
			return $filtered_image;
		}

		$original_src = $matches[1];

		// Only process JPEG and PNG
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $original_src ) ) {
			return $filtered_image;
		}

		$webp_src = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $original_src );

		// Convert URL to path to check if WebP exists
		$upload_dir = wp_upload_dir();
		$webp_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $webp_src );

		if ( file_exists( $webp_path ) ) {
			$filtered_image = str_replace( $original_src, esc_url( $webp_src ), $filtered_image );
		}

		return $filtered_image;
	}

	/**
	 * Check if browser supports WebP
	 *
	 * @return bool Browser support status.
	 */
	private function browser_supports_webp() {
	if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
		return false;
	}
	
	$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
	return false !== strpos( $accept, 'image/webp' );
}

	/**
	 * Optimize Google Fonts loading
	 */
	private function optimize_google_fonts() {
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_google_fonts' ), 999 );
		add_action( 'wp_head', array( $this, 'add_optimized_google_fonts' ), 1 );
		add_filter( 'style_loader_tag', array( $this, 'add_font_display_swap' ), 10, 2 );
	}

	/**
	 * Dequeue original Google Fonts
	 */
	public function dequeue_google_fonts() {
		global $wp_styles;
		if ( ! is_object( $wp_styles ) ) {
			return;
		}

		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( strpos( $style->src, 'fonts.googleapis.com' ) !== false ) {
				wp_dequeue_style( $handle );
			}
		}
	}

	/**
	 * Add optimized Google Fonts with preconnect
	 */
	public function add_optimized_google_fonts() {
		global $wp_styles;

		if ( ! is_object( $wp_styles ) ) {
			return;
		}

		$handles = array();

		foreach ( $wp_styles->registered as $handle => $style ) {
			if ( ! empty( $style->src ) && strpos( $style->src, 'fonts.googleapis.com' ) !== false ) {
				$handles[ $handle ] = $style->src;
			}
		}

		if ( empty( $handles ) ) {
			return;
		}

		// Output preconnects (allowed).
		add_action(
			'wp_head',
			static function () {
				echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
				echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
			},
			1
		);

		// Re-enqueue Google Fonts with display=swap.
		foreach ( $handles as $handle => $src ) {
			wp_dequeue_style( $handle );

			$src = add_query_arg( 'display', 'swap', $src );

			wp_enqueue_style(
				$handle,
				esc_url( $src ),
				array(),
				SPEED_MATRIX_VERSION
			);
		}
	}


	/**
	 * Add font-display: swap to font faces
	 *
	 * @param string $tag    The link tag.
	 * @param string $handle The handle name.
	 * @return string Modified tag.
	 */
	public function add_font_display_swap( $tag, $handle ) {
		if ( strpos( $tag, 'fonts.googleapis.com' ) !== false || strpos( $tag, 'fonts.gstatic.com' ) !== false ) {
			$tag = str_replace( "rel='stylesheet'", "rel='stylesheet' media='print' onload=\"this.media='all'\"", $tag );
			$tag = str_replace( 'rel="stylesheet"', 'rel="stylesheet" media="print" onload="this.media=\'all\'"', $tag );
		}
		return $tag;
	}

	/**
	 * Add DNS prefetch headers
	 */
	public function add_dns_prefetch() {
		if ( empty( $this->settings['dns_prefetch_urls'] ) ) {
			return;
		}

		$urls = array_map( 'trim', explode( "\n", $this->settings['dns_prefetch_urls'] ) );
		foreach ( $urls as $url ) {
			if ( ! empty( $url ) ) {
				echo '<link rel="dns-prefetch" href="' . esc_url( $url ) . '">' . "\n";
			}
		}
	}

	/**
	 * Preload key requests
	 */
	public function preload_key_requests() {
		if ( empty( $this->settings['preload_urls'] ) ) {
			return;
		}

		$preloads = array_map( 'trim', explode( "\n", $this->settings['preload_urls'] ) );

		foreach ( $preloads as $preload ) {
			if ( empty( $preload ) ) {
				continue;
			}

			// Format: URL|type (e.g., /style.css|style or /script.js|script)
			$parts = explode( '|', $preload );
			$url = $parts[0];
			$type = isset( $parts[1] ) ? $parts[1] : 'script';

			$as_type = 'script';
			if ( $type === 'style' || strpos( $url, '.css' ) !== false ) {
				$as_type = 'style';
			} elseif ( $type === 'font' || preg_match( '/\.(woff2?|ttf|otf|eot)$/i', $url ) ) {
				$as_type = 'font';
			} elseif ( $type === 'image' || preg_match( '/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url ) ) {
				$as_type = 'image';
			}

			$crossorigin = ( $as_type === 'font' ) ? ' crossorigin="anonymous"' : '';

			echo '<link rel="preload" href="'
				. esc_url( $url )
				. '" as="'
				. esc_attr( $as_type )
				. '"'
				. esc_attr( $crossorigin )
				. '>' . "\n";
		}
	}


	/**
	 * Check if resource is excluded
	 *
	 * @param string $handle Handle name.
	 * @param string $type   Resource type (js or css).
	 * @return bool Exclusion status.
	 */
	private function is_resource_excluded( $handle, $type ) {
		$excluded = ( $type === 'js' ) ? $this->excluded_js : $this->excluded_css;

		if ( empty( $excluded ) ) {
			return false;
		}

		foreach ( $excluded as $pattern ) {
			if ( ! empty( $pattern ) && strpos( $handle, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if file is local
	 *
	 * @param string $src File source URL.
	 * @return bool Local file status.
	 */
	private function is_local_file( $src ) {
		if ( empty( $src ) ) {
			return false;
		}

		$site_url = site_url();
		$home_url = home_url();

		// Check if URL starts with site URL or is a relative path
		if ( strpos( $src, $site_url ) === 0 || strpos( $src, $home_url ) === 0 || strpos( $src, '//' ) !== 0 && strpos( $src, 'http' ) !== 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Get file system path from URL
	 *
	 * @param string $url File URL.
	 * @return string File path.
	 */
	private function get_file_path( $url ) {
		// Remove query strings
		$url = strtok( $url, '?' );

		// Convert URL to path
		$upload_dir = wp_upload_dir();
		$content_url = content_url();

		// Try various base URLs
		$base_urls = array(
			site_url(),
			home_url(),
			$content_url,
			$upload_dir['baseurl'],
		);

		$base_paths = array(
			ABSPATH,
			ABSPATH,
			WP_CONTENT_DIR,
			$upload_dir['basedir'],
		);

		foreach ( $base_urls as $index => $base_url ) {
			if ( strpos( $url, $base_url ) === 0 ) {
				$relative_path = str_replace( $base_url, '', $url );
				return $base_paths[ $index ] . ltrim( $relative_path, '/' );
			}
		}

		// Handle protocol-relative URLs
		if ( strpos( $url, '//' ) === 0 ) {
			$url = 'https:' . $url;
			return $this->get_file_path( $url );
		}

		// Handle relative paths
		if ( strpos( $url, '/' ) === 0 ) {
			return ABSPATH . ltrim( $url, '/' );
		}

		return '';
	}

	/**
	 * Clear all Speed Matrix cache
	 */
	public static function clear_cache() {
		$speed_matrix_cache_dirs = array(
			SPEED_MATRIX_CACHE_DIR . 'css/',
			SPEED_MATRIX_CACHE_DIR . 'js/',
		);

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		foreach ( $speed_matrix_cache_dirs as $dir ) {
			if ( $wp_filesystem->is_dir( $dir ) ) {
				$speed_matrix_files = glob( $dir . '*' );
				if ( is_array( $speed_matrix_files ) ) {
					foreach ( $speed_matrix_files as $speed_matrix_file ) {
						if ( $wp_filesystem->is_file( $speed_matrix_file ) ) {
							$wp_filesystem->delete( $speed_matrix_file );
						}
					}
				}
			}
		}

		return true;
	}


	/**
	 * Apply CDN URL unless excluded
	 */
	public function speed_matrix_check_url( $url ) {

		if ( empty( $this->settings['cdn_url'] ) ) {
			return $url;
		}

		// Skip excluded URLs
		foreach ( $this->excluded_urls as $excluded ) {
			if ( ! empty( $excluded ) && stripos( $url, $excluded ) !== false ) {
				return $url;
			}
		}

		$site_url = get_site_url();
		$cdn_url = untrailingslashit( trim( $this->settings['cdn_url'] ) );

		if ( strpos( $url, $site_url ) === 0 ) {
			$url = str_replace( $site_url, $cdn_url, $url );
		}

		return $url;
	}




	/**
	 * Output LCP image preload tag
	 */
	public function speed_matrix_get_lcp_image_url() {

		// LCP image URL from settings (manual override)
		if ( empty( $this->settings['lcp_image_url'] ) ) {
			return;
		}

		$lcp_url = trim( $this->settings['lcp_image_url'] );

		if ( ! $lcp_url ) {
			return;
		}

		// Check excluded URLs
		if ( ! empty( $this->excluded_urls ) ) {
			foreach ( $this->excluded_urls as $excluded ) {
				if ( stripos( $lcp_url, $excluded ) !== false ) {
					return; // Do not preload excluded LCP image
				}
			}
		}

		// Apply CDN if enabled
		if ( ! empty( $this->settings['cdn_url'] ) ) {
			$site_url = get_site_url();
			$cdn_url = trailingslashit( trim( $this->settings['cdn_url'] ) );
			$lcp_url = str_replace( $site_url, untrailingslashit( $cdn_url ), $lcp_url );
		}

		// Output preload tag
		echo '<link rel="preload" as="image" href="' . esc_url( $lcp_url ) . '" fetchpriority="high">' . "\n";
	}



	/**
	 * Preload font URLs
	 * Font URLs are added one per line in settings
	 */
	public function speed_matrix_preload_fonts() {

		// Skip admin, feeds, REST, AJAX
		if ( is_admin() || is_feed() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}


		if ( empty( $this->settings['font_urls'] ) ) {
			return;
		}

		$font_urls = array_filter(
			array_map( 'trim', explode( "\n", $this->settings['font_urls'] ) )
		);

		if ( empty( $font_urls ) ) {
			return;
		}

		foreach ( $font_urls as $font_url ) {

			// Skip excluded URLs
			foreach ( $this->excluded_urls as $excluded ) {
				if ( ! empty( $excluded ) && stripos( $font_url, $excluded ) !== false ) {
					continue 2;
				}
			}

			// Apply CDN
			if ( ! empty( $this->settings['cdn_url'] ) ) {
				$site_url = get_site_url();
				$cdn_url = untrailingslashit( trim( $this->settings['cdn_url'] ) );
				$font_url = str_replace( $site_url, $cdn_url, $font_url );
			}

			// Detect font type
			$type = '';
			if ( preg_match( '/\.woff2$/i', $font_url ) ) {
				$type = 'font/woff2';
			} elseif ( preg_match( '/\.woff$/i', $font_url ) ) {
				$type = 'font/woff';
			} elseif ( preg_match( '/\.ttf$/i', $font_url ) ) {
				$type = 'font/ttf';
			} elseif ( preg_match( '/\.otf$/i', $font_url ) ) {
				$type = 'font/otf';
			}

			echo '<link rel="preload" href="' . esc_url( $font_url ) . '" as="font" type="' . esc_attr( $type ) . '" crossorigin>' . "\n";
		}
	}




	/**
	 * Handle Google Fonts loading method
	 */
	private function handle_google_fonts_method() {

		$method = $this->settings['google_fonts_method'];

		switch ( $method ) {

			case 'combine':
				$this->optimize_google_fonts(); // reuse your existing logic
				break;

			case 'async':
				add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_google_fonts' ), 999 );
				add_action( 'wp_head', array( $this, 'add_optimized_google_fonts' ), 1 );
				add_filter( 'style_loader_tag', array( $this, 'add_font_display_swap' ), 10, 2 );
				break;


			case 'preconnect':
				add_action( 'wp_head', array( $this, 'add_google_fonts_preconnect' ), 1 );
				break;

			case 'disable':
				add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_google_fonts' ), 999 );
				add_filter( 'style_loader_src', array( $this, 'disable_google_fonts_src' ), 10, 2 );
				add_filter( 'wp_resource_hints', array( $this, 'remove_google_fonts_hints' ), 10, 2 );
				break;
		}
	}


	/**
	 * Add Google Fonts preconnect
	 */
	public function add_google_fonts_preconnect() {
		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}

	/**
	 * Disable Google Fonts loading
	 */
	public function disable_google_fonts_src( $src, $handle ) {
		if ( strpos( $src, 'fonts.googleapis.com' ) !== false ) {
			return false;
		}
		return $src;
	}

	/**
	 * Remove Google Fonts resource hints
	 */
	public function remove_google_fonts_hints( $urls, $relation_type ) {

		if ( in_array( $relation_type, array( 'dns-prefetch', 'preconnect' ), true ) ) {
			foreach ( $urls as $key => $url ) {
				if (
					strpos( $url, 'fonts.googleapis.com' ) !== false ||
					strpos( $url, 'fonts.gstatic.com' ) !== false
				) {
					unset( $urls[ $key ] );
				}
			}
		}

		return $urls;
	}


	/**
	 * remove unused JS
	 */
	public function removed_unused_css_script() {
		wp_enqueue_script(
			$this->plugin_name . '-public',
			SPEED_MATRIX_PLUGIN_URL . 'assets/js/remove-unused.js',
			array(),
			$this->version,
			true
		);
	}
}