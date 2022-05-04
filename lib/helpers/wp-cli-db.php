<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

require_once __DIR__ . '/wp-cli-db/class-config.php';
require_once __DIR__ . '/wp-cli-db/class-db-server.php';
require_once __DIR__ . '/wp-cli-db/class-wp-cli-db.php';

( new Wp_Cli_Db( new Config() ) )->early_init();
