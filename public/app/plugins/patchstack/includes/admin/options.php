<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to register all the options that Patchstack uses and anything
 * related to options.
 */
class P_Admin_Options extends P_Core {

	/**
	 * Add the actions required for the otions.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
	}

	/**
	 * All options and their default values.
	 *
	 * @var array
	 */
	public $options = [
		// Hardening options.
		'patchstack_pluginedit'                         => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_userenum'                           => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_hidewpversion'                      => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_activity_log_is_enabled'            => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_activity_log_failed_logins'         => ['default' => 1, 'autoload' => 'no'],
		'patchstack_activity_log_failed_logins_db'      => ['default' => 0, 'autoload' => 'no'],
		'patchstack_activity_log_posts'					=> ['default' => 0, 'autoload' => 'yes'],
		'patchstack_activity_log_comments'				=> ['default' => 0, 'autoload' => 'yes'],
		'patchstack_xmlrpc_is_disabled'                 => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_captcha_type'                       => ['default' => 'v2', 'autoload' => 'no'],
		'patchstack_captcha_public_key_v3'              => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_private_key_v3'             => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_public_key_v3_new'          => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_private_key_v3_new'         => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_public_key_turnstile'       => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_private_key_turnstile'      => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_public_key'                 => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_private_key'                => ['default' => '', 'autoload' => 'no'],
		'patchstack_captcha_login_form'                 => ['default' => 0, 'autoload' => 'no'],
		'patchstack_captcha_registration_form'          => ['default' => 0, 'autoload' => 'no'],
		'patchstack_captcha_reset_pwd_form'             => ['default' => 0, 'autoload' => 'no'],
		'patchstack_captcha_on_comments'                => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_json_is_disabled'                   => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_auto_update'                        => ['default' => [], 'autoload' => 'yes'],
		'patchstack_application_passwords_disabled'     => ['default' => 1, 'autoload' => 'yes'],

		// The firewall and whitelist rules.
		'patchstack_firewall_rules'                     => ['default' => '', 'autoload' => 'yes'],
		'patchstack_firewall_rules_v3'                  => ['default' => '[]', 'autoload' => 'yes'],
		'patchstack_firewall_rules_v3_ap'				=> ['default' => '[]', 'autoload' => 'yes'],
		'patchstack_firewall_ap_error'					=> ['default' => '', 'autoload' => 'no'],
		'patchstack_whitelist_rules'                    => ['default' => '', 'autoload' => 'yes'],
		'patchstack_whitelist_rules_v3'                 => ['default' => '[]', 'autoload' => 'yes'],
		'patchstack_whitelist_keys_rules'               => ['default' => '', 'autoload' => 'yes'],

		// Firewall options.
		'patchstack_basic_firewall'                     => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_basic_firewall_roles'               => ['default' => [ 'administrator', 'editor', 'author', 'contributor' ], 'autoload' => 'yes'],
		'patchstack_firewall_ip_header'                 => ['default' => '', 'autoload' => 'yes'],
		'patchstack_ip_header_computed'					=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_ip_header_force_compute'			=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_disable_htaccess'                   => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_block_debug_log_access'             => ['default' => 1, 'autoload' => 'no'],
		'patchstack_block_fake_bots'                    => ['default' => 1, 'autoload' => 'no'],
		'patchstack_index_views'                        => ['default' => 1, 'autoload' => 'no'],
		'patchstack_proxy_comment_posting'              => ['default' => 1, 'autoload' => 'no'],
		'patchstack_image_hotlinking'                   => ['default' => 0, 'autoload' => 'no'],
		'patchstack_prevent_default_file_access'        => ['default' => 1, 'autoload' => 'no'],
		'patchstack_basicscanblock'                     => ['default' => 1, 'autoload' => 'no'],
		'patchstack_firewall_custom_rules'              => ['default' => '', 'autoload' => 'no'],
		'patchstack_firewall_custom_rules_loc'          => ['default' => 'bottom', 'autoload' => 'no'],
		'patchstack_add_security_headers'               => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_ip_block_list'                      => ['default' => '', 'autoload' => 'yes'],

		// Login and firewall brute force options.
		'patchstack_block_bruteforce_ips'               => ['default' => 0, 'autoload' => 'no'],
		'patchstack_anti_bruteforce_attempts'           => ['default' => 10, 'autoload' => 'no'],
		'patchstack_anti_bruteforce_minutes'            => ['default' => 5, 'autoload' => 'no'],
		'patchstack_anti_bruteforce_blocktime'          => ['default' => 60, 'autoload' => 'no'],
		'patchstack_autoblock_attempts'                 => ['default' => 60, 'autoload' => 'yes'],
		'patchstack_autoblock_minutes'                  => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_autoblock_blocktime'                => ['default' => 1, 'autoload' => 'yes'],
		'patchstack_login_2fa'                          => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_login_whitelist'                    => ['default' => '', 'autoload' => 'no'],

		// General options.
		'patchstack_environment_hash'					=> ['default' => '', 'autoload' => 'no'],
		'patchstack_software_data_hash'                 => ['default' => '', 'autoload' => 'yes'],
		'patchstack_software_upload_attempted'			=> ['default' => 0, 'autoload' => 'yes'],
		'patchstack_license_expiry'						=> ['default' => '', 'autoload' => 'yes'],
		'patchstack_clientid'                           => ['default' => '', 'autoload' => 'yes'],
		'patchstack_secretkey'                          => ['default' => '', 'autoload' => 'no'],
		'patchstack_secretkey_nonce'                    => ['default' => '', 'autoload' => 'no'],
		'patchstack_license_free'                       => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_api_token'                          => ['default' => '', 'autoload' => 'yes'],
		'patchstack_subscription_class'					=> ['default' => '', 'autoload' => 'no'],
		'patchstack_last_license_check'					=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_last_sync'							=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_whitelist'                          => ['default' => '', 'autoload' => 'yes'],
		'patchstack_show_settings'                      => ['default' => 0, 'autoload' => 'no'],
		'patchstack_firewall_log_lastid'                => ['default' => 0, 'autoload' => 'no'],
		'patchstack_eventlog_lastid'                    => ['default' => 0, 'autoload' => 'no'],
		'patchstack_ott_action'							=> ['default' => '', 'autoload' => 'no'],
		'patchstack_managed'							=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_managed_text'						=> ['default' => '', 'autoload' => 'no'],
		'patchstack_latest_vulnerable'					=> ['default' => [], 'autoload' => 'no'],
		'patchstack_site_id'							=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_activation_secret'					=> ['default' => '', 'autoload' => 'no'],
		'patchstack_activation_time'					=> ['default' => '', 'autoload' => 'no'],
		'patchstack_firewall_ap_enabled'				=> ['default' => 0, 'autoload' => 'yes'],
		'patchstack_firewall_log_processing'			=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_eventlog_processing'				=> ['default' => 0, 'autoload' => 'no'],
		'patchstack_firewall_log_ap_processing'			=> ['default' => 0, 'autoload' => 'no'],

		// Admin page rename options.
		'patchstack_mv_wp_login'                        => ['default' => 0, 'autoload' => 'yes'],
		'patchstack_rename_wp_login'                    => ['default' => 'swlogin', 'autoload' => 'yes'],
		'patchstack_rename_wp_login_whitelist'			=> ['default' => [], 'autoload' => 'no'],

		// Statistics
		'patchstack_vulns_present'						=> ['default' => '?', 'autoload' => 'no'],
		'patchstack_fixes_present'						=> ['default' => '?', 'autoload' => 'no'],
		'patchstack_vpatches_present'					=> ['default' => '?', 'autoload' => 'no'],
		'patchstack_non_vpatches_present'				=> ['default' => '?', 'autoload' => 'no'],
		'patchstack_hits_last_30'						=> ['default' => [], 'autoload' => 'no'],
		'patchstack_hits_all_time'						=> ['default' => 0, 'autoload' => 'no'],
	];

	/**
	 * Register all the options, if not set already.
	 *
	 * @return void
	 */
	public function settings_init() {
		// Add the options.
		foreach ( $this->options as $name => $value ) {
			add_option( $name, $value['default'], '', $value['autoload'] );

			// Clone settings for multisite so we can set defaults.
			if ( is_multisite() ) {
				add_site_option( $name, $value['default'] );
			}
		}
	}
}
