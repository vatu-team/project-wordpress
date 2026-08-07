<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used as a base for communicating with the Patchstack API.
 */
class P_Api extends P_Core {

	/**
	 * @var integer The current blog id.
	 */
	public $blog_id;

	/**
	 * @var string Error message from the API.
	 */
	public $message;

	/**
	 * Add the actions required for the API.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
		$this->blog_id = get_current_blog_id();
		add_action( 'patchstack_update_license_status', [ $this, 'update_license_status' ] );
		add_action( 'patchstack_send_ping', [ $this, 'ping' ] );
		add_action( 'patchstack_send_header_request', [ $this, 'send_header_request' ] );
	}

	/**
	 * Get the API token.
	 *
	 * @param string  $clientid The API client ID.
	 * @param string  $secretkey The API secret key.
	 * @param boolean $fresh Whether or not to get a fresh token.
	 * @return null|string
	 */
	public function get_access_token( $clientid = '', $secretkey = '', $fresh = false ) {
		// Get current access token, if it exists.
		$token_data = $this->get_blog_option( $this->blog_id, 'patchstack_api_token', false );

		// If we do not need a fresh token, get the current one if it's not expired.
		if ( ! $fresh && isset( $token_data['token'] ) && ! $this->has_expired( $token_data['expiresin'] ) ) {
			return $token_data['token'];
		}

		// Call API and get the new access token.
		$response = $this->fetch_access_token( $clientid, $secretkey );
		if ( $response && $response->result == 'success' ) {
			$this->update_blog_option(
				$this->blog_id,
				'patchstack_api_token',
				[
					'token'     => $response->message,
					'expiresin' => $response->expiresin,
				]
			);
			return $response->message;
		}

		// If we reach this, it means we were not able to get the access token.
		$this->message = $response;
		$this->update_blog_option( $this->blog_id, 'patchstack_api_token', '' );
		return null;
	}

	/**
	 * Fetch the API Token from API Server.
	 *
	 * @param string $clientid The API client ID.
	 * @param string $secretkey The API secret key.
	 * @return string|array|object
	 */
	public function fetch_access_token( $clientid = '', $secretkey = '' ) {
		// Skeleton for the response data.
		$response_data = (object) [
			'result'    => '',
			'message'   => '',
			'expiresin' => '',
		];

		// Determine if the license id/key is set.
		$client_id = $this->get_blog_option( $this->blog_id, 'patchstack_clientid', $clientid );

		// Decrypt the secret key, if it is encrypted.
		$client_secret = $this->get_blog_option( $this->blog_id, 'patchstack_secretkey', $secretkey );
		$client_nonce = $this->get_blog_option( $this->blog_id, 'patchstack_secretkey_nonce', false );
		if ( $client_nonce ) {
			$client_secret = $this->decrypt( $client_secret, $client_nonce );
		}

		// Make sure these values are set.
		if ( empty( $client_id ) || empty( $client_secret ) ) {
			$response_data->result  = 'failed';
			$response_data->message = esc_attr__( 'API keys missing! Unable to obtain an access token.', 'patchstack' );
			return $response_data;
		}

		// Send a request to our server to obtain the access token.
		$response = wp_remote_post(
			$this->plugin->auth_url . '/oauth/token',
			[
				'method'      => 'POST',
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => [],
				'body'        => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'client_credentials',
				],
				'cookies'     => [],
			]
		);

		// Stop if we received an error from the API.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) == 401 ) {
			$this->message = wp_remote_retrieve_body( $response );

			if ( wp_remote_retrieve_response_code( $response ) == 401 ) {
				$this->update_blog_option( $this->blog_id, 'patchstack_clientid', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_secretkey', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_secretkey_nonce', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_api_token', '' );
			}

			$response_data->result  = 'failed';
			$response_data->message = esc_attr__( 'Unexpected error! Unable to obtain an access token. Error code: ', 'patchstack' ) . wp_remote_retrieve_response_code( $response );
			$response_data->body = $this->message;
			return $response_data;
		}

		// Parse the result.
		$result = json_decode( wp_remote_retrieve_body( $response ) );
		if ( isset( $result->access_token ) ) {
			$response_data->result    = 'success';
			$response_data->message   = $result->access_token;
			$response_data->expiresin = $result->expires_in;

			// We need to know when the token expires.
			// Defer to 'expires' if it is provided instead.
			if ( isset( $result->expires_in ) ) {
				if ( ! is_numeric( $result->expires_in ) ) {
					$response_data->result  = 'failed';
					$response_data->message = 'expires_in value must be an integer';
					return $response_data;
				}
				$response_data->expiresin = $result->expires_in != 0 ? time() + $result->expires_in : 0;
			}

			return $response_data;
		} elseif ( isset( $result->error ) ) {
			$response_data->result  = $result->error;
			$response_data->message = esc_attr__( 'Unexpected error! Unable to obtain an access token.', 'patchstack' ) . $result->message;
			return $response_data;
		}
	}

	/**
	 * Send a request to the API with optionally POST data.
	 *
	 * @param string $url
	 * @param string $method
	 * @param array  $data
	 * @return void|array If successful array, otherwise void.
	 */
	public function send_request( $url, $method, $data = [] ) {
		// Attempt to get the access token.
		$token = $this->get_access_token();
		if ( empty( $token ) ) {
			return;
		}

		// Pass the multisite value to all requests, only for POST requests.
		if ( $method == 'POST' ) {
			$data['is_multisite'] = $this->is_multi_site ? 1 : 0;
		}
		
		// Send the remote request using the WordPress built-in method.
		$response = wp_remote_request(
			$this->plugin->api_url . $url,
			[
				'method'      => $method,
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => [
					'Authorization' => 'Bearer ' . $token,
					'LicenseID'     => $this->get_blog_option( $this->blog_id, 'patchstack_clientid', 0 ),
					'Source-Host'   => get_site_url(),
				],
				'body'        => $data,
				'cookies'     => [],
			]
		);

		// Check error or status code.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {

			// See if we received a site API connection termination.
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $body['cancel'] ) ) {
				$this->update_blog_option( $this->blog_id, 'patchstack_clientid', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_secretkey', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_secretkey_nonce', '' );
				$this->update_blog_option( $this->blog_id, 'patchstack_api_token', '' );
			}

			return wp_remote_retrieve_response_code( $response );
		}

		// A 200 OK means we successfully communicated with the API for this sync
		// action (license verify, log/software upload, rule pull, ping, etc.), so
		// record it as the last successful sync time.
		$this->update_blog_option( $this->blog_id, 'patchstack_last_sync', time() );

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Checks if the API token has expired.
	 *
	 * @param integer $expiresin API token expiry.
	 * @return boolean If the token has expired.
	 */
	public function has_expired( $expiresin ) {
		// A stored expiry of 0 means the token never expires.
		if ( $expiresin === 0 ) {
			return false;
		}
		return ( $expiresin < ( time() + 30 ) );
	}

	/**
	 * Retrieve the status of a license.
	 *
	 * @param boolean $fetchPolicy Whether or not to fetch the policy settings.
	 * @return void|array
	 */
	public function update_license_status($fetchPolicy = false) {
		// Get current license status.
		$response = $this->send_request( '/api/license/verify' . ($fetchPolicy ? '?fetchPolicy=true' : ''), 'GET' );

		// Invalid license, or no longer active.
		if ( ! is_array( $response ) && $response == 422 ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_clientid', '' );
			$this->update_blog_option( $this->blog_id, 'patchstack_secretkey', '' );
			$this->update_blog_option( $this->blog_id, 'patchstack_secretkey_nonce', '' );
			$this->update_blog_option( $this->blog_id, 'patchstack_api_token', '' );
			return;
		}

		// Update the representing options.
		// Expiry date.
		if ( isset( $response['expires_at'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_license_expiry', $response['expires_at'] );
		}

		// Free vs Paid license.
		if ( isset( $response['free'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_license_free', $response['free'] == false ? 0 : 1 );

			if ( $response['free'] == true ) {
				$this->update_blog_option( $this->blog_id, 'patchstack_show_settings', 0 );
				$this->update_blog_option( $this->blog_id, 'patchstack_firewall_rules_v3', '[]' );
			} else {
				$this->send_header_request();
			}
		}

		// Active subscription.
		if ( isset( $response['active'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_license_activated', $response['active'] == true ? 1 : 0 );
		}

		// Subscription class.
		if ( isset( $response['class'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_subscription_class', $response['class'] );
			$this->update_blog_option( $this->blog_id, 'patchstack_last_license_check', time() );
		}

		// Managed site status.
		if ( isset( $response['managed'], $response['managed_string'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_managed', $response['managed'] ? 1 : 0 );
			$this->update_blog_option( $this->blog_id, 'patchstack_managed_text', $response['managed_string'] );
		}

		// Site ID.
		if ( isset( $response['site_id'] ) ) {
			$this->update_blog_option( $this->blog_id, 'patchstack_site_id', $response['site_id'] );
		}

		// Policy settings.
		if ( isset( $response['policy'] ) && is_array( $response['policy'] ) && count( $response['policy'] ) > 0 ) {
			foreach ( $response['policy'] as $key => $value ) {
				// Make sure the option exists.
				if ( ! array_key_exists( $key, $this->plugin->admin_options->options ) ) {
					continue;
				}

				// Booleans would persist as '1' / '' otherwise, store them as 1/0 so type checks behave consistently.
				if ( is_bool( $value ) ) {
					$value = $value ? 1 : 0;
				}

				// Update the option.
				$this->update_blog_option( $this->blog_id, $key, $value );
			}
		}

		return $response;
	}

	/**
	 * Send a request to our API for the IP address header.
	 * 
	 * @return void
	 */
	public function send_header_request()
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
	 * Get the firewall rules.
	 *
	 * @return array The firewall rules.
	 */
	public function post_firewall_rule_json() {
		return $this->send_request( '/api/get-rules/3', 'POST' );
	}

	/**
	 * Get the .htaccess rules.
	 *
	 * @param array $settings The settings on which .htaccess rules to get.
	 * @return array The .htaccess rules.
	 */
	public function post_firewall_rule( $settings ) {
		return $this->send_request( '/api/rules', 'POST', $settings );
	}

	/**
	 * Send the firewall logs to the API.
	 *
	 * @param array $logs
	 * @return array
	 */
	public function upload_firewall_logs( $logs ) {
		return $this->send_request( '/api/logs/log', 'POST', $logs );
	}

	/**
	 * Send the activity logs to the server.
	 *
	 * @param array $logs
	 * @return array
	 */
	public function upload_activity_logs( $logs ) {
		return $this->send_request( '/api/activity/log', 'POST', $logs );
	}

	/**
	 * Send WordPress core, theme, plugins versions and information to the API.
	 *
	 * @param array $software
	 * @return array
	 */
	public function upload_software( $software ) {
		return $this->send_request( '/api/sw/json', 'POST', $software );
	}

	/**
	 * Update the firewall status.
	 *
	 * @param array $status
	 * @return array
	 */
	public function update_firewall_status( $status ) {
		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		return $this->send_request( '/api/firewall/update/status', 'POST', $status );
	}

	/**
	 * Update the URL on the API.
	 *
	 * @param array $url The current URL of the site.
	 * @return array
	 */
	public function update_url( $url ) {
		return $this->send_request( '/api/plugin/update/url', 'POST', $url );
	}

	/**
	 * Send list of sites and get the id and secret key in response.
	 *
	 * @param array $sites
	 * @return array
	 */
	public function get_site_licenses( $sites ) {
		return $this->send_request( '/api/multisite-keys', 'POST', $sites );
	}

	/**
	 * Send a ping to the Patchstack API every 3 hours to make sure that the plugin is still running.
	 *
	 * @return void
	 */
	public function ping() {
		$this->send_request( '/api/ping', 'POST', [ 'firewall' => $this->get_option( 'patchstack_basic_firewall' ) == 1 ? 1 : 0 ] );
	}

	/**
	 * Generate a secret value and send it to the Patchstack API for quick activation.
	 * 
	 * @param string $secret
	 * @return void
	 */
	public function send_secret_token( $secret ) {
		$response = wp_remote_request(
			$this->plugin->api_url . '/api/secret',
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
					'secret' => $secret,
					'url' => get_site_url()
				],
				'cookies'     => [],
			]
		);

		// Check error or status code.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) != 200 ) {
			return false;
		}

		// Determine if auto-activation succeeded.
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ($result && isset($result['activated'])) {
			return $result['activated'];
		}

		return false;
	}
}
