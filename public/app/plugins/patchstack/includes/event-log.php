<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any events that happen on the WordPress site.
 */
class P_Event_Log extends P_Core {

	/**
	 * Add the actions required for the activity logger.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );
		if ( ! $this->get_option( 'patchstack_activity_log_is_enabled', true ) ) {
			return;
		}

		// The activity logger feature can only be used on an activated license.
		if ( ! $this->license_is_active() || $this->get_option( 'patchstack_license_free', 0 ) == 1 ) {
			return;
		}

		// Include all the different event loggers.
		foreach ( [ 'posts', 'plugins', 'core', 'options', 'users', 'comments', 'attachment' ] as $event ) {
			require_once dirname( __FILE__ ) . '/events/' . $event . '.php';
			$class = 'P_Event_' . ucfirst( $event );
			new $class();
		}
	}

	/**
	 * If any of our event listeners get triggered, it will insert data using this method.
	 *
	 * @param array $args The arguments to log.
	 * @return void
	 */
	public function insert( $args ) {
		// Get the author name, if the user is logged in.
		$user   = get_user_by( 'id', get_current_user_id() );
		$author = ! $user ? 'Unauthenticated user' : $user->data->user_login;

		// If it's a scheduled task running, we override the name.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$author = 'WPCron';
		}

		// Exception for when the action is 'logged in'.
		if ( $args['action'] == 'logged in' ) {
			$author = $args['object_name'];
		}

		// Log the action.
		if ( ! is_null( $this->get_ip() ) ) {

			// Skip unauthenticated user on post object.
			if ( $author == 'Unauthenticated user' && $args['object'] == 'post' ) {
				return;
			}

			// Insert into the logs.
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'patchstack_event_log',
				[
					'author'      => $author,
					'ip'          => $this->get_ip(),
					'object'      => isset( $args['object'] ) ? $args['object'] : '',
					'object_id'   => isset( $args['object_id'] ) ? (int) $args['object_id'] : '',
					'action'      => isset( $args['action'] ) ? $args['action'] : '',
					'object_name' => isset( $args['object_name'] ) ? $args['object_name'] : '',
					'date'        => current_time( 'mysql' ),
					'flag'        => '',
				],
				[ '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
			);
		}
	}
}
