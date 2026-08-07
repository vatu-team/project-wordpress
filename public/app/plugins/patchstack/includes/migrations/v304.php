<?php

// Try to create the mu-plugins folder/file.
// No need to do this if it already exists.
if ( file_exists( WPMU_PLUGIN_DIR . '/_patchstack.php' ) || ( defined( 'PS_DISABLE_MU' ) && PS_DISABLE_MU ) ) {
    update_option('patchstack_db_version', '3.0.4');
    return;
}

if ( file_exists( WPMU_PLUGIN_DIR . '/patchstack.php' )) {
    @rename( WPMU_PLUGIN_DIR . '/patchstack.php', WPMU_PLUGIN_DIR . '/_patchstack.php' );
}

update_option('patchstack_db_version', '3.0.4');