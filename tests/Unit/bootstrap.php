<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely\Tests\Unit;

/**
 * Require BrainMonkey files and autoload the plugin code.
 */
require_once dirname( __DIR__ ) . '/../vendor/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';
require_once dirname( __DIR__ ) . '/../vendor/autoload.php';
