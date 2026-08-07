<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to communicate from the API to the plugin.
 */
class P_Listener extends P_Core {

	/**
	 * Add the actions required to hide the login page.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		// Only hook into the action if the authentication is set and valid.
		if ( isset( $_POST['patchstack_secret'] ) && is_string( $_POST['patchstack_secret'] ) && $this->verifyToken( $_POST['patchstack_secret'] ) ) {
			add_action( 'init', [ $this, 'handleRequest' ] );
		}

		// OTT action.
		if ( isset( $_POST['patchstack_ott_action'] ) && is_string( $_POST['patchstack_ott_action'] ) ) {
			$ott = get_option( 'patchstack_ott_action', '' );
			if ( ! empty( $ott ) && hash_equals( $ott, $_POST['patchstack_ott_action'] ) ) {
				$this->setIpHeader();
			}
		}

		// License (re)activation.
		if ( isset( $_POST['patchstack_ra_action'] ) && is_string( $_POST['patchstack_ra_action'] ) ) {
			$aas = get_option( 'patchstack_activation_secret', '' );
			$aat = get_option( 'patchstack_activation_time', '' );
			if ( ! empty( $aas ) && hash_equals( $aas, $_POST['patchstack_ra_action'] ) && ! empty ( $aat ) && ( time() - $aat ) < 1800 ) {
				$this->setLicenseInfo();
			}
		}
	}

	/**
	 * Handle the incoming request.
	 *
	 * @return void
	 */
	public function handleRequest() {
		// Get the request data for the listener action.
		$request = json_decode(base64_decode($_POST['patchstack_secret']), true);
		if ( ! isset( $request['data'] ) ) {
			return;
		}

		// Parse it; backwards support.
		$requestData = json_decode( $request['data'], true );

		// Double JSON encoding, edge cases.
		if ( ! is_array( $requestData ) ) {
			$requestData = json_decode( $requestData, true );
		}

		// Failsafe.
		if ( ! is_array( $requestData ) ) {
			return;
		}

		// Set our primary keys.
		foreach ($requestData as $key => $data) {
			$_POST[$key] = $data;
		}

		// Action to execute.
		$action = $requestData['action'];

		// Available mapped actions.
		$actions = [
			'patchstack_remote_users'       => 'listUsers',
			'patchstack_firewall_switch'    => 'switchFirewallStatus',
			'patchstack_wordpress_upgrade'  => 'wordpressCoreUpgrade',
			'patchstack_theme_upgrade'      => 'themeUpgrade',
			'patchstack_plugins_upgrade'    => 'pluginsUpgrade',
			'patchstack_plugins_toggle'     => 'pluginsToggle',
			'patchstack_plugins_delete'     => 'pluginsDelete',
			'patchstack_get_options'        => 'getAvailableOptions',
			'patchstack_set_options'        => 'saveOptions',
			'patchstack_refresh_rules'      => 'refreshRules',
			'patchstack_get_firewall_bans'  => 'getFirewallBans',
			'patchstack_firewall_unban_ip'  => 'unbanFirewallIp',
			'patchstack_firewall_unban_all' => 'unbanFirewallAll',
			'patchstack_upload_software'    => 'uploadSoftware',
			'patchstack_upload_logs'        => 'uploadLogs',
			'patchstack_send_ping'          => 'sendPing',
			'patchstack_login_bans'         => 'getLoginBans',
			'patchstack_unban_login'        => 'unbanLogin',
			'patchstack_debug_info'         => 'debugInfo',
			'patchstack_set_ip_header'	    => 'setIpHeader',
			'patchstack_refresh_license'    => 'refreshLicense',
			'patchstack_reset_2fa'			=> 'resetTFA',
			'patchstack_reset_cache'		=> 'resetCache'
		];

		// Action must exist.
		if ( ! isset( $actions[ $action ] ) ) {
			return;
		}
		
		// Execute the action.
		call_user_func( [ $this, $actions[ $action ] ] );
	}

	/**
	 * Determine if the provided secret hash equals the sha1 of the private id and key.
	 *
	 * @param string $secret Hash that is sent from our API.
	 * @return boolean
	 */
	public function verifyToken( $secret ) {
		// A non-string value (e.g. patchstack_secret[]=x) would fatal in base64_decode() on PHP 8.
		if ( empty ( $secret ) || ! is_string( $secret ) ) {
			return false;
		}

		$secret = base64_decode( $secret );
		$request = json_decode( $secret, true );
		if ( ! $request || ! isset( $request['nonce'], $request['data'], $request['hmac'], $request['time'] ) ) {
		  return false;
		}

        // +- 10 minutes.
        $found = false;
        for ( $i = -10; $i <= 10; $i++ ) {
            if ( floor(( time() + ( $i * 30 ) ) / 30 ) == $request['time'] ) {
                $found = true;
            }
        }

        // Timestamp must match.
        if ( ! $found ) {
            return false;
        }

        // Get client id and key.
		$id  = get_option( 'patchstack_clientid' );
		$key = $this->get_secret_key();
		if ( empty( $id ) || empty ( $key ) ) {
			return false;
		}

		// Compute the hmac.
        $hmac = hash_hmac( 'sha1', $request['nonce'] . $request['time'] . $request['data'], $key . '-' . $id );

        // Ensure it matches.
        if ( ! hash_equals( $hmac, $request['hmac'] ) ) {
            return false;
        }

        return true;
	}

	/**
	 * Determine if given action succeded or not, then return the appropriate message.
	 *
	 * @param mixed  $thing
	 * @param string $success
	 * @param string $fail
	 * @return void
	 */
	private function returnResults( $thing, $success = '', $fail = '' ) {
		if ( ! is_wp_error( $thing ) && $thing !== false ) {
			wp_send_json( [ 'success' => $success ] );
		}

		wp_send_json( [ 'error' => $fail ] );
	}

	/**
	 * Send a ping back to the API.
	 *
	 * @return void
	 */
	private function sendPing() {
		do_action( 'patchstack_send_ping' );
		wp_send_json( [ 'firewall' => $this->get_option( 'patchstack_basic_firewall' ) == 1 ] );
	}

	/**
	 * Get list of all users on WordPress
	 *
	 * @return void
	 */
	private function listUsers() {
		// Only fetch data we actually need.
		$users = get_users( [ 'role__in' => [ 'administrator', 'editor', 'author', 'contributor' ] ] );
		$roles = wp_roles();
		$roles = $roles->get_names();
		$data  = [];

		// Loop through all users.
		foreach ( $users as $user ) {

			// Get text friendly version of the role.
			$text = '';
			foreach ( $user->roles as $role ) {
				if ( isset( $roles[ $role ] ) ) {
					$text .= $roles[ $role ] . ', ';
				} else {
					$text .= $role . ', ';
				}
			}

			// Push to array that we will eventually output.
			array_push(
				$data,
				[
					'id'       => $user->data->ID,
					'username' => $user->data->user_login,
					'email'    => $user->data->user_email,
					'roles'    => substr( $text, 0, -2 ),
				]
			);
		}

		wp_send_json( [ 'users' => $data ] );
	}

	/**
	 * Switch the firewall status from on to off or off to on.
	 *
	 * @return string
	 */
	private function switchFirewallStatus() {
		$state = $this->get_option( 'patchstack_basic_firewall' ) == 1;
		update_option( 'patchstack_basic_firewall', $state == 1 ? 0 : 1, true );
		$this->returnResults( null, 'Firewall ' . ( $state == 1 ? 'disabled' : 'enabled' ) . '.', null );
	}

	/**
	 * Upgrade the core of WordPress.
	 *
	 * @return string|void
	 */
	private function wordpressCoreUpgrade() {
		@set_time_limit( 180 );

		// Get the core update info.
		wp_version_check();
		$core = get_site_transient( 'update_core' );

		// Any updates available?
		if ( ! isset( $core->updates[0] ) ) {
			$this->returnResults( false, null, 'No update available at this time.' );
		}

		// Are we on the latest version already?
		if ( $core->updates[0]->response == 'latest' ) {
			$this->returnResults( false, null, 'Site is already running the latest version available.' );
		}

		// Require some libraries and attempt the upgrade.
		@include_once ABSPATH . '/wp-admin/includes/admin.php';
		@include_once ABSPATH . '/wp-admin/includes/class-wp-upgrader.php';
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Core_Upgrader( $skin );
		$result   = $upgrader->upgrade(
			$core->updates[0],
			[
				'attempt_rollback'             => true,
				'do_rollback'                  => true,
				'allow_relaxed_file_ownership' => true,
			]
		);
		if ( ! $result ) {
			$this->returnResults( false, null, 'The WordPress core could not be upgraded, most likely because of invalid filesystem connection information.' );
		}

		// Synchronize again with the API.
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( $result, 'WordPress core has been upgraded.', 'The WordPress core could not be upgraded.' );
	}

	/**
	 * Upgrade a WordPress theme.
	 *
	 * @return string|void
	 */
	private function themeUpgrade() {
		if ( !isset( $_POST['patchstack_theme_upgrade'] ) ) {
			return;
		}

		@set_time_limit( 180 );

		// Require some files we need to execute the upgrade.
		$theme = wp_filter_nohtml_kses( $_POST['patchstack_theme_upgrade'] );
		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( file_exists( ABSPATH . 'wp-admin/includes/class-theme-upgrader.php' ) ) {
			@include_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		}
		@include_once ABSPATH . 'wp-admin/includes/misc.php';
		@include_once ABSPATH . 'wp-admin/includes/file.php';

		// Upgrade the theme.
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $theme, [ 'allow_relaxed_file_ownership' => true ] );
		if ( ! $result ) {
			$this->returnResults( false, null, 'The theme could not be upgraded, most likely because of invalid filesystem connection information.' );
		}

		// Synchronize again with the API.
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( null, 'The theme has been updated successfully.' );
	}

	/**
	 * Upgrade a batch of plugins at once.
	 *
	 * @return string|void
	 */
	private function pluginsUpgrade() {
		if (!isset( $_POST['patchstack_plugins_upgrade'] ) ) {
			return;
		}

		@set_time_limit( 180 );

		// Must have a valid number of plugins received to upgrade.
		$plugins = wp_filter_nohtml_kses( $_POST['patchstack_plugins_upgrade'] );
		$plugins = explode( '|', $plugins );
		if ( count( $plugins ) == 0 ) {
			$this->returnResults( false, null, 'No valid plugin names have been given.' );
		}

		// Require some files we need to execute the upgrade.
		@include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( file_exists( ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php' ) ) {
			@include_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		@include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

		@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		@include_once ABSPATH . 'wp-admin/includes/misc.php';
		@include_once ABSPATH . 'wp-admin/includes/file.php';
		@include_once ABSPATH . 'wp-admin/includes/template.php';
		@wp_update_plugins();
		$all_plugins = get_plugins();

		// New array with all available plugins and the ones we want to upgrade.
		$upgrade = [];
		foreach ( $all_plugins as $path => $data ) {
			$t = explode( '/', $path );
			if ( in_array( $t[0], $plugins ) ) {
				array_push( $upgrade, $path );
			}
		}

		// Don't continue if we have no valid plugins to upgrade.
		if ( count( $upgrade ) == 0 ) {
			$this->returnResults( false, null, 'No valid plugin names have been given.' );
		}

		// Upgrade the plugins.
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->bulk_upgrade( $upgrade, [ 'allow_relaxed_file_ownership' => true ] );
		if ( ! $result ) {
			$this->returnResults( false, null, 'The plugins could not be upgraded, most likely because of invalid filesystem connection information.' );
		}

		// Synchronize again with the API.
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( null, 'The plugins have been updated successfully.' );
	}

	/**
	 * Toggle the state of a batch of plugin to activated or de-activated.
	 *
	 * @return string|void
	 */
	private function pluginsToggle() {
		if (!isset( $_POST['patchstack_plugins'], $_POST['patchstack_plugins_toggle'] ) ) {
			return;
		}

		@set_time_limit( 180 );

		// Must have a valid number of plugins received to toggle.
		$plugins = wp_filter_nohtml_kses( $_POST['patchstack_plugins'] );
		$plugins = explode( '|', $plugins );
		$state = $_POST['patchstack_plugins_toggle'] == 'on' ? 'on' : 'off';
		if ( count( $plugins ) == 0 ) {
			$this->returnResults( false, null, 'No valid plugin names have been given.' );
		}

		@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$all_plugins = get_plugins();

		// New array with all available plugins and the ones we want to toggle.
		$toggle = [];
		foreach ( $all_plugins as $path => $data ) {
			$t = explode( '/', $path );

			// Don't continue if the plugin does not exist locally.
			if ( ! in_array( $t[0], $plugins ) ) {
				continue;
			}

			// If plugin should be turned on, check if it's already turned on first.
			if ( $state == 'on' && ! is_plugin_active( $path ) ) {
				array_push( $toggle, $path );
			}

			// If plugin should be turned off, check if it's already turned off first.
			if ( $state == 'off' && is_plugin_active( $path ) ) {
				array_push( $toggle, $path );
			}
		}

		// Don't continue if we have no valid plugins to toggle..
		if ( count( $toggle ) == 0 ) {
			$this->returnResults( false, null, 'The plugins are already turned ' . $state . '.' );
		}

		// Turn the plugins on or off?
		if ( $state == 'on' ) {
			activate_plugins( $toggle );
		}

		if ( $state == 'off' ) {
			deactivate_plugins( $toggle );
		}

		// Synchronize again with the API.
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( null, 'The ' . ( count( $toggle ) == 1 ? 'plugin has' : 'plugins have' ) . ' been successfully turned ' . $state . '.' );
	}

	/**
	 * Delete a batch of plugins.
	 *
	 * @return string|void
	 */
	private function pluginsDelete() {
		if (!isset( $_POST['patchstack_plugins'] ) ) {
			return;
		}

		@set_time_limit( 180 );

		// Must have a valid number of plugins received to toggle.
		$plugins = wp_filter_nohtml_kses( $_POST['patchstack_plugins'] );
		$plugins = explode( '|', $plugins );
		if ( count( $plugins ) == 0 ) {
			$this->returnResults( false, null, 'No valid plugin names have been given.' );
		}

		@include_once ABSPATH . 'wp-admin/includes/file.php';
		@include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$all_plugins = get_plugins();

		// New array with all available plugins and the ones we want to toggle.
		$delete = [];
		foreach ( $all_plugins as $path => $data ) {
			$t = explode( '/', $path );

			// Don't continue if the plugin does not exist locally.
			if ( ! in_array( $t[0], $plugins ) ) {
				continue;
			}

			array_push( $delete, $path );
		}

		// Don't continue if we have no valid plugins to toggle..
		if ( count( $delete ) == 0 ) {
			$this->returnResults( false, null, 'No valid plugins to delete.' );
		}

		@deactivate_plugins( $delete );
		@delete_plugins( $delete );

		// Synchronize again with the API.
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( null, 'The plugins have been successfully deleted.' );
	}

	/**
	 * Save received options.
	 *
	 * @return void
	 */
	private function saveOptions() {
		if ( ! isset( $_POST['patchstack_set_options'], $_POST['patchstack_secret'] ) ) {
			exit;
		}

		// Get the received options.
		$options = json_decode( base64_decode( $_POST['patchstack_set_options'] ), true );
		if ( ! $options || count( $options ) == 0 ) {
			exit;
		}

		// Loop through the options and update their value.
		$exclude_filter = ['patchstack_firewall_custom_rules'];
		foreach ( $options as $key => $value ) {
			if ( array_key_exists( $key, $this->plugin->admin_options->options ) ) {

				// Booleans would persist as '1' / '' otherwise, store them as 1/0 so type checks behave consistently.
				if ( is_bool( $value ) ) {
					$value = $value ? 1 : 0;
				}

				// Some options should not be filtered and could cause unexpected behavior if they are filtered.
				if ( ! in_array( $key, $exclude_filter ) ) {
					$value = map_deep( $value, 'wp_filter_nohtml_kses' );
				}

				update_option( $key, $value );
			}
		}

		$this->returnResults( null, 'Plugin options has been updated.' );
	}

	/**
	 * Return list of keys and values of Patchstack options.
	 *
	 * @return array
	 */
	private function getAvailableOptions() {
		// Get all options and filter by the Patchstack prefix.
		global $wpdb;
		$options  = $wpdb->get_results( "SELECT option_name, option_value FROM " . $wpdb->options . " WHERE option_name LIKE 'patchstack_%'" );
		$settings = [];
		$found = [];
		foreach ( $options as $option ) {
			array_push( $found, $option->option_name );
			$settings[] = (array) $option;
		}

		// Check for potential missing options and add them to the output.
		foreach( [ 'patchstack_firewall_custom_rules' ] as $slug ) {
			if ( ! in_array( $slug, $found, true ) ) {
				$settings[] = [
					'option_name'  => $slug,
					'option_value' => $this->get_option( $slug, '' )
				];
			}
		}

		// Add custom values which aren't directly available from the options table.
		// User roles available for whitelisting.
		$roles           = wp_roles();
		$roles           = $roles->get_names();
		$roles_available = [];
		foreach ( $roles as $key => $role ) {
			$roles_available[ $key ] = $role;
		}
		$settings[] = [
			'option_name'  => 'patchstack_basic_firewall_roles_available',
			'option_value' => serialize( $roles_available ),
		];

		// Whether or not auto-updates are disabled in the code.
		$settings[] = [
			'option_name'  => 'patchstack_auto_updates_disabled',
			'option_value' => defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED,
		];

		wp_send_json( $settings );
	}

	/**
	 * Pull firewall rules from the API.
	 *
	 * @return void
	 */
	private function refreshRules() {
		do_action( 'patchstack_post_dynamic_firewall_rules' );
		$this->returnResults( null, 'Firewall rules have been refreshed.' );
	}

	/**
	 * Get a list of IP addresses that are currently banned by the firewall.
	 *
	 * @return void|array
	 */
	private function getFirewallBans($return = false) {
		// Calculate block time.
		$minutes = (int) $this->get_option( 'patchstack_autoblock_minutes', 30 );
		$timeout = (int) $this->get_option( 'patchstack_autoblock_blocktime', 60 );
		if ( empty( $minutes ) || empty( $timeout ) ) {
			$time = 30 + 60;
		} else {
			$time = $minutes + $timeout;
		}

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT ip FROM ' . $wpdb->prefix . "patchstack_firewall_log WHERE apply_ban = 1 AND log_date >= ('" . current_time( 'mysql' ) . "' - INTERVAL %d MINUTE) GROUP BY ip", [ $time ] ),
			OBJECT
		);

		$out = [];
		foreach ( $results as $result ) {
			if ( isset( $result->ip ) ) {
				array_push( $out, $result->ip );
			}
		}

		if ($return) {
			return $out;
		}

		wp_send_json( $out );
	}

	/**
	 * Unban a specific IP address from the firewall.
	 *
	 * @return void
	 */
	private function unbanFirewallIp() {
		if ( ! isset( $_POST['patchstack_ip'] ) || !filter_var( $_POST['patchstack_ip'], FILTER_VALIDATE_IP ) ) {
			return;
		}

		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'patchstack_firewall_log SET apply_ban = 0 WHERE ip = %s', [ $_POST['patchstack_ip'] ] ) );
		$this->returnResults( null, 'The IP has been unbanned.' );
	}

	/**
	 * Unban a specific IP address from the firewall.
	 *
	 * @return void
	 */
	private function unbanFirewallAll() {
		global $wpdb;

		// Get all banned IP addresses.
		$ips = $this->getFirewallBans(true);
		if (count($ips) == 0) {
			$this->returnResults( null, 'There are no IP addresses to unban.' );
		}

		// Unban all IP addresses.
		foreach ($ips as $ip) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'patchstack_firewall_log SET apply_ban = 0 WHERE ip = %s', [ $ip ] ) );
		}
		
		$this->returnResults( null, 'All IP addresses has been unbanned.' );
	}

	/**
	 * Send all current software on the WordPress site to the API.
	 *
	 * @return void
	 */
	private function uploadSoftware() {
		do_action( 'patchstack_send_software_data' );
		$this->returnResults( null, 'The software data has been sent to the API.' );
	}

	/**
	 * Upload the firewall and activity logs.
	 *
	 * @return void
	 */
	private function uploadLogs() {
		do_action( 'patchstack_send_hacker_logs' );
		do_action( 'patchstack_send_event_logs' );
		$this->returnResults( null, 'The logs have been sent to the API.' );
	}

	/**
	 * Get the currently banned IP addresses from the login page.
	 *
	 * @return void
	 */
	private function getLoginBans() {
		// Calculate block time.
		$minutes = (int) $this->get_option( 'patchstack_anti_bruteforce_minutes', 30 );
		$timeout = (int) $this->get_option( 'patchstack_anti_bruteforce_blocktime', 60 );
		if ( empty( $minutes ) || empty( $timeout ) ) {
			$time = 30 + 60;
		} else {
			$time = $minutes + $timeout;
		}

		// Check if X failed login attempts were made.
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, ip, date FROM ' . $wpdb->prefix . "patchstack_event_log WHERE action = 'failed login' AND date >= ('" . current_time( 'mysql' ) . "' - INTERVAL %d MINUTE) GROUP BY ip HAVING COUNT(ip) >= %d ORDER BY date DESC", [ $time, $this->get_option( 'patchstack_anti_bruteforce_attempts', 10 ) ] ),
			OBJECT
		);

		// Return the banned IP addresses.
		wp_send_json( [ 'banned' => $results ] );
	}

	/**
	 * Unban a banned login IP address.
	 *
	 * @return void
	 */
	private function unbanLogin() {
		if ( ! isset( $_POST['id'], $_POST['type'] ) || ! is_scalar( $_POST['id'] ) || ! ctype_digit( (string) $_POST['id'] ) ) {
			exit;
		}

		global $wpdb;

		// Unblock the IP; delete the logs of the IP.
		if ( $_POST['type'] == 'unblock' ) {
			// First get the IP address to unblock.
			$result = $wpdb->get_results(
				$wpdb->prepare( 'SELECT ip FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE id = %d', [ (int) $_POST['id'] ] )
			);

			// Unblock the IP address.
			if ( isset( $result[0], $result[0]->ip ) && filter_var( $result[0]->ip, FILTER_VALIDATE_IP ) ) {
				$wpdb->query(
					$wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE ip = %s', [ $result[0]->ip ] )
				);
			}
		}

		// Unblock and whitelist the IP.
		if ( $_POST['type'] == 'unblock_whitelist' ) {
			// First get the IP address to whitelist.
			$result = $wpdb->get_results(
				$wpdb->prepare( 'SELECT ip FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE id = %d', [ (int) $_POST['id'] ] )
			);

			// Whitelist and unblock the IP address.
			if ( isset( $result[0], $result[0]->ip ) && filter_var( $result[0]->ip, FILTER_VALIDATE_IP )  ) {
				update_option( 'patchstack_login_whitelist', $this->get_option( 'patchstack_login_whitelist', '' ) . "\n" . $result[0]->ip );
				$wpdb->query(
					$wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE ip = %s', [ $result[0]->ip ] )
				);
			}
		}

		$this->returnResults( null, 'The unban has been processed.' );
	}

	/**
	 * Get information for debugging purposes.
	 * 
	 * @return void
	 */
	private function debugInfo() {
		$debug = [
			'server' 	=> $_SERVER,
			'php' 		=> phpversion()
		];

		wp_send_json( $debug );
	}

	/**
	 * Try to determine the proper IP address headers.
	 * 
	 * @return void
	 */
	private function setIpHeader() {
		if ( ! isset( $_POST['ip'] ) ) {
			return;
		}

		$ips = ! is_array ( $_POST['ip'] ) ? [ $_POST['ip'] ] : $_POST['ip'];

		// REMOTE_ADDR?
		foreach ( $ips as $ip ) {
			if ( isset( $_SERVER['REMOTE_ADDR'] ) && $_SERVER['REMOTE_ADDR'] == $ip ) {
				update_option( 'patchstack_firewall_ip_header', 'REMOTE_ADDR', true );
				update_option( 'patchstack_ip_header_computed', 1 );
				update_option( 'patchstack_ott_action', '' );
				wp_send_json( [ 'success' => true, 'header' => 'REMOTE_ADDR' ] );
			}
		}

		// IP address headers in order of priority.
		$priority = [ 'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_SUCURI_CLIENTIP',  'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'SUCURI_RIP' ];
		foreach ( $ips as $ip ) {
			foreach ( $priority as $header ) {
				if ( isset( $_SERVER[ $header ] ) && $_SERVER[ $header ] == $ip ) {
					update_option( 'patchstack_firewall_ip_header', $header, true );
					update_option( 'patchstack_ip_header_computed', 1 );
					update_option( 'patchstack_ott_action', '' );
					wp_send_json( [ 'success' => true,  'header' => $header ] );
				}
			}
		}

		// Still not found? Iterate over all $_SERVER keys.
		foreach ( $ips as $ip ) {
			foreach ( $_SERVER as $key => $value ) {
				if ( $value == $ip ) {
					update_option( 'patchstack_firewall_ip_header', $key, true );
					update_option( 'patchstack_ip_header_computed', 1 );
					update_option( 'patchstack_ott_action', '' );
					wp_send_json( [ 'success' => true,  'header' => $key ] );
				}
			}
		}

		update_option( 'patchstack_ott_action', '' );
		wp_send_json( [ 'success' => false, 'header' => 'unknown' ] );
	}

	/**
	 * Refresh the license and subscription information.
	 * 
	 * @return void
	 */
	private function refreshLicense () {
		do_action( 'patchstack_update_license_status' );
		do_action( 'patchstack_send_software_data' );
		do_action( 'patchstack_post_dynamic_firewall_rules' );
		
		wp_send_json( array( 'success' => true ) );
	}

	/**
	 * Set license information.
	 * 
	 * @return void
	 */
	private function setLicenseInfo () {
		if ( ! isset( $_POST['id'], $_POST['secret'] ) ) {
			wp_send_json( [ 'success' => false, 'message' => 'Missing required parameters.' ] );
		}

		$result = $this->plugin->activation->alter_license( $_POST['id'], $_POST['secret'], 'activate' );
		if ( $result['result'] == 'error' ) {
			wp_send_json( [ 'success' => false, 'message' => 'The license could not be activated.' ] );
		}

		update_option( 'patchstack_activation_secret', '' );
		update_option( 'patchstack_activation_time', '' );
		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Reset the 2FA data all users or specific ones.
	 * 
	 * @return void
	 */
	private function resetTFA () {
		global $wpdb;

		// Delete by user id if specified.
		$where = '';
		$params = [];
		if (isset($_POST['user_id'])) {
			$where = 'AND user_id = %d';
			$params = [$_POST['user_id']];
		} elseif (isset($_POST['user_name'])) {
			$where = 'AND user_id IN (SELECT ID FROM ' . $wpdb->users . ' WHERE user_login = %s OR user_email = %s)';
			$params = [$_POST['user_name'], $_POST['user_name']];
		}

		// Delete the 2FA data.
		$wpdb->query(
			$wpdb->prepare( "
				DELETE FROM " . $wpdb->usermeta. "
				WHERE `meta_key` IN ('webarx_2fa_enabled', 'webarx_2fa_secretkey', 'webarx_2fa_secretkey_nonce') 
				" . $where,
				$params
			)
		);

		wp_send_json( [ 'success' => true ] );
	}

	/**
	 * Reset the WordPress cache.
	 * 
	 * @return void
	 */
	private function resetCache () {
		wp_send_json( [ 'success' => wp_cache_flush() ] );
	}
}
