<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class provides the firewall functionality.
 */
class P_Firewall extends P_Core {

	/**
	 * Launch the firewall rule processor.
	 *
	 * @param bool       $from_main Whether or not the firewall is loaded from the main script or not.
	 * @param Patchstack $core
	 * @param bool       $skip Whether or not to process and execute the rules.
	 * @param bool		 $muCall Whether or not this was called from mu-plugin.
	 * @return void
	 */
	public function __construct( $from_main = false, $core = null, $skip = false, $muCall = false ) {
		if ( ! $from_main || ! $core ) {
			if ( $core ) {
				parent::__construct( $core );
			}
			return;
		}
		
		parent::__construct( $core );

		// If we only want to initialize the firewall but not execute the rules.
		if ( $skip || defined( 'DOING_CRON' ) ) {
			return;
		}

		// Load the extension.
		require_once __DIR__ . '/../lib/patchstack/vendor/autoload.php';
		$extension = new Patchstack\Extensions\WordPress\Extension(
			[
				'patchstack_basic_firewall_roles' => $this->get_option( 'patchstack_basic_firewall_roles', [ 'administrator', 'editor', 'author' ] ),
				'patchstack_whitelist' => get_option( 'patchstack_whitelist', '' )
			],
			$this
		);

		// Initiate the firewall processor with our settings.
		$firewall = new Patchstack\Processor(
			$extension,
			$this->decode_rule_option('patchstack_firewall_rules_v3'),
			$this->decode_rule_option('patchstack_whitelist_rules_v3'),
			[
				'autoblockAttempts' => $this->get_option( 'patchstack_autoblock_attempts', 10 ),
				'autoblockMinutes' => $this->get_option( 'patchstack_autoblock_minutes', 30 ),
				'autoblockTime' => $this->get_option( 'patchstack_autoblock_blocktime', 60 ),
				'whitelistKeysRules' => $this->decode_rule_option( 'patchstack_whitelist_keys_rules' ),
				'mustUsePluginCall' => $muCall
			],
			$this->decode_rule_option('patchstack_firewall_rules'),
			$this->decode_rule_option('patchstack_whitelist_rules')
		);

		// Launch the firewall.
		$firewall->launch();
	}

	/**
	 * Safely decode a stored rule option into an array. The option is expected to be
	 * a JSON string, but may already be an array (or a malformed value); passing a
	 * non-string to json_decode() is a fatal TypeError on PHP 8.
	 *
	 * @param string $name
	 * @return array
	 */
	private function decode_rule_option( $name ) {
		$value = get_option( $name, '[]' );

		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) ) {
			return [];
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Determine if the user is authenticated and in the list of whitelisted roles.
	 *
	 * @return bool
	 */
	public function is_authenticated() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Get the whitelisted roles.
		$roles = $this->get_option( 'patchstack_basic_firewall_roles', [ 'administrator', 'editor', 'author' ] );
		if ( ! is_array( $roles ) ) {
			return false;
		}
		
		// Special scenario for super admins on a multisite environment.
		if ( in_array( 'administrator', $roles ) && is_multisite() && is_super_admin() ) {
			return true;
		}

		// Get the roles of the user.
		$user = wp_get_current_user();
		if ( ! isset( $user->roles ) || count( (array) $user->roles ) == 0 ) {
			return false;
		}

		// Is the user in the whitelist roles list?
		$role_count = array_intersect( $user->roles, $roles );
		return count( $role_count ) != 0;
	}

	/**
	 * Display error page.
	 *
	 * @param integer $fid
	 * @return void
	 */
	public function display_error_page( $fid = 1 ) {
		if ( $fid != 22 && $fid != 23 && $fid != 24 && $fid != 'login' ) {
			$this->log_request( $fid );
		}

		// Supported by a number of popular caching plugins.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

        // Because WP Fastest Cache just has to be special...
        if (function_exists('wpfc_exclude_current_page')) {
            @wpfc_exclude_current_page();
        }

		// Send forbidden headers and no-caching headers as well.
		status_header(403);
		send_nosniff_header();
		nocache_headers();

		if ( $fid == 'login' ) {
			require_once dirname( __FILE__ ) . '/views/access-denied-login.php';
		} else {
			require_once dirname( __FILE__ ) . '/views/access-denied.php';
		}
		
		exit;
	}

	/**
	 * Log the blocked request.
	 * 
	 * @param int $fid
	 * @return void
	 */
	private function log_request( $fid = 1 ) {
		global $wpdb;
		if ( ! $wpdb || $fid == 22 || $fid == 23 || $fid == 24 || $fid == 'login' ) {
			return;
		}

		// Insert into the logs.
		$wpdb->insert(
			$wpdb->prefix . 'patchstack_firewall_log',
			array(
				'ip'          => $this->get_ip(),
				'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
				'method'      => isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '',
				'fid'         => $fid,
				'flag'        => '',
				'post_data'   => '',
				'block_type'  => 'BLOCK',
			)
		);
	}

	/**
	 * Extract the number of blocked hits the past 30 days based on the current counters.
	 * 
	 * @return int
	 */
	public function get_hits_counter() {
		$counters = get_option( 'patchstack_hits_last_30', [] );
		if (!is_array($counters) || count($counters) === 0) {
			return 0;
		}

        // Set the range of dates we need.
		$hits = 0;
        $start = new \DateTime();
        $start->modify('-30 days');

        $end = new \DateTime();
		$end->modify('+1 day');

        $interval = new \DateInterval('P1D');
        $range = new \DatePeriod($start, $interval, $end);
    
        // Set the range from -6 days to +1 day from now.
        foreach ($range as $date) {
            $formattedDate = $date->format('Y-m-d');
            if (isset($counters[$formattedDate])) {
                $hits += $counters[$formattedDate];
            }
        }
    
        return $hits;
	}
}
