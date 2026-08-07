<?php

/**
 * Environment config: Development
 *
 * Configuration overrides for WP_ENVIRONMENT_TYPE === 'development'.
 * Note: Use this as a starting point by copying it as `local.php`. This will stop conflicts between developers.
 *
 * @package   Vatu\Wordpress\Config
 * @author    Vatu <hello@vatu.dev>
 * @link      https://vatu.dev/
 * @license   GNU General Public License v3.0
 * @copyright 2023-2024 Vatu Limited.
 */

declare(strict_types=1);

use Roots\WPConfig\Config;

Config::define( 'SAVEQUERIES', true );
Config::define( 'WP_DEBUG', true );
Config::define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );

/**
 * Query Monitor
 */
Config::define( 'QM_DARK_MODE', true );
Config::define( 'QM_ENABLE_CAPS_PANEL', true );
