<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any user related action.
 */
class P_Event_Users extends P_Event_Log {

	/**
	 * Hook into the actions so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_login', [ &$this, 'login' ], 10, 2 );
		add_action( 'delete_user', [ &$this, 'deleteUser' ] );
		add_action( 'user_register', [ &$this, 'register' ] );
		add_action( 'profile_update', [ &$this, 'profileUpdate' ] );
		add_action( 'validate_password_reset', [ &$this, 'resetPassword' ], 10, 2 );
		add_filter( 'wp_login_failed', [ &$this, 'wrongPassword' ] );
	}

	/**
	 * When a user is logging in.
	 *
	 * @param string $user_login
	 * @param object $user
	 * @return void
	 */
	public function login( $user_login, $user ) {
		$this->insert(
			[
				'action'      => 'logged in',
				'object'      => 'user',
				'object_id'   => $user->ID,
				'object_name' => esc_html( $user->user_nicename ),
			]
		);
	}

	/**
	 * When a new account has been created.
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function register( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$this->insert(
			[
				'action'      => 'registered',
				'object'      => 'user',
				'object_id'   => $user->ID,
				'object_name' => esc_html( $user->user_nicename ),
			]
		);
	}

	/**
	 * When a user is deleted.
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function deleteUser( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$this->insert(
			[
				'action'      => 'deleted',
				'object'      => 'user',
				'object_id'   => $user->ID,
				'object_name' => esc_html( $user->user_nicename ),
			]
		);
	}

	/**
	 * When a profile is updated.
	 *
	 * @param integer $user_id
	 * @return void
	 */
	public function profileUpdate( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		$this->insert(
			[
				'action'      => 'updated',
				'object'      => 'user',
				'object_id'   => $user->ID,
				'object_name' => esc_html( $user->user_nicename ),
			]
		);
	}

	/**
	 * When a failed login attempt was made due to wrong password.
	 *
	 * @param string $username
	 * @return void
	 */
	public function wrongPassword( $username ) {
		$this->insert(
			[
				'action'      => 'failed login',
				'object'      => 'user',
				'object_id'   => 0,
				'object_name' => esc_html( $username ),
			]
		);
	}

	/**
	 * When a password is being reset through the reset password procedure.
	 *
	 * @param object $errors
	 * @param object $user
	 * @return void
	 */
	public function resetPassword( $errors, $user ) {
		// validate_password_reset also fires when the form is merely displayed (GET);
		// only log when a new password was actually submitted.
		if ( is_a( $user, 'WP_User' ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
			$this->insert(
				[
					'action'      => 'password reset',
					'object'      => 'user',
					'object_id'   => 0,
					'object_name' => esc_html( $user->user_login ),
				]
			);
		}
	}
}
