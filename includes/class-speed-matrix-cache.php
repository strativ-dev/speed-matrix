<?php
/**
 * Speed Matrix Cache Class
 * 
 * @package Speed_Matrix
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speed Matrix Cache Class
 */
class Speed_Matrix_Cache {

	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	private $speed_matrix_settings;

	/**
	 * Cache directory path
	 *
	 * @var string
	 */
	private $speed_matrix_cache_dir;

	/**
	 * Excluded URLs
	 *
	 * @var array
	 */
	private $excluded_urls = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'speed_matrix_settings', array() );
		$this->cache_dir = $this->get_cache_base_dir() . 'html/';

		if ( ! empty( $this->settings['exclude_urls'] ) ) {
			$this->excluded_urls = array_map( 'trim', explode( "\n", $this->settings['exclude_urls'] ) );
		}
	}

	/**
	 * Get cache base directory
	 *
	 * @return string
	 */
	private function get_cache_base_dir() {
		// Get upload directory
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/speed-matrix-cache/';

		/**
		 * Filter cache directory path
		 * 
		 * @since 1.0.0
		 * @param string $base_dir Cache base directory path
		 */
		return apply_filters( 'speed_matrix_cache_dir', $base_dir );
	}

	/**
	 * Initialize cache hooks
	 */
	public function serve_cache() {

		// Fast fail.
		if ( ! $this->should_cache() ) {
			return;
		}

		$cache_file = $this->get_cache_file();

		if ( ! is_readable( $cache_file ) ) {
			return;
		}

		$cache_time = filemtime( $cache_file );
		$cache_lifetime = ! empty( $this->settings['cache_lifetime'] )
			? absint( $this->settings['cache_lifetime'] )
			: 3600;

		$cache_age = time() - $cache_time;

		// Load WP_Filesystem early (needed for delete + read).
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return;
		}

		// Cache expired.
		if ( $cache_age >= $cache_lifetime ) {
			if ( $wp_filesystem->exists( $cache_file ) ) {
				$wp_filesystem->delete( $cache_file );
			}
			return;
		}

		// Clean all output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Send headers.
		if ( ! headers_sent() ) {
			header( 'X-Speed-Matrix-Cache: HIT' );
			header( 'X-Cache-Age: ' . absint( $cache_age ) );
			header(
				'Cache-Control: public, max-age=' . absint(
					max( 0, $cache_lifetime - $cache_age )
				)
			);
			header( 'Content-Type: text/html; charset=UTF-8' );
		}

		// HEAD requests should not output body.		
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

		if ( $request_method === 'HEAD' ) {
			exit;
		}


		// Output cached HTML.
		$contents = $wp_filesystem->get_contents( $cache_file );

		if ( false !== $contents ) {
			// Append cache info at the bottom
			$contents .= "\n<!-- Cached by Speed Matrix on " . current_time( 'mysql' ) . " -->";
			$contents .= "\n<!-- Served from Speed Matrix Cache -->";

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cached HTML is generated internally and must be served raw.
			echo wp_kses_post( $contents );

		}

		exit;
	}




	/**
	 * Save page to cache
	 */
	public function save_cache() {
		if ( ! $this->should_cache() ) {
			return;
		}

		// Get the output buffer content
		$buffer = '';

		// Try to get buffer content
		if ( ob_get_level() > 0 ) {
			$buffer = ob_get_contents();
		}

		// Validate buffer
		if ( empty( $buffer ) || strlen( $buffer ) < 255 ) {
			return;
		}

		// Check if it's valid HTML
		$is_html = (
			stripos( $buffer, '<html' ) !== false ||
			stripos( $buffer, '<!doctype' ) !== false ||
			stripos( $buffer, '<body' ) !== false
		);

		if ( ! $is_html ) {
			return;
		}

		// Don't cache if it contains error messages
		if ( stripos( $buffer, '<html><body><h1>Fatal error' ) !== false ) {
			return;
		}

		$cache_file = $this->get_cache_file();
		$speed_matrix_cache_dir = dirname( $cache_file );

		// Initialize WP Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create cache directory if needed
		if ( ! $wp_filesystem->is_dir( $speed_matrix_cache_dir ) ) {
			wp_mkdir_p( $speed_matrix_cache_dir );
		}

		// Check if directory is writable
		if ( ! $wp_filesystem->is_writable( $speed_matrix_cache_dir ) ) {
			return;
		}

		// Add cache marker comment
		$buffer .= "\n<!-- Cached by Speed Matrix on " . current_time( 'mysql' ) . " -->";


		// Save cache file using WP Filesystem
		$saved = $wp_filesystem->put_contents( $cache_file, $buffer, FS_CHMOD_FILE );

		if ( false !== $saved ) {
			// File saved successfully
			do_action( 'speed_matrix_cache_saved', $cache_file );
		}
	}



	/**
	 * Check if request should be cached
	 *
	 * @return bool
	 */
	private function should_cache() {
		// Don't cache if disabled
		if ( empty( $this->settings['enable_page_cache'] ) || $this->settings['enable_page_cache'] !== '1' ) {
			return false;
		}

		// Don't cache admin, AJAX, cron, or REST API
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		// Only cache GET requests without POST data
		if ( ! $this->is_cacheable_request() ) {
			return false;
		}

		// Don't cache if there are non-UTM query parameters
		if ( ! $this->has_only_utm_params() ) {
			return false;
		}

		// Don't cache logged-in users (unless enabled)
		if ( is_user_logged_in() && empty( $this->settings['cache_logged_in'] ) ) {
			return false;
		}

		// Don't cache excluded URLs, 404 pages, or search results
		if ( $this->is_excluded_url() || is_404() || is_search() ) {
			return false;
		}

		/**
		 * Filter whether the current request should be cached
		 * 
		 * @since 1.0.0
		 * @param bool $should_cache Whether to cache this request
		 */
		return apply_filters( 'speed_matrix_should_cache', true );
	}

	/**
	 * Check if the current request is cacheable (GET with no POST data)
	 *
	 * @return bool
	 */
	private function is_cacheable_request() {
		// Check request method
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Comparing only
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';

		if ( 'GET' !== $request_method ) {
			return false;
		}

		// Check for POST data
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Existence check for cache decision only
		if ( ! empty( $_POST ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if request has only UTM parameters (or no parameters)
	 *
	 * @return bool True if only UTM params or no params, false otherwise
	 */
	private function has_only_utm_params() {
		// Nonce verification not required: Only checking parameter names (not values) 
		// for cache decision. No data processing, saving, or state changes occur.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache logic
		if ( empty( $_GET ) ) {
			return true; // No query params is allowed
		}

		$allowed_utm_params = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );

		// Get and sanitize parameter names
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking names for cache decision
		$query_param_names = array_keys( $_GET );
		$sanitized_param_names = array_map( 'sanitize_key', $query_param_names );

		// Check if there are any non-UTM parameters
		$non_utm_params = array_diff( $sanitized_param_names, $allowed_utm_params );

		return empty( $non_utm_params );
	}

	/**
	 * Check if current URL is excluded
	 *
	 * @return bool
	 */
	private function is_excluded_url() {
		// Using sanitize_text_field and wp_unslash as recommended by WordPress.org
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $current_url ) ) {
			return false;
		}

		// Default excluded patterns
		$default_excluded = array(
			'/wp-admin',
			'/wp-login',
			'/wp-json',
			'/xmlrpc.php',
			'/feed',
			'/cart',
			'/checkout',
			'/my-account',
			'?s=',
			'/preview=',
			'/wp-cron.php',
		);

		$all_excluded = array_merge( $default_excluded, $this->excluded_urls );

		foreach ( $all_excluded as $excluded ) {
			if ( ! empty( $excluded ) && strpos( $current_url, $excluded ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get cache file path for current request
	 *
	 * @return string
	 */
	private function get_cache_file() {
		$url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		// Remove query string
		$url = strtok( $url, '?' );

		// Create hash for uniqueness
		$hash = md5( $url );

		// Separate cache for mobile devices if enabled
		if ( ! empty( $this->settings['cache_mobile_separate'] ) && $this->settings['cache_mobile_separate'] === '1' ) {
			if ( wp_is_mobile() ) {
				$hash .= '-mobile';
			}
		}

		// Create safe filename from URL path
		$path = trim( $url, '/' );
		$path = empty( $path ) ? 'index' : $path;
		$path = preg_replace( '/[^a-z0-9_\-\/]/i', '-', $path );
		$path = preg_replace( '/[\-\/]+/', '-', $path );
		$path = substr( $path, 0, 200 );
		$path = trim( $path, '-' );

		$cache_file = $this->cache_dir . $path . '-' . $hash . '.html';

		/**
		 * Filter cache file path
		 * 
		 * @since 1.0.0
		 * @param string $cache_file Cache file path
		 * @param string $url Original URL
		 */
		return apply_filters( 'speed_matrix_cache_file', $cache_file, $url );
	}

	/**
	 * Clear cache when post is saved
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_post_cache( $post_id ) {
		if ( ! $post_id ) {
			return;
		}

		// Don't clear on autosave or revision
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Clear the post page itself
		$post_url = get_permalink( $post_id );
		if ( $post_url ) {
			$this->clear_cache_by_url( $post_url );
		}

		// Clear homepage
		$this->clear_cache_by_url( home_url( '/' ) );

		// Clear archives for posts
		$post_type = get_post_type( $post_id );
		if ( 'post' === $post_type ) {
			// Clear categories
			$categories = get_the_category( $post_id );
			if ( is_array( $categories ) ) {
				foreach ( $categories as $category ) {
					$cat_link = get_category_link( $category->term_id );
					if ( $cat_link ) {
						$this->clear_cache_by_url( $cat_link );
					}
				}
			}

			// Clear tags
			$tags = get_the_tags( $post_id );
			if ( is_array( $tags ) ) {
				foreach ( $tags as $tag ) {
					$tag_link = get_tag_link( $tag->term_id );
					if ( $tag_link ) {
						$this->clear_cache_by_url( $tag_link );
					}
				}
			}

			// Clear blog page if set
			$posts_page = get_option( 'page_for_posts' );
			if ( $posts_page ) {
				$blog_url = get_permalink( $posts_page );
				if ( $blog_url ) {
					$this->clear_cache_by_url( $blog_url );
				}
			}
		}

		do_action( 'speed_matrix_post_cache_cleared', $post_id );
	}

	/**
	 * Clear cache when comment is posted
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function clear_post_cache_by_comment( $comment_id ) {
		$comment = get_comment( $comment_id );
		if ( $comment && ! empty( $comment->comment_post_ID ) ) {
			$this->clear_post_cache( $comment->comment_post_ID );
		}
	}

	/**
	 * Clear cache by URL
	 *
	 * @param string $url URL to clear cache for.
	 */
	private function clear_cache_by_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			$path = '/';
		}

		$cache_file = $this->get_cache_file_from_path( $path );

		// Initialize WP Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Delete desktop version
		if ( $wp_filesystem->exists( $cache_file ) ) {
			wp_delete_file( $cache_file );
		}

		// Delete mobile version
		$mobile_cache = str_replace( '.html', '-mobile.html', $cache_file );
		if ( $wp_filesystem->exists( $mobile_cache ) ) {
			wp_delete_file( $mobile_cache );
		}
	}

	/**
	 * Get cache file path from URL path
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private function get_cache_file_from_path( $path ) {
		$hash = md5( $path );
		$url_path = trim( $path, '/' );
		$url_path = empty( $url_path ) ? 'index' : $url_path;
		$url_path = preg_replace( '/[^a-z0-9_\-\/]/i', '-', $url_path );
		$url_path = preg_replace( '/[\-\/]+/', '-', $url_path );
		$url_path = substr( $url_path, 0, 200 );
		$url_path = trim( $url_path, '-' );

		return $this->cache_dir . $url_path . '-' . $hash . '.html';
	}

	/**
	 * Clear all cache
	 */
	public function clear_all_cache() {
		self::clear_all_static();
	}

	/**
	 * Static method to clear all cache
	 */
	public static function clear_all_static() {
		$upload_dir = wp_upload_dir();
		$cache_base = $upload_dir['basedir'] . '/speed-matrix-cache/';

		if ( ! is_dir( $cache_base ) ) {
			return;
		}

		// Initialize WP Filesystem
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Clear HTML cache
		$html_dir = $cache_base . 'html/';
		if ( $wp_filesystem->is_dir( $html_dir ) ) {
			self::delete_directory_contents( $html_dir );
		}

		// Clear CSS cache
		$css_dir = $cache_base . 'css/';
		if ( $wp_filesystem->is_dir( $css_dir ) ) {
			self::delete_directory_contents( $css_dir );
		}

		// Clear JS cache
		$js_dir = $cache_base . 'js/';
		if ( $wp_filesystem->is_dir( $js_dir ) ) {
			self::delete_directory_contents( $js_dir );
		}

		do_action( 'speed_matrix_all_cache_cleared' );
	}

	/**
	 * Recursively delete directory contents
	 *
	 * @param string $dir Directory path.
	 */
	private static function delete_directory_contents( $dir ) {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->is_dir( $dir ) || ! $wp_filesystem->is_writable( $dir ) ) {
			return;
		}

		$speed_matrix_files = $wp_filesystem->dirlist( $dir, true, true );

		if ( ! is_array( $speed_matrix_files ) ) {
			return;
		}

		foreach ( $speed_matrix_files as $speed_matrix_file ) {
			$speed_matrix_file_path = trailingslashit( $dir ) . $speed_matrix_file['name'];

			if ( 'd' === $speed_matrix_file['type'] ) {
				// Recursively delete directory
				self::delete_directory_contents( $speed_matrix_file_path );
				$wp_filesystem->rmdir( $speed_matrix_file_path );
			} else {
				// Delete file
				wp_delete_file( $speed_matrix_file_path );
			}
		}
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public static function get_cache_stats() {
		$upload_dir = wp_upload_dir();
		$cache_base = $upload_dir['basedir'] . '/speed-matrix-cache/';

		$stats = array(
			'files' => 0,
			'size' => 0,
			'html_files' => 0,
			'css_files' => 0,
			'js_files' => 0,
		);

		if ( ! is_dir( $cache_base ) ) {
			return $stats;
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Count HTML files
		$html_dir = $cache_base . 'html/';
		if ( $wp_filesystem->is_dir( $html_dir ) ) {
			$speed_matrix_files = $wp_filesystem->dirlist( $html_dir, true, true );
			if ( is_array( $speed_matrix_files ) ) {
				foreach ( $speed_matrix_files as $speed_matrix_file ) {
					if ( 'f' === $speed_matrix_file['type'] && preg_match( '/\.html$/', $speed_matrix_file['name'] ) ) {
						$stats['html_files']++;
						$stats['files']++;
						$stats['size'] += $speed_matrix_file['size'];
					}
				}
			}
		}

		// Count CSS files
		$css_dir = $cache_base . 'css/';
		if ( $wp_filesystem->is_dir( $css_dir ) ) {
			$speed_matrix_files = $wp_filesystem->dirlist( $css_dir, false, false );
			if ( is_array( $speed_matrix_files ) ) {
				foreach ( $speed_matrix_files as $speed_matrix_file ) {
					if ( 'f' === $speed_matrix_file['type'] && preg_match( '/\.css$/', $speed_matrix_file['name'] ) ) {
						$stats['css_files']++;
						$stats['files']++;
						$stats['size'] += $speed_matrix_file['size'];
					}
				}
			}
		}

		// Count JS files
		$js_dir = $cache_base . 'js/';
		if ( $wp_filesystem->is_dir( $js_dir ) ) {
			$speed_matrix_files = $wp_filesystem->dirlist( $js_dir, false, false );
			if ( is_array( $speed_matrix_files ) ) {
				foreach ( $speed_matrix_files as $speed_matrix_file ) {
					if ( 'f' === $speed_matrix_file['type'] && preg_match( '/\.js$/', $speed_matrix_file['name'] ) ) {
						$stats['js_files']++;
						$stats['files']++;
						$stats['size'] += $speed_matrix_file['size'];
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Format bytes to human readable size
	 *
	 * @param int $bytes Size in bytes.
	 * @param int $precision Decimal precision.
	 * @return string Formatted size
	 */
	public static function format_bytes( $bytes, $precision = 2 ) {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

		$bytes = max( $bytes, 0 );
		$pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow = min( $pow, count( $units ) - 1 );

		$bytes /= pow( 1024, $pow );

		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	/**
	 * Check if cache is working
	 *
	 * @return bool
	 */
	public static function is_cache_working() {
		$upload_dir = wp_upload_dir();
		$cache_base = $upload_dir['basedir'] . '/speed-matrix-cache/';
		$test_file = $cache_base . 'test.txt';
		$test_dir = dirname( $test_file );

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create directory if needed
		if ( ! $wp_filesystem->is_dir( $test_dir ) ) {
			wp_mkdir_p( $test_dir );
		}

		// Check if directory is writable
		if ( ! $wp_filesystem->is_writable( $test_dir ) ) {
			return false;
		}

		// Try to write test file
		$test_content = 'Speed Matrix Cache Test - ' . time();
		$result = $wp_filesystem->put_contents( $test_file, $test_content, FS_CHMOD_FILE );

		if ( false !== $result ) {
			// Try to read it back
			$read_content = $wp_filesystem->get_contents( $test_file );

			if ( $wp_filesystem->exists( $test_file ) ) {
				wp_delete_file( $test_file );
			}

			return ( $read_content === $test_content );
		}

		return false;
	}
}