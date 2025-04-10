<?php

namespace Automattic\VIP\Search\Commands;

use WP_CLI;

require_once __DIR__ . '/../../../../vip-helpers/vip-wp-cli.php';

/**
 * Commands to view and manage individual documents
 *
 * @package Automattic\VIP\Search
 */
class DocumentCommand extends \WPCOM_VIP_CLI_Command {
	/**
	 * Get details about a document by type and an id
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * <object_id>
	 * : The ID of the object
	 *
	 * [--format=<string>]
	 * : Optional one of: table json csv yaml ids count
	 *
	 * ## EXAMPLES
	 *     wp vip-search documents get post 2
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc_args ) {
		$type      = $args[0];
		$object_id = $args[1];

		\Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return WP_CLI::error( sprintf( 'Indexable %s not found. Is the feature active?', $type ) );
		}

		$document = $indexable->get( $object_id );

		if ( ! $document ) {
			return WP_CLI::error( sprintf( 'Document with ID %s of type %s was not found.', $object_id, $type ) );
		}

		$keys = array_keys( $document );
		\WP_CLI\Utils\format_items( $assoc_args['format'], array( $document ), $keys );
	}
}
