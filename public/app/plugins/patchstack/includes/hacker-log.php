<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to block a user when a bad request has been
 * detected or matches our firewall rules in the .htaccess file.
 */
class P_Hacker_Log extends P_Core {

	/**
	 * Add the actions required for the logging of hackers.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
		add_action( 'parse_request', [ $this, 'parse_request' ] );
	}

	/**
	 * Register our query parameters to WordPress.
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function query_vars( $query_vars ) {
		$query_vars[] = 'webarx_fpage';
		return $query_vars;
	}

	/**
	 * Parse the incoming request and determine if our registered
	 * parameters are set.
	 *
	 * @param WP $query
	 * @return void|WP
	 */
	public function parse_request( $query ) {
		if ( $this->plugin->firewall_base->is_authenticated() ) {
			return $query;
		}

		if ( array_key_exists( 'webarx_fpage', $query->query_vars ) ) {
			$this->plugin->firewall_base->display_error_page( (int) $query->query_vars['webarx_fpage'] );
		} else {
			return $query;
		}
	}
}
