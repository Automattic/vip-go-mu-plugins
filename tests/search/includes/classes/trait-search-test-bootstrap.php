<?php

namespace Automattic\VIP\Search;

use Automattic\Test\Constant_Mocker;
use ElasticPress\Indexable\User\User;
use ElasticPress\Indexables;

require_once __DIR__ . '/../../../../search/search.php';

/**
 * Boots a fresh, non-singleton Search instance for a test.
 *
 * The caller still clears Constant_Mocker in tearDown().
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
}
