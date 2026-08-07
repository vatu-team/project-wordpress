<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to pull the firewall/whitelist rules from our API.
 */
class P_Rules extends P_Core {

	/**
	 * Add the actions required to pull the rules.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
		add_action( 'patchstack_post_firewall_rules', [ $this, 'post_firewall_rules' ] );
		add_action( 'patchstack_post_dynamic_firewall_rules', [ $this, 'dynamic_firewall_rules' ] );
	}

	/**
	 * Pull the hardening .htaccess rules from the API.
	 * Then apply it to the .htaccess file after we create a backup.
	 *
	 * @return void
	 */
	public function post_firewall_rules() {
		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Check if server is supported and if htaccess modifications are disabled.
		if ( get_site_option( 'patchstack_disable_htaccess', 0 ) || ( defined( 'PS_DISABLE_HTACCESS' ) && PS_DISABLE_HTACCESS ) ) {
			return;
		}

		$rules    = $this->plugin->htaccess->get_firewall_rule_settings();
		$settings = json_encode( $rules );
		$results  = $this->plugin->api->post_firewall_rule( [ 'settings' => $settings ] );

		// If no rules returned (empty, or a status code/null from a failed request),
		// we assume all settings are turned off. Guard against assigning to a string
		// offset, which is a fatal error on PHP 7.1+.
		if ( ! is_array( $results ) ) {
			$results = [ 'rules' => '' ];
		}

		// We have rules so apply it to the .htaccess file.
		if ( isset( $results['rules'] ) ) {
			$this->plugin->htaccess->write_to_htaccess( $results['rules'] );
			return;
		}
	}

	/**
	 * Pull the firewall/whitelist rules from the API.
	 *
	 * @return void
	 */
	public function dynamic_firewall_rules() {
		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Get the firewall and whitelist rules.
		$results = $this->plugin->api->post_firewall_rule_json();
		if ( ! isset( $results['firewall'] ) ) {
			return;
		}

		// Separate the new firewall engine rules from the old ones.
		$newRules = [];
		$newRulesAP = [];
		$oldRules = [];

		// Counters for displaying purposes on the API key page.
		$vPatchCount = 0;
		$ruleCount = 0;

		// Parse the rules.
		foreach ( $results['firewall'] as $rule ) {
			if ( isset( $rule['rule_v2'] ) ) {
				$rule['rules'] = $rule['rule_v2'];
				unset( $rule['rule_v2'] );

				// Mark vPatches based on substring.
				if ( stripos( $rule['title'], ' vulnerabilit' ) !== false && stripos( $rule['title'], 'block ' ) !== false ) {
					$vPatchCount++;
				} else {
					$ruleCount++;
				}

				// Differentiate between auto prepend rules and regular ones.
				if ( isset( $rule['ap'] ) && !empty( $rule['ap'] ) ) {
					$newRulesAP[] = $rule;
				} else {
					$newRules[] = $rule;
				}
			} else {
				$ruleCount++;
				$oldRules[] = $rule;
			}
		}

		// Update firewall rules.
		update_option( 'patchstack_firewall_rules', json_encode( $oldRules ), true );
		update_option( 'patchstack_firewall_rules_v3', json_encode( $newRules ), true );
		update_option( 'patchstack_firewall_rules_v3_ap', json_encode( $newRulesAP ), true );

		// Update the counters.
		update_option( 'patchstack_vpatches_present', $vPatchCount );
		update_option( 'patchstack_non_vpatches_present', $ruleCount );

		// Separate the new firewall engine rules from the old ones. Only touch the
		// stored whitelists when the API actually returned them, otherwise a partial
		// response would wipe the existing whitelist rules.
		if ( isset( $results['whitelists'] ) && is_array( $results['whitelists'] ) ) {
			$newRules = [];
			$oldRules = [];
			foreach ( $results['whitelists'] as $rule ) {
				if ( isset( $rule['rule_v2'] ) ) {
					$rule['rules'] = $rule['rule_v2'];
					unset( $rule['rule_v2'] );
					$newRules[] = $rule;
				} else {
					$oldRules[] = $rule;
				}
			}

			// Update whitelist rules.
			update_option( 'patchstack_whitelist_rules', json_encode( $oldRules ), true );
			update_option( 'patchstack_whitelist_rules_v3', json_encode( $newRules ), true );
		}

		// Update the whitelisted keys.
		if ( isset( $results['whitelist_keys'] ) ) {
			update_option( 'patchstack_whitelist_keys_rules', json_encode( $results['whitelist_keys'] ), true );
		}
	}
}
