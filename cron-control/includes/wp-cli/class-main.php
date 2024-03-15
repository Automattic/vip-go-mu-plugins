<?php
/**
 * Top-level CLI command
 *
 * Mostly exists for WP-CLI to provide better documentation
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control\CLI;
use WP_CLI;

/**
 * Manage Cron Control, including its data store, caches, and locks
 */
class Main extends \WP_CLI_Command {}
WP_CLI::add_command( 'cron-control', 'Automattic\WP\Cron_Control\CLI\Main' );
