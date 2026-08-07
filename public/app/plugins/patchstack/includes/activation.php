<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to activate and deactivate the plugin.
 * Additionally, we use it to run migrations.
 */
class P_Activation extends P_Core {

	/**
	 * Holds any activation errors.
	 * 
	 * @var array
	 */
	private $activation_errors = [];

	/**
	 * Add the actions required for the activation.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		add_action( 'activated_plugin', [ $this, 'redirect_activation' ], 10, 2 );
		add_action( 'updated_option', [ $this, 'updated_option' ], 10, 3 );
	}

	/**
	 * Redirect the user to our settings page after plugin activation.
	 *
	 * @param string  $plugin The plugin that is activated.
	 * @param boolean $network_activation If a network wide activation. (multisite)
	 * @return void
	 */
	public function redirect_activation( $plugin, $network_activation ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Only if it's the current plugin.
		if ( $plugin == $this->plugin->basename && ! isset( $_REQUEST['_ajax_nonce'] ) ) {

			// If plugin bulk activate through wp-admin, we ignore the redirect if it's more than 1 plugin.
			if ( isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) && count( $_POST['checked'] ) > 1 ) {
				return;
			}

			// If the plugin is already connected or API activated, no need to redirect again.
			if ( $this->license_is_active() || $this->is_connected() ) {
				return;
			}

			// Determine if secret token was set, if so, sync with API.
			$attemptAuto = false;
			$secretToken = get_option( 'patchstack_activation_secret', '' );
			if ( ! empty( $secretToken ) ) {
				$attemptAuto = true;
			}

			// In case of multisite, we want to redirect the user to a different page.
			if ( $network_activation ) {
				wp_safe_redirect( network_admin_url( 'admin.php?page=patchstack-multisite-settings&tab=multisite&ps_activated=1' . ($attemptAuto ? '&ps_autoa=1' : '' ) ) );
			} else {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $this->plugin->name . '&ps_activated=1' . ($attemptAuto ? '&ps_autoa=1' : '' ) ) );
			}
			exit;
		}
	}

	/**
	 * Check if the plugin meets requirements and disable it if they are not present.
	 *
	 * @return boolean
	 */
	public function check_requirements() {
		if ( $this->meets_requirements() ) {
			return true;
		}

		// Add a dashboard notice.
		add_action( 'all_admin_notices', [ $this, 'requirements_not_met_notice' ] );
		return false;
	}

	/**
	 * Check that all plugin requirements are met.
	 *
	 * @return boolean
	 */
	public function meets_requirements() {
		// Check to see if we can access the API.
		$response = wp_remote_request(
			$this->plugin->api_url,
			[
				'method'      => 'GET',
				'timeout'     => 10,
				'redirection' => 5,
			]
		);

		// Check if we can access the API.
		if ( is_wp_error( $response ) ) {
			$this->activation_errors[] = 'We were unable to contact our API server. Please contact your host and ask them to make sure that outgoing connections to api.patchstack.com are not blocked.<br />Additional error message to give to your host: ' . $response->get_error_message();
			return false;
		}

		// Do checks for required classes / functions or similar.
		// Add detailed messages to $this->activation_errors array.
		if ( version_compare( phpversion(), '5.6.0', '<' ) ) {
			$this->activation_errors[] = 'Please update the PHP version on your host to at least 5.6.0. Ask your host if you do not know what this means.';
			return false;
		}

		global $wp_version;
		if ( version_compare( $wp_version, '4.3.0', '<' ) ) {
			$this->activation_errors[] = 'Please upgrade your WordPress site to at least 4.3.0.';
			return false;
		}

		return true;
	}

	/**
	 * Adds a notice to the dashboard if the plugin requirements are not met.
	 *
	 * @return void
	 */
	public function requirements_not_met_notice() {
		// Deactivate the plugin.
		deactivate_plugins( $this->plugin->basename );

		// Compile default message.
		$default_message = esc_attr__( 'Patchstack could not be activated due to a conflict. See below for information regarding the conflict.<br />', 'patchstack' );

		// Print the errors on the screen.
		echo wp_kses_post( $default_message );
		echo wp_kses_post( implode( '<br />', $this->activation_errors ) );
	}

	/**
	 * Activate the plugin.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function activate( $core ) {
		// Bail early if requirements are not met.
		if ( ! $this->check_requirements() ) {
			$this->requirements_not_met_notice();
			exit;
		}

		// Check if the webarx/webarx.php plugin is present, if so, remove it.
		if ( is_dir( WP_PLUGIN_DIR . '/webarx' ) ) {

			// Migrate all current options to the new prefix.
			global $wpdb;
			$exists = $wpdb->get_var( "SELECT COUNT(*) FROM " . $wpdb->prefix . "options WHERE option_name = 'webarx_api_token'" );
			
			// Move over the options.
			if ( !is_null( $exists ) && $exists >= 1 ) {
				$wpdb->query( 'INSERT IGNORE INTO ' . $wpdb->prefix . "options (option_name, option_value, autoload) SELECT REPLACE(option_name, 'webarx_', 'patchstack_') as option_name, option_value, autoload FROM " . $wpdb->prefix . "options WHERE option_name like 'webarx_%'" );
				$wpdb->query( 'UPDATE ' . $wpdb->prefix . 'options AS a SET option_value = (SELECT option_value FROM ' . $wpdb->prefix . "options WHERE option_name = REPLACE(a.option_name, 'patchstack_', 'webarx_')) WHERE option_name LIKE 'patchstack_%'" );
			}

			// Deactivate the plugin.
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( [ 'webarx/webarx.php' ] );
			update_option( 'patchstack_license_free', '0', true );
		}

		// Make sure any rewrite functionality has been loaded.
		$this->migrate();
		add_option( 'patchstack_first_activated', '1' );

		// Whether or not we should send a secret key to our API.
		$sendSecret = false;

		// Activate the license.
		if ( $this->plugin->client_id != 'PATCHSTACK_CLIENT_ID' && $this->plugin->private_key != 'PATCHSTACK_PRIVATE_KEY' ) {
			$this->alter_license( $this->plugin->client_id, $this->plugin->private_key, 'activate' );
		} elseif ( get_option( 'patchstack_clientid', false ) != false && get_option( 'patchstack_secretkey', false ) != false ) {
			$this->alter_license( get_option( 'patchstack_clientid' ), $this->get_secret_key(), 'activate' );
		} else {
			$sendSecret = true;
			update_option( 'patchstack_license_free', '1', true );
		}

		// Update firewall status after activating plugin
		$api   = new P_Api( $core );
		$token = $api->get_access_token();
		if ( ! empty( $token ) ) {
			$api->update_firewall_status( [ 'status' => 1 ] );
			$api->update_url( [ 'plugin_url' => get_option( 'siteurl' ) ] );
		} elseif ( $sendSecret ) {
			$secretToken = wp_generate_password( 36, true );
			update_option( 'patchstack_activation_secret', $secretToken );
			update_option( 'patchstack_activation_time', time() + 59 ) ;
		}

		// Immediately send software data to our server to set firewall as enabled.
		// Also immediately download the whitelist file and the firewall rules.
		do_action( 'patchstack_send_software_data' );
		if ( get_option( 'patchstack_license_free', 0 ) != 1 ) {
			do_action( 'patchstack_post_firewall_rules' );
			do_action( 'patchstack_post_dynamic_firewall_rules' );
		}

		// Try to create the mu-plugins folder/file.
		// No need to do this if it already exists.
		if ( file_exists( WPMU_PLUGIN_DIR . '/patchstack.php' ) || file_exists( WPMU_PLUGIN_DIR . '/_patchstack.php' ) || ( defined( 'PS_DISABLE_MU' ) && PS_DISABLE_MU ) ) {
			return;
		}

		// The mu-plugin does not exist, try to create it.
		@include_once ABSPATH . 'wp-admin/includes/file.php';
		$wpfs = WP_Filesystem();

		// Failed to initialize WP_Filesystem.
		if ( ! $wpfs ) {
			return;
		}

		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			wp_mkdir_p( WPMU_PLUGIN_DIR );
		}

		// Failed to create the mu-plugin folder.
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			return;
		}

		// Create the mu-plugin file in the folder.
		if ( is_writable( WPMU_PLUGIN_DIR ) ) {
			$php = @file_get_contents( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'mu-plugin.php' );
			@file_put_contents( trailingslashit( WPMU_PLUGIN_DIR ) . '_patchstack.php', $php );
		}
	}

	/**
	 * Build the required Patchstack tables.
	 *
	 * @param null|string  $ver The version to upgrade to.
	 * @param null|integer $site_id The blog id to perform the upgrades on.
	 * @return void
	 */
	public function migrate( $ver = null, $site_id = null ) {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $site_id != null ? $wpdb->get_blog_prefix( $site_id ) : $wpdb->prefix;

		// The following conditions will only execute if Patchstack is installed because of an update
		// and if we need to perform migrations.
		if ( $ver !== null && file_exists( dirname( __FILE__ ) . '/migrations/v' . str_replace( '.', '', $ver ) . '.php' ) ) {
			require_once dirname( __FILE__ ) . '/migrations/v' . str_replace( '.', '', $ver ) . '.php';
			return;
		}

		// Require the base migration.
		require_once dirname( __FILE__ ) . '/migrations/base.php';
	}

	/**
	 * Check if the database version of the plugin is running behind.
	 * If so, run the migrations up until the latest version.
	 *
	 * @return void
	 */
	public function migrate_check() {
		// Only perform migrations if we have any to execute.
		$versions = ['3.0.0', '3.0.1', '3.0.2', '3.0.3', '3.0.4'];
		if ( count( $versions ) == 0 ) {
			return;
		}

		// Get current database version and run the migrations.
		$db_version = get_option( 'patchstack_db_version', false );
		foreach ( $versions as $version ) {
			if ( version_compare( $db_version, $version, '<' ) ) {
				$this->migrate( $version );
			}
		}
	}

	/**
	 * Perform cleanup when the plugin is deactivated.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Update firewall status after de-activating plugin
		try {
			$token = $this->plugin->api->get_access_token();
			if ( ! empty( $token ) ) {
				$this->plugin->api->update_firewall_status( [ 'status' => 0 ] );
			}
		} catch (\Exception $e) {
			//
		}

		// Clear all Patchstack scheduled tasks.
		$tasks = [ 'patchstack_zip_backup', 'patchstack_send_software_data', 'patchstack_send_hacker_logs', 'patchstack_send_visitor_logs', 'patchstack_send_event_logs', 'patchstack_reset_blocked_attacks', 'patchstack_post_firewall_rules', 'patchstack_post_firewall_htaccess_rules', 'patchstack_post_dynamic_firewall_rules', 'patchstack_update_license_status', 'patchstack_update_plugins', 'patchstack_send_ping', 'patchstack_check_env', 'puc_cron_check_updates-webarx' ];
		foreach ( $tasks as $task ) {
			wp_clear_scheduled_hook( $task );
		}

		// Cleanup the .htaccess file.
		$this->plugin->htaccess->cleanup_htaccess_file();
		$this->auto_prepend_removal();

		// Remove the mu-plugin file if it exists.
		foreach (['patchstack.php', '_patchstack.php'] as $file) {
			if ( file_exists( WPMU_PLUGIN_DIR . '/' . $file )) {
				wp_delete_file( WPMU_PLUGIN_DIR . '/' . $file );
			}
		}
	}

	/**
	 * Activate or deactivate a license on the current site.
	 *
	 * @param integer $id
	 * @param string  $secret
	 * @param string  $action
	 * @return array
	 */
	public function alter_license( $id, $secret, $action ) {
		// Set default options in case they have not been set yet.
		$this->plugin->admin_options->settings_init();

		// Store current keys in tmp variable so in case it fails, we can set it back.
		$tmp_id  = get_option( 'patchstack_clientid' );
		$tmp_key = $this->get_secret_key();

		// Set the new values.
		update_option( 'patchstack_clientid', $id );
		$this->set_secret_key( $secret );

		// Activate the license.
		if ( $action == 'activate' ) {
			$api_result = $this->plugin->api->get_access_token( $id, $secret, true );

			// Valid result?
			if ( ! $api_result ) {
				update_option( 'patchstack_clientid', $tmp_id );
				$this->set_secret_key( $tmp_key );

				return [
					'result'  => 'error',
					'body' => json_encode($this->plugin->api->message),
					'message' => 'Cannot activate license!',
				];
			}

			// Successfully activated.
			update_option( 'patchstack_license_activated', '1', true );

			// Update license status and fetch policy settings.
			$fetchPolicy = (int) get_option( 'patchstack_last_license_check', 0 ) == 0;
			$this->plugin->api->update_license_status( $fetchPolicy );

			// Perform post-activation actions, incl. access token retrieval.
			$token = $this->plugin->api->get_access_token();
			if ( ! empty( $token ) ) {

				// Immediately send software data to our server to set firewall as enabled.
				// Also immediately download the whitelist file and the firewall rules.
				do_action( 'patchstack_send_software_data' );
				if ( get_option( 'patchstack_license_free', 0 ) != 1 ) {
					update_option( 'patchstack_basic_firewall', 1, true );
					do_action( 'patchstack_post_firewall_rules' );
					do_action( 'patchstack_post_dynamic_firewall_rules' );
					$this->header();
				}

				// Update firewall status, URL and ping API.
				$this->plugin->api->update_firewall_status( [ 'status' => $this->get_option( 'patchstack_basic_firewall' ) == 1 ] );
				$this->plugin->api->update_url( [ 'plugin_url' => get_option( 'siteurl' ) ] );
				$this->auto_prepend_injection();
			}

			return [
				'result'  => 'success',
				'message' => 'License activated!',
			];
		}

		// Deactivate the license.
		if ( $action == 'deactivate' ) {
			update_option( 'patchstack_api_token', '' );
			update_option( 'patchstack_license_activated', '0' );
			update_option( 'patchstack_clientid', '' );
			update_option( 'patchstack_secretkey', '' );
			update_option( 'patchstack_secretkey_nonce', '' );

			$this->auto_prepend_removal();
	
			return [
				'result'  => 'success',
				'message' => 'License deactivated!',
			];
		}
	}

	/**
	 * Send a request to our API for the IP address header.
	 * 
	 * @return void
	 */
	public function header()
	{
		$header = get_option( 'patchstack_firewall_ip_header', '' );
		$computed = get_option( 'patchstack_ip_header_computed', 0 );
		$force = get_option( 'patchstack_ip_header_force_compute', 0 );

		if ( ( $header == '' && ! $computed ) || $force ) {
			// Create an OTT token.
			$ott = md5( wp_generate_password( 32, true, true ) );
			update_option( 'patchstack_ott_action', $ott );
	
			// Tell our API.
			wp_remote_request(
				$this->plugin->api_url . '/api/header',
				[
					'method'      => 'POST',
					'timeout'     => 60,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => true,
					'headers'     => [
						'Source-Host'   => get_site_url(),
					],
					'body'        => [
						'token' => $ott,
						'url' => get_site_url()
					],
					'cookies'     => [],
				]
			);
		}
	}

	/**
	 * Create the environment needed for the auto prepend firewall functionality.
	 * 1. First we check if an auto_prepend_file already exists somewhere.
	 * 2. Then we write to the .htaccess file and check its status code.
	 * 3. Then we write to the .user.ini file and check its status code, .user.ini is optional if there are any errors with it.
	 * 
	 * @param boolean $refresh
	 * @return boolean
	 */
	public function auto_prepend_injection($refresh = false)
	{
		// Determine if AP firewall is enabled.
		if ( ! get_option( 'patchstack_firewall_ap_enabled', false ) ) {
			return;
		}

		// Determine if we received an error that hasn't been cleared yet.
		if ( get_option( 'patchstack_firewall_ap_error', '' ) != '' ) {
			return;
		}

		// No need to display this error if the .htaccess functionality has been disabled.
		if ( get_option( 'patchstack_disable_htaccess', 0 ) || ( defined( 'PS_DISABLE_HTACCESS' ) && PS_DISABLE_HTACCESS ) || ( defined( 'PS_DISABLE_MU' ) && PS_DISABLE_MU ) ) {
			return;
		}

		// Get filesystem.
		global $wp_filesystem;
		if ( ! $this->get_filesystem() ) {
			update_option( 'patchstack_firewall_ap_error', 'Could not establish filesystem.' );
			return false;
		}

		// First ensure a .htaccess file exists, otherwise no point.
		$htaccess_file = ABSPATH . '.htaccess';
		if ( ! $wp_filesystem->exists( $htaccess_file ) && ! $wp_filesystem->touch( $htaccess_file ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The .htaccess file could be found nor created.' );
			return false;
		}

		// Completely halt if there is already an auto_prepend_file present in .htaccess and not of Patchstack.
		$htaccess_content = $wp_filesystem->get_contents( $htaccess_file );
		if ( stripos( $htaccess_content, 'auto_prepend_file' ) !== false && stripos( $htaccess_content, 'mu-plugin-ap.php' ) === false ) {
			update_option( 'patchstack_firewall_ap_error', 'A different auto_prepend_file value is already present in the .htaccess file.' );
			return false;
		}

		// Completely halt if there is already an auto_prepend_file present in .user.ini and not of Patchstack.
		$user_ini = ini_get( 'user_ini.filename' );
		if ( $user_ini && $wp_filesystem->exists( ABSPATH . $user_ini ) ) {
			$ini_content = $wp_filesystem->get_contents( ABSPATH . $user_ini );
			if ( stripos( $ini_content, 'auto_prepend_file' ) !== false && stripos( $ini_content, 'mu-plugin-ap.php' ) === false ) {
				update_option( 'patchstack_firewall_ap_error', 'A different auto_prepend_file value is already present in the .user.ini file.' );
				return false;
			}
		}

		// Determine if we can write the /wp-content/pslogs/ folder.
		$logs_dir = WP_CONTENT_DIR . '/pslogs/';
		if ( ! $wp_filesystem->exists( $logs_dir ) && ! $wp_filesystem->mkdir( $logs_dir ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The path ' . $logs_dir . ' could not be created.' );
			return false;
		}

		// Create the blank index.php file.
		if ( ! $wp_filesystem->exists( $logs_dir . 'index.php' ) && ! $wp_filesystem->put_contents( $logs_dir . 'index.php', '' ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The file ' . $logs_dir . 'index.php could not be created.' );
			return false;
		}

		// Create the logs.php file.
		if ( ! $wp_filesystem->exists( $logs_dir . 'logs.php' ) && ! $wp_filesystem->put_contents( $logs_dir . 'logs.php', '<?php exit; ?>' . PHP_EOL ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The file ' . $logs_dir . 'logs.php could not be created.' );
			return false;
		}

		// Save current site id.
		$current_id = get_current_blog_id();

		// Pull data to save into the config.php file.
		$sites = $this->get_sites();
		$data = [];
		foreach ($sites as $site) {
			$this->switch_to_blog( $site->id );
			$data[] = [
				'site_id' => $site->id,
				'site_url' => preg_replace( '/^https?:\/\//i', '', $site->siteurl ),
				'home_url' => preg_replace( '/^https?:\/\//i', '', get_option( 'home' ) ),
				'patchstack_basic_firewall' => get_option( 'patchstack_basic_firewall', 1 ),
				'patchstack_license_activated' => get_option( 'patchstack_license_activated', 0 ),
				'patchstack_license_free' => get_option( 'patchstack_license_free', 0 ),
				'patchstack_firewall_ip_header' => get_option( 'patchstack_firewall_ip_header', '' ),
				'patchstack_firewall_rules_v3_ap' => base64_encode( get_option( 'patchstack_firewall_rules_v3_ap', '[]' ) )
			];
		}

		// Switch back to current site.
		$this->switch_to_blog( $current_id );

		// Save into the config.php file.
		if ( ! $wp_filesystem->put_contents( $logs_dir . 'config.php', '<?php return ' . var_export( $data, true ) . ';' ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The file ' . $logs_dir . 'config.php could not be created.' );
			return false;
		}

		// In case we only want to refresh the auto prepend rules, we stop here.
		if ( $refresh ) {
			return true;
		}

		// Prepare the rules to inject into .htaccess.
		$prepend_rules = $this->get_auto_prepend_rules();
		if ( ! $prepend_rules ) {
			return false;
		}

		// Determine if the rules already exist and overwrite them in case of path change.
		$original_htaccess = $htaccess_content;
		$re = '/# BEGIN AP Patchstack.*?# END AP Patchstack/is';
		if ( preg_match( $re, $htaccess_content ) ) {
			$htaccess_content = preg_replace( $re, rtrim($prepend_rules['htaccess']), $htaccess_content );
		} else {
			$htaccess_content .= "\n" . $prepend_rules['htaccess'];
		}

		// Attempt to write to the .htaccess file.
		if ( ! $wp_filesystem->put_contents( $htaccess_file, $htaccess_content ) ) {
			update_option( 'patchstack_firewall_ap_error', 'Could not inject into the .htaccess file.' );
			return false;
		}

		// Determine if the site still works as expected with the injected htaccess rules.
		if ( $this->get_site_status_code() >= 400 ) {
			$wp_filesystem->put_contents( $htaccess_file, $original_htaccess );
			update_option( 'patchstack_firewall_ap_error', 'The .htaccess rules caused a fatal internal server error.' );
			return false;
		}

		// Ensure a .user.ini is present.
		$user_ini = ini_get( 'user_ini.filename' );
		if ( ! $user_ini ) {
			update_option( 'patchstack_firewall_ap_error', '' );
			return true;
		}

		// Define full path to the .user.ini file.
		$user_ini = ABSPATH . $user_ini;

		// Create the file if it does not exist.
		if ( ! $wp_filesystem->exists( $user_ini ) && ! $wp_filesystem->touch( $user_ini ) ) {
			update_option( 'patchstack_firewall_ap_error', 'The .user.ini file could not be created.' );
			return true;
		}
		
		// Get the contents of the current .user.ini file.
		$ini_content = $wp_filesystem->get_contents( $user_ini );

		// Determine if the rules already exist and overwrite them in case of path change.
		$original_ini = $ini_content;
		$re = '/; BEGIN AP Patchstack.*?; END AP Patchstack/is';
		if ( preg_match( $re, $ini_content ) ) {
			$ini_content = preg_replace( $re, rtrim($prepend_rules['ini']), $ini_content );
		} else {
			$ini_content .= "\n" . $prepend_rules['ini'];
		}

		// Attempt to write to the .user.ini file.
		if ( ! $wp_filesystem->put_contents( $user_ini, $ini_content ) ) {
			update_option( 'patchstack_firewall_ap_error', 'Could not inject into the .user.ini file.' );
			return true;
		}

		// Determine if the site still works as expected with the injected .user.ini rules.
		if ( $this->get_site_status_code() == 500 ) {
			$wp_filesystem->put_contents( $user_ini, $original_ini );
			update_option( 'patchstack_firewall_ap_error', 'The .user.ini rules caused a fatal internal server error.' );
			return false;
		}

		update_option( 'patchstack_firewall_ap_error', '' );
		return true;
	}

	/**
	 * Remove everything related to the auto prepend functionality.
	 * 
	 * @return boolean
	 */
	public function auto_prepend_removal()
	{
		global $wp_filesystem;
		$this->get_filesystem();

		// Define our paths to access.
		$logs_dir = WP_CONTENT_DIR . '/pslogs/';
		$htaccess_file = ABSPATH . '.htaccess';
		$ini_file = ABSPATH . '.user.ini';

		// Remove the entire /pslogs/ directory.
		if ( $wp_filesystem->is_dir( $logs_dir ) ) {
			$wp_filesystem->delete( $logs_dir, true );
		}

		// Remove the .htaccess injected rules.
		if ( $wp_filesystem->is_file( $htaccess_file ) ) {
			$htaccess_content = $wp_filesystem->get_contents( $htaccess_file );
			$re = '/# BEGIN AP Patchstack.*?# END AP Patchstack/is';
			if ( preg_match( $re, $htaccess_content ) ) {
				$htaccess_content = preg_replace( $re, '', $htaccess_content );
				$wp_filesystem->put_contents( $htaccess_file, $htaccess_content );
			}
		}

		// Remove the .user.ini injected rules.
		if ( $wp_filesystem->is_file( $ini_file ) ) {
			$ini_content = $wp_filesystem->get_contents( $ini_file );
			$re = '/; BEGIN AP Patchstack.*?; END AP Patchstack/is';
			if ( preg_match( $re, $ini_content ) ) {
				$ini_content = preg_replace( $re, '', $ini_content );
				$wp_filesystem->put_contents( $ini_file, $ini_content );
			}
		}
	}

	/**
	 * Attempt to establish the proper WP_FileSystem.
	 * 
	 * @return boolean
	 */
	private function get_filesystem()
	{
		// Seems to be the only native way to obtain FTP credentials, if defined.
		include_once( ABSPATH . 'wp-admin/includes/file.php' );
		ob_start();
		$creds = request_filesystem_credentials( admin_url( 'admin-ajax.php' ), '', false, ABSPATH, null, true );
		ob_end_clean();

		// Returns false if no filesystem connection could be determined.
		if ( $creds === false ) {
			update_option( 'patchstack_firewall_ap_error', 'Unable to establish filesystem connection.' );
			return false;
		}

		// Attempt to initialize it.
		$fs = WP_Filesystem( $creds, ABSPATH, true );
		if ( ! $fs ) {
			update_option( 'patchstack_firewall_ap_error', 'Unable to establish filesystem connection through acquired creds.' );
			return false;
		}

		return true;
	}

	/**
	 * Get sites as part of the environment.
	 * 
	 * @return array
	 */
	private function get_sites()
	{
		if ( ! function_exists( 'get_sites' ) ) {
			return [
				(object) [
					'id' => 0,
					'siteurl' => get_site_url()
				]
			];
		}

		return get_sites();
	}

	/**
	 * Switch to a different site.
	 * 
	 * @param integer $site_id
	 * @return void
	 */
	private function switch_to_blog($site_id)
	{
		if ( ! function_exists( 'switch_to_blog' ) ) {
			return;
		}

		switch_to_blog( $site_id );
	}

	/**
	 * Determine the web-server software and make sure we support it before we generate the .htaccess rules for it.
	 * 
	 * @return array|boolean
	 */
	private function get_auto_prepend_rules()
	{
		// Establish location of the auto prepend file.
		$mu_file = __DIR__ . '/mu-plugin-ap.php';
		if ( ! file_exists( $mu_file ) ) {
			return false;
		}

		// Ensure that the SERVER_SOFTWARE value is set.
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';
		if ( ! $software ) {
			update_option( 'patchstack_firewall_ap_error', 'Unsupported SERVER_SOFTWARE, found: ' . $software );
			return false;
		}

		// At this time, reject non-Apache environments.
		$sapi = function_exists( 'php_sapi_name' ) ? php_sapi_name() : false;
		if ( ! $sapi || stripos( $_SERVER['SERVER_SOFTWARE'], 'litespeed' ) === false && $sapi != 'litespeed' && stripos($_SERVER['SERVER_SOFTWARE'], 'apache' ) === false) {
			update_option( 'patchstack_firewall_ap_error', 'Unsupported SERVER_SOFTWARE, found: ' . $_SERVER['SERVER_SOFTWARE'] . ' and ' . $sapi );
			return false;
		}

		// Seperate flag for LiteSpeed.
		$is_litespeed = stripos( $_SERVER['SERVER_SOFTWARE'], 'litespeed' ) !== false || $sapi == 'litespeed';

		// Attempt to find the Apache version, < 2.4 does not support <If>.
		// This depends on ServerTokens value, so only stop execution if we can't find the specific unsupported versions.
		$version = function_exists( 'apache_get_version' ) ? apache_get_version() : $software;
		if ( ! $is_litespeed && stripos( $version, 'Apache/2.4' ) === false ) {
			update_option( 'patchstack_firewall_ap_error', 'Unsupported SERVER_SOFTWARE, found: ' . $software );
			return false;
		}

		// Add c-style slashes.
		$mu_file_as = wp_normalize_path(addcslashes($mu_file, "'"));

		// Bit different rules for LiteSpeed.
		if ( ! $is_litespeed ) {
			$rules = "<IfModule mod_php.c>
      php_value auto_prepend_file '" . $mu_file_as . "'
    </IfModule>
    <IfModule mod_php5.c>
      php_value auto_prepend_file '" . $mu_file_as . "'
    </IfModule>
    <IfModule mod_php7.c>
      php_value auto_prepend_file '" . $mu_file_as . "'
    </IfModule>";
		} else {
			$rules = "<IfModule LiteSpeed>
      php_value auto_prepend_file '" . $mu_file_as . "'
    </IfModule>
    <IfModule lsapi_module>
      php_value auto_prepend_file '" . $mu_file_as . "'
    </IfModule>";
		}

		return [
			'htaccess' => "# BEGIN AP Patchstack
<IfModule mod_authz_core.c>
	<If \"-f '" . $mu_file_as . "'\">
		" . $rules . "

		<Files \".user.ini\">
		<IfModule mod_authz_core.c>
			Require all denied
		</IfModule>
		<IfModule !mod_authz_core.c>
			Order deny,allow
			Deny from all
		</IfModule>
		</Files>
	</If>
</IfModule>
# END AP Patchstack
",
			'ini' => "; BEGIN AP Patchstack
auto_prepend_file = '" . $mu_file_as . "'
; END AP Patchstack
"
		];
	}

	/**
	 * Retrieve the status code of the site.
	 * This is done to determine if the .htaccess rules do not trigger an error.
	 *
	 * @return integer
	 */
	public function get_site_status_code() {
		$response = wp_remote_get( get_site_url() );
		$http_code = wp_remote_retrieve_response_code( $response );
		return $http_code;
	}

	/**
	 * If option is updated, refresh AP config file.
	 *
	 * @param string $option_name
	 * @param string $option_name
	 * @param mixed  $value
	 * @return void
	 */
	public function updated_option( $option_name, $old_value, $value ) {
		// Only allow to run for our options. The IP header is embedded in the AP config
		// file too, so a change there must also regenerate it.
		if ( !in_array( $option_name, [ 'patchstack_basic_firewall', 'patchstack_license_free', 'patchstack_firewall_rules_v3_ap', 'patchstack_firewall_ip_header' ] ) ) {
			return;
		}

		// Not strict type matching.
		if ( $old_value == $value ) {
			return;
		}

		$this->auto_prepend_injection(true);
	}
}
