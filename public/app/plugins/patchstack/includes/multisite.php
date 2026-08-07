<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to handle anything multisite related.
 */
class P_Multisite extends P_Core {
	/**
	 * Add the actions required for the multisite functionality.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
		if ( ! is_super_admin() ) {
			return;
		}

		// When we need to re-run the migration of a specific site.
		if ( isset( $_GET['site'], $_GET['PatchstackNonce'] ) && wp_verify_nonce( $_GET['PatchstackNonce'], 'patchstack-migration' ) ) {
			$this->run_migration();
		}
	}

	/**
	 * This will be called when the network admin clicks on the "Sites" button.
	 * It will show all sites.
	 *
	 * @return void
	 */
	public function sites_section_callback() {
		require_once dirname( __FILE__ ) . '/views/pages/multisite-table.php';
	}

	/**
	 * Re-run the migration for a specific multisite site.
	 * 
	 * @return void
	 */
	private function run_migration( ) {
		// Must be a number.
		if ( ! isset( $_GET['site'] ) || ! is_scalar( $_GET['site'] ) || ! ctype_digit( (string) $_GET['site'] ) ) {
			exit;
		}

		// Site must be valid and exists.
		$site = function_exists( 'get_site' ) ? get_site( $_GET['site'] ) : null;
		if ( is_null( $site ) ) {
			exit;
		}

		// Perform base migration.
		$this->plugin->activation->migrate( null, $site->id );

		// Perform specific version migrations.
		$versions = array('3.0.1', '3.0.2');
		foreach ( $versions as $version ) {
			$this->plugin->activation->migrate( $version, $site->id );
		}

		wp_safe_redirect( add_query_arg( [ 'success' => '1', 'site' => $site->id ], remove_query_arg( [ 'PatchstackNonce', 'site' ] ) ) );
		exit;
	}
}
