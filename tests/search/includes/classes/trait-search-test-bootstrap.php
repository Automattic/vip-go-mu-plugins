<?php

namespace Automattic\VIP\Search;

use Automattic\Test\Constant_Mocker;
use ElasticPress\Indexable\User\User;
use ElasticPress\Indexables;

require_once __DIR__ . '/../../../../search/search.php';

/**
 * Boots a fresh, non-singleton Search instance for a test.
 *
 * Constant_Mocker is cleared after every test by VIP_Test_Listener.
 */
trait Search_Test_Bootstrap {
	/**
	 * @param array $constants     Constants to mock before Search::init(), keyed by name.
	 * @param bool  $register_user Also register the User indexable, which EP doesn't register by default.
	 */
	protected function boot_search( array $constants = [ 'VIP_ELASTICSEARCH_ENDPOINTS' => [ 'https://elasticsearch:9200' ] ], bool $register_user = false ): Search {
		Constant_Mocker::clear();
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$search = new Search();
		$search->init();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		if ( $register_user ) {
			Indexables::factory()->register( new User() );
		}

		return $search;
	}

	/**
	 * Create the queue table as a real table. The WP test library makes tables created during a test temporary, and
	 * MySQL can't open a temporary table twice in one query, as the queue's self-joins do. Call it from
	 * wpSetUpBeforeClass(), outside any test's transaction, as the DDL would commit what the test wrote before it.
	 */
	protected static function create_queue_table(): void {
		global $wpdb;

		// Search isn't booted yet, so the class isn't loaded.
		require_once __DIR__ . '/../../../../search/includes/classes/queue/class-schema.php';

		$schema = new class() extends Queue\Schema {
			public function create(): void {
				$this->_prepare_table();
			}
		};

		// A temporary table left by an earlier test class would hide the real one.
		$wpdb->query( $wpdb->prepare( 'DROP TEMPORARY TABLE IF EXISTS %i', $schema->get_table_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$schema->create();
	}

	protected static function drop_queue_table(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', ( new Queue\Schema() )->get_table_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
