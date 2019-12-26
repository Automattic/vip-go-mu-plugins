<?php

namespace Automattic\SharedPlugins\VIPElasticsearch\Adapters;

class ElasticPress implements Adapter {
	public function setup() {
		$this->setup_constants();
		$this->setup_filters();
	}

	public function setup_constants() {
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 250 );
		}
	}

	public function setup_filters() {
		add_filter( 'ep_index_name', [ $this, 'filter__es_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.
	}

	public function filter__es_index_name( $index_name, $blog_id, $indexables ) {
		// TODO: Use FILES_CLIENT_SITE_ID for now as VIP_GO_ENV_ID is not ready yet. Should replace once it is.
		$index_name = sprintf( 'vip-%s-%s', FILES_CLIENT_SITE_ID, $indexables->slug );

		// $blog_id won't be present on global indexes (such as users)
		if ( $blog_id ) {
			$index_name .= sprintf( '-%s', $blog_id );
		}

		return $index_name;
	}
}