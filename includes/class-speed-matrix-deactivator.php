<?php
class Speed_Matrix_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();

		$deactivator = new self();
		$deactivator->speed_matrix_deactivate();
	}

	private function speed_matrix_deactivate() {
		if ( file_exists( get_home_path() . '.htaccess' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';

			insert_with_markers( get_home_path() . '.htaccess', 'SpeedMatrix-Compression', [] );
		}
	}
}
