<?php

/**
 * Project Configuration.
 *
 * @package   Vatu\Wordpress\Config
 * @author    Vatu <hello@vatu.dev>
 * @link      https://vatu.dev/
 * @license   GNU General Public License v3.0
 * @copyright 2023-2024 Vatu Limited.
 *
 * Your base production configuration goes in this file. Environment-specific
 * overrides go in their respective config/environments/{WP_ENVIRONMENT_TYPE}.php file.
 */

declare(strict_types=1);

use Dotenv\Dotenv;
use Roots\WPConfig\Config;

/**
 * Directory containing all of the site's files
 *
 * @var string $root_dir
 */
$root_dir = dirname( path: __DIR__ );

/**
 * Document Root
 *
 * @var string $webroot_dir
 */
$webroot_dir = "{$root_dir}/public";

/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
$dotenv = Dotenv::createUnsafeImmutable( paths: $root_dir );

if ( file_exists( filename: "{$root_dir}/.env" ) ) {
	$dotenv->load();
	$dotenv->required( variables: [ 'WP_HOME', 'WP_SITEURL' ] );

	if ( ! getenv( name: 'DATABASE_URL' ) ) {
		$dotenv->required( variables: [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD' ] );
	}
}

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define( constant_name: 'WP_ENV', value: getenv( name: 'WP_ENV' ) ?: 'production' );
define( constant_name: 'WP_ENVIRONMENT_TYPE', value: getenv( name: 'WP_ENVIRONMENT_TYPE' ) ?: 'production' );
define( constant_name: 'WP_DEVELOPMENT_MODE', value: getenv( name: 'WP_DEVELOPMENT_MODE' ) ?: null );

/**
 * URLs
 */
Config::define( key: 'WP_HOME', value: getenv( name: 'WP_HOME' ) );
Config::define( key: 'WP_SITEURL', value: getenv( name: 'WP_SITEURL' ) );

/**
 * Custom Content Directory
 */
Config::define( key: 'CONTENT_DIR', value: '/app' );
Config::define( key: 'WP_CONTENT_DIR', value: $webroot_dir . Config::get( key: 'CONTENT_DIR' ) );
Config::define( key: 'WP_CONTENT_URL', value: Config::get( key: 'WP_HOME' ) . Config::get( key: 'CONTENT_DIR' ) );

/**
 * DB settings
 */
Config::define( key: 'DB_NAME', value: getenv( name: 'DB_NAME' ) );
Config::define( key: 'DB_USER', value: getenv( name: 'DB_USER' ) );
Config::define( key: 'DB_PASSWORD', value: getenv( name: 'DB_PASSWORD' ) );
Config::define( key: 'DB_HOST', value: getenv( name: 'DB_HOST' ) ?: 'localhost' );
Config::define( key: 'DB_CHARSET', value: 'utf8mb4' );
Config::define( key: 'DB_COLLATE', value: '' );

// phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
$table_prefix = getenv( name: 'DB_PREFIX' ) ?: 'wp_';

if ( getenv( name: 'DATABASE_URL' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$dsn = (object) parse_url( url: getenv( name: 'DATABASE_URL' ) );

	Config::define( key: 'DB_NAME', value: substr( string: $dsn->path, offset: 1 ) );
	Config::define( key: 'DB_USER', value: $dsn->user );
	Config::define( key: 'DB_PASSWORD', value: $dsn->pass ?? null );
	Config::define( key: 'DB_HOST', value: isset( $dsn->port ) ? "{$dsn->host}:{$dsn->port}" : $dsn->host );
}

/**
 * Authentication Unique Keys and Salts
 */
Config::define( key: 'AUTH_KEY', value: getenv( name: 'AUTH_KEY' ) );
Config::define( key: 'SECURE_AUTH_KEY', value: getenv( name: 'SECURE_AUTH_KEY' ) );
Config::define( key: 'LOGGED_IN_KEY', value: getenv( name: 'LOGGED_IN_KEY' ) );
Config::define( key: 'NONCE_KEY', value: getenv( name: 'NONCE_KEY' ) );
Config::define( key: 'AUTH_SALT', value: getenv( name: 'AUTH_SALT' ) );
Config::define( key: 'SECURE_AUTH_SALT', value: getenv( name: 'SECURE_AUTH_SALT' ) );
Config::define( key: 'LOGGED_IN_SALT', value: getenv( name: 'LOGGED_IN_SALT' ) );
Config::define( key: 'NONCE_SALT', value: getenv( name: 'NONCE_SALT' ) );

/**
 * Custom Settings
 */
Config::define( key: 'AUTOMATIC_UPDATER_DISABLED', value: true );
Config::define( key: 'DISABLE_WP_CRON', value: getenv( name: 'DISABLE_WP_CRON' ) ?: false );
// Disable the plugin and theme file editor in the admin.
Config::define( key: 'DISALLOW_FILE_EDIT', value: true );
// Disable plugin and theme updates and installation from the admin.
Config::define( key: 'DISALLOW_FILE_MODS', value: true );
// Limit the number of post revisions that WordPress stores (true (default WP): store every revision).
Config::define( key: 'WP_POST_REVISIONS', value: getenv( 'WP_POST_REVISIONS' ) ?: true );
Config::define( key: 'WP_DEFAULT_THEME', value: getenv( 'WP_DEFAULT_THEME' ) ?: null );

/**
 * Debugging Settings
 */
Config::define( key: 'WP_DEBUG', value: getenv( name: 'WP_DEBUG' ) ?: false );
Config::define( key: 'WP_DEBUG_DISPLAY', value: getenv( name: 'WP_DEBUG' ) ?: false );
Config::define( key: 'WP_DEBUG_LOG', value: getenv( name: 'WP_DEBUG_LOG' ) ?: false );
Config::define( key: 'SCRIPT_DEBUG', value: false );
ini_set( option: 'display_errors', value: '0' );

/**
 * Use X-Forwarded-For HTTP Header to Get Visitor's Real IP Address
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
	$http_x_headers         = explode( separator: ',', string: $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$_SERVER['REMOTE_ADDR'] = $http_x_headers[0];
}

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__ . '/environments/' . WP_ENVIRONMENT_TYPE . '.php';

if ( file_exists( filename: $env_config ) ) {
	require_once $env_config;
}

/**
 * Multisite
 */
Config::define( key: 'WP_ALLOW_MULTISITE', value: getenv( name: 'WP_ALLOW_MULTISITE' ) ?: false );
// Config::define( key: 'MULTISITE', value: getenv( name: 'MULTISITE' ) ?: false );

if ( getenv( name: 'MULTISITE' ) === true ) {
	Config::define( key: 'SUBDOMAIN_INSTALL', value: true );
	Config::define( key: 'DOMAIN_CURRENT_SITE', value: getenv( name: 'WP_DOMAIN_CURRENT_SITE' ) );
	Config::define( key: 'PATH_CURRENT_SITE', value: '/' );
	Config::define( key: 'SITE_ID_CURRENT_SITE', value: 1 );
	Config::define( key: 'BLOG_ID_CURRENT_SITE', value: 1 );
	Config::define( key: 'COOKIE_DOMAIN', value: getenv( name: 'COOKIE_DOMAIN' ) ?: null );
}

/*
 * S3 Uploads
 */
Config::define( key: 'S3_UPLOADS_BUCKET', value: getenv( name: 'S3_UPLOADS_BUCKET' ) ?: null );
Config::define( key: 'S3_UPLOADS_REGION', value: getenv( name: 'S3_UPLOADS_REGION' ) ?: null );
Config::define( key: 'S3_UPLOADS_KEY', value: getenv( name: 'S3_UPLOADS_KEY' ) ?: null );
Config::define( key: 'S3_UPLOADS_SECRET', value: getenv( name: 'S3_UPLOADS_SECRET' ) ?: null );
Config::define( key: 'S3_UPLOADS_BUCKET_URL', value: getenv( name: 'S3_UPLOADS_BUCKET_URL' ) ?: null );
Config::define( key: 'S3_UPLOADS_USE_LOCAL', value: getenv( name: 'S3_UPLOADS_USE_LOCAL' ) ?: null );
Config::define( key: 'S3_UPLOADS_OBJECT_ACL', value: getenv( name: 'S3_UPLOADS_OBJECT_ACL' ) ?: null );

/**
 * Google Tag Manager
 */
Config::define(
	key: 'GOOGLE_TAG_MANAGER_CONTAINER_ID',
	value: getenv( name: 'GOOGLE_TAG_MANAGER_CONTAINER_ID' ) ?: null
);

Config::apply();

/**
 * Bootstrap WordPress
 */
if ( ! defined( constant_name: 'ABSPATH' ) ) {
	define( constant_name: 'ABSPATH', value: "{$webroot_dir}/wp/" );
}
