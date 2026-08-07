<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any comment related action.
 */
class P_Event_Comments extends P_Event_Log {

	/**
	 * Hook into the actions so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! get_option( 'patchstack_activity_log_comments', 0 ) ) {
			return;
		}

		add_action( 'wp_insert_comment', [ &$this, 'handleCommentLog' ], 10, 2 );
		add_action( 'edit_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'trash_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'untrash_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'spam_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'unspam_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'delete_comment', [ &$this, 'handleCommentLog' ] );
		add_action( 'transition_comment_status', [ &$this, 'transitionCommentStatus' ], 10, 3 );
	}

	/**
	 * Log the action regarding the comment.
	 *
	 * @param integer $id
	 * @param string  $action
	 * @param string  $comment
	 * @return void
	 */
	protected function _addCommentLog( $id, $action, $comment = null ) {
		if ( is_null( $comment ) ) {
			$comment = get_comment( $id );
		}

		$this->insert(
			[
				'action'      => $action,
				'object'      => 'comment',
				'object_id'   => $id,
				'object_name' => esc_html( get_the_title( $comment->comment_post_ID ) ),
			]
		);
	}

	/**
	 * Determine what kind of comment action is executed.
	 *
	 * @param integer $comment_id
	 * @param string  $comment
	 * @return void
	 */
	public function handleCommentLog( $comment_id, $comment = null ) {
		if ( is_null( $comment ) ) {
			$comment = get_comment( $comment_id );
		}

		$action = 'created';
		switch ( current_filter() ) {
			case 'wp_insert_comment':
				$action = 1 === (int) $comment->comment_approved ? 'approved' : 'pending';
				break;
			case 'edit_comment':
				$action = 'updated';
				break;
			case 'delete_comment':
				$action = 'deleted';
				break;
			case 'trash_comment':
				$action = 'trashed';
				break;
			case 'untrash_comment':
				$action = 'untrashed';
				break;
			case 'spam_comment':
				$action = 'spammed';
				break;
			case 'unspam_comment':
				$action = 'unspammed';
				break;
		}

		$this->_addCommentLog( $comment_id, $action, $comment );
	}

	/**
	 * When the status of a comment is transitioned.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param string $comment
	 * @return void
	 */
	public function transitionCommentStatus( $new_status, $old_status, $comment ) {
		$this->_addCommentLog( $comment->comment_ID, $new_status, $comment );
	}
}
