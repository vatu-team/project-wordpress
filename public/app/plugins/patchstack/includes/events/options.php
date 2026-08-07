<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any option change related action.
 */
class P_Event_Options extends P_Event_Log {

	/**
	 * Hook into the action so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'updated_option', [ &$this, 'updatedOption' ], 10, 3 );
	}

	/**
	 * When an option is updated, determine which option is updated and log the action.
	 *
	 * @param string $option
	 * @param string $old_value
	 * @param string $new_value
	 * @return void
	 */
	public function updatedOption( $option, $old_value, $new_value ) {
		// No need to log if the values are the same.
		if ( $old_value === $new_value ) {
			return;
		}

		// If either options are an array for some reason, convert to a JSON object.
		if ( is_array( $old_value ) ) {
			$old_value = json_encode( $old_value );
		}
		if ( is_array( $new_value ) ) {
			$new_value = json_encode ( $new_value );
		}

		// Options that will be logged.
		if ( in_array(
			$option,
			[
				// General
				'blogname',
				'blogdescription',
				'siteurl',
				'home',
				'admin_email',
				'users_can_register',
				'default_role',
				'timezone_string',
				'date_format',
				'time_format',
				'start_of_week',

				// Writing
				'use_smilies',
				'use_balanceTags',
				'default_category',
				'default_post_format',
				'mailserver_url',
				'mailserver_login',
				'mailserver_pass',
				'default_email_category',
				'ping_sites',

				// Reading
				'show_on_front',
				'page_on_front',
				'page_for_posts',
				'posts_per_page',
				'posts_per_rss',
				'rss_use_excerpt',
				'blog_public',

				// Discussion
				'default_pingback_flag',
				'default_ping_status',
				'default_comment_status',
				'require_name_email',
				'comment_registration',
				'close_comments_for_old_posts',
				'close_comments_days_old',
				'thread_comments',
				'thread_comments_depth',
				'page_comments',
				'comments_per_page',
				'default_comments_page',
				'comment_order',
				'comments_notify',
				'moderation_notify',
				'comment_moderation',
				'comment_whitelist',
				'comment_max_links',
				'moderation_keys',
				'blacklist_keys',
				'show_avatars',
				'avatar_rating',
				'avatar_default',

				// Media
				'thumbnail_size_w',
				'thumbnail_size_h',
				'thumbnail_crop',
				'medium_size_w',
				'medium_size_h',
				'large_size_w',
				'large_size_h',
				'uploads_use_yearmonth_folders',

				// Permalinks
				'permalink_structure',
				'category_base',
				'tag_base',

				// Widgets
				'sidebars_widgets',
			]
		) ) {
			$this->insert(
				[
					'action'      => 'updated',
					'object'      => 'option',
					'object_name' => $option . ' from "' . $old_value . '" to "' . $new_value . '"',
				]
			);
		}
	}

}
