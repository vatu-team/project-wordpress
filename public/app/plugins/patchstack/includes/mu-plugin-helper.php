<?php

/**
 * Based on the current path and domain name, determine which option index we should use.
 * 
 * @param array $options
 * @return integer
 */
function patchstack_get_active_domain( $options )
{
    // Essential server variables must be set.
    if ( ! isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
        return $options[0];
    }

    // Give priority to home_url, then site_url.
    foreach ( [ 'home_url', 'site_url'] as $base ) {
        // Attempt to match against substring match.
        foreach ( $options as $option ) {
            // Only needed if the site is under a different folder path.
            if ( ! isset( $option[$base] ) || strpos( $option[$base], '/' ) === false ) {
                continue;
            }

            // Make sure there's a substring match.
            if ( substr( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 0, strlen( $option[$base] ) ) == $option[$base] ) {
                return $option;
            }
        }

        // Attempt to match against just the hostname.
        foreach ( $options as $option ) {
            if ( isset( $option[$base] ) && strpos( $option[$base], '/' ) === false && $_SERVER['HTTP_HOST'] == $option[$base]) {
                return $option;
            }
        }
    }

    // Return first options set by default.
    return $options[0];
}

/**
 * Given the option values, determine if the WAF should run.
 * 
 * @param array $options
 * @return boolean
 */
function patchstack_get_should_run( $options )
{
    // A stale config from an older version may be missing keys; treat anything
    // absent as "do not run" and avoid undefined-key warnings before WordPress loads.
    if ( ! isset( $options['patchstack_license_activated'] ) || $options['patchstack_license_activated'] == 0 ) {
        return false;
    }

    if ( ! isset( $options['patchstack_basic_firewall'] ) || $options['patchstack_basic_firewall'] != 1 ) {
        return false;
    }

    if ( ! isset( $options['patchstack_license_free'] ) || $options['patchstack_license_free'] == 1 ) {
        return false;
    }

    if ( ! isset( $options['patchstack_firewall_rules_v3_ap'] ) ) {
        return false;
    }

    $rules = base64_decode( $options['patchstack_firewall_rules_v3_ap'] );
    if ( $rules == '' || $rules == '[]' ) {
        return false;
    }

    return true;
}
