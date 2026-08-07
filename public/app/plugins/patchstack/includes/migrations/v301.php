<?php

// Do not allow the file to be called directly.
if (!defined('ABSPATH')) {
	exit;
}

update_option('patchstack_db_version', '3.0.1');

// Get the last id.
$lastid_firewall = get_option( 'patchstack_firewall_log_lastid', 0 );
$lastid_event = get_option( 'patchstack_eventlog_lastid', 0 );

// Get the current first id.
$first_firewall = $wpdb->get_var( 'SELECT id FROM ' . $prefix . 'patchstack_firewall_log ORDER BY id ASC LIMIT 0, 1' );
$first_event = $wpdb->get_var( 'SELECT id FROM ' . $prefix . 'patchstack_event_log ORDER BY id ASC LIMIT 0, 1' );

// Get the current last id.
$last_firewall = $wpdb->get_var( 'SELECT id FROM ' . $prefix . 'patchstack_firewall_log ORDER BY id DESC LIMIT 0, 1' );
$last_event = $wpdb->get_var( 'SELECT id FROM ' . $prefix . 'patchstack_event_log ORDER BY id DESC LIMIT 0, 1' );

// Update the lastid value of the firewall log.
if ( ! is_null( $first_firewall ) && ! is_null( $last_firewall ) ) {
	if ( (int) $lastid_firewall > (int) $last_firewall ) {
		update_option( 'patchstack_firewall_log_lastid', ( (int) $first_firewall ) - 1 );
	}
}

// Update the lastid value of the activity log.
if ( ! is_null( $first_event ) && ! is_null( $last_event ) ) {
	if ( (int) $lastid_event > (int) $last_event ) {
		update_option( 'patchstack_eventlog_lastid', ( (int) $first_event ) - 1 );
	}
}
