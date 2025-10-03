<?php

class QM_Collector_VIP extends QM_Collector {

	/**
	 * @var string
	 */
	public $id = 'vip';

	/**
	 * @return string
	 */
	public function name() {
		return esc_html__( 'VIP', 'query-monitor' );
	}

	/**
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_VIP();
	}

	/**
	 * @return void
	 */
	public function process() {
		$this->process_version_file();

		$this->process_app();
	}

	private function process_version_file() {
		$version_file = WPVIP_MU_PLUGIN_DIR . '/.version';
		if ( ! file_exists( $version_file ) ) {
			$this->set_default_version_info();
			return;
		}
		$version = file_get_contents( $version_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( ! $version ) {
			$this->set_default_version_info();
			return;
		}

		$info          = json_decode( $version );
		$branch        = $info->tag ?? '';
		$stack_version = $info->stack_version ?? null;
		$version_info  = $stack_version ? explode( '-', $stack_version ) : [];
		$date          = isset( $version_info[0] ) ? gmdate( 'F j, Y', strtotime( $version_info[0] ) ) : null;
		$commit        = $version_info[1] ?? null;

		$this->data['mu_plugins'] = [
			'branch' => $branch,
			'commit' => $commit,
			'date'   => $date,
		];
	}

	private function process_app() {
		$env = constant( 'VIP_GO_APP_ENVIRONMENT' );

		if ( 'local' !== $env ) {
			if ( defined( 'VIP_GO_APP_ID' ) ) {
				$this->data->app['id'] = constant( 'VIP_GO_APP_ID' );
			}
			if ( defined( 'VIP_GO_APP_NAME' ) ) {
				$this->data->app['name'] = constant( 'VIP_GO_APP_NAME' );
			}
			$commit = getenv( 'VIP_GO_APP_CURRENT_COMMIT_HASH' );
			if ( $commit ) {
				$this->data->app['commit'] = $commit;
			}
			if ( defined( 'VIP_GO_APP_BRANCH' ) ) {
				$this->data->app['branch'] = constant( 'VIP_GO_APP_BRANCH' );
			}

			if ( is_automattician() && defined( 'VIP_IS_FEDRAMP' ) ) {
				$this->data->app['fedramp'] = constant( 'VIP_IS_FEDRAMP' );
			}
		}

		if ( defined( 'JETPACK__VERSION' ) ) {
			$this->data->app['jetpack'] = constant( 'JETPACK__VERSION' );
		}

		if ( defined( 'VIP_ENABLE_VIP_SEARCH' ) && true === constant( 'VIP_ENABLE_VIP_SEARCH' ) && method_exists( '\ElasticPress\Elasticsearch', 'get_elasticsearch_version' ) ) {
			$this->data->app['es_version'] = $this->get_elasticsearch_version_with_migration_context();
		}
	}

	/**
	 * Get Elasticsearch version with ES7/ES8 migration context
	 *
	 * @return string ES version information with migration context
	 */
	private function get_elasticsearch_version_with_migration_context() {
		// Get the base ES version
		$base_version = \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version() ?: 'Unknown';

		$migration_in_progress = defined( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS' ) && constant( 'VIP_ELASTICSEARCH_MIGRATION_IN_PROGRESS' );

		if ( ! $migration_in_progress ) {
			return $base_version;
		}

		$is_testing_next = false;

		if ( defined( 'VIP_ELASTICSEARCH_TEST_ES_NEXT' ) && constant( 'VIP_ELASTICSEARCH_TEST_ES_NEXT' ) ) {
			$is_testing_next = true;
		} elseif ( current_user_can( 'manage_options' ) ) {
			// Check for query parameter
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['vip-search-test-es-next'] ) &&
				( 'true' === $_GET['vip-search-test-es-next'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					|| '1' === $_GET['vip-search-test-es-next'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					|| 'session' === $_GET['vip-search-test-es-next'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				) ) {
				$is_testing_next = true;
				// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			} elseif ( isset( $_COOKIE['vip-search-test-es-next'] ) && ( '1' === $_COOKIE['vip-search-test-es-next'] || 'true' === $_COOKIE['vip-search-test-es-next'] ) ) {
				$is_testing_next = true;
			}
		}

		if ( $is_testing_next ) {
			return sprintf( '%s (Using ES8)', $base_version );
		} else {
			return sprintf( '%s (Using ES7)', $base_version );
		}
	}

	/**
	 * Return some default information if no .version file is found or if the file is empty.
	 *
	 */
	private function set_default_version_info() {
		$this->data->mu_plugins = [
			'branch' => 'unbuilt',
		];
	}
}
