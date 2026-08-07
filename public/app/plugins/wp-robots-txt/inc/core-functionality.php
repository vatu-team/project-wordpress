<?php
/**
 * WP Robots Txt
 *
 * Copyright 2013  George Pattichis (gpattihis@gmail.com)
 *
 * "WP Robots Txt" is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * "WP Robots Txt" is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * "along with WP Robots Txt". If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
 *
 * @category    WordPress
 * @package     WPRobotsTxt
 * @author      George Pattichis
 * @copyright   2013 George Pattichis
 * @license     http://opensource.org/licenses/GPL-2.0 GPL-2.0+
 */

/**
 * Dynamically create the robots.txt file with our saved content.
 *
 * @since   1.2
 * @uses    get_option
 * @uses    esc_attr
 * @param string $output The contents of robots.txt filtered.
 * @param string $public The visibility option.
 * @return  string
 */
function robtxt_filter_robots( $output, $public ) {
	$content = get_option( 'robtxt_content' );
	if ( $content ) {
		$output = esc_attr( wp_strip_all_tags( $content ) ) . PHP_EOL;
	}

	return $output;
}

/**
 * Deactivation hook. Deletes our option containing the robots.txt content.
 *
 * @since   1.2
 * @uses    delete_option
 * @return  void
 */
function robtxt_deactivation() {
	delete_option( 'robtxt_content' );
}

/**
 * Activation hook.  Adds the option we'll be using.
 *
 * @since   1.2
 * @uses    add_option
 * @return  void
 */
function robtxt_activation() {
	add_option( 'robtxt_content', false );

	// Backwards compatibility.
	$old = get_option( 'cd_rdte_content' );
	if ( false !== $old ) {
		update_option( 'robtxt_content', $old );
		delete_option( 'cd_rdte_content' );
	}
}
