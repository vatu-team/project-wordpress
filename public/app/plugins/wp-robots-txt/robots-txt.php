<?php
/**
 * WP Robots Txt
 *
 * @category    WordPress
 * @package     WPRobotsTxt
 * @author      George Pattichis
 * @copyright   2022 George Pattichis
 * @license     http://opensource.org/licenses/GPL-2.0 GPL-2.0+
 * @link        https://profiles.wordpress.org/pattihis/
 *
 * Plugin Name: WP Robots Txt
 * Plugin URI: https://github.com/pattihis/wp-robots.txt
 * Description: Edit your robots.txt file from the WordPress admin
 * Version: 1.3.5
 * Requires at least: 5.3.0
 * Tested up to: 6.8
 * Requires PHP: 7.0
 * Author: George Pattichis
 * Author URI: https://profiles.wordpress.org/pattihis/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-robots-txt
 */

/**
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
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current plugin version.
 */
define( 'WP_ROBOTS_TXT_VERSION', '1.3.5' );

define( 'WP_ROBOTS_TXT_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin's basename
 */
define( 'WP_ROBOTS_TXT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The core plugin file that is used to run our functionality.
 */
require_once WP_ROBOTS_TXT_DIR . 'inc/core-functionality.php';

/**
 * The core plugin class that is used to define admin options and hooks.
 */
if ( is_admin() ) {
	require_once WP_ROBOTS_TXT_DIR . 'inc/class-robtxt-admin-page.php';
	ROBTXT_Admin_Page::init();
}

/**
 * The main hook that filters the contents of the generated file.
 */
add_filter( 'robots_txt', 'robtxt_filter_robots', 10, 2 );

/**
 * The code that runs during plugin activation.
 */
register_activation_hook( __FILE__, 'robtxt_activation' );

/**
 * The code that runs during plugin deactivation.
 */
register_deactivation_hook( __FILE__, 'robtxt_deactivation' );
