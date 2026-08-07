<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to perform interactions with the
 * .htaccess file.
 */
class P_Htaccess extends P_Core {

	/**
	 * Add the actions required for htaccess interactions.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		add_action( 'updated_option', [ $this, 'update_option_extras' ], 10, 3 );
	}

	/**
	 * If option is updated, write to .htaccess file.
	 *
	 * @param string $option_name
	 * @param string $option_name
	 * @param mixed  $value
	 * @return void
	 */
	public function update_option_extras( $option_name, $old_value, $value ) {
		if ( !in_array( $option_name, [ 'patchstack_prevent_default_file_access', 'patchstack_basic_firewall', 'patchstack_pingback_protection', 'patchstack_block_debug_log_access', 'patchstack_block_fake_bots', 'patchstack_index_views', 'patchstack_trace_and_track', 'patchstack_proxy_comment_posting', 'patchstack_image_hotlinking', 'patchstack_firewall_custom_rules' ] ) ) {
			return;
		}

		if ( $old_value == $value ) {
			return;
		}

		$this->plugin->rules->post_firewall_rules();
	}

	/**
	 * Get the turned on .htaccess firewall settings.
	 *
	 * @return array
	 */
	public function get_firewall_rule_settings() {
		$settings = [];
		$options  = [ 'patchstack_prevent_default_file_access', 'patchstack_basic_firewall', 'patchstack_block_debug_log_access', 'patchstack_block_fake_bots', 'patchstack_index_views', 'patchstack_proxy_comment_posting', 'patchstack_image_hotlinking', 'patchstack_basicscanblock' ];
		foreach ( $options as $option ) {
			if ( get_site_option( $option ) ) {
				$settings[] = ( $option == 'patchstack_basicscanblock' ? 'webarx_wpscan_block' : str_replace( 'patchstack_', 'webarx_', $option ) );
			}
		}

		return $settings;
	}

	/**
	 * Determine the current state of the firewall.
	 *
	 * @return boolean
	 */
	public function firewall() {
		// Get the firewall state.
		$sum_of_firewall = 0;
		foreach ( [ 'patchstack_prevent_default_file_access', 'patchstack_basic_firewall', 'patchstack_pingback_protection', 'patchstack_block_debug_log_access', 'patchstack_block_fake_bots', 'patchstack_index_views', 'patchstack_trace_and_track', 'patchstack_proxy_comment_posting', 'patchstack_image_hotlinking' ] as $option ) {
			$value = get_site_option( $option, 0 );
			$sum_of_firewall  += empty( $value ) ? 0 : 1;
		}

		// Update the options.
		$onoff           = $sum_of_firewall > 1 ? 0 : 1;
		foreach ( [ 'patchstack_prevent_default_file_access', 'patchstack_basic_firewall', 'patchstack_block_debug_log_access', 'patchstack_index_views', 'patchstack_proxy_comment_posting' ] as $option ) {
			update_site_option( $option, $onoff );
		}
		update_site_option( 'patchstack_block_fake_bots', 0 );
		update_site_option( 'patchstack_image_hotlinking', 0 );

		// Pull the rules or cleanup the .htaccess file?
		if ( $onoff == 1 ) {
			$this->plugin->rules->post_firewall_rules();
		} else {
			$this->cleanup_htaccess_file();
		}

		return true;
	}

	/**
	 * Write the .htaccess firewall rules to the .htaccess file.
	 *
	 * @param string $rules
	 * @return void
	 */
	public function write_rules_to_htaccess( $rules = '' ) {
		if ( ! $this->is_server_supported() || get_site_option( 'patchstack_disable_htaccess', 0 ) || ( defined( 'PS_DISABLE_HTACCESS' ) && PS_DISABLE_HTACCESS ) ) {
			return false;
		}

		// Determine if the .htaccess file exists.
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new WP_Filesystem_Direct( '' );
		if ( ! $fs->exists( ABSPATH . '.htaccess' ) ) {
			$fs->touch( ABSPATH . '.htaccess' );

			// Don't continue if .htaccess does not exist or cannot be written to.
			if ( ! $fs->exists( ABSPATH . '.htaccess' ) ) {
				return false;
			}
		}

		// Get the current rules.
		$current = $old = $fs->get_contents( ABSPATH . '.htaccess' );
		$current = $this->delete_all_between( '# Patchstack Firewall Start', '# Patchstack Firewall End', $current );

		// If no rules, then we delete the old ones.
		if ( $rules != '' ) {
			$current = "# Patchstack Firewall Start\r\n<IfModule mod_rewrite.c>\r\nRewriteEngine On\r\n" . $rules . "\r\n</IfModule>\r\n# Patchstack Firewall End\r\n" . $current;
		}

		// Put the contents into the .htaccess file.
		$fs->put_contents( ABSPATH . '.htaccess', $current, FS_CHMOD_FILE );

		// Check if the new rules work.
		// 500 internal server error - did not work. Restore old rules.
		$status = $this->get_site_status_code();
		if ( $status == '' || $status >= 500 ) {
			$fs->put_contents( ABSPATH . '.htaccess', $old, FS_CHMOD_FILE );
			return false;
		}

		return true;
	}

	/**
	 * Write given rules to the .htaccess file.
	 *
	 * @param string $rules
	 * @return boolean
	 */
	public function write_to_htaccess( $rules = '' ) {
		if ( ! $this->is_server_supported() || get_site_option( 'patchstack_disable_htaccess', 0 ) || ( defined( 'PS_DISABLE_HTACCESS' ) && PS_DISABLE_HTACCESS ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new WP_Filesystem_Direct( '' );
		if ( ! $fs->exists( ABSPATH . '.htaccess' ) ) {
			$fs->touch( ABSPATH . '.htaccess' );
		}

		// Some rule adjustments.
		$rewrites = [
			'RedirectMatch 409 .(htaccess|htpasswd|errordocs|logs)$' => 'RedirectMatch 403 \.(htaccess|htpasswd|errordocs|logs)$',
			"\n  RewriteCond %{HTTP_COOKIE} !^.*wordpress_logged_in.*$ [NC]" => '',
			"\n  RewriteCond %{REMOTE_ADDR} !=18.221.197.243" => '',
			'^wp-includes/[^/]+.php$' => '^wp-includes/.*\.php$',
			'RewriteRule ^debug*.*log$ index.php?webarx_fpage=502 [L,QSA]' => 'RewriteRule debug\.log$ index.php?webarx_fpage=502 [L,QSA]',
			'*.*' => '\.'
		];

		foreach ($rewrites as $find => $replace) {
			$rules = str_replace($find, $replace, $rules);
		}

		return $this->plugin->htaccess->self_check( $rules );
	}

	/**
	 * Get the web-server type.
	 *
	 * @return boolean
	 */
	public function is_server_supported() {
		$server = strtolower( $_SERVER['SERVER_SOFTWARE'] );
		foreach ( [ 'apache', 'nginx', 'litespeed' ] as $webserver ) {
			if ( strstr( $server, $webserver ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Write .htaccess directly without causing a server missconfiguration (500)
	 * (PHP will end the process even if the browser-window was closed.)
	 *
	 * @param string $new_rules
	 * @return boolean
	 */
	public function self_check( $new_rules ) {
		// Don't continue if we have no rules
		if ( empty( $new_rules ) ) {
			return false;
		}
		$new_rules = PHP_EOL . PHP_EOL . '# BEGIN Patchstack' . PHP_EOL . $new_rules . PHP_EOL . '# END Patchstack' . PHP_EOL . PHP_EOL;

		// Require the filesystem libraries.
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new WP_Filesystem_Direct( '' );

		// Don't continue if .htaccess does not exist or cannot be written to.
		if ( ! $fs->exists( ABSPATH . '.htaccess' ) ) {
			return false;
		}

		// Get the current data in the .htaccess file.
		$current_rules = $old = $fs->get_contents( ABSPATH . '.htaccess' );

		// Delete all Patchstack related stuff so we can properly re-inject it.
		$current_rules = $this->delete_all_between( '# BEGIN WebARX', '# END WebARX', $current_rules );
		$current_rules = $this->delete_all_between( '# BEGIN Patchstack', '# END Patchstack', $current_rules );
		$current_rules = $this->delete_all_between( '# CUSTOM WEBARX RULES', '# END CUSTOM WEBARX RULES', $current_rules );
		$current_rules = $this->delete_all_between( '# CUSTOM PATCHSTACK RULES', '# END CUSTOM PATCHSTACK RULES', $current_rules );
		$new_rules     = $this->delete_all_between( '# BEGIN WordPress', '# END WordPress', $new_rules );

		// Get the custom .htaccess rules, if any are set.
		$custom = $this->get_custom_rules();

		if ( get_site_option( 'patchstack_firewall_custom_rules_loc', 'bottom' ) == 'top' ) {
			$new_rules = $custom . $new_rules;
		} else {
			$new_rules = $new_rules . $custom;
		}

		// Determine if the new rules even need to be saved.
		$rules_hash = sha1( $new_rules );
		if ( get_site_option( 'patchstack_htaccess_rules_hash', '' ) == $rules_hash && ( stripos( $old, 'begin webarx' ) !== false || stripos( $old, 'begin patchstack' ) !== false ) ) {
			return false;
		}

		// Save the new rules and adjust # and newline of our own rules.
		update_site_option( 'patchstack_htaccess_rules_hash', $rules_hash );
		$new_rules = preg_replace( "/[\r\n]+/", "\r\n", $new_rules );
		$new_rules = preg_replace( '/#/', "\r\n#", $new_rules );

		// In order to support all Patchstack plugin versions with newline fix, we have to remove this part ourselves.
		$new_rules = str_replace( "\r\n\r\n# BEGIN Patchstack", '# BEGIN Patchstack', $new_rules );
		$new_rules = str_replace( "# END Patchstack\r\n", '# END Patchstack', $new_rules );

		// Remove RewriteBase / from the Patchstack rules.
		$new_rules = str_replace( "\r\n  RewriteBase /", '', $new_rules );
		$new_rules = str_replace( '/index.php', 'index.php', $new_rules );

		// Merge the rules together.
		$new_rules = $new_rules . "\n" . $current_rules;

		// Determine if the Patchstack rules starts on its own line.
		$lines = explode( "\n", $new_rules );
		foreach ( $lines as $line ) {
			if ( stripos( $line, 'begin patchstack' ) !== false && trim( strtolower( $line ) ) != '# begin patchstack' ) {
				$new_rules = str_replace( '# BEGIN Patchstack', "\r\n# BEGIN Patchstack", $new_rules );
			}
		}

		// Put the contents into the .htaccess file.
		$fs->put_contents( ABSPATH . '.htaccess', $new_rules, FS_CHMOD_FILE );

		// Check if the new rules work.
		// 500 internal server error - did not work. Restore old rules.
		$status = $this->get_site_status_code();
		if ( $status == '' || $status >= 500 ) {
			$fs->put_contents( ABSPATH . '.htaccess', $old, FS_CHMOD_FILE );
			update_site_option( 'patchstack_firewall_custom_rules', '' );
		}

		return $status < 500;
	}

	/**
	 * Retrieve the custom .htaccess rules and inject into the .htaccess file.
	 *
	 * @return string
	 */
	public function get_custom_rules() {
		$custom = get_site_option( 'patchstack_firewall_custom_rules', '' );
		if ( empty( $custom ) || is_array( $custom ) || $custom == 'Array' ) {
			$custom = '';
		}

		// Do we have any custom rules to inject?
		$tmp = '';
		if ( $custom != '' ) {
			$tmp  = PHP_EOL . '# CUSTOM PATCHSTACK RULES' . PHP_EOL;
			$tmp .= $custom . PHP_EOL;
			$tmp .= '# END CUSTOM PATCHSTACK RULES';
		}
		return $tmp;
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
	 * Remove all Patchstack rules from the .htaccess file.
	 *
	 * @return void
	 */
	public function cleanup_htaccess_file() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$fs = new WP_Filesystem_Direct( '' );

		if ( $fs->exists( ABSPATH . '.htaccess' ) ) {
			$curdata = $fs->get_contents( ABSPATH . '.htaccess' );
			$rules   = $this->delete_all_between( '# BEGIN Patchstack', '# END Patchstack', $curdata );
			$rules   = $this->delete_all_between( '# CUSTOM PATCHSTACK RULES', '# END CUSTOM PATCHSTACK RULES', $rules );
			$fs->put_contents( ABSPATH . '.htaccess', $rules, FS_CHMOD_FILE );
		}
	}

	/**
	 * Delete characters between a begin and end string.
	 *
	 * @param string $begin
	 * @param string $end
	 * @param string $string
	 * @return string
	 */
	public function delete_all_between( $begin, $end, $string ) {
		$begin_pos = strpos( $string, $begin );
		$end_pos   = strpos( $string, $end );
		if ( $begin_pos === false || $end_pos === false ) {
			return $string;
		}

		$end_pos += strlen( $end );

		// Consume any trailing newline characters so the block is removed cleanly
		// regardless of CRLF/LF line endings and repeated writes don't stack blank lines.
		while ( isset( $string[ $end_pos ] ) && ( $string[ $end_pos ] === "\r" || $string[ $end_pos ] === "\n" ) ) {
			$end_pos++;
		}

		$delete = substr( $string, $begin_pos, $end_pos - $begin_pos );
		return str_replace( $delete, '', $string );
	}
}
