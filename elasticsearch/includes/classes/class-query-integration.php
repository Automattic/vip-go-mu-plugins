<?php

namespace Automattic\VIP\Elasticsearch;

class Query_Integration {

	function __construct() {
		add_filter( 'ep_skip_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );
		add_filter( 'ep_skip_user_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );
	}

	/**
	 * Separate plugin enabled and querying the index
	 *
	 * The index can be tested at any time by setting an `es` query argument.
	 * When we're ready to use the index in production, the `vip_enable_elasticsearch`
	 * option will be set to `true`, which will enable querying for everyone.
	 */
	function ep_skip_query_integration( $skip ) {
		if ( isset( $_GET[ 'es' ] ) ) {
			return false;
		}

		/**
		 * Honor filters that skip query integration
		 *
		 * It may be desirable to skip query integration for specific
		 * queries. We should honor those other filters. Since this
		 * defaults to false, it will only kick in if someone specifically
		 * wants to bypass ES in addition to what we're doing here.
		 */
		if ( $skip ) {
			return true;
		}

		return ! ( defined( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION' )
			&& true === VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION );
	}
}

new Query_Integration;
