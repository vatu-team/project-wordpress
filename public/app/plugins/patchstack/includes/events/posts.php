<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any post related action.
 */
class P_Event_Posts extends P_Event_Log {

	/**
	 * Hook into the actions so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! get_option( 'patchstack_activity_log_posts', 0 ) ) {
			return;
		}

		add_action( 'transition_post_status', [ &$this, 'transition_post_status' ], 10, 3 );
		add_action( 'delete_post', [ &$this, 'delete_post' ] );
	}

	/**
	 * Determine the title.
	 *
	 * @param integer $post
	 * @return string
	 */
	protected function _draft_or_post_title( $post = 0 ) {
		$title = esc_html( get_the_title( $post ) );
		if ( empty( $title ) ) {
			return esc_attr__( '(no title)', 'patchstack' );
		}

		return $title;
	}

	/**
	 * When the status of a post is transitioned.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param object $post
	 * @return void
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		// Determine the action.
		if ( $old_status === 'auto-draft' && ( $new_status !== 'auto-draft' && $new_status !== 'inherit' ) ) {
			$action = 'created';
		} elseif ( $new_status === 'auto-draft' || ( $old_status === 'new' && $new_status === 'inherit' ) ) {
			return;
		} elseif ( $new_status === 'trash' ) {
			$action = 'trashed';
		} elseif ( $old_status === 'trash' ) {
			$action = 'restored';
		} else {
			$action = 'updated';
		}

		// No need to log revisions.
		if ( wp_is_post_revision( $post->ID ) ) {
			return;
		}

		// Skip for menu items.
		if ( get_post_type( $post->ID ) === 'nav_menu_item' ) {
			return;
		}

		$this->insert(
			[
				'object'      => 'post',
				'object_id'   => $post->ID,
				'action'      => $action,
				'object_name' => $this->_draft_or_post_title( $post->ID ),
			]
		);
	}

	/**
	 * When a post is deleted.
	 *
	 * @param integer $post_id
	 * @return void
	 */
	public function delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( in_array( $post->post_status, [ 'auto-draft', 'inherit' ] ) ) {
			return;
		}

		// Skip for menu items.
		if ( get_post_type( $post->ID ) === 'nav_menu_item' ) {
			return;
		}

		$this->insert(
			[
				'object'      => 'post',
				'object_id'   => $post->ID,
				'action'      => 'deleted',
				'object_name' => $this->_draft_or_post_title( $post->ID ),
			]
		);
	}
}
