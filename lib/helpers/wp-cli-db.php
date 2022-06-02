<?php

/**
 * WP-CLI `wp db` Command Helper
 * Add necessary configuration details prior to handing off control to the wp-cli/db-command.
 * **IMPORTANT:** Everything in this directory runs **before** WordPress loads, so no wp-specific functions may be used (actions, filters, etc.)
 */

namespace Automattic\VIP\Helpers\WP_CLI_DB;

require_once __DIR__ . '/wp-cli-db/class-config.php';
require_once __DIR__ . '/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/wp-cli-db/class-wp-cli-db.php';

( new Wp_Cli_Db( new Config() ) )->early_init();
