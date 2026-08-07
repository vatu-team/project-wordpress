<?php

// Do not allow the file to be called directly.
if (!defined('ABSPATH')) {
	exit;
}

update_option('patchstack_db_version', '3.0.0');

// Migrate all current options to the new prefix.
$exists = $wpdb->get_var( "SELECT COUNT(*) FROM " . $prefix . "options WHERE option_name = 'webarx_api_token'" );
if ( !is_null( $exists ) && $exists >= 1 ) {
	$wpdb->query( 'INSERT IGNORE INTO ' . $prefix . "options (option_name, option_value, autoload) SELECT REPLACE(option_name, 'webarx_', 'patchstack_') as option_name, option_value, autoload FROM " . $prefix . "options WHERE option_name like 'webarx_%'" );
	$wpdb->query( 'UPDATE ' . $prefix . 'options AS a SET option_value = (SELECT option_value FROM ' . $prefix . "options WHERE option_name = REPLACE(a.option_name, 'patchstack_', 'webarx_')) WHERE option_name LIKE 'patchstack_%'" );
}
