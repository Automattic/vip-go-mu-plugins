window.indexNames = null;

before(() => {
	cy.wpCliEval(
		`
		// Clear any stuck sync process.
		delete_transient( 'ep_wpcli_sync' );
		delete_transient( 'ep_cli_sync_progress' );
		delete_transient( 'ep_wpcli_sync_interrupted' );

		$features = json_decode( '${JSON.stringify(cy.elasticPress.defaultFeatures)}', true );

		if ( ! \\ElasticPress\\Utils\\is_epio() ) {
			$host            = \\ElasticPress\\Utils\\get_host();
			$host            = str_replace( '172.17.0.1', 'localhost', $host );
			$index_name      = \\ElasticPress\\Indexables::factory()->get( 'post' )->get_index_name();
			$as_endpoint_url = $host . $index_name . '/_search';

			$features['autosuggest']['endpoint_url'] = $as_endpoint_url;
		}

		update_option( 'ep_feature_settings', $features );

		WP_CLI::runcommand('vip-search get-indexes');
		`,
	).then((wpCliResponse) => {
		window.indexNames = JSON.parse(wpCliResponse.stdout);
	});
});
