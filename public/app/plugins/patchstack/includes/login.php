<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to alter anything related to the login page.
 */
class P_Login extends P_Core {

	/**
	 * Validated request or not.
	 * 
	 * @param boolean
	 */
	private $validated = false;

	/**
	 * Add the actions required to interact with the login process.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		if ( $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		add_action( 'login_init', [ $this, 'add_captcha' ] );
		add_action( 'login_init', [ $this, 'check_ipban' ] );
		add_action( 'login_head', [ $this, 'add_captcha' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'login_enqueue_scripts' ], 1 );

		// WooCommerce related functionality.
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_login_form_start', [ $this, 'add_captcha' ] );
			add_action( 'woocommerce_register_form_start', [ $this, 'add_captcha' ] );
			add_action( 'wp_authenticate', [ $this, 'add_captcha' ] );
			add_action( 'woocommerce_before_lost_password_form', [ $this, 'add_captcha' ] );
		}

		// 2FA related actions.
		if ( $this->get_option( 'patchstack_login_2fa', 0 ) ) {
			add_action( 'login_form', [ $this, 'tfa_login_form' ] );
			add_action( 'authenticate', [ $this, 'tfa_authenticate' ], 30, 3 );
			add_action( 'profile_personal_options', [ $this, 'tfa_profile_personal_options' ] );
			add_action( 'personal_options', [ $this, 'tfa_personal_options' ] );
			add_action( 'edit_user_profile_update', [ $this, 'tfa_options_update' ] );
			add_action( 'personal_options_update', [ $this, 'tfa_personal_options_update' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'tfa_admin_enqueue_scripts' ] );

			// WooCommerce related functionality.
			if ( class_exists( 'WooCommerce' ) ) {
				add_action( 'woocommerce_login_form', [ $this, 'tfa_woocommerce_login_form' ] );
				add_action( 'woocommerce_edit_account_form', [ $this, 'tfa_woocommerce_profile_personal_options' ] );
				add_action( 'woocommerce_save_account_details_errors', [ $this, 'tfa_woocommerce_validate_tfa'], 10, 2 );
			}
		}
	}

	/**
	 * Register the Google reCAPTCHA JavaScript for the login area.
	 *
	 * @return void
	 */
	public function login_enqueue_scripts() {
		if ( $this->get_option( 'patchstack_captcha_login_form', false ) && $this->get_option( 'patchstack_captcha_type' ) != 'v3' && $this->get_option( 'patchstack_captcha_type' ) != 'turnstile' ) {
			wp_enqueue_script( 'patchstack_captcha', 'https://www.google.com/recaptcha/api.js' );
		}
	}

	/**
	 * Add the 2FA code to the login form.
	 *
	 * @return void
	 */
	public function tfa_login_form() {
		require_once dirname( __FILE__ ) . '/views/2fa-login-form.php';
	}

	/**
	 * Add the 2FA code to the WooCommerce login form.
	 *
	 * @return void
	 */
	public function tfa_woocommerce_login_form() {
		require_once dirname( __FILE__ ) . '/views/2fa-login-form-woocommerce.php';
	}

	/**
	 * Check the 2FA code, if 2FA is enabled for the user.
	 *
	 * @param object $user
	 * @param string $username
	 * @param string $password
	 * @return object|WP_User|WP_Error
	 */
	public function tfa_authenticate( $user, $username = '', $password = '' ) {
		if ( ! isset( $user->ID ) ) {
			return $user;
		}

		// If we have a valid user object, check to see if the user has 2FA enabled.
		$enabled = get_user_option( 'webarx_2fa_enabled', $user->ID );
		if ( empty( $enabled ) ) {
			return $user;
		}

		// If enabled, check to see if the verification code is being sent.
		if ( ! isset( $_POST['patchstack_2fa'] ) || ( isset( $_POST['patchstack_2fa'] ) && $_POST['patchstack_2fa'] == '' ) ) {
			return new WP_Error( 'patchstack_2fa_empty_code', esc_attr__( 'Please enter the 2FA authentication code that is generated on your device.', 'patchstack' ) );
		}

		// Verify the code.
		require_once dirname( __FILE__ ) . '/2fa/rfc6238.php';
		$secret  = $this->tfa_get_secret( $user );
		if ( ! TokenAuth6238::verify( $secret, trim( $_POST['patchstack_2fa'] ) ) ) {
			return new WP_Error( 'patchstack_2fa_invalid_code', esc_attr__( 'The 2FA authentication code you entered is invalid.', 'patchstack' ) );
		}

		return $user;
	}

	/**
	 * Show the 2FA disable field to the admin.
	 * 
	 * @param object $user
	 * @return void
	 */
	public function tfa_personal_options( $user ) {
		require_once dirname( __FILE__ ) . '/views/2fa-profile-configuration-admin.php';
	}

	/**
	 * Show the 2FA fields.
	 *
	 * @param object $user
	 * @return void
	 */
	public function tfa_profile_personal_options( $user ) {
		$secret = $this->tfa_get_secret( $user );
		require_once dirname( __FILE__ ) . '/views/2fa-profile-configuration.php';
	}

	/**
	 * Show the 2FA fields.
	 *
	 * @param object $user
	 * @return void
	 */
	public function tfa_woocommerce_profile_personal_options( $user ) {
		$secret = $this->tfa_get_secret( $user );
		require_once dirname( __FILE__ ) . '/views/2fa-profile-configuration-woocommerce.php';
	}

	/**
	 * Validate the 2FA connection of a WooCommerce customer.
	 * 
	 * @param mixed $errors
	 * @param mixed $user
	 * @return void
	 */
	public function tfa_woocommerce_validate_tfa( &$errors, &$user ) {
		// If we have a valid user object, check to see if the user has 2FA enabled.
		$enabled = get_user_option( 'webarx_2fa_enabled', $user->ID );
		if ( $enabled || ! isset ( $_POST['patchstack_2fa_enabled'] ) ) {
			$this->tfa_personal_options_update( $user->ID );
			return;
		}

		// Verify the code.
		require_once dirname( __FILE__ ) . '/2fa/rfc6238.php';
		$secret  = $this->tfa_get_secret( $user );
		$code    = isset( $_POST['patchstack_2fa_secretkey_verification'] ) ? trim( $_POST['patchstack_2fa_secretkey_verification'] ) : '';
		if ( ! TokenAuth6238::verify( $secret, $code ) ) {
			wc_add_notice( __( 'The 2FA authentication code you entered is invalid.', 'patchstack' ), 'error' );
			return;
		}

		$this->tfa_personal_options_update( $user->ID );
	}

	/**
	 * Update the 2FA fields.
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function tfa_personal_options_update( $user_id ) {
		update_user_option( $user_id, 'webarx_2fa_enabled', ! empty( $_POST['patchstack_2fa_enabled'] ), true );
	}

	/**
	 * Update the 2FA fields on admin.
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function tfa_options_update( $user_id ) {
		update_user_option( $user_id, 'webarx_2fa_enabled', ! empty( $_POST['patchstack_2fa_enabled'] ), true );
	}

	/**
	 * Add the QRCode image generator JavaScript library.
	 *
	 * @return void
	 */
	public function tfa_admin_enqueue_scripts() {
		wp_register_script( 'patchstack_qrcode', $this->plugin->url . '/assets/js/qrcode.min.js', [], $this->plugin->version );
		wp_enqueue_script( 'patchstack_qrcode' );
	}

	/**
	 * In case of legacy conditions, we encrypt the secret key and then store it.
	 * 
	 * @return string
	 */
	private function tfa_get_secret( $user ) {
		$secret = get_user_option( 'webarx_2fa_secretkey', $user->ID );

		// If user has no secret key set yet, generate one.
		if ( empty( $secret ) || strlen( $secret ) === 16 ) {
			if ( empty( $secret ) ) {
				require_once dirname( __FILE__ ) . '/2fa/rfc6238.php';
				$secret = TokenAuth6238::generateRandomClue();
			}

			$enc = $this->encrypt( $secret );
			update_user_option( $user->ID, 'webarx_2fa_secretkey', $enc['cipher'], true );
			update_user_option( $user->ID, 'webarx_2fa_secretkey_nonce', $enc['nonce'], true );
		} else {
			$nonce = get_user_option( 'webarx_2fa_secretkey_nonce', $user->ID );
			$secret = $this->decrypt( $secret, $nonce );
		}

		return $secret;
	}

	/**
	 * Check if the IP address is banned from attempting to guess passwords.
	 *
	 * @return void
	 */
	public function check_ipban() {
		if ( is_user_logged_in() || ! $this->get_option( 'patchstack_block_bruteforce_ips', 0 ) ) {
			return;
		}

		// Check if the users IP address is whitelisted.
		$ip = $this->get_ip();
		if ( $this->plugin->ban->is_ip_whitelisted( $ip ) ) {
			return;
		}

		// Calculate block time.
		$minutes = (int) $this->get_option( 'patchstack_anti_bruteforce_minutes', 30 );
		$timeout = (int) $this->get_option( 'patchstack_anti_bruteforce_blocktime', 60 );
		if ( empty( $minutes ) || empty( $timeout ) ) {
			$time = 30 + 60;
		} else {
			$time = $minutes + $timeout;
		}

		// Check if X failed login attempts were made.
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare( 'SELECT COUNT(*) AS numIps FROM ' . $wpdb->prefix . "patchstack_event_log WHERE ip = '%s' AND action = 'failed login' AND date >= ('" . current_time( 'mysql' ) . "' - INTERVAL %d MINUTE)", [ $ip, $time ] ),
			OBJECT
		);

		// Determine the number of attempts.
		if ( ! isset( $results, $results[0], $results[0]->numIps ) ) {
			$num = 0;
		} else {
			$num = $results[0]->numIps;
		}

		// Block the user?
		if ( $num >= $this->get_option( 'patchstack_anti_bruteforce_attempts', 10 ) ) {
			$this->plugin->firewall_base->display_error_page( 24 );
		}
	}

	/**
	 * Determine if we should inject reCAPTCHA into certain pages.
	 *
	 * @return void
	 */
	public function add_captcha() {
		switch ( $this->get_option( 'patchstack_captcha_type' ) ) {
			case 'v2':
				$public  = $this->get_option( 'patchstack_captcha_public_key', '' );
				$private = $this->get_option( 'patchstack_captcha_private_key', '' );
				break;
			case 'invisible':
				$public  = $this->get_option( 'patchstack_captcha_public_key_v3', '' );
				$private = $this->get_option( 'patchstack_captcha_private_key_v3', '' );
				break;
			case 'v3':
				$public  = $this->get_option( 'patchstack_captcha_public_key_v3_new', '' );
				$private = $this->get_option( 'patchstack_captcha_private_key_v3_new', '' );
				break;
			case 'turnstile':
				$public  = $this->get_option( 'patchstack_captcha_public_key_turnstile', '' );
				$private = $this->get_option( 'patchstack_captcha_private_key_turnstile', '' );
				break;
			default:
				return;
			break;
		}

		// Make sure that the keys are set.
		if ( $public == '' || $private == '' ) {
			return;
		}

		// reCAPTCHA on the login page.
		if ( $this->get_option( 'patchstack_captcha_login_form' ) ) {
			add_filter( 'login_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_filter( 'woocommerce_login_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_filter( 'wp_authenticate_user', [ $this, 'login_captcha_check' ], 10, 2 );
		}

		// reCAPTCHA on the registration form.
		if ( $this->get_option( 'patchstack_captcha_registration_form' ) ) {
			add_action( 'register_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_action( 'woocommerce_register_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_action( 'registration_errors', [ $this, 'general_captcha_check' ] );
			add_filter( 'woocommerce_process_registration_errors', [$this, 'general_captcha_check' ], 10, 1 );
		}

		// reCAPTCHA on the reset password form.
		if ( $this->get_option( 'patchstack_captcha_reset_pwd_form' ) ) {
			add_action( 'lostpassword_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_action( 'woocommerce_lostpassword_form', [ $this->plugin->hardening, 'captcha_display' ] );
			add_action( 'allow_password_reset', [ $this, 'general_captcha_check' ] );

			// WooCommerce only.
			if ( class_exists( 'WooCommerce' ) ) {
				add_action( 'lostpassword_post', [ $this, 'general_captcha_check' ], 1, 1 );
			}
		}
	}

	/**
	 * Check reCAPTCHA upon login.
	 *
	 * @param string $user
	 * @param string $password
	 * @return WP_User|WP_Error
	 */
	public function login_captcha_check( $user, $password ) {
		if ( $this->validated ) {
			return $user;
		}

		$result = $this->plugin->hardening->captcha_check();

		if ( ! $result['response'] ) {
			if ( $result['reason'] === 'ERROR_NO_KEYS' ) {
				$this->validated = true;
				return $user;
			}
			$error_message = sprintf( '<strong>%s</strong>: %s', 'Error', esc_attr__( 'You have entered an incorrect reCAPTCHA value.', 'patchstack' ) );

			if ( $result['reason'] === 'VERIFICATION_FAILED' || $result['reason'] === 'RECAPTCHA_EMPTY_RESPONSE' ) {
				wp_clear_auth_cookie();
				return new WP_Error( 'patchstack_error', $error_message );
			}

			if ( isset( $_REQUEST['log'], $_REQUEST['pwd'] ) ) {
				return new WP_Error( 'patchstack_error', $error_message );
			}
		} else {
			$this->validated = true;
			return $user;
		}
	}

	/**
	 * Check reCAPTCHA upon login.
	 *
	 * @param string $user
	 * @param string $password
	 * @return WP_User|WP_Error
	 */
	public function login_captcha_check_woocommerce( $error, $username, $password, $email ) {
		if ( $this->validated ) {
			return $error;
		}

		$result = $this->plugin->hardening->captcha_check();

		if ( $result['response'] || $result['reason'] == 'ERROR_NO_KEYS' ) {
			$this->validated = true;
			return $error;
		}

		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error();
		}

		$error->add( 'patchstack_error', 'ERROR' . ':&nbsp;' . esc_attr__( 'You have entered an incorrect reCAPTCHA value.', 'patchstack' ) );
		return $error;
	}

	/**
	 * Captcha check for the register or lost password form.
	 *
	 * @param mixed|WP_Error $error
	 * @return WP_Error
	 */
	public function general_captcha_check( $error ) {
		if ( $this->validated ) {
			return $error;
		}

		$result = $this->plugin->hardening->captcha_check();

		if ( $result['response'] || $result['reason'] == 'ERROR_NO_KEYS' ) {
			$this->validated = true;
			return $error;
		}

		if ( ! is_wp_error( $error ) ) {
			$error = new WP_Error();
		}

		$error->add( 'patchstack_error', 'ERROR' . ':&nbsp;' . esc_attr__( 'You have entered an incorrect reCAPTCHA value.', 'patchstack' ) );
		return $error;
	}
}
