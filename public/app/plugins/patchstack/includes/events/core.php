<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any core updates.
 */
class P_Event_Core extends P_Event_Log {

	/**
	 * Hook into the action so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( '_core_updated_successfully', [ &$this, 'eventCoreUpdatedSuccessfully' ] );
	}

	/**
	 * When the core version of WordPress has been updated.
	 *
	 * @param string $wp_version
	 * @return void
	 */
	public function eventCoreUpdatedSuccessfully( $wp_version ) {
		global $pagenow;
		if ( $pagenow !== 'update-core.php' ) {
			$object_name = 'WordPress core automatically updated';
		} else {
			$object_name = 'WordPress core updated';
		}

		$this->insert(
			[
				'action'      => 'updated',
				'object'      => 'core',
				'object_id'   => 0,
				'object_name' => $object_name,
			]
		);
	}
}
