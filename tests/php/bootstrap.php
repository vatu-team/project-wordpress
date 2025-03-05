<?php

/**
 * PHPUnit bootstrap file.
 *
 * @package   Vatu\Wordpress\Tests
 * @author    Vatu <hello@vatu.dev>
 * @link      https://vatu.dev/
 * @license   GNU General Public License v3.0
 * @copyright 2022-2025 Vatu Ltd.
 */

declare(strict_types=1);

$root_dir = dirname( path: __DIR__, levels: 2 );

require_once "{$root_dir}/tools/vendor/autoload.php";
require_once "{$root_dir}/vendor/autoload.php";
require_once "{$root_dir}/tests/php/TestCase.php";
