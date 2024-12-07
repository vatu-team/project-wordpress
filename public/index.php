<?php

/**
 * WordPress bootstrap file.
 *
 * @package   Vatu\Wordpress\Core
 * @author    Vatu <hello@vatu.dev>
 * @link      https://vatu.dev/
 * @license   GNU General Public License v3.0
 * @copyright 2022-2024 Vatu Ltd.
 */

define( constant_name: 'WP_USE_THEMES', value: true );
require __DIR__ . '/wp/wp-blog-header.php';
