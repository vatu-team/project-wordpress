<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to determine if the IP address of the
 * user is banned. Along with that we check the IP address whitelist.
 */
class P_Ban extends P_Core {

	/**
	 * Add the actions required for determining the ban.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		add_action( 'init', [ $this, 'ip_ban' ], ~PHP_INT_MAX + 1 );
	}

	/**
	 * Determine if the IP address of the user is blocked.
	 *
	 * @return void
	 */
	public function ip_ban() {
		if ( ! is_user_logged_in() && $this->is_ip_blocked( $this->get_ip() ) ) {
			$this->plugin->firewall_base->display_error_page( 22 );
		}
	}

	/**
	 * Check IP ban.
	 *
	 * @param string $ip The IP address of the user.
	 * @return boolean Whether or not the user is blocked.
	 */
	public function is_ip_blocked( $ip ) {
		$ip_rules = $this->get_option( 'patchstack_ip_block_list', '' );
		if ( empty( $ip_rules ) ) {
			return false;
		}

		$blocked  = false;
		$ip_rules = explode( "\n", $ip_rules );
		foreach ( $ip_rules as $blocked_ip ) {
			$blocked_ip = trim( $blocked_ip );
			if ( strpos( $blocked_ip, '*' ) !== false ) {
				$blocked = $this->check_wildcard_rule( $ip, $blocked_ip );
			} elseif ( strpos( $blocked_ip, '-' ) !== false ) {
				$blocked = $this->check_range_rule( $ip, $blocked_ip );
			} elseif ( strpos( $blocked_ip, '/' ) !== false ) {
				$blocked = $this->check_subnet_mask_rule( $ip, $blocked_ip );
			} elseif ( $ip == $blocked_ip ) {
				return true;
			}

			if ( $blocked ) {
				return true;
			}
		}

		return $blocked;
	}

	/**
	 * Check IP whitelist for login protection.
	 *
	 * @param string $ip The IP address of the user.
	 * @return boolean Whether or not the user is whitelisted.
	 */
	public function is_ip_whitelisted( $ip ) {
		$ipRules = explode( "\n", $this->get_option( 'patchstack_login_whitelist', '' ) );
		if ( empty( $ipRules ) ) {
			return true;
		}

		$whitelisted = false;
		foreach ( $ipRules as $ipRule ) {
			$ipRule = trim( $ipRule );
			if ( strpos( $ipRule, '*' ) !== false ) {
				$whitelisted = $this->check_wildcard_rule( $ip, $ipRule );
			} elseif ( strpos( $ipRule, '-' ) !== false ) {
				$whitelisted = $this->check_range_rule( $ip, $ipRule );
			} elseif ( strpos( $ipRule, '/' ) !== false ) {
				$whitelisted = $this->check_subnet_mask_rule( $ip, $ipRule );
			} elseif ( $ip == $ipRule ) {
				return true;
			}

			if ( $whitelisted ) {
				return true;
			}
		}

		return $whitelisted;
	}

	/**
	 * CIDR notation IP block check.
	 *
	 * @param string $ip The IP address of the user.
	 * @param string $range The range to check.
	 * @return boolean Whether or not the IP is in the range.
	 */
	public function check_subnet_mask_rule( $ip, $range ) {
		list($range, $netmask) = explode( '/', $range, 2 );

		// A malformed netmask (e.g. "1.2.3.4/abc") would throw a TypeError on the
		// arithmetic below on PHP 8; treat anything outside 0-32 as a non-match.
		if ( ! is_numeric( $netmask ) || $netmask < 0 || $netmask > 32 ) {
			return false;
		}
		$netmask = (int) $netmask;

		$range_decimal         = ip2long( $range );
		$ip_decimal            = ip2long( $ip );
		$wildcard_decimal      = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal       = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}

	/**
	 * Wildcard IP block check.
	 *
	 * @param string $ip The IP address of the user.
	 * @param string $rule The wildcard range to check against.
	 * @return boolean Whether or not the IP is in the wilcard range.
	 */
	public function check_wildcard_rule( $ip, $rule ) {
		$match = explode( '*', $rule );
		$match = $match[0];
		return ( substr( $ip, 0, strlen( $match ) ) == $match );
	}

	/**
	 * IP range block check.
	 *
	 * @param string|array $ip The IP address of the user.
	 * @param string       $rule The range to check against.
	 * @return boolean Whether or not the IP is in the range.
	 */
	public function check_range_rule( $ip, $rule ) {
		// Check if client has multiple IPs
		if ( is_array( $ip ) ) {
			$ip = $ip[0];
		}

		$first_ip  = explode( '-', $rule );
		$second_ip = explode( '-', $rule );

		$start_ip   = ip2long( $first_ip[0] );
		$end_ip     = ip2long( $second_ip[1] );
		$request_ip = ip2long( $ip );

		return ( $request_ip >= $start_ip && $request_ip <= $end_ip );
	}
}
