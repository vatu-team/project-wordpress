<?php

// Try to create the mu-plugins folder/file.
// No need to do this if it already exists.
if ( file_exists( WPMU_PLUGIN_DIR . '/patchstack.php' ) || file_exists( WPMU_PLUGIN_DIR . '/_patchstack.php' )) {
    update_option('patchstack_db_version', '3.0.3');
    return;
}

// The mu-plugin does not exist, try to create it.
@include_once ABSPATH . 'wp-admin/includes/file.php';
$wpfs = WP_Filesystem();

// Failed to initialize WP_Filesystem.
if ( ! $wpfs ) {
    update_option('patchstack_db_version', '3.0.3');
    return;
}

// Try to create the mu-plugins folder.
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
    wp_mkdir_p( WPMU_PLUGIN_DIR );
}

// Failed to create the mu-plugin folder.
if ( ! is_dir( WPMU_PLUGIN_DIR ) || ( defined( 'PS_DISABLE_MU' ) && PS_DISABLE_MU ) ) {
    update_option('patchstack_db_version', '3.0.3');
    return;
}

// Create the mu-plugin file in the folder.
if ( is_writable( WPMU_PLUGIN_DIR ) ) {
    $php = file_get_contents( trailingslashit( plugin_dir_path( __FILE__ ) ) . '../mu-plugin.php' );
    file_put_contents( trailingslashit( WPMU_PLUGIN_DIR ) . '_patchstack.php', $php );
}

update_option('patchstack_db_version', '3.0.3');