<?php

namespace Automattic\VIP\Helpers\WP_CLI_DB;

class VIP_DB_Command extends \DB_Command {
	/**
	 * mysql client options passed on by `wp db query`: output formats and `safe-updates`.
	 */
	private const ALLOWED_MYSQL_OPTIONS = [
		'batch',
		'binary-as-hex',
		'column-names',
		'column-type-info',
		'html',
		'raw',
		'safe-updates',
		'show-warnings',
		'silent',
		'skip-column-names',
		'table',
		'vertical',
		'xml',
	];

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Wrapper for WP-CLI's db query command to allow for certain flags to not get passed into the subcommand.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function query( $args, $assoc_args ) {
		unset( $assoc_args['read-write'], $assoc_args['skip-confirm'] );

		if ( ! $this->config->is_local() ) {
			// Pass only output options on to the mysql client.
			$assoc_args                      = array_intersect_key( $assoc_args, array_flip( self::ALLOWED_MYSQL_OPTIONS ) );
			$assoc_args['sandbox']           = true;
			$assoc_args['skip-local-infile'] = true;
		}

		parent::query( $args, $assoc_args );
	}
}
