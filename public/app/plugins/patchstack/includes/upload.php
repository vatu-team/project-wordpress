<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to upload the local logs to our API so it can
 * be shown on the app.
 */
class P_Upload extends P_Core {

	/**
	 * Add the actions required to upload logs to our API.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		// In case the software has never been synchronized, force it.
		if ( ! get_option( 'patchstack_software_data_hash', false ) && ! get_option( 'patchstack_software_upload_attempted', false ) ) {
			$this->upload_software();
		}

		// Register the actions.
		add_action( 'patchstack_send_software_data', [ $this, 'upload_software' ] );
		add_action( 'patchstack_send_hacker_logs', [ $this, 'upload_firewall_logs' ] );
		add_action( 'patchstack_send_event_logs', [ $this, 'upload_activity_logs' ] );
		add_action( 'patchstack_import_ap_logs', [ $this, 'import_ap_logs' ] );

		// In case a plugin or upgrade has been performed, re-synchronize with the app.
		add_action( 'activated_plugin', [ $this, 'upload_software' ] );
		add_action( 'deactivated_plugin', [ $this, 'upload_software' ] );
		add_action( 'deleted_plugin', [ $this, 'upload_software' ] );
		add_action( 'upgrader_process_complete', [ $this, 'upload_software' ] );
		add_action( '_core_updated_successfully', [ &$this, 'upload_software' ] );
	}

	/**
	 * Synchronize the software data with our API.
	 * This includes plugins, themes, WordPress and PHP version.
	 *
	 * @return void|array
	 */
	public function upload_software() {
		// Get the software data and hash.
		$data = $this->get_software_data();
		$hash = sha1( json_encode( $data ) );

		// Do not sync for no reason.
		if ( ! defined( 'DOING_CRON' ) && ! isset( $_POST['patchstack_secret'] ) && get_option( 'patchstack_software_data_hash', false ) === $hash && ! is_admin() && ! defined( 'WP_CLI' ) ) {
			return;
		}

		// Make sure to not keep calling this function.
		update_option( 'patchstack_software_upload_attempted', 1 );

		// Synchronize the software list with the API.
		$results = $this->plugin->api->upload_software( [ 'software' => json_encode( $data ) ] );
		if ( isset( $results['success'] ) ) {
			update_option( 'patchstack_software_data_hash', $hash );

			// The result will also contain a list of all vulnerable plugins on the site that is returned by the API.
			// If the auto update setting is enabled for vulnerable plugins, perform the update once the 15 minute
			// scheduled task "patchstack_update_plugins" is executed.
			$update = get_site_option( 'patchstack_auto_update', [] );
			if ( isset( $results['vulnerable'] ) && is_array( $update ) && in_array( 'vulnerable', $update ) ) {
				update_site_option( 'patchstack_vulnerable_plugins', $results['vulnerable'] );
			}

			// If we have vulnerable plugins, determine if we had them before and if not, pull latest firewall rules.
			if ( isset( $results['vulnerable'] ) && count( $results['vulnerable'] ) > 0 ) {
				$prev = get_site_option( 'patchstack_latest_vulnerable', [] );
				foreach ( $results['vulnerable'] as $vuln ) {
					if ( is_array( $prev ) && ! in_array ( $vuln, $prev ) ) {
						do_action( 'patchstack_post_dynamic_firewall_rules' );
						break;
					}
				}

				update_site_option( 'patchstack_latest_vulnerable', $results['vulnerable'] );
			} else {
				update_site_option( 'patchstack_latest_vulnerable', [] );
			}

			// If we received the number of vulnerable count.
			if ( isset ( $results['vulnerability_count'], $results['vulnerability_fix_count'] ) ) {
				update_option( 'patchstack_vulns_present', $results['vulnerability_count'] );
				update_option( 'patchstack_fixes_present', $results['vulnerability_fix_count'] );
			}

			return $results;
		}

		return;
	}

	/**
	 * Synchronize the firewall logs with our API.
	 *
	 * @return void
	 */
	public function upload_firewall_logs() {
		global $wpdb;

		// Do not execute upload action on free sites.
		if ( ! $this->license_is_active() || $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Do not process if we are already processing a previous batch.
		if ( get_option( 'patchstack_firewall_log_processing', false ) ) {
			return;
		}

		update_option( 'patchstack_firewall_log_processing', 1 );

		// Attempt to fetch data, if any.
		$lastId = get_option( 'patchstack_firewall_log_lastid', 0 );
		$successId = $lastId;

		// Do a maximum of 100 log entries per cronjob.
		for ($i = 0; $i <= 1; $i++) {
			// Pull the data from the database, in batches of 100.
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, ip, log_date, request_uri, user_agent, fid, method, post_data FROM ' . $wpdb->prefix . 'patchstack_firewall_log WHERE id > %d ORDER BY id LIMIT 0,100',
					$lastId
				)
			);

			// No need to continue if we have no data.
			if ( $wpdb->num_rows == 0 ) {
				update_option( 'patchstack_firewall_log_lastid', 0 );
				break;
			}
	
			// Construct the array to be uploaded to our API.
			$logs = [];
			foreach ( $items as $item ) {

				// Always advance the cursor so filtered rows don't stall the batch.
				$lastId = $item->id;

				// Entries that we don't want to store on the API side.
				if ( stripos( $item->request_uri, 'wp-comments-post' ) !== false ) {
					continue;
				}

				// Push to entries to be uploaded.
				$logs[] = [
					'ip'          => $item->ip,
					'fid'         => $item->fid,
					'request_uri' => $item->request_uri,
					'user_agent'  => $item->user_agent,
					'method'      => $item->method,
					'log_date'    => $item->log_date,
					'post_data'   => $item->post_data,
				];
			}
	
			// JSON encode the logs and upload.
			$logs = json_encode( $logs );
			$results = $this->plugin->api->upload_firewall_logs(
				[
					'logs' => $logs,
					'type' => 'firewall',
				]
			);

			// A failed upload returns a status code or null instead of an array;
			// bail without advancing so the logs are not deleted before they reach the API.
			if ( ! is_array( $results ) || isset( $results['errors'] ) ) {
				update_option( 'patchstack_firewall_log_lastid', $successId );
				break;
			}

			$successId = $lastId;
			update_option( 'patchstack_firewall_log_lastid', $successId );
		}

		// Delete the logs.
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'patchstack_firewall_log WHERE id <= ' . (int) $successId );

		// No longer processing.
		update_option( 'patchstack_firewall_log_processing', 0 );
	}

	/**
	 * Synchronize the activity logs with our API.
	 *
	 * @return void
	 */
	public function upload_activity_logs() {
		global $wpdb;

		// Do not execute upload action on free sites.
		if ( ! $this->license_is_active() || $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Do not process if we are already processing a previous batch.
		if ( get_option( 'patchstack_eventlog_processing', false ) ) {
			return;
		}

		update_option( 'patchstack_eventlog_processing', 1 );

		// Determine if we should upload failed logins to the app.
		$where = " AND action != 'failed login' ";
		if ( $this->get_option( 'patchstack_activity_log_failed_logins_db', 0 ) == 1 ) {
			$where = ' ';
		}

		// Attempt to fetch data, if any.
		$lastId = get_option( 'patchstack_eventlog_lastid', 0 );
		$successId = $lastId;

		// Do a maximum of several hundred log entries per cronjob.
		for ($i = 0; $i <= 1; $i++) {
			// Pull the data from the database, in batches of 100.
			$items = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT id, author, ip, object, object_id, object_name, action, date FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE id > %d' . $where . 'ORDER BY id LIMIT 0,100',
					$lastId
				),
				ARRAY_A
			);

			// No need to continue if we have no data.
			if ( $wpdb->num_rows == 0 ) {
				update_option( 'patchstack_eventlog_lastid', 0 );
				break;
			}

			// Get the last ID in the result set.
			$lastId = $items[count($items) - 1]['id'];

			// Send to the API.
			$logs = json_encode( $items );
			$results = $this->plugin->api->upload_activity_logs( [ 'logs' => $logs ] );

			// A failed upload returns a status code or null instead of an array;
			// bail without advancing so the logs are not deleted before they reach the API.
			if ( ! is_array( $results ) || isset( $results['errors'] ) ) {
				update_option( 'patchstack_eventlog_lastid', $successId );
				break;
			}

			$successId = $lastId;
			update_option( 'patchstack_eventlog_lastid', $successId );
		}

		// Delete the logs.
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'patchstack_event_log WHERE id <= ' . (int) $successId );

		// No longer processing.
		update_option( 'patchstack_eventlog_processing', 0 );
	}

	/**
	 * Obtain information about the software that the user has installed.
	 * This includes plugins, themes, WordPress and PHP version.
	 *
	 * @return array
	 */
	public function get_software_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		// Refetch updates data if we are performing a plugin listener related action.
		if ( isset( $_POST['patchstack_secret'] ) ) {
			@require_once ABSPATH . 'wp-includes/update.php';
			@wp_update_themes();
			@wp_update_plugins();
		}

		// Fetch list of plugins.
		$all_plugin        = get_plugins();
		$installed_plugins = array_keys( $all_plugin );
		$updatable_plugins = get_plugin_updates();
		$software_list     = [];

		foreach ( $installed_plugins as $plugin ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
				continue;
			}

			$plugin_data    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
			$new_version    = empty( $updatable_plugins[ $plugin ]->update->new_version ) ? '' : $updatable_plugins[ $plugin ]->update->new_version;
			$plugin_name    = empty( $plugin_data['Name'] ) ? '' : $plugin_data['Name'];
			$plugin_version = empty( $plugin_data['Version'] ) ? '' : $plugin_data['Version'];

			if ( ! empty( $plugin_name ) && ! empty( $plugin_version ) ) {
				
				// Determine the active state.
				if ( isset( $_GET['action'], $_GET['plugin'] ) && $_GET['action'] == 'deactivate' && $_GET['plugin'] == $plugin) {
					$active = 0;
				} else {
					$active = (int) is_plugin_active( $plugin );
				}

				$software_list[] = [
					'sw_type'    => 'plugin',
					'sw_name'    => $plugin_name,
					'sw_cur_ver' => $plugin_version,
					'sw_new_ver' => $new_version,
					'sw_key'     => $plugin,
					'sw_active'  => $active
				];
			}
		}

		// Fetch list of themes.
		$themes           = wp_get_themes();
		$themes_keys      = array_keys( $themes );
		$updatable_themes = get_theme_updates();

		foreach ( $themes_keys as $theme_key ) {
			$themes_data       = $themes[ $theme_key ];
			$theme_temporary   = empty( $updatable_themes[ $theme_key ] ) ? '' : $updatable_themes[ $theme_key ];
			$theme_new_version = empty( $updatable_themes[ $theme_key ] ) || ! isset( $theme_temporary->update, $theme_temporary->update['new_version'] ) ? '' : $theme_temporary->update['new_version'];
			$theme_name        = $themes_data->get( 'Name' );
			$theme_version     = $themes_data->get( 'Version' );

			if ( ! empty( $theme_name ) && ! empty( $theme_version ) ) {
				$software_list[] = [
					'sw_type'    => 'theme',
					'sw_name'    => $theme_name,
					'sw_cur_ver' => $theme_version,
					'sw_new_ver' => $theme_new_version,
					'sw_key'     => $theme_key,
				];
			}
		}

		// Fetch WordPress version.
		global $wp_version;
		$core_updates    = get_core_updates();
		$new_wp_version  = ( ! empty( $core_updates ) && $core_updates[0]->response == 'upgrade' ) ? $core_updates[0]->version : '';
		$software_list[] = [
			'sw_type'    => 'wordpress',
			'sw_name'    => 'WordPress',
			'sw_cur_ver' => $wp_version,
			'sw_new_ver' => $new_wp_version,
		];

		// Fetch PHP version.
		$software_list[] = [
			'sw_type'    => 'php',
			'sw_name'    => 'PHP',
			'sw_cur_ver' => phpversion(),
			'sw_new_ver' => '',
		];

		// Fetch database server version.
		global $wpdb;
		if ( ! is_null( $wpdb ) ) {
			$software_list[] = [
				'sw_type' => 'database',
				'sw_name' => 'Database',
				'sw_cur_ver' => $wpdb->get_var( 'SELECT VERSION()' ),
				'sw_new_ver' => ''
			];
		}

		$software_list = apply_filters( 'patchstack_get_software_data', $software_list );
		return $software_list;
	}

	/**
	 * Import the logs generated by the auto prepend firewall rules.
	 * 
	 * @return void
	 */
	public function import_ap_logs()
	{
		if ( ! get_option( 'patchstack_firewall_ap_enabled', false ) ) {
			return;
		}

		// Do not process if we are already processing a previous batch.
		if ( get_option( 'patchstack_firewall_log_ap_processing', false ) ) {
			return;
		}

		// Attempt to load config file.
		$logs = __DIR__ . '/../../../pslogs/logs.php';
		if ( ! file_exists( $logs ) ) {
			return;
		}

		// Load the extension.
		if ( ! file_exists( __DIR__ . '/../lib/patchstack/vendor/autoload.php' ) ) {
			return;
		}

		// Set the processing lock only once we know there is work to do, otherwise an
		// early return above would leave the lock stuck and block all future imports.
		update_option( 'patchstack_firewall_log_ap_processing', 1 );

		global $wpdb;

		require_once __DIR__ . '/../lib/patchstack/vendor/autoload.php';
		$extension = new Patchstack\Extensions\WordPress\Extension( [], $this );

		// Read the logs file.
		$file = new SplFileObject( $logs );

		// Iterate through each line.
		while ( ! $file->eof() ) {
			$line = $file->fgets();

			// Skip first line.
			if ( trim($line) == '' || strpos( $line, '<?php' ) !== false ) {
				continue;
			}

			// Decode the line to import.
			$data = json_decode( base64_decode( $line ), true );
			if ( ! $data || ! is_array( $data ) ) {
				continue;
			}

			// Skip malformed entries that are missing the fields we rely on below.
			if ( ! isset( $data['site_id'], $data['ip'], $data['request_uri'], $data['user_agent'], $data['method'], $data['fid'], $data['post_data'] ) ) {
				continue;
			}

			// Insert into the logs.
			$wpdb->insert(
				$wpdb->get_blog_prefix( $data['site_id'] ) . 'patchstack_firewall_log',
				[
					'ip'          => $data['ip'],
					'request_uri' => $data['request_uri'],
					'user_agent'  => $data['user_agent'],
					'method'      => $data['method'],
					'fid'         => $data['fid'],
					'flag'        => '',
					'post_data'   => $data['post_data'],
					'block_type'  => 'BLOCK'
				]
			);

			// Update counters.
			$hits = (int) $this->get_blog_option( $data['site_id'], 'patchstack_hits_all_time', 0 );
			$this->update_blog_option( $data['site_id'], 'patchstack_hits_all_time', $hits + 1 );
	
			$counters = $this->get_blog_option( $data['site_id'], 'patchstack_hits_last_30', [] );
			$counters = $extension->merge_counters( [ date('Y-m-d') => 1 ], $counters );
			$this->update_blog_option( $data['site_id'], 'patchstack_hits_last_30', $counters );
		}

		$file = null;
		file_put_contents( $logs, '<?php exit; ?>' . PHP_EOL );

		// Update processing state.
		update_option( 'patchstack_firewall_log_ap_processing', 0 );
	}
}
