<?php

// Do not allow the file to be called directly.
if (!defined('ABSPATH')) {
	exit;
}

update_option('patchstack_db_version', '3.0.2');

if ( get_option( 'patchstack_license_activated', 0 ) == 1 && get_option( 'patchstack_license_free', 0 ) == 0 ) {
    $this->header();
}