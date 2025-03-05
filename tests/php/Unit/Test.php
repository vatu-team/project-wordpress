<?php

/**
 * Test: Suite is running.
 *
 * @package   Vatu\Wordpress\Tests
 * @author    Vatu <hello@vatu.dev>
 * @link      https://vatu.dev/
 * @license   GNU General Public License v3.0
 * @copyright 2022-2025 Vatu Ltd.
 */

declare(strict_types=1);

namespace Vatu\Wordpress\Tests;

class Test extends TestCase
{
	#[CoversNothing]
	public function testSuiteIsRunning(): void
	{
		self::assertTrue( true );
	}
}
