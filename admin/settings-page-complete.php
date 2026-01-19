<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Complete settings with ALL features
$speed_matrix_default_settings = array(
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
	'disable_for_admins' => '1',
	'test_mode' => '0',
);

$speed_matrix_settings = wp_parse_args( get_option( 'speed_matrix_settings', array() ), $speed_matrix_default_settings );

// Handle form submission
$speed_matrix_show_success = false;
if ( isset( $_POST['speed_matrix_save_settings'] ) && check_admin_referer( 'speed_matrix_settings_nonce', 'speed_matrix_nonce' ) ) {
	$speed_matrix_new_settings = array();
	$speed_matrix_text_fields = array( 'optimization_preset', 'cache_expiry', 'delay_js_timeout', 'exclude_first_images', 'google_fonts_method', 'cleanup_frequency', 'lcp_image_url', 'cdn_url' );
	foreach ( $speed_matrix_text_fields as $speed_matrix_field ) {
		$speed_matrix_new_settings[ $speed_matrix_field ] = isset( $_POST[ $speed_matrix_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $speed_matrix_field ] ) ) : $speed_matrix_default_settings[ $speed_matrix_field ];
	}
	$speed_matrix_textarea_fields = array( 'delay_js_patterns', 'exclude_js', 'exclude_css', 'exclude_urls', 'dns_prefetch_urls', 'font_urls', 'critical_css' );
	foreach ( $speed_matrix_textarea_fields as $speed_matrix_field ) {
		$speed_matrix_new_settings[ $speed_matrix_field ] = isset( $_POST[ $speed_matrix_field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $speed_matrix_field ] ) ) : $speed_matrix_default_settings[ $speed_matrix_field ];
	}
	$speed_matrix_checkbox_fields = array_keys( $speed_matrix_default_settings );
	$speed_matrix_checkbox_fields = array_diff( $speed_matrix_checkbox_fields, $speed_matrix_text_fields, $speed_matrix_textarea_fields );
	foreach ( $speed_matrix_checkbox_fields as $speed_matrix_field ) {
		$speed_matrix_new_settings[ $speed_matrix_field ] = isset( $_POST[ $speed_matrix_field ] ) ? '1' : '0';
	}
	update_option( 'speed_matrix_settings', $speed_matrix_new_settings );
	$speed_matrix_settings = $speed_matrix_new_settings;
	$speed_matrix_show_success = true;
}

// Handle cache clear
if (
	isset( $_POST['speed_matrix_clear_cache'] ) &&
	check_admin_referer( 'speed_matrix_clear_cache_nonce', 'speed_matrix_clear_nonce' )
) {
	$speed_matrix_cache_dir = SPEED_MATRIX_CACHE_DIR;

	if ( is_dir( $speed_matrix_cache_dir ) ) {
		$speed_matrix_files = glob( $speed_matrix_cache_dir . '{,*/,*/*/}*.{html,css,js}', GLOB_BRACE );

		if ( ! empty( $speed_matrix_files ) ) {
			foreach ( $speed_matrix_files as $speed_matrix_file ) {
				if ( is_file( $speed_matrix_file ) ) {
					wp_delete_file( $speed_matrix_file );
				}
			}
		}
	}
}


// Get database statistics

// Count post revisions and auto-drafts
$speed_matrix_all_post_counts = wp_count_posts();
$speed_matrix_post_revisions = isset( $speed_matrix_all_post_counts->revision ) ? $speed_matrix_all_post_counts->revision : 0;
$speed_matrix_auto_drafts = isset( $speed_matrix_all_post_counts->{'auto-draft'} ) ? $speed_matrix_all_post_counts->{'auto-draft'} : 0;

// Count spam and trash comments
$speed_matrix_comment_counts = wp_count_comments();
$speed_matrix_spam_comments = $speed_matrix_comment_counts->spam;
$speed_matrix_trash_comments = $speed_matrix_comment_counts->trash;

// Count transients (cached for 1 hour)
$speed_matrix_transients = wp_cache_get( 'speed_matrix_transients_count' );


if ( false === $speed_matrix_transients ) {
	$speed_matrix_all_options = wp_load_alloptions(); // gets all autoloaded options
	$speed_matrix_transient_count = 0;

	foreach ( $speed_matrix_all_options as $speed_matrix_option_name => $speed_matrix_value ) {
		if ( strpos( $speed_matrix_option_name, '_transient_' ) === 0 ) {
			$speed_matrix_transient_count++;
		}
	}

	$speed_matrix_transients = $speed_matrix_transient_count;
	wp_cache_set( 'speed_matrix_transients_count', $speed_matrix_transients, '', 3600 );
}

?>

<div class="wrap speed-matrix-wrapper">
	<h1 class="speed-matrix-plugin-header">
		<img src="<?php echo esc_url( SPEED_MATRIX_PLUGIN_URL . 'assets/images/speed-matrix-logo.png' ); ?>"
			alt="<?php esc_attr_e( 'Speed Matrix Logo', 'speed-matrix' ); ?>" width="32" height="32">
		<?php esc_html_e( 'Speed Matrix Settings', 'speed-matrix' ); ?>
	</h1>


	<?php if ( $speed_matrix_show_success ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><strong>
					<?php esc_html_e( 'Settings saved successfully!', 'speed-matrix' ); ?>
				</strong></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_POST['speed_matrix_clear_cache'] ) ) : ?>

		<div class="notice notice-success is-dismissible">
			<p><strong>
					<?php esc_html_e( 'Cache cleared successfully!', 'speed-matrix' ); ?>
				</strong></p>
		</div>
	<?php endif; ?>

	<div class="speed-matrix-container">
		<div class="speed-matrix-sidebar">
			<div class="sidebar-section">
				<div class="plugin-status active">
					<span class="status-dot"></span>
					<span class="status-text">
						<?php echo esc_html__( 'Active', 'speed-matrix' ); ?>
					</span>
				</div>
			</div>

			<nav class="sidebar-nav">
				<a href="#dashboard" class="nav-item active" data-tab="dashboard">
					<span class="dashicons dashicons-dashboard"></span>
					<?php esc_html_e( 'Dashboard', 'speed-matrix' ); ?>
				</a>
				<a href="#cache" class="nav-item" data-tab="cache">
					<span class="dashicons dashicons-performance"></span>
					<?php esc_html_e( 'Cache', 'speed-matrix' ); ?>
				</a>
				<a href="#file-optimization" class="nav-item" data-tab="file-optimization">
					<span class="dashicons dashicons-media-code"></span>
					<?php esc_html_e( 'File Optimization', 'speed-matrix' ); ?>
				</a>
				<a href="#media" class="nav-item" data-tab="media">
					<span class="dashicons dashicons-format-image"></span>
					<?php esc_html_e( 'Media', 'speed-matrix' ); ?>
				</a>
				<a href="#preloading" class="nav-item" data-tab="preloading">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Preloading', 'speed-matrix' ); ?>
				</a>
				<a href="#advanced" class="nav-item" data-tab="advanced">
					<span class="dashicons dashicons-admin-settings"></span>
					<?php esc_html_e( 'Advanced', 'speed-matrix' ); ?>
				</a>
				<a href="#database" class="nav-item" data-tab="database">
					<span class="dashicons dashicons-database"></span>
					<?php esc_html_e( 'Database', 'speed-matrix' ); ?>
				</a>
				<a href="#tools" class="nav-item" data-tab="tools">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Tools', 'speed-matrix' ); ?>
				</a>
			</nav>

			<div class="sidebar-footer">
				<form method="post" action="">
					<?php wp_nonce_field( 'speed_matrix_clear_cache_nonce', 'speed_matrix_clear_nonce' ); ?>
					<button type="submit" name="speed_matrix_clear_cache" class="button button-secondary button-block">
						<?php esc_html_e( 'Clear Cache', 'speed-matrix' ); ?>
					</button>
				</form>
			</div>
		</div>

		<div class="speed-matrix-content">
			<form method="post" action="" id="speed-matrix-form">
				<?php wp_nonce_field( 'speed_matrix_settings_nonce', 'speed_matrix_nonce' ); ?>

				<!-- Dashboard Tab -->
				<div id="dashboard" class="content-tab active">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Dashboard', 'speed-matrix' ); ?>
						</h2>
					</div>

					<div class="sidebar-section">
						<div class="plugin-status active">
							<span class="status-dot"></span>
							<span class="status-text">
								<?php echo esc_html__( 'Active', 'speed-matrix' ); ?>
							</span>
						</div>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Quick Start Guide', 'speed-matrix' ); ?>
						</h3>
						<ol class="quick-guide">
							<li>
								<?php esc_html_e( 'Go to Advanced tab and select a preset', 'speed-matrix' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Enable Page Caching in Cache tab', 'speed-matrix' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Click Save Changes', 'speed-matrix' ); ?>
							</li>
							<li>
								<?php esc_html_e( 'Test your site', 'speed-matrix' ); ?>
							</li>
						</ol>
					</div>
				</div>

				<!-- Cache Tab -->
				<div id="cache" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Cache Settings', 'speed-matrix' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Page caching creates static HTML files for instant loading', 'speed-matrix' ); ?>
						</p>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Page Caching', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="enable_page_cache">
										<?php esc_html_e( 'Enable Page Caching', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="enable_page_cache" id="enable_page_cache" value="1"
											<?php checked( $speed_matrix_settings['enable_page_cache'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Turn this ON first! Creates static HTML files for instant page loads. Required for CSS/JS combining to work properly.', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="cache_mobile_separate">
										<?php esc_html_e( 'Separate Mobile Cache', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="cache_mobile_separate" id="cache_mobile_separate"
											value="1" <?php checked( $speed_matrix_settings['cache_mobile_separate'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Create separate cache for mobile devices (recommended)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>

						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Browser Cache', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="enable_browser_cache">
										<?php esc_html_e( 'Enable Browser Caching', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="enable_browser_cache" id="enable_browser_cache"
											value="1" <?php checked( $speed_matrix_settings['enable_browser_cache'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Cache files in visitor\'s browser', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="cache_expiry">
										<?php esc_html_e( 'Cache Expiry (seconds)', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<input type="number" name="cache_expiry" id="cache_expiry"
										value="<?php echo esc_attr( $speed_matrix_settings['cache_expiry'] ); ?>"
										min="0" class="small-text">
									<p class="description">
										<?php esc_html_e( '31536000 = 1 year (recommended)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>

						</table>
					</div>
				</div>

				<!-- File Optimization Tab -->
				<div id="file-optimization" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'File Optimization', 'speed-matrix' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Optimize CSS, JavaScript, and HTML files', 'speed-matrix' ); ?>
						</p>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'CSS Optimization', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="minify_css">
										<?php esc_html_e( 'Minify CSS', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="minify_css" id="minify_css" value="1" <?php checked( $speed_matrix_settings['minify_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove whitespace and comments (safe)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="combine_css">
										<?php esc_html_e( 'Combine CSS Files', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="combine_css" id="combine_css" value="1" <?php checked( $speed_matrix_settings['combine_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( '⚠️ Requires page caching. May break some themes. Test carefully!', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="async_css">
										<?php esc_html_e( 'Load CSS Asynchronously', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="async_css" id="async_css" value="1" <?php checked( $speed_matrix_settings['async_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( '✨ Eliminates render-blocking CSS (recommended)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="remove_unused_css">
										<?php esc_html_e( 'Remove Unused CSS', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="remove_unused_css" id="remove_unused_css" value="1"
											<?php checked( $speed_matrix_settings['remove_unused_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( '⚠️ Advanced feature. Can reduce CSS by 70% but may break styling.', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="inline_critical_css">
										<?php esc_html_e( 'Inline Critical CSS', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="inline_critical_css" id="inline_critical_css"
											value="1" <?php checked( $speed_matrix_settings['inline_critical_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Inline above-the-fold CSS for faster rendering', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="critical_css">
										<?php esc_html_e( 'Critical CSS', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="critical_css" id="critical_css" rows="5"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['critical_css'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'CSS code to inline in the head section', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="exclude_css">
										<?php esc_html_e( 'Exclude CSS Files', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="exclude_css" id="exclude_css" rows="3"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['exclude_css'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'CSS files to exclude from optimization (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'JavaScript Optimization', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="minify_js">
										<?php esc_html_e( 'Minify JavaScript', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="minify_js" id="minify_js" value="1" <?php checked( $speed_matrix_settings['minify_js'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove whitespace and comments (safe)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="combine_js">
										<?php esc_html_e( 'Combine JavaScript Files', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="combine_js" id="combine_js" value="1" <?php checked( $speed_matrix_settings['combine_js'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( '⚠️ Requires page caching. May break functionality. Test carefully!', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="defer_js">
										<?php esc_html_e( 'Defer JavaScript', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="defer_js" id="defer_js" value="1" <?php checked( $speed_matrix_settings['defer_js'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( '✨ Load JS without blocking page render (safe, recommended)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="exclude_jquery">
										<?php esc_html_e( 'Exclude jQuery from Optimization', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="exclude_jquery" id="exclude_jquery" value="1" <?php checked( $speed_matrix_settings['exclude_jquery'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Recommended! Prevents jQuery-related errors', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="delay_js_execution">
										<?php esc_html_e( 'Delay JavaScript Execution', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="delay_js_execution" id="delay_js_execution"
											value="1" <?php checked( $speed_matrix_settings['delay_js_execution'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Delay non-critical JS until user interaction', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="delay_js_timeout">
										<?php esc_html_e( 'Delay Timeout (seconds)', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<input type="number" name="delay_js_timeout" id="delay_js_timeout"
										value="<?php echo esc_attr( $speed_matrix_settings['delay_js_timeout'] ); ?>"
										min="1" max="30" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Wait time before loading delayed scripts (default: 5)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="delay_js_patterns">
										<?php esc_html_e( 'Scripts to Delay', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="delay_js_patterns" id="delay_js_patterns" rows="5"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['delay_js_patterns'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'JavaScript patterns to delay (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="exclude_js">
										<?php esc_html_e( 'Exclude JavaScript Files', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="exclude_js" id="exclude_js" rows="3"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['exclude_js'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'JavaScript files to exclude from ALL optimizations (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'HTML Optimization', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="minify_html">
										<?php esc_html_e( 'Minify HTML', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="minify_html" id="minify_html" value="1" <?php checked( $speed_matrix_settings['minify_html'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove whitespace from HTML (safe)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="minify_inline_css">
										<?php esc_html_e( 'Minify Inline CSS', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="minify_inline_css" id="minify_inline_css" value="1"
											<?php checked( $speed_matrix_settings['minify_inline_css'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Minify CSS in <style> tags', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="minify_inline_js">
										<?php esc_html_e( 'Minify Inline JavaScript', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="minify_inline_js" id="minify_inline_js" value="1"
											<?php checked( $speed_matrix_settings['minify_inline_js'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Minify JavaScript in <script> tags', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>

						</table>
					</div>
				</div>

				<!-- Media Tab -->
				<div id="media" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Media Optimization', 'speed-matrix' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Optimize images and videos for better performance', 'speed-matrix' ); ?>
						</p>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Image Lazy Loading', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="lazy_load">
										<?php esc_html_e( 'Enable Lazy Loading', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="lazy_load" id="lazy_load" value="1" <?php checked( $speed_matrix_settings['lazy_load'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Load images only when they enter viewport (safe, recommended)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lazy_load_iframes">
										<?php esc_html_e( 'Lazy Load iframes/Videos', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="lazy_load_iframes" id="lazy_load_iframes" value="1"
											<?php checked( $speed_matrix_settings['lazy_load_iframes'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Lazy load YouTube, Vimeo, and other embeds', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="exclude_first_images">
										<?php esc_html_e( 'Exclude First Images', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<input type="number" name="exclude_first_images" id="exclude_first_images"
										value="<?php echo esc_attr( $speed_matrix_settings['exclude_first_images'] ); ?>"
										min="0" max="10" class="small-text">
									<p class="description">
										<?php esc_html_e( 'Don\'t lazy load the first N images (for LCP optimization, recommended: 2)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Image Formats', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="enable_webp">
										<?php esc_html_e( 'WebP Images', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="enable_webp" id="enable_webp" value="1" <?php checked( $speed_matrix_settings['enable_webp'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Serve WebP format', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>

						</table>
					</div>
				</div>

				<!-- Preloading Tab -->
				<div id="preloading" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Preloading & DNS', 'speed-matrix' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Preload critical resources for faster page loads', 'speed-matrix' ); ?>
						</p>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Critical Resource Preloading', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="preload_key_requests">
										<?php esc_html_e( 'Preload Key Requests', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="preload_key_requests" id="preload_key_requests"
											value="1" <?php checked( $speed_matrix_settings['preload_key_requests'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Automatically preload critical CSS, JS, and fonts', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lcp_image_url">
										<?php esc_html_e( 'LCP Image URL', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<input type="url" name="lcp_image_url" id="lcp_image_url"
										value="<?php echo esc_url( $speed_matrix_settings['lcp_image_url'] ); ?>"
										class="regular-text">
									<p class="description">
										<?php esc_html_e( 'URL of your largest above-the-fold image (hero image)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="preload_fonts">
										<?php esc_html_e( 'Preload Fonts', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="preload_fonts" id="preload_fonts" value="1" <?php checked( $speed_matrix_settings['preload_fonts'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Preload font files', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="font_urls">
										<?php esc_html_e( 'Font URLs', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="font_urls" id="font_urls" rows="3"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['font_urls'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'Font file URLs to preload (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Google Fonts Optimization', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="optimize_google_fonts">
										<?php esc_html_e( 'Optimize Google Fonts', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="optimize_google_fonts" id="optimize_google_fonts"
											value="1" <?php checked( $speed_matrix_settings['optimize_google_fonts'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Optimize Google Fonts loading', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="google_fonts_method">
										<?php esc_html_e( 'Optimization Method', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<select name="google_fonts_method" id="google_fonts_method">
										<option value="combine" <?php selected( $speed_matrix_settings['google_fonts_method'], 'combine' ); ?>>
											<?php esc_html_e( 'Combine + Preconnect (Best)', 'speed-matrix' ); ?>
										</option>
										<option value="async" <?php selected( $speed_matrix_settings['google_fonts_method'], 'async' ); ?>>
											<?php esc_html_e( 'Async Load', 'speed-matrix' ); ?>
										</option>
										<option value="preconnect" <?php selected( $speed_matrix_settings['google_fonts_method'], 'preconnect' ); ?>>
											<?php esc_html_e( 'Preconnect Only', 'speed-matrix' ); ?>
										</option>
										<option value="disable" <?php selected( $speed_matrix_settings['google_fonts_method'], 'disable' ); ?>>
											<?php esc_html_e( 'Disable Google Fonts', 'speed-matrix' ); ?>
										</option>
									</select>
									<p class="description">
										<?php esc_html_e( 'How to handle Google Fonts', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="font_display_swap">
										<?php esc_html_e( 'Font Display: Swap', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="font_display_swap" id="font_display_swap" value="1"
											<?php checked( $speed_matrix_settings['font_display_swap'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Prevent invisible text while fonts load', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'DNS & Preconnect', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="dns_prefetch">
										<?php esc_html_e( 'DNS Prefetch', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="dns_prefetch" id="dns_prefetch" value="1" <?php checked( $speed_matrix_settings['dns_prefetch'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Resolve DNS for external domains early', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="dns_prefetch_urls">
										<?php esc_html_e( 'DNS Prefetch URLs', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="dns_prefetch_urls" id="dns_prefetch_urls" rows="3"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['dns_prefetch_urls'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'External domains to prefetch (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Advanced Tab -->
				<div id="advanced" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Advanced Settings', 'speed-matrix' ); ?>
						</h2>
					</div>

					<div class="settings-card preset-card">
						<h3>
							<?php esc_html_e( 'Optimization Presets', 'speed-matrix' ); ?>
						</h3>
						<p>
							<?php esc_html_e( 'Choose a preset to automatically configure settings', 'speed-matrix' ); ?>
						</p>

						<div class="preset-options">
							<label class="preset-option">
								<input type="radio" name="preset_radio" value="basic" <?php checked( $speed_matrix_settings['optimization_preset'], 'basic' ); ?>>
								<div class="preset-box">
									<div class="preset-title">
										<?php esc_html_e( 'Basic', 'speed-matrix' ); ?>
									</div>
									<div class="preset-desc">
										<?php esc_html_e( 'Safe & Simple', 'speed-matrix' ); ?>
									</div>
								</div>
							</label>

							<label class="preset-option">
								<input type="radio" name="preset_radio" value="recommended" <?php checked( $speed_matrix_settings['optimization_preset'], 'recommended' ); ?>>
								<div class="preset-box preset-recommended">
									<div class="preset-badge">
										<?php esc_html_e( 'Recommended', 'speed-matrix' ); ?>
									</div>
									<div class="preset-title">
										<?php esc_html_e( 'Recommended', 'speed-matrix' ); ?>
									</div>
									<div class="preset-desc">
										<?php esc_html_e( 'Best Balance', 'speed-matrix' ); ?>
									</div>
								</div>
							</label>

							<label class="preset-option">
								<input type="radio" name="preset_radio" value="advanced" <?php checked( $speed_matrix_settings['optimization_preset'], 'advanced' ); ?>>
								<div class="preset-box">
									<div class="preset-title">
										<?php esc_html_e( 'Advanced', 'speed-matrix' ); ?>
									</div>
									<div class="preset-desc">
										<?php esc_html_e( 'Maximum Speed', 'speed-matrix' ); ?>
									</div>
								</div>
							</label>
						</div>
						<input type="hidden" name="optimization_preset" id="optimization_preset"
							value="<?php echo esc_attr( $speed_matrix_settings['optimization_preset'] ); ?>">
					</div>



					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Performance Tweaks', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="remove_query_strings">
										<?php esc_html_e( 'Remove Query Strings', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="remove_query_strings" id="remove_query_strings"
											value="1" <?php checked( $speed_matrix_settings['remove_query_strings'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove ?ver= from static resources', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="disable_emojis">
										<?php esc_html_e( 'Disable Emojis', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="disable_emojis" id="disable_emojis" value="1" <?php checked( $speed_matrix_settings['disable_emojis'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove WordPress emoji scripts', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="disable_embeds">
										<?php esc_html_e( 'Disable Embeds', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="disable_embeds" id="disable_embeds" value="1" <?php checked( $speed_matrix_settings['disable_embeds'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove WordPress embed scripts', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="disable_dashicons">
										<?php esc_html_e( 'Disable Dashicons', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="disable_dashicons" id="disable_dashicons" value="1"
											<?php checked( $speed_matrix_settings['disable_dashicons'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove Dashicons CSS on frontend', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="disable_jquery_migrate">
										<?php esc_html_e( 'Remove jQuery Migrate', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="disable_jquery_migrate" id="disable_jquery_migrate"
											value="1" <?php checked( $speed_matrix_settings['disable_jquery_migrate'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Remove jQuery Migrate (only if compatible)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Advanced Features', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">


							<tr>
								<th scope="row">
									<label for="cdn_url">
										<?php esc_html_e( 'CDN URL', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<input type="url" name="cdn_url" id="cdn_url"
										value="<?php echo esc_url( $speed_matrix_settings['cdn_url'] ); ?>"
										class="regular-text">
									<p class="description">
										<?php esc_html_e( 'Your CDN URL (if using one)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="exclude_urls">
										<?php esc_html_e( 'Exclude URLs', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<textarea name="exclude_urls" id="exclude_urls" rows="3"
										class="large-text code"><?php echo esc_textarea( $speed_matrix_settings['exclude_urls'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'URL patterns to exclude from all optimizations (one per line)', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Database Tab -->
				<div id="database" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Database Optimization', 'speed-matrix' ); ?>
						</h2>
						<p>
							<?php esc_html_e( 'Clean and optimize your database', 'speed-matrix' ); ?>
						</p>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Automatic Cleanup', 'speed-matrix' ); ?>
						</h3>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="auto_cleanup">
										<?php esc_html_e( 'Enable Auto Cleanup', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<label class="toggle-switch">
										<input type="checkbox" name="auto_cleanup" id="auto_cleanup" value="1" <?php checked( $speed_matrix_settings['auto_cleanup'], '1' ); ?>>
										<span class="toggle-slider"></span>
									</label>
									<p class="description">
										<?php esc_html_e( 'Automatically clean revisions, spam, and transients', 'speed-matrix' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="cleanup_frequency">
										<?php esc_html_e( 'Cleanup Frequency', 'speed-matrix' ); ?>
									</label>
								</th>
								<td>
									<select name="cleanup_frequency" id="cleanup_frequency">
										<option value="daily" <?php selected( $speed_matrix_settings['cleanup_frequency'], 'daily' ); ?>>
											<?php esc_html_e( 'Daily', 'speed-matrix' ); ?>
										</option>
										<option value="weekly" <?php selected( $speed_matrix_settings['cleanup_frequency'], 'weekly' ); ?>>
											<?php esc_html_e( 'Weekly', 'speed-matrix' ); ?>
										</option>
										<option value="monthly" <?php selected( $speed_matrix_settings['cleanup_frequency'], 'monthly' ); ?>>
											<?php esc_html_e( 'Monthly', 'speed-matrix' ); ?>
										</option>
									</select>
								</td>
							</tr>
							<tr>
								<td>
									<?php
									$speed_matrix_last_cleanup = get_option( 'speed_matrix_last_cleanup' );
									if ( $speed_matrix_last_cleanup ) :
										?>
										<p class="description">
											<strong>
												<?php esc_html_e( 'Last Cleanup:', 'speed-matrix' ); ?>
											</strong>
											<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $speed_matrix_last_cleanup ) ) ); ?>
										</p>
										<?php
									endif;
									?>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Database Statistics', 'speed-matrix' ); ?>
						</h3>
						<div class="db-stats">
							<div class="stat-item">
								<span class="stat-label">
									<?php esc_html_e( 'Post Revisions', 'speed-matrix' ); ?>
								</span>
								<span class="stat-value">
									<?php echo esc_html( $speed_matrix_post_revisions ); ?>
								</span>
							</div>
							<div class="stat-item">
								<span class="stat-label">
									<?php esc_html_e( 'Auto Drafts', 'speed-matrix' ); ?>
								</span>
								<span class="stat-value">
									<?php echo esc_html( $speed_matrix_auto_drafts ); ?>
								</span>
							</div>
							<div class="stat-item">
								<span class="stat-label">
									<?php esc_html_e( 'Spam Comments', 'speed-matrix' ); ?>
								</span>
								<span class="stat-value">
									<?php echo esc_html( $speed_matrix_spam_comments ); ?>
								</span>
							</div>
							<div class="stat-item">
								<span class="stat-label">
									<?php esc_html_e( 'Trashed Comments', 'speed-matrix' ); ?>
								</span>
								<span class="stat-value">
									<?php echo esc_html( $speed_matrix_trash_comments ); ?>
								</span>
							</div>
							<div class="stat-item">
								<span class="stat-label">
									<?php esc_html_e( 'Transients', 'speed-matrix' ); ?>
								</span>
								<span class="stat-value">
									<?php echo esc_html( $speed_matrix_transients ); ?>
								</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Tools Tab -->
				<div id="tools" class="content-tab">
					<div class="section-header">
						<h2>
							<?php esc_html_e( 'Tools', 'speed-matrix' ); ?>
						</h2>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Export / Import Settings', 'speed-matrix' ); ?>
						</h3>
						<div class="tool-buttons">
							<button type="button" onclick="exportSettings()" class="button button-secondary">
								<?php esc_html_e( 'Export Settings', 'speed-matrix' ); ?>
							</button>
							<button type="button" onclick="document.getElementById('import-file').click()"
								class="button button-secondary">
								<?php esc_html_e( 'Import Settings', 'speed-matrix' ); ?>
							</button>
							<input type="file" id="import-file" style="display:none" accept=".json"
								onchange="importSettings(event)">
						</div>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'System Information', 'speed-matrix' ); ?>
						</h3>
						<table class="system-info">
							<tr>
								<td><strong>
										<?php esc_html_e( 'WordPress Version', 'speed-matrix' ); ?>
									</strong></td>
								<td>
									<?php echo esc_html( get_bloginfo( 'version' ) ); ?>
								</td>
							</tr>
							<tr>
								<td><strong>
										<?php esc_html_e( 'PHP Version', 'speed-matrix' ); ?>
									</strong></td>
								<td>
									<?php echo esc_html( phpversion() ); ?>
								</td>
							</tr>
							<tr>
								<td><strong>
										<?php esc_html_e( 'Server', 'speed-matrix' ); ?>
									</strong></td>
								<td>
									<?php echo esc_html( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown' ); ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="settings-card">
						<h3>
							<?php esc_html_e( 'Performance Testing', 'speed-matrix' ); ?>
						</h3>
						<div class="tool-buttons">
							<a href="https://developers.google.com/speed/pagespeed/insights/?url=<?php echo urlencode( home_url() ); ?>"
								target="_blank" class="button button-secondary">
								<?php esc_html_e( 'Test with PageSpeed Insights', 'speed-matrix' ); ?>
							</a>
							<a href="https://gtmetrix.com/?url=<?php echo urlencode( home_url() ); ?>" target="_blank"
								class="button button-secondary">
								<?php esc_html_e( 'Test with GTmetrix', 'speed-matrix' ); ?>
							</a>
						</div>
					</div>
				</div>

				<div class="form-footer">
					<button type="submit" name="speed_matrix_save_settings" class="button button-primary button-large">
						<?php esc_html_e( 'Save Changes', 'speed-matrix' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	jQuery(document).ready(function ($) {
		// Tab navigation
		$('.nav-item').on('click', function (e) {
			e.preventDefault();
			var tab = $(this).data('tab');
			$('.nav-item').removeClass('active');
			$(this).addClass('active');
			$('.content-tab').removeClass('active');
			$('#' + tab).addClass('active');
			localStorage.setItem('speedMatrixActiveTab', tab);
		});

		// Restore active tab
		var activeTab = localStorage.getItem('speedMatrixActiveTab');
		if (activeTab) {
			$('.nav-item[data-tab="' + activeTab + '"]').click();
		}

		// Preset handling
		const presets = {
			basic: {
				enable_page_cache: true,
				minify_html: true,
				minify_css: true,
				minify_js: true,
				defer_js: true,
				lazy_load: true,
				disable_emojis: true,
				enable_browser_cache: true,
			},
			recommended: {
				enable_page_cache: true,
				cache_mobile_separate: true,
				enable_browser_cache: true,
				minify_html: true,
				minify_css: true,
				combine_css: false,
				async_css: true,
				minify_js: true,
				defer_js: true,
				delay_js_execution: true,
				lazy_load: true,
				enable_webp: true,
				preload_key_requests: true,
				remove_query_strings: true,
				disable_emojis: true,
				disable_embeds: true,
				disable_dashicons: true,
			},
			advanced: {
				enable_page_cache: true,
				cache_mobile_separate: true,
				enable_browser_cache: true,
				minify_html: true,
				minify_inline_css: true,
				minify_inline_js: true,
				minify_css: true,
				combine_css: true,
				async_css: true,
				minify_js: true,
				combine_js: true,
				defer_js: true,
				delay_js_execution: true,
				lazy_load: true,
				lazy_load_iframes: true,
				enable_webp: true,
				preload_key_requests: true,
				optimize_google_fonts: true,
				dns_prefetch: true,
				remove_query_strings: true,
				disable_emojis: true,
				disable_embeds: true,
				disable_dashicons: true,
				disable_jquery_migrate: true,
			}
		};

		$('input[name="preset_radio"]').on('change', function () {
			const presetName = $(this).val();
			const presetConfig = presets[presetName];
			$('#optimization_preset').val(presetName);
			$('input[type="checkbox"]').each(function () {
				var name = $(this).attr('name');
				if (name !== 'exclude_jquery') {
					$(this).prop('checked', false);
				}
			});
			Object.keys(presetConfig).forEach(function (key) {
				if (presetConfig[key]) {
					$('input[name="' + key + '"]').prop('checked', true);
				}
			});
		});
	});

	// Export/Import Functions
	function exportSettings() {
		var settings = <?php echo wp_json_encode( $speed_matrix_settings ); ?>;
		var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings, null, 2));
		var downloadAnchor = document.createElement('a');
		downloadAnchor.setAttribute("href", dataStr);
		downloadAnchor.setAttribute("download", "speed-matrix-settings.json");
		document.body.appendChild(downloadAnchor);
		downloadAnchor.click();
		downloadAnchor.remove();
	}

	function importSettings(event) {
		var file = event.target.files[0];
		if (file) {
			var reader = new FileReader();
			reader.onload = function (e) {
				try {
					var settings = JSON.parse(e.target.result);
					Object.keys(settings).forEach(function (key) {
						var element = document.querySelector('[name="' + key + '"]');
						if (element) {
							if (element.type === 'checkbox') {
								element.checked = settings[key] === '1';
							} else {
								element.value = settings[key];
							}
						}
					});
					alert('<?php esc_html_e( 'Settings imported successfully!', 'speed-matrix' ); ?>');
				} catch (error) {
					alert('<?php esc_html_e( 'Error importing settings', 'speed-matrix' ); ?>');
				}
			};
			reader.readAsText(file);
		}
	}
</script>
<style>
	.db-stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
		gap: 15px;
		margin-top: 15px;
	}

	.stat-item {
		background: #f6f7f7;
		padding: 15px;
		text-align: center;
		border-radius: 4px;
	}

	.stat-label {
		display: block;
		font-size: 13px;
		color: #646970;
		margin-bottom: 5px;
	}

	.stat-value {
		display: block;
		font-size: 24px;
		font-weight: 600;
		color: #0073aa;
	}

	.system-info {
		width: 100%;
		max-width: 600px;
	}

	.system-info tr td {
		padding: 10px;
		border-bottom: 1px solid #eee;
	}

	.system-info tr:last-child td {
		border-bottom: none;
	}
</style>