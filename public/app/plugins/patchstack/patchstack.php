<?php
/**
 * Plugin Name: Patchstack Security
 * Plugin URI:  https://patchstack.com/?utm_medium=wp&utm_source=dashboard&utm_campaign=patchstack%20plugin
 * Author URI: https://patchstack.com/?utm_medium=wp&utm_source=dashboard&utm_campaign=patchstack%20plugin
 * Description: Patchstack identifies security vulnerabilities in WordPress plugins, themes, and core.
 * Version: 2.3.7
 * Author: Patchstack
 * License: GPLv3
 * Text Domain: patchstack
 * Domain Path: /languages
 * Requires at least: 5.2
 * Requires PHP: 5.6
 */

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'patchstack_autoload_classes' ) ) {
	/**
	 * Autoloads the Patchstack classes when called.
	 *
	 * @param string $class_name The class name to autoload.
	 * @return void
	 */
	function patchstack_autoload_classes( $class_name ) {
		// If the requested class doesn't have our prefix, don't load it.
		if ( strpos( $class_name, 'P_' ) !== 0 ) {
			return;
		}

		// Set up our filename.
		$file_name = strtolower( str_replace( '_', '-', substr( $class_name, strlen( 'P_' ) ) ) );
		$dir       = trailingslashit( dirname( __FILE__ ) ) . 'includes/';
		$target    = [ $dir . $file_name . '.php', $dir . 'admin/' . str_replace( 'admin-', '', $file_name ) . '.php' ];

		// Attempt each target and load if it exists.
		foreach ( $target as $file ) {
			if ( file_exists( $file ) ) {
				include_once $file;
			}
		}
	}
}
spl_autoload_register( 'patchstack_autoload_classes' );

if ( ! class_exists( 'patchstack' ) ) {

	/**
	 * This is the main Patchstack class used for all Patchstack related features and to launch
	 * the Patchstack plugin.
	 */
	class Patchstack {

		/**
		 * The plugin version.
		 *
		 * @var string
		 */
		const VERSION = '2.3.7';

		/**
		 * API URL of Patchstack to communicate with.
		 *
		 * @var    string
		 */
		const API_URL = 'https://api.patchstack.com';

		/**
		 * API Auth URL of Patchstack to communicate with.
		 *
		 * @var    string
		 */
		const AUTH_URL = 'https://auth.patchstack.com';

		/**
		 * Client ID, this is only set when freshly downloaded from the app.
		 *
		 * @var string
		 */
		const CLIENT_ID = 'PATCHSTACK_CLIENT_ID';

		/**
		 * Client private key, this is only set when freshly downloaded from the app.
		 *
		 * @var string
		 */
		const PRIVATE_KEY = 'PATCHSTACK_PRIVATE_KEY';

		/**
		 * URL of the plugin directory.
		 *
		 * @var string
		 */
		protected $url = '';

		/**
		 * Plugin basename.
		 *
		 * @var string
		 */
		protected $basename = '';

		/**
		 * Plugin name.
		 *
		 * @var string
		 */
		protected $name = '';

		/**
		 * Detailed activation error messages.
		 *
		 * @var array
		 */
		protected $activation_errors = [];

		/**
		 * Singleton instance of plugin.
		 *
		 * @var Patchstack
		 */
		protected static $single_instance = null;

		/**
		 * Define all the variables that will hold the Patchstack classes.
		 * These must be defined because it allows us to communicate from one class to the other.
		 */
		protected $firewall;
		protected $firewall_base;
		protected $activation;
		protected $cron;
		protected $api;
		protected $login;
		protected $ban;
		protected $hardening;
		protected $htaccess;
		protected $hacker_log;
		protected $upload;
		protected $rules;
		protected $hide_login;
		protected $listener;
		protected $event_log;
		protected $multisite;
		protected $admin_ajax;
		protected $admin_general;
		protected $admin_menu;
		protected $admin_options;

		/**
		 * Setup a few base variables for the plugin.
		 * Also make sure certain constants are defined.
		 *
		 * @return void
		 */
		protected function __construct() {
			// Set the permission constants if not already set.
			if ( ! defined( 'FS_CHMOD_DIR' ) ) {
				define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
			}

			if ( ! defined( 'FS_CHMOD_FILE' ) ) {
				define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
			}

			// Define local variables.
			$this->basename = plugin_basename( __FILE__ );
			$this->url      = plugin_dir_url( __FILE__ );
			$names          = explode( '/', $this->basename );
			$this->name     = $names[0];

			// Define WP_CLI command.
			if ( defined( 'WP_CLI' ) && WP_CLI && method_exists('\WP_CLI', 'add_command')) {
				\WP_CLI::add_command( 'patchstack activate', [ $this, 'cli_activate' ] );
				\WP_CLI::add_command( 'patchstack deactivate', [ $this, 'cli_deactivate' ] );
				\WP_CLI::add_command( 'patchstack status', [ $this, 'cli_status' ] );
			}
		}

		/**
		 * Call the constructor of all the Patchstack related classes.
		 *
		 * @return void
		 */
		public function plugin_classes() {
			// Define the array of the classes.
			foreach ( [
				'admin_options' => 'P_Admin_Options',
				'cron'          => 'P_Cron',
				'api'           => 'P_Api',
				'login'         => 'P_Login',
				'ban'           => 'P_Ban',
				'hardening'     => 'P_Hardening',
				'htaccess'      => 'P_Htaccess',
				'hacker_log'    => 'P_Hacker_Log',
				'upload'        => 'P_Upload',
				'rules'         => 'P_Rules',
				'hide_login'    => 'P_Hide_Login',
				'event_log'     => 'P_Event_Log',
				'activation'    => 'P_Activation',
				'listener'      => 'P_Listener',
				'multisite'     => 'P_Multisite',
				'admin_ajax'    => 'P_Admin_Ajax',
				'admin_general' => 'P_Admin_General',
				'admin_menu'    => 'P_Admin_Menu',
			] as $var => $class ) {
				$this->$var = new $class( $this );
			}

			// Load firewall base functionality.
			$this->firewall_base = new P_Firewall( true, $this, true );
		}

		/**
		 * Activate the plugin.
		 *
		 * @return void
		 */
		public function activate() {
			$this->plugin_classes();
			$this->activation->activate( $this );
		}

		/**
		 * Connects the Patchstack plugin to the API with the license id and secret key.
		 *
		 * Returns an error if the connection was not successful.
		 *
		 * ## OPTIONS
		 *
		 * [<id>]
		 * : The API client id.
		 *
		 * [<secret>]
		 * : The API secret key.
		 *
		 * <secret-id>
		 * : The API client id and secret key merged together, found in the App. E.g. 2b072e8b60402e30d481df351fc08183906254e0-123456
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp patchstack activate 123456 2b072e8b60402e30d481df351fc08183906254e0
		 *     Success: The Patchstack plugin has been successfully connected.
		 *
		 * 	   or
		 *
		 *     $ wp patchstack activate 2b072e8b60402e30d481df351fc08183906254e0-123456
		 *     Success: The Patchstack plugin has been successfully connected.
		 */
		public function cli_activate( $args ) {
			// Handle both ways to activate the plugin.
			if ( count( $args ) === 1 && strpos( $args[0], '-' ) !== false ) {
				list( $secret, $id ) = explode( '-', $args[0] );
			} else {
				$id = isset( $args[0] ) ? trim( $args[0] ) : '';
				$secret = isset( $args[1] ) ? trim( $args[1] ) : '';
			}

			$result = $this->activation->alter_license( $id, $secret, 'activate' );
			if ( $result['result'] == 'error' ) {
				\WP_CLI::error( "The Patchstack plugin could not be connected. Make sure the id and secret key are valid and that api.patchstack.com is not blocked. Additional information:\n" . $result['body'] );
				return;
			}

			\WP_CLI::success( 'The Patchstack plugin has been successfully connected.' );
		}

		/**
		 * Disconnects the Patchstack plugin from the API and removes the API key.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp patchstack deactivate
		 *     Success: The Patchstack plugin has been successfully disconnected.
		 */
		public function cli_deactivate() {
			$this->activation->deactivate();
			$this->activation->alter_license( '', '', 'deactivate' );

			\WP_CLI::success( 'The Patchstack plugin has been successfully disconnected.' );
		}

		/**
		 * Gets the current API connection status from the Patchstack plugin.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp patchstack status
		 *     Success: The Patchstack plugin is currently connected to the API.
		 *
		 *     $ wp patchstack status
		 *     Warning: The Patchstack plugin is not connected to the API.
		 */
		public function cli_status() {
			if ( $this->api->is_connected() ) {
				\WP_CLI::success( __( 'The Patchstack plugin is currently connected to the API.', 'patchstack' ) );
			} else {
				\WP_CLI::warning( __( 'The Patchstack plugin is not connected to the API.', 'patchstack' ) );
			}
		}

		/**
		 * Deactivate the plugin.
		 *
		 * @return void
		 */
		public function deactivate() {
			$this->plugin_classes();
			$this->activation->deactivate();
		}

		/**
		 * Load translated strings for the plugin.
		 *
		 * @return void
		 */
		public function load_textdomain () {
			load_plugin_textdomain( 'patchstack', false, dirname( $this->basename ) . '/languages/' );
		}

		/**
		 * Boot Patchstack.
		 *
		 * @return void
		 */
		public function init() {
			add_action( 'init', [ $this, 'load_textdomain' ] );

			// Initialize plugin classes.
			$this->plugin_classes();

			// Perform migrations if necessary.
			$this->activation->migrate_check();

			// If license expiration has not been fetched yet while the plugin is active, update it.
			if ( get_option( 'patchstack_api_token', '' ) == '' && get_option( 'patchstack_license_expiry', '' ) == '' ) {
				$this->api->update_license_status();
			}

			// Run firewall if not disabled and license activated.
			if ( get_option( 'patchstack_license_activated', 0 ) == 1 && get_option( 'patchstack_basic_firewall', 0 ) == 1 && get_option( 'patchstack_license_free', 0 ) == 0 ) {
				$this->firewall = new P_Firewall( true, $this );
			}
		}

		/**
		 * Creates or returns an instance of this class.
		 *
		 * @return Patchstack
		 */
		public static function get_instance() {
			if ( null === self::$single_instance ) {
				self::$single_instance = new self();
			}

			return self::$single_instance;
		}

		/**
		 * Magic getter.
		 *
		 * @param string $field The field to magically get.
		 * @return mixed
		 */
		public function __get( $field ) {
			switch ( $field ) {
				case 'version':
					return self::VERSION;
				case 'api_url':
					return self::API_URL;
				case 'auth_url':
					return self::AUTH_URL;
				case 'client_id':
					return self::CLIENT_ID;
				case 'private_key':
					return self::PRIVATE_KEY;
				default:
					try {
						return $this->$field;
					} catch ( \Exception $e ) {
						return null;
					}
			}
		}
	}
}

if ( ! function_exists( 'patchstack_uninstall' ) ) {
	/**
	 * Called when the plugin is uninstalled/removed from the site.
	 * This is not the same as deactivation, where the plugin still resides on the site.
	 *
	 * @return void
	 */
	function patchstack_uninstall() {
		// Delete most of the Patchstack options.
		global $wpdb;
		$options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'patchstack_%'" );

		// Few options we want to keep.
		$keep = ['patchstack_hits_last_30', 'patchstack_hits_all_time', 'patchstack_clientid', 'patchstack_secretkey', 'patchstack_secretkey_nonce', 'patchstack_api_token'];

		// Delete everything else.
		foreach( $options as $option ) {
			if ( in_array( $option->option_name, $keep ) || stripos( $option->option_name, 'patchstack_captcha_' ) !== false ) {
				continue;
			}

			delete_option( $option->option_name );

			if ( is_multisite() ) {
				delete_site_option( $option->option_name );
			}
		}

		// Drop all tables.
		global $wpdb;
		$prefixes = ['patchstack_', 'webarx_'];
		foreach ( $prefixes as $prefix ) {
			$tables = [ 'user_log', 'visitor_log', 'firewall_log', 'file_hashes', 'logic', 'ip', 'event_log' ];
			foreach ( $tables as $table ) {
				$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $prefix . $table );
			}
		}
	}
}

if ( ! function_exists( 'patchstack' ) ) {
	/**
	 * Grab the Patchstack object and return it.
	 *
	 * @return Patchstack
	 */
	function patchstack() {
		return patchstack::get_instance();
	}
}

if ( ! has_action( 'plugins_loaded', [ patchstack(), 'init' ] ) ) {
	// Kick it off.
	add_action( 'plugins_loaded', [ patchstack(), 'init' ] );

	// Activation and deactivation hooks.
	register_activation_hook( __FILE__, [ patchstack(), 'activate' ] );
	register_deactivation_hook( __FILE__, [ patchstack(), 'deactivate' ] );
	register_uninstall_hook( __FILE__, 'patchstack_uninstall' );
}
