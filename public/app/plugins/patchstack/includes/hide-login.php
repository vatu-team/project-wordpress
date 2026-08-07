<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to hide the login page, if it's enabled.
 */
class P_Hide_Login extends P_Core {
	/**
	 * Add the actions required to hide the login page.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Update the renamed login page if it's set to our hardcoded one. Write with the
		// same scope it is read back with below, otherwise this would rewrite every request.
		if ( (int) get_option( 'patchstack_mv_wp_login', 0 ) == 0 && get_option( 'patchstack_rename_wp_login' ) == 'swlogin' ) {
			update_option( 'patchstack_rename_wp_login', md5( wp_generate_password( 32, true, true ) ) );
		}

		// No need to continue if it is not enabled.
		if ( ! get_option( 'patchstack_mv_wp_login' ) || ! get_option( 'patchstack_rename_wp_login' ) ) {
			return;
		}

		// Register the filters and actions for the functionality.
		add_action( 'init', [ $this, 'init' ], ~PHP_INT_MAX + 1 );
		add_action( 'wp_logout', [ $this, 'wp_logout' ] );
	}

	/**
	 * Deny access to wp-login.php if the login page rename feature is enabled.
	 *
	 * @return void
	 */
	public function init() {
		// Do not block the user if they are already logged in as that would block a logout.
		if ( is_user_logged_in() ) {
			return;
		}

		// Determine if the user is whitelisted.
		if ( ( ( isset( $_SERVER['REQUEST_URI'] ) && stripos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) || ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) || ( isset( $_SERVER['PHP_SELF'] ) && $_SERVER['PHP_SELF'] === '/wp-login.php' ) ) && ! $this->is_whitelisted() ) {
			if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], ['confirm_admin_email', 'postpass', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'checkemail', 'confirmaction'] ) ) {
				return;
			}

			$this->plugin->firewall_base->display_error_page( 'login' );
		}

		// If the current page is the renamed login page we give the user access for 10 minutes to the login page.
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], get_option( 'patchstack_rename_wp_login' ) ) !== false ) {
			// Whitelist the current IP address.
			$this->whitelist_ip();

			// Supported by a number of popular caching plugins.
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}

			// Because WP Fastest Cache just has to be special...
			if ( function_exists( 'wpfc_exclude_current_page' ) ) {
				@wpfc_exclude_current_page();
			}

			// No caching.
			send_nosniff_header();
			nocache_headers();

			// User should be whitelisted now, redirect to the login page.
			wp_safe_redirect( 'wp-login.php', 307 );
			exit;
		}
	}

	/**
	 * If the user is logging out, whitelist them again so they don't see a blocked page.
	 * 
	 * @return void
	 */
	public function wp_logout() {
		$this->whitelist_ip();
	}

	/**
	 * Determine if the IP address is whitelisted.
	 * 
	 * @return boolean
	 */
	private function is_whitelisted() {
		// Process the whitelist, and remove old ones.
		$whitelist = get_site_option( 'patchstack_rename_wp_login_whitelist', [] );
		$new_whitelist = [];
		$allow = false;

		// Only continue if there are actually any whitelist entries.
		if ( is_array( $whitelist ) && count( $whitelist ) != 0 ){
			$ip = $this->get_ip();
			foreach ( $whitelist as $entry ) {

				// Determine if the whitelist entry is still valid.
				if ( ( time() - $entry[1] ) <= 600 ) {
					array_push( $new_whitelist, $entry );

					// Determine if the IP address matches.
					if ( $ip === $entry[0] ) {
						$allow = true;
					}
				}
			}

			update_site_option( 'patchstack_rename_wp_login_whitelist', $new_whitelist );
		}

		return $allow;
	}

	/**
	 * Whitelist the current IP address, or extend the time.
	 * 
	 * @return void
	 */
	private function whitelist_ip() {
		$whitelist = get_site_option( 'patchstack_rename_wp_login_whitelist', [] );
		$new_whitelist = [];

		// If the IP address is already whitelisted, reset the timestamp.
		if ( is_array( $whitelist ) && count( $whitelist ) != 0 ) {
			$ip = $this->get_ip();
			$whitelisted = false;
			foreach ( $whitelist as $entry ) {
				// Determine if we should extend the whitelist time or ignore if already whitelisted.
				if ( $ip === $entry[0] ) {
					$new_whitelist[] = [ $ip, time() ];
					$whitelisted = true;
				} else {
					$new_whitelist[] = $entry;
				}
			}

			// Whitelist the IP address.
			if ( ! $whitelisted ) {
				$new_whitelist[] = [ $ip, time() ];
			}

			update_site_option( 'patchstack_rename_wp_login_whitelist', $new_whitelist );
		} else {
			update_site_option( 'patchstack_rename_wp_login_whitelist', [ [ $this->get_ip(), time() ] ] );
		}
	}
}
