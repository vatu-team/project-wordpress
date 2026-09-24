<?php

/**
 * Plugin Name: Site Specific Configuration
 * Plugin URI: https://vatu.dev
 * Description: Site settings independent to the a theme or plugin.
 * Version: 1.0.0
 * Author: Vatu
 * Author URI: https://vatu.dev/
 * License: GPL3
 *
 * @package   Vatu/Wordpress/Plugin/SiteConfig
 * @copyright 2020-2024 Vatu Ltd.
 */

declare(strict_types=1);

/**
 * Disable indexing for development sites.
 */
if (
	defined( constant_name: 'WP_ENVIRONMENT_TYPE' )
	&& in_array( needle: WP_ENVIRONMENT_TYPE, haystack: [ 'local', 'development', 'qa', 'staging' ], strict: true )
	&& ! \has_filter( hook_name: 'pre_option_blog_public', callback: '__return_zero' )
) {
	\add_filter(
		hook_name: 'pre_option_blog_public',
		callback: '__return_zero'
	);
}

/**
 * Disable XML-RPC
 *
 * XML-RPC is not used in our environment and is a common attack vector.
 */
add_filter( hook_name: 'xmlrpc_enabled', callback: '__return_false' );

function vatu_remove_wordpress_generator_version(): string
{
	return '';
}

add_filter( hook_name: 'the_generator', callback: 'vatu_remove_wordpress_generator_version' );

/**
 * Disable current theme validation
 *
 * By default, WordPress falls back to a default theme if it can't find
 * the active theme. This is undesirable because it requires manually
 * re-activating the correct theme and can lead to data loss in the form
 * of deactivated widgets and menu location assignments.
 */
add_filter(
	hook_name: 'validate_current_theme',
	callback: '__return_false'
);

/**
 * Remove warning about site health statuses.
 *
 * @param array<string,array<string,string>> $tests
 * @return array<string,array<string,string>>
 */
function vatu_disable_site_health_tests( array $tests ): array
{
	unset( $tests['direct']['debug_enabled'] );
	unset( $tests['direct']['available_updates_disk_space'] );
	unset( $tests['async']['background_updates'] );
	return $tests;
}

add_filter(
	hook_name: 'site_status_tests',
	callback: 'vatu_disable_site_health_tests',
	priority: 10,
	accepted_args: 1
);

/**
 * Disable Admin notification of User password change.
 */
add_filter(
	hook_name: 'wp_password_change_notification_email',
	callback: '__return_false',
	priority: 10,
	accepted_args: 0
);

/**
 * Disable User notification of password change.
 */
add_filter(
	hook_name: 'send_password_change_email',
	callback: '__return_false',
	priority: 10,
	accepted_args: 0
);

/**
 * Set Content Security Policy header.
 */
function vatu_content_security_policy_header(): void
{
		$csp_header = [
			'default-src'               => apply_filters(
				'site-config.csp.default-src',
				[
					"'self'",
					'blob:',
					'https:',
					"'unsafe-inline'",
				]
			),
			'script-src'                => apply_filters(
				'site-config.csp.script-src',
				[
					"'self'",
					"'unsafe-inline'",
					"'unsafe-eval'",
					'blob:',
					'data:',
					'https://*.googletagmanager.com',
					'https://www.googletagmanager.com',
					'https://tagmanager.google.com',
					'https://*.cloudflareinsights.com',
					'https://static.cloudflareinsights.com',
					'https://cdn-cookieyes.com',
					'https://challenges.cloudflare.com',
					'https://www.google.com/recaptcha/',
					'https://www.gstatic.com/recaptcha/',
					'https://connect.facebook.net/',
					'https://snap.licdn.com',
				]
			),
			'style-src'                 => apply_filters(
				'site-config.csp.style-src',
				[
					"'self'",
					"'unsafe-inline'",
					'blob:',
					'data:',
					'https://googletagmanager.com',
					'https://tagmanager.google.com',
					'https://fonts.googleapis.com',
					'https://use.typekit.net',
					'https://p.typekit.net',
					'https://maxcdn.bootstrapcdn.com',
				]
			),
			'img-src'                   => apply_filters(
				'site-config.csp.img-src',
				[
					"'self'",
					'data:',
					'https://patterns.olliewp.com',
					'https://cdn.XXXXXXXXXXXX.com',
					'https://XXXXXXXXXXXX.cloudfront.net',
					'https://secure.gravatar.com',
					'www.googletagmanager.com',
					'https://googletagmanager.com',
					'https://ssl.gstatic.com',
					'https://www.gstatic.com',
					'https://*.google-analytics.com',
					'https://*.googletagmanager.com',
					'https://s.w.org',
					'https://i.ytimg.com',
					'https://*.ytimg.com',
					'https://*.tumblr.com',
					'https://cdn-cookieyes.com',
					'https://*.linkedin.com',
					'https://*.facebook.com',
					'https://*.googlesyndication.com',
				]
			),
			'manifest-src'              => apply_filters(
				'site-config.csp.manifest-src',
				[
					"'self'",
				]
			),
			'media-src'                 => apply_filters(
				'site-config.csp.media-src',
				[
					"'self'",
				]
			),
			'child-src'                 => apply_filters(
				'site-config.csp.child-src',
				[
					"'self'",
				]
			),
			'worker-src'                => apply_filters(
				'site-config.csp.worker-src',
				[
					"'self'",
					'blob:',
					'data:',
				]
			),
			'object-src'                => apply_filters(
				'site-config.csp.object-src',
				[
					"'none'",
				]
			),
			'frame-src'                 => apply_filters(
				'site-config.csp.frame-src',
				[
					"'self'",
					'https://challenges.cloudflare.com',
					'https://www.google.com/recaptcha/',
					'https://www.google.com/maps/',
					'https://recaptcha.google.com/recaptcha/',
					'https://www.youtube.com',
					'https://youtube.com',
					'https://www.youtube-nocookie.com',
				]
			),
			'connect-src'               => apply_filters(
				'site-config.csp.connect-src',
				[
					"'self'",
					'https://challenges.cloudflare.com',
					'www.googletagmanager.com',
					'www.google.com',
					'https://*.google-analytics.com',
					'https://*.analytics.google.com',
					'https://*.googletagmanager.com',
					'https://www.google.com/recaptcha/',
					'https://*.cookieyes.com',
					'cdn-cookieyes.com',
					'https://px.ads.linkedin.com',
					'https://*.contentsquare.net',
					'https://*.googlesyndication.com',
				]
			),
			'frame-ancestors'           => apply_filters(
				'site-config.csp.frame-ancestors',
				[
					"'self'",
				]
			),
			'upgrade-insecure-requests' => apply_filters(
				'site-config.csp.upgrade-insecure-requests',
				[]
			),
			'font-src' => apply_filters(
				'site-config.csp.font-src',
				[
					"'self'",
					'data:',
					'https:',
				]
			),
		];

		$header = 'Content-Security-Policy:';

		foreach ( $csp_header as $type => $value ) {
			$header .= ' ' . $type . ' ' . implode( ' ', $value ) . ';';
		}

		header( $header );
}

add_action(
	hook_name: 'send_headers',
	callback: 'vatu_content_security_policy_header',
	priority: 10,
	accepted_args: 0
);

/**
 * Set Referrer header.
 */
function vatu_referrer_policy_header(): void
{
	header( 'Referrer-Policy: origin-when-cross-origin, strict-origin-when-cross-origin' );
}

add_action(
	hook_name: 'send_headers',
	callback: 'vatu_referrer_policy_header',
	priority: 10,
	accepted_args: 0
);

/**
 * Set Permissions Policy header.
 */
function vatu_permission_policy_header(): void
{
	header(
		header: 'Permissions-Policy: accelerometer=(),autoplay=(),camera=(),display-capture=(),encrypted-media=(),fullscreen=(),geolocation=(),gyroscope=(),magnetometer=(),microphone=(),midi=(),payment=(),picture-in-picture=(),publickey-credentials-get=(),screen-wake-lock=(),sync-xhr=(self),usb=(),web-share=(),xr-spatial-tracking=()'
	);
}

add_action(
	hook_name: 'send_headers',
	callback: 'vatu_permission_policy_header',
	priority: 10,
	accepted_args: 0
);

/**
 * Fixes: AWS SDK looking for shared config files which do not exist in our environment.
 *
 * @param array<string,mixed> $params
 * @return array<string,mixed>
 */
function vatu_remove_aws_shared_config_lookup( array $params ): array
{
	$params['use_aws_shared_config_files'] = false;
	return $params;
}

add_filter(
	hook_name: 's3_uploads_s3_client_params',
	callback: 'vatu_remove_aws_shared_config_lookup',
	priority: 10,
	accepted_args: 1
);
