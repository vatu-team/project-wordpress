<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any attachment related event.
 */
class P_Event_Attachment extends P_Event_Log {

	/**
	 * Hook into the actions so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'add_attachment', [ &$this, 'addAttachment' ] );
		add_action( 'edit_attachment', [ &$this, 'editAttachment' ] );
		add_action( 'delete_attachment', [ &$this, 'deleteAttachment' ] );
	}

	/**
	 * Log the action regarding the attachment.
	 *
	 * @param string  $action
	 * @param integer $attachment_id
	 * @return void
	 */
	protected function _addLogAttachment( $action, $attachment_id ) {
		$attachment = get_post( $attachment_id );
		$this->insert(
			[
				'action'      => $action,
				'object'      => 'attachment',
				'object_id'   => $attachment_id,
				'object_name' => esc_html( get_the_title( $attachment->ID ) ),
			]
		);
	}

	/**
	 * When an attachment is deleted.
	 *
	 * @param integer $attachment_id
	 * @return void
	 */
	public function deleteAttachment( $attachment_id ) {
		$this->_addLogAttachment( 'deleted', $attachment_id );
	}

	/**
	 * When an attachment is modified.
	 *
	 * @param integer $attachment_id
	 * @return void
	 */
	public function editAttachment( $attachment_id ) {
		$this->_addLogAttachment( 'updated', $attachment_id );
	}

	/**
	 * When an attachment is added.
	 *
	 * @param integer $attachment_id
	 * @return void
	 */
	public function addAttachment( $attachment_id ) {
		$this->_addLogAttachment( 'added', $attachment_id );
	}
}
