<?php

namespace Automattic\VIP\Search;

use \WP_CLI;
use WP_Post;

class Search {
	public const QUERY_COUNT_CACHE_KEY              = 'query_count';
	public const QUERY_RATE_LIMITED_START_CACHE_KEY = 'query_rate_limited_start';
	public const SEARCH_CACHE_GROUP                 = 'vip_search';
	public const QUERY_INTEGRATION_FORCE_ENABLE_KEY = 'vip-search-enabled';
	public const QUERY_FORCE_VERSION_PATTERN        = 'vip_search_%s_version';
	public const SEARCH_ALERT_SLACK_CHAT            = '#vip-go-es-alerts';
	public const SEARCH_ALERT_LEVEL                 = 2; // Level 2 = 'alert'
	public const MAX_RESULT_WINDOW                  = 10000;
	/**
	 * Empty for now. Will flesh out once migration path discussions are underway and/or the same meta are added to the filter across many
	 * sites.
	 */
	public const POST_META_DEFAULT_ALLOW_LIST = array();
	/**
	 * Jetpack default post meta allow list to make migration path easier. See usage of jetpack_sync_post_meta_whitelist to see how this is used.
	 */
	public const JETPACK_POST_META_DEFAULT_ALLOW_LIST = array(
		'_feedback_akismet_values',
		'_feedback_email',
		'_feedback_extra_fields',
		'_g_feedback_shortcode',
		'_jetpack_post_thumbnail',
		'_menu_item_classes',
		'_menu_item_menu_item_parent',
		'_menu_item_object',
		'_menu_item_object_id',
		'_menu_item_orphaned',
		'_menu_item_type',
		'_menu_item_xfn',
		'_publicize_facebook_user',
		'_publicize_twitter_user',
		'_thumbnail_id',
		'_wp_attached_file',
		'_wp_attachment_backup_sizes',
		'_wp_attachment_context',
		'_wp_attachment_image_alt',
		'_wp_attachment_is_custom_background',
		'_wp_attachment_is_custom_header',
		'_wp_attachment_metadata',
		'_wp_page_template',
		'_wp_trash_meta_comments_status',
		'_wpas_mess',
		'content_width',
		'custom_css_add',
		'custom_css_preprocessor',
		'enclosure',
		'imagedata',
		'nova_price',
		'publicize_results',
		'sharing_disabled',
		'switch_like_status',
		'videopress_guid',
		'vimeo_poster_image',
		'advanced_seo_description',
	);

	public const ES_QUERY_RESERVED_NAMES = array(
		'author',
		'author__in',
		'author__not_in',
		'author_id',
		'author_name',
		'cache_results',
		'cat',
		'category__and',
		'category__in',
		'category__not_in',
		'category_name',
		'comment_count',
		'date_query',
		'day',
		'fields',
		'has_password',
		'hour',
		'ignore_sticky_posts',
		'm',
		'meta_compare',
		'meta_key',
		'meta_query',
		'meta_value',
		'meta_value_num',
		'minute',
		'monthnum',
		'name',
		'nopaging',
		'offset',
		'order',
		'orderby',
		'p',
		'page',
		'page_id',
		'paged',
		'pagename',
		'perm',
		'post__in',
		'post__not_in',
		'post_mime_type',
		'post_name__in',
		'post_parent',
		'post_parent__in',
		'post_parent__not_in',
		'post_password',
		'post_status',
		'post_type',
		'posts_per_archive_page',
		'posts_per_page',
		's',
		'second',
		'tag',
		'tag__and',
		'tag__in',
		'tag__not_in',
		'tag_id',
		'tag_slug__and',
		'tag_slug__in',
		'tax',
		'tax_query',
		'update_post_meta_cache',
		'update_post_term_cache',
		'w',
		'year',
	);

	public const ALLOWED_DATACENTERS = [
		'dca',
		'dfw',
		'bur',
		'ams',
	];

	private static $query_count_ttl;

	private const MAX_SEARCH_LENGTH              = 255;
	private const DISABLE_POST_META_ALLOW_LIST   = array();
	private const STALE_QUEUE_WAIT_LIMIT         = 3600; // 1 hour in seconds
	private const POST_FIELD_COUNT_LIMIT         = 5000;
	private const QUERY_RATE_LIMITED_ALERT_LIMIT = 7200; // 2 hours in seconds

	private const DEFAULT_QUERY_COUNT_TTL     = 5 * \MINUTE_IN_SECONDS;
	private const LOWER_BOUND_QUERY_COUNT_TTL = 1 * \MINUTE_IN_SECONDS;
	private const UPPER_BOUND_QUERY_COUNT_TTL = 2 * \HOUR_IN_SECONDS;

	private const DEFAULT_MAX_QUERY_COUNT        = 50000 + 1;
	private const LOWER_BOUND_QUERIES_PER_SECOND = 10;
	private const UPPER_BOUND_QUERIES_PER_SECOND = 500;

	private const DEFAULT_QUERY_DB_FALLBACK_VALUE     = 5;
	private const LOWER_BOUND_QUERY_DB_FALLBACK_VALUE = 1;
	private const UPPER_BOUND_QUERY_DB_FALLBACK_VALUE = 10;

	// Global timeouts used both for ES query timeout and the Search client timeout.
	private const GLOBAL_QUERY_TIMEOUT_WEB_SEC = 2;
	private const GLOBAL_QUERY_TIMEOUT_CLI_SEC = 5;

	// Threshold for Indexable counts before increasing shards
	private const POST_SHARD_THRESHOLD = 1000000;
	private const USER_SHARD_THRESHOLD = 2000000;

	public $healthcheck;
	public $settings_healthcheck;
	public $versioning_cleanup;
	public $field_count_gauge;
	public $queue_wait_time;
	/** @var Queue */
	public $queue;
	/** @var Versioning */
	public $versioning;
	public $statsd;
	public $indexables;
	public $alerts;
	public $logger;
	/** @var Concurrency_Limiter */
	public $concurrency_limiter;
	public $time;
	public static $stat_sampling_drop_value = 5; // Value to compare >= against rand( 1, 10 ). 5 should result in roughly half being true.

	/**
	 * Maximum number of queries before rate-limiting kicks in.
	 *
	 * @var int
	 */
	public static $max_query_count;
	public static $query_db_fallback_value;

	private static $instance;
	private $current_host_index = 0;

	/**
	 * Initialize the VIP Search plugin
	 */
	public function init() {
		$this->apply_settings(); // Applies filters for tweakable Search settings and should run first.
		$this->setup_constants();
		$this->maybe_enable_ep_query_logging();
		$this->load_dependencies();
		$this->setup_hooks();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			$this->load_commands();
			$this->setup_cron_jobs();
			$this->setup_regular_stat_collection();
		}
	}

	/**
	 * Check if the constants needed for ElasticSearch connection are defined.
	 *
	 * @return bool true if constants are defined, false otherwise
	 */
	public static function are_es_constants_defined() {
		$endpoints_defined = defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) && is_array( VIP_ELASTICSEARCH_ENDPOINTS ) && ! empty( VIP_ELASTICSEARCH_ENDPOINTS );
		$username_defined  = defined( 'VIP_ELASTICSEARCH_USERNAME' ) && VIP_ELASTICSEARCH_USERNAME;
		$password_defined  = defined( 'VIP_ELASTICSEARCH_PASSWORD' ) && VIP_ELASTICSEARCH_PASSWORD;
		return $endpoints_defined && $username_defined && $password_defined;
	}

	public static function instance() {
		if ( ! ( static::$instance instanceof Search ) ) {
			static::$instance = new Search();
			static::$instance->init();
		}

		return static::$instance;
	}

	protected function load_dependencies() {
		// Load ElasticPress
		require_once __DIR__ . '/../../elasticpress/elasticpress.php';

		// Load health check cron job
		require_once __DIR__ . '/class-healthjob.php';

		// Load settings health check cron job
		require_once __DIR__ . '/class-settingshealthjob.php';

		// Load versioning cleanup job
		require_once __DIR__ . '/class-versioningcleanupjob.php';

		// Load field count gauge cron job
		require_once __DIR__ . '/class-fieldcountgaugejob.php';

		// Load queue wait time cron job
		require_once __DIR__ . '/class-queuewaittimejob.php';

		// Load our custom dashboard
		require_once __DIR__ . '/class-dashboard.php';

		require_once __DIR__ . '/class-queue.php';

		require_once __DIR__ . '/class-syncmanager-helper.php';

		SyncManager_Helper::instance();

		require_once __DIR__ . '/class-concurrency-limiter.php';

		$this->queue = new Queue();
		$this->queue->init();

		// Caching layer
		require_once __DIR__ . '/class-cache.php';
		$this->cache = new Cache();

		// Index versioning
		require_once __DIR__ . '/class-versioning.php';
		$this->versioning = new Versioning();

		// StatsD - can be set explicitly for mocking purposes
		if ( ! $this->statsd ) {
			$this->statsd = new \Automattic\VIP\StatsD();
		}

		// Indexables - can be set explicitly for mocking purposes
		if ( ! $this->indexables ) {
			$this->indexables = \ElasticPress\Indexables::factory();
		}

		// Alerts - can be set explicitly for mocking purposes
		if ( ! $this->alerts ) {
			$this->alerts = \Automattic\VIP\Utils\Alerts::instance();
		}

		// Logger - can be set explicitly for mocking purposes
		if ( ! $this->logger ) {
			$this->logger = new \Automattic\VIP\Logstash\Logger();
		}

		if ( ! $this->concurrency_limiter ) {
			$this->concurrency_limiter = new Concurrency_Limiter();
		}

		/**
		 * Load CLI commands
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/commands/class-corecommand.php';
			require_once __DIR__ . '/commands/class-healthcommand.php';
			require_once __DIR__ . '/commands/class-queuecommand.php';
			require_once __DIR__ . '/commands/class-versioncommand.php';
			require_once __DIR__ . '/commands/class-documentcommand.php';

			// Remove elasticpress command. Need a better way.
			//WP_CLI::add_hook( 'before_add_command:elasticpress', [ $this, 'abort_elasticpress_add_command' ] );
		}
	}

	public function apply_settings() {
		/**
		 * The period with which the Elasticsearch query rate limiting threshold is set.
		 *
		 * A set amount of queries are allowed per-period before Elasticsearch query rate limiting occurs.
		 *
		 * @hook vip_search_ratelimit_period
		 * @param int $period The period, in seconds, for Elasticsearch query rate limiting checks.
		 */
		self::$query_count_ttl = apply_filters( 'vip_search_ratelimit_period', self::DEFAULT_QUERY_COUNT_TTL );

		if ( ! is_numeric( self::$query_count_ttl ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_ratelimit_period should be an integer.',
				'5.5.3'
			);

			self::$query_count_ttl = self::DEFAULT_QUERY_COUNT_TTL;
		}

		self::$query_count_ttl = intval( self::$query_count_ttl );

		if ( self::$query_count_ttl < self::LOWER_BOUND_QUERY_COUNT_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_ratelimit_period should not be set below %d seconds.', self::LOWER_BOUND_QUERY_COUNT_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$query_count_ttl = self::LOWER_BOUND_QUERY_COUNT_TTL;
		}

		if ( self::$query_count_ttl > self::UPPER_BOUND_QUERY_COUNT_TTL ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_ratelimit_period should not be set above %d seconds.', self::UPPER_BOUND_QUERY_COUNT_TTL ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$query_count_ttl = self::UPPER_BOUND_QUERY_COUNT_TTL;
		}

		/**
		 * The number of queries allowed per period before Elasticsearch rate limiting takes effect.
		 *
		 * Ratelimiting works by sending a percentage of traffic to the database rather than Elasticsearch to keep the cluster stable.
		 *
		 * @hook vip_search_max_query_count
		 * @param int $ratelimit_threshold The threshold to trigger ratelimiting for the period.
		 */
		self::$max_query_count = apply_filters( 'vip_search_max_query_count', self::DEFAULT_MAX_QUERY_COUNT );

		if ( ! is_numeric( self::$max_query_count ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_max_query_count should be an integer.',
				'5.5.3'
			);

			self::$max_query_count = self::DEFAULT_MAX_QUERY_COUNT;
		}

		self::$max_query_count = intval( self::$max_query_count );

		$lower_bound_max_query_count = ( self::$query_count_ttl * self::LOWER_BOUND_QUERIES_PER_SECOND ) + 1;

		if ( self::$max_query_count < $lower_bound_max_query_count ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_query_count should not be below %d queries per second.', self::LOWER_BOUND_QUERIES_PER_SECOND ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_query_count = $lower_bound_max_query_count;
		}

		$upper_bound_max_query_count = ( self::$query_count_ttl * self::UPPER_BOUND_QUERIES_PER_SECOND ) + 1;

		if ( self::$max_query_count > $upper_bound_max_query_count ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_max_query_count should not exceed %d queries per second.', self::UPPER_BOUND_QUERIES_PER_SECOND ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			self::$max_query_count = $upper_bound_max_query_count;
		}

		/**
		 * The chance of an individual request being sent to the database when Elasticsearch queries are rate limited.
		 *
		 * This value is compared >= rand( 1, 10 ) so a setting of 5 would cause roughly half of requests to go to the database. A setting of 3 would yield a 70% chance of going to the database.
		 *
		 * @hook vip_search_query_db_fallback_value
		 * @param int $fallback_value The value compared >= rand( 1, 10 ) to determine if a request will go to the database if Elasticsearch query rate limited.
		 */
		self::$query_db_fallback_value = apply_filters( 'vip_search_query_db_fallback_value', self::DEFAULT_QUERY_DB_FALLBACK_VALUE );

		if ( ! is_numeric( self::$query_db_fallback_value ) ) {
			_doing_it_wrong(
				'add_filter',
				'vip_search_query_db_fallback_value should be an integer.',
				'5.5.3'
			);

			self::$query_db_fallback_value = self::DEFAULT_QUERY_DB_FALLBACK_VALUE;
		}

		self::$query_db_fallback_value = intval( self::$query_db_fallback_value );

		if ( self::$query_db_fallback_value < self::LOWER_BOUND_QUERY_DB_FALLBACK_VALUE || self::$query_db_fallback_value > self::UPPER_BOUND_QUERY_DB_FALLBACK_VALUE ) {
			_doing_it_wrong(
				'add_filter',
				sprintf( 'vip_search_query_db_fallback_value should be between %d and %d.', self::LOWER_BOUND_QUERY_DB_FALLBACK_VALUE, self::UPPER_BOUND_QUERY_DB_FALLBACK_VALUE ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'5.5.3'
			);

			// Set to default rather than to one of the bounds since this setting has serious performance and user impact.
			self::$query_db_fallback_value = self::DEFAULT_QUERY_DB_FALLBACK_VALUE;
		}
	}

	protected function setup_constants() {
		// Ensure we limit bulk indexing chunk size to a reasonable number (no limit by default)
		if ( ! defined( 'EP_SYNC_CHUNK_LIMIT' ) ) {
			define( 'EP_SYNC_CHUNK_LIMIT', 500 );
		}

		if ( ! defined( 'EP_HOST' ) && defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) && is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			$host                     = $this->get_random_host( VIP_ELASTICSEARCH_ENDPOINTS );
			$this->current_host_index = array_search( $host, VIP_ELASTICSEARCH_ENDPOINTS );

			define( 'EP_HOST', $host );
		}

		if ( ! defined( 'ES_SHIELD' ) && ( defined( 'VIP_ELASTICSEARCH_USERNAME' ) && defined( 'VIP_ELASTICSEARCH_PASSWORD' ) ) ) {
			define( 'ES_SHIELD', sprintf( '%s:%s', VIP_ELASTICSEARCH_USERNAME, VIP_ELASTICSEARCH_PASSWORD ) );
		}

		// Do not allow sync via Dashboard (WP-CLI is preferred for indexing).
		// The Dashboard is hidden anyway but just in case.
		if ( ! defined( 'EP_DASHBOARD_SYNC' ) ) {
			define( 'EP_DASHBOARD_SYNC', false );
		}

		// Disable DB and ES query logs for CLI commands to keep memory under control
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( ! defined( 'SAVEQUERIES' ) ) {
				define( 'SAVEQUERIES', false );
			}

			if ( ! defined( 'EP_QUERY_LOG' ) ) {
				define( 'EP_QUERY_LOG', false );
			}
		}
	}

	/**
	 * Query logging in ElasticPress must be enabled via defining WP_EP_DEBUG as true before ElasticPress is instantiated.
	 * This is separate from setup_hooks because some parts of setup_hooks require ElasticPress.
	 */
	protected function maybe_enable_ep_query_logging() {
		add_action( 'plugins_loaded', [ $this, 'enable_ep_query_logging_if_debug_bar_or_query_monitor_enabled' ] );
	}

	/**
	 * Check if query monitor or debug bar are enabled. If so, define WP_EP_DEBUG as true so ElasticPress enables query logging and then load the ElasticPress debug bar panel.
	 */
	public function enable_ep_query_logging_if_debug_bar_or_query_monitor_enabled() {
		if ( apply_filters( 'debug_bar_enable', false ) || apply_filters( 'wpcom_vip_qm_enable', false ) ) {
			if ( ! defined( 'WP_EP_DEBUG' ) ) {
				define( 'WP_EP_DEBUG', true );
			}

			// Load query log override function to remove Authorization header from requests
			require_once __DIR__ . '/../functions/ep-get-query-log.php';

			// Load ElasticPress Debug Bar
			require_once __DIR__ . '/../../debug-bar-elasticpress/debug-bar-elasticpress.php';
		}
	}

	protected function setup_hooks() {
		add_action( 'plugins_loaded', [ $this, 'action__plugins_loaded' ] );

		add_action( 'init', [ $this, 'action__init' ] );

		add_filter( 'ep_index_name', [ $this, 'filter__ep_index_name' ], PHP_INT_MAX, 3 ); // We want to enforce the naming, so run this really late.
		add_filter( 'ep_global_alias', [ $this, 'filter__ep_global_alias' ], PHP_INT_MAX, 2 );

		// Override default per page value set in elasticpress/includes/classes/Indexable.php
		add_filter( 'ep_bulk_items_per_page', [ $this, 'filter__ep_bulk_items_per_page' ], PHP_INT_MAX );

		// Network layer replacement to use VIP helpers (that handle slow/down upstream server)
		add_filter( 'ep_intercept_remote_request', '__return_true', 9999 );
		add_filter( 'ep_do_intercept_request', [ $this, 'filter__ep_do_intercept_request' ], 9999, 5 );

		// Disable query integration by default
		add_filter( 'ep_skip_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5, 2 );
		add_filter( 'ep_skip_user_query_integration', array( __CLASS__, 'ep_skip_query_integration' ), 5 );
		// Rate limit query integration
		add_filter( 'ep_skip_query_integration', array( $this, 'rate_limit_ep_query_integration' ), PHP_INT_MAX );

		// Disable certain EP Features
		add_filter( 'ep_feature_active', array( $this, 'filter__ep_feature_active' ), PHP_INT_MAX, 3 );

		// Round-robin retry hosts if connection to a host fails
		add_filter( 'ep_pre_request_host', array( $this, 'filter__ep_pre_request_host' ), PHP_INT_MAX, 2 );

		add_filter( 'ep_valid_response', array( $this, 'filter__ep_valid_response' ) );

		// Allow querying while a bulk index is running
		add_filter( 'ep_enable_query_integration_during_indexing', '__return_true' );

		// Set facet taxonomies size. Shouldn't currently be used, but it makes sense to have it set to a sensible
		// default just in case it ends up in use so that the application doesn't error
		add_filter( 'ep_facet_taxonomies_size', array( $this, 'filter__ep_facet_taxonomies_size' ) );

		// Disable facet queries
		add_filter( 'ep_facet_include_taxonomies', '__return_empty_array' );

		// Enable track_total_hits for all queries for proper result sets if track_total_hits isn't already set
		// Adjust the post query ES arguments (the whole payload)
		add_filter( 'ep_post_formatted_args', array( $this, 'filter__ep_post_formatted_args' ), 10, 3 );

		// Disable query fuzziness by default
		add_filter( 'ep_fuzziness_arg', '__return_zero', 0 );

		// Replace base 'should' with 'must' in Elasticsearch query if formatted args structure matches what's expected
		add_filter( 'ep_formatted_args', array( $this, 'filter__ep_formatted_args' ), 0, 2 );

		// Disable indexing of filtered content by default, as it's not searched by default
		add_filter( 'ep_allow_post_content_filtered_index', '__return_false' );

		// Better replica counts
		add_filter( 'ep_default_index_number_of_replicas', array( $this, 'filter__ep_default_index_number_of_replicas' ) );

		// Date relevancy defaults. Taken from Jetpack Search.
		// Set to 'gauss'
		add_filter( 'epwr_decay_function', array( $this, 'filter__epwr_decay_function' ), 0 );
		// Set to '360d'
		add_filter( 'epwr_scale', array( $this, 'filter__epwr_scale' ), 0 );
		// Set to .9
		add_filter( 'epwr_decay', array( $this, 'filter__epwr_decay' ), 0 );
		// Set to '0d'
		add_filter( 'epwr_offset', array( $this, 'filter__epwr_offset' ), 0 );
		// Set to 'multiply'
		add_filter( 'epwr_score_mode', array( $this, 'filter__epwr_score_mode' ), 0 );
		// Set to 'multiply'
		add_filter( 'epwr_boost_mode', array( $this, 'filter__epwr_boost_mode' ), 0 );
		// Set to 0.001
		add_filter( 'epwr_weight', array( $this, 'filter__epwr_weight' ), 0 );

		//	Reduce existing filters based on post meta allow list and make sure the maximum field count is respected
		add_filter( 'ep_prepare_meta_data', array( $this, 'filter__ep_prepare_meta_data' ), PHP_INT_MAX, 2 );

		// Implement a more convenient way to filter which taxonomies get synced to posts
		add_filter( 'ep_sync_taxonomies', array( $this, 'filter__ep_sync_taxonomies' ), 999, 2 );

		// Truncate search strings to a reasonable length
		add_action( 'parse_query', array( $this, 'truncate_search_string_length' ), PHP_INT_MAX );

		// Try to prevent the field limit from being set too high
		add_filter( 'ep_total_field_limit', array( $this, 'limit_field_limit' ), PHP_INT_MAX );

		// Check if meta is on allow list. If not, don't re-index
		add_filter( 'ep_skip_post_meta_sync', array( $this, 'filter__ep_skip_post_meta_sync' ), PHP_INT_MAX, 4 );

		// Override value of ep_prepare_meta_allowed_protected_keys with the value of vip_search_post_meta_allow_list
		add_filter( 'ep_prepare_meta_allowed_protected_keys', array( $this, 'filter__ep_prepare_meta_allowed_protected_keys' ), PHP_INT_MAX, 2 );

		// Alter the default index mapping/settings for each indexable type (primarily to add index allocation settings)
		// NOTE - if new indexables are added, they need to be added here. EP doesn't currently have a generic ep_mapping type filter
		add_filter( 'ep_post_mapping', array( $this, 'filter__ep_indexable_mapping' ) );
		add_filter( 'ep_term_mapping', array( $this, 'filter__ep_indexable_mapping' ) );
		add_filter( 'ep_user_mapping', array( $this, 'filter__ep_indexable_mapping' ) );

		// Better shard counts
		add_filter( 'ep_default_index_number_of_shards', array( $this, 'filter__ep_default_index_number_of_shards' ) );
		if ( defined( 'VIP_GO_ENV' ) && 'production' === constant( 'VIP_GO_ENV' ) ) {
			// Only adjust shard count for production environments.
			add_filter( 'ep_post_mapping', array( $this, 'filter__ep_post_mapping' ) );
			add_filter( 'ep_user_mapping', array( $this, 'filter__ep_user_mapping' ) );
		}

		// Do not show the above compat notice since VIP Search will support whatever Elasticsearch version we're running
		add_filter( 'pre_option_ep_hide_es_above_compat_notice', '__return_true' );

		// If protected content is enabled, ensure that the attachment post type is an indexable post type.
		// Set the priority to 9999 so customers can unset it if needed.
		// The current usages of this filter have priority 10 in ElasticPress. May need to be adjusted if this changes.
		if ( false !== $this->is_protected_content_enabled() ) {
			add_filter( 'ep_indexable_post_types', array( $this, 'add_attachment_to_ep_indexable_post_types' ), 9999 );
		}

		add_filter( 'vip_search_post_meta_allow_list', array( $this, 'filter__vip_search_post_meta_allow_list_defaults' ) );

		// Limit the max result window on index settings
		add_filter( 'ep_max_result_window', [ $this, 'limit_max_result_window' ], PHP_INT_MAX );
		add_filter( 'ep_term_max_result_window', [ $this, 'limit_max_result_window' ], PHP_INT_MAX );
		add_filter( 'ep_user_max_result_window', [ $this, 'limit_max_result_window' ], PHP_INT_MAX );

		// Limit the max result window on query arguments
		add_filter( 'ep_max_results_window', [ $this, 'limit_max_result_window' ], PHP_INT_MAX );

		add_action( 'after_setup_theme', array( $this, 'apply_settings' ), PHP_INT_MAX ); // Try to apply Search settings after other actions in this hook.

		// Lock search algorithm to 3.5
		add_filter( 'ep_search_algorithm_version', [ $this, 'filter__ep_search_algorithm_version' ] );

		add_filter( 'ep_post_tax_excluded_wp_query_root_check', [ $this, 'exclude_es_query_reserved_names' ] );

		add_filter( 'ep_sync_indexable_kill', [ $this, 'do_not_sync_if_no_index' ], PHP_INT_MAX, 2 );
	}

	protected function load_commands() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'vip-search health', __NAMESPACE__ . '\Commands\HealthCommand' );
			WP_CLI::add_command( 'vip-search queue', __NAMESPACE__ . '\Commands\QueueCommand' );
			WP_CLI::add_command( 'vip-search index-versions', __NAMESPACE__ . '\Commands\VersionCommand' );
			WP_CLI::add_command( 'vip-search documents', __NAMESPACE__ . '\Commands\DocumentCommand' );
			WP_CLI::add_command( 'vip-search', __NAMESPACE__ . '\Commands\CoreCommand' );
		}
	}

	/**
	 * Setup the needed cron jobs (this fires in WP_CLI context)
	 *
	 * @return void
	 */
	public function setup_cron_jobs() {
		$this->healthcheck          = new HealthJob( $this );
		$this->settings_healthcheck = new SettingsHealthJob( $this );
		$this->versioning_cleanup   = new VersioningCleanupJob( $this->indexables, $this->versioning );

		add_action( 'wp_loaded', [ $this->healthcheck, 'init' ], 0 );
		add_action( 'wp_loaded', [ $this->settings_healthcheck, 'init' ], 0 );
		add_action( 'wp_loaded', [ $this->versioning_cleanup, 'init' ], 0 );
	}

	protected function setup_regular_stat_collection() {
		$this->field_count_gauge = new FieldCountGaugeJob();
		$this->field_count_gauge->init();

		$this->queue_wait_time = new QueueWaitTimeJob();
		$this->queue_wait_time->init();
	}

	/**
	 * To allow consistent testing against timestamps, set the time used in functionality.
	 *
	 * @param int $time The fixed time you want to use in testing.
	 */
	public function set_time( $time ) {
		if ( is_numeric( $time ) ) {
			$this->time = intval( $time );
		}
	}

	/**
	 * To allow consistent testing against timestamps, get the fixed time if set or return the current time.
	 *
	 * @return int Either the fixed time previously set if defined or the current timestamp
	 */
	public function get_time() {
		if ( isset( $this->time ) && is_numeric( $this->time ) ) {
			return intval( $this->time );
		}

		return time();
	}

	/**
	 * To allow consistent testing against timestamps, allow fixed times to be reset to current time.
	 */
	public function reset_time() {
		$this->time = null;
	}

	public function query_es( $type, $es_args = array(), $wp_query_args = array(), $index_name = null ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $type );

		if ( ! $indexable ) {
			return new \WP_Error( 'indexable-not-found', 'Invalid query type specified. Must be a valid Indexable from ElasticPress' );
		}

		return $indexable->query_es( $es_args, $wp_query_args, $index_name );
	}

	public function action__plugins_loaded() {
		$this->maybe_load_es_wp_query();

		$this->maybe_change_index_version();
	}

	public function maybe_load_es_wp_query() {
		if ( ! self::should_load_es_wp_query() ) {
			return;
		}

		require_once __DIR__ . '/../../es-wp-query/es-wp-query.php';

		// There's another adapter loaded already, this should be avoided.
		// To fail gracefully we simply won't try to load our adapter.
		// But we also need to surface the error.
		if ( class_exists( '\\ES_WP_Query' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			_doing_it_wrong( self::class . '::' . __FUNCTION__, "Search: tried to load 'vip-search' adapter, but another adapter is already loaded. Please disable standalone 'es-wp-query' and remove calls to 'es_wp_query_load_adapter' in your code.", null );
		}

		// If no other adapter has already been loaded, load ours.
		// This is to prevent fatals (duplicate function/class definitions),
		// if other adapters were somehow loaded before ours.
		if ( ! class_exists( '\\ES_WP_Query' ) && function_exists( 'es_wp_query_load_adapter' ) ) {
			es_wp_query_load_adapter( 'vip-search' );
		}
	}

	/**
	 * Helper to determine whether to load the bundled version of `es-wp-query`:
	 * we only need to load it if query integration enabled.
	 *
	 * @return boolean
	 */
	public static function should_load_es_wp_query() {
		// Don't load if plugin already loaded elsewhere.
		if ( class_exists( '\\ES_WP_Query_Shoehorn' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			_doing_it_wrong( self::class . '::' . __FUNCTION__, "Search: tried to load 'es-wp-query', but another copy is already loaded. Please disable your copy of 'es-wp-query'.", null );
			return false;
		}

		return self::is_query_integration_enabled();
	}

	/**
	 * Filter ElasticPress index name if using VIP ES infrastructure
	 */
	public function filter__ep_index_name( $index_name, $blog_id, $indexable ) {
		// TODO: Use FILES_CLIENT_SITE_ID for now as VIP_GO_ENV_ID is not ready yet. Should replace once it is.
		$index_name = sprintf( 'vip-%s-%s', FILES_CLIENT_SITE_ID, $indexable->slug );

		// $blog_id won't be present on global indexes (such as users)
		if ( $blog_id ) {
			$index_name .= sprintf( '-%s', $blog_id );
		}

		$current_version = $this->versioning->get_current_version_number( $indexable );

		if ( is_int( $current_version ) && $current_version > 1 ) {
			$index_name .= sprintf( '-v%d', $current_version );
		}

		return $index_name;
	}

	/**
	 * Filter ElasticPress global index alias (for cross-subsite searching)
	 */
	public function filter__ep_global_alias( $alias_name, $indexable ) {
		// TODO: Use FILES_CLIENT_SITE_ID for now as VIP_GO_ENV_ID is not ready yet. Should replace once it is.
		$alias_name = sprintf( 'vip-%s-%s-all', FILES_CLIENT_SITE_ID, $indexable->slug );

		return $alias_name;
	}

	/**
	 * Filter to set ep_bulk_items_per_page to 500
	 */
	public function filter__ep_bulk_items_per_page() {
		return 500;
	}

	/**
	 * Generate option name for cached index_exists request.
	 * 
	 * @return string $option_name Name of generated option.
	 */
	private function get_index_exists_option_name( $url ) {
		$parsed_url  = wp_parse_url( $url );
		$index_name  = isset( $parsed_url['path'] ) ? trim( $parsed_url['path'], '/' ) : '';
		$option_name = "es_index_exists_{$index_name}";

		return $option_name;
	}

	/**
	 * Filter to intercept EP remote requests.
	 * 
	 * @param  array  $request  New remote request response
	 * @param  array  $query    Remote request arguments
	 * @param  array  $args     Request arguments
	 * @param  array  $failures Number of failures
	 * @param  string $type     Type of request
	 * @return array  $request  New request
	 */
	public function filter__ep_do_intercept_request( $request, $query, $args, $failures = 0, $type = null ) {
		if ( is_wp_error( $request ) && ( 503 === $request->get_error_code() || 429 === $request->get_error_code() ) ) {
			return $request;
		}

		$index_exists_invalidation_actions = [
			'delete_index',
			'refresh_indices',
			'put_mapping',
			'bulk_index',
		];
		$valid_index_exists_response_codes = [ 200, 404 ];
		if ( 'index_exists' === $type || in_array( $type, $index_exists_invalidation_actions, true ) ) {
			$index_exists_option_name    = $this->get_index_exists_option_name( $query['url'] );
			$cached_index_exists_request = get_option( $index_exists_option_name );
			if ( false !== $cached_index_exists_request ) {
				$cached_index_exists_response_code = (int) wp_remote_retrieve_response_code( $cached_index_exists_request );
				if ( 'index_exists' === $type && in_array( $cached_index_exists_response_code, $valid_index_exists_response_codes, true ) ) {
					// Return cached index_exists option.
					return $cached_index_exists_request;
				} else {
					// Invalidate index_exists caching on certain actions.
					delete_option( $index_exists_option_name );

					// Ensure the cache for the option was actually deleted.
					if ( false !== wp_cache_get( $index_exists_option_name, 'options' ) ) {
						wp_cache_delete( $index_exists_option_name, 'options' );
					}
				}
			}
		}
		
		// Add custom headers to identify authorized traffic
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = [];
		}

		$args['headers'] = array_merge(
			$args['headers'],
			[
				'X-Client-Site-ID' => FILES_CLIENT_SITE_ID,
				'X-Client-Env'     => VIP_GO_ENV,
				'Accept-Encoding'  => 'gzip, deflate',
			]
		);

		$statsd_mode            = $this->get_statsd_request_mode_for_request( $query['url'], $args );
		$collect_per_doc_metric = $this->is_bulk_url( $query['url'] );
		$statsd_prefix          = $this->get_statsd_prefix( $query['url'], $statsd_mode );

		// Cache handling
		$is_cacheable = $this->is_url_query_cacheable( $query['url'], $args );
		$cache_key    = 'es_query_cache:' . md5( $query['url'] . wp_json_encode( $args ) ) . ':' . wp_cache_get_last_changed( self::SEARCH_CACHE_GROUP );

		/**
		 * Serve cached response right away, if available and not stale and the query is cacheable
		 */
		$cached_response = $is_cacheable ? wp_cache_get( $cache_key, self::SEARCH_CACHE_GROUP ) : false;
		if ( $cached_response ) {
			return $cached_response;
		}

		$start_time = microtime( true );

		$timeout = $this->get_http_timeout_for_query( $query, $args );

		/**
		 * Skip vip_safe_wp_remote_request for non-query (search) requests
		 * Any timeouts happening in non-search/non-query context shouldn't count towards the request disabling threshold.
		 */
		if ( 'query' === $type ) {
			$response = vip_safe_wp_remote_request( $query['url'], false, 5, $timeout, 10, $args );
		} else {
			$args['timeout'] = $timeout;
			$response        = wp_remote_request( $query['url'], $args );
		}

		$end_time = microtime( true );
		$duration = ( $end_time - $start_time ) * 1000;

		$this->maybe_increment_stat( $statsd_prefix . '.total' );

		$response_code = (int) wp_remote_retrieve_response_code( $response );

		/**
		 * If request resulted in an error flag it as such but try to return the fallback value if available.
		 */
		if ( is_wp_error( $response ) || $response_code >= 400 ) {
			$this->ep_handle_failed_request( $request, $response, $query, $statsd_prefix, $type, $cached_response );
			if ( isset( $cached_response['response'] ) ) {
				return $cached_response['response'];
			}
		} else {
			// Record engine time (have to parse JSON to get it)
			$response_body_json = wp_remote_retrieve_body( $response );
			$response_body      = json_decode( $response_body_json, true );

			if ( $response_body && isset( $response_body['took'] ) && is_int( $response_body['took'] ) ) {
				$this->maybe_send_timing_stat( $statsd_prefix . '.engine', $response_body['took'] );
			}
			$this->maybe_send_timing_stat( $statsd_prefix . '.total', $duration );

			if ( $collect_per_doc_metric && $response_body && isset( $response_body['items'] ) && is_array( $response_body['items'] ) ) {
				$doc_count = count( $response_body['items'] );
				$this->maybe_send_timing_stat( $statsd_prefix . '.per_doc', $duration / $doc_count );
			}

			$response_headers = wp_remote_retrieve_headers( $response );

			// Check for 'Warning' headers and log them
			if ( isset( $response_headers['warning'] ) ) {
				$warning_messages = $response_headers['warning'];
				if ( ! is_array( $warning_messages ) ) {
					$warning_messages = array( $warning_messages );
				}

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				$backtrace       = wp_debug_backtrace_summary();
				$sanitized_query = $this->sanitize_ep_query_for_logging( $query );
				foreach ( $warning_messages as $message ) {
					trigger_error( esc_html( $message ), \E_USER_WARNING );
					\Automattic\VIP\Logstash\log2logstash( array(
						'severity' => 'warning',
						'feature'  => 'search_es_warning',
						'message'  => $message,
						'extra'    => [
							'query'     => $sanitized_query,
							'backtrace' => $backtrace,
						],
					) );
				}
			}
		}

		if ( is_wp_error( $response ) ) {
			// Return a generic VIP Search WP_Error instead of the one from wp_remote_request
			return new \WP_Error( 'vip-search-upstream-request-failed', 'There was an error connecting to the upstream search server' );
		}

		if ( 'index_exists' === $type && in_array( $response_code, $valid_index_exists_response_codes, true ) ) {
			// Cache index_exists into option since we didn't return a cached value earlier.
			add_option( $index_exists_option_name, $response );
		}

		if ( $is_cacheable ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( $cache_key, $response, self::SEARCH_CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Check whether this particular ES query response can be cached.
	 *
	 * @param string $url
	 * @return boolean
	 */
	public function is_url_query_cacheable( string $url, $args ): bool {
		$is_cacheable = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['vip-debug'] ) && 'true' === $_GET['vip-debug'] ) {
			return $is_cacheable;
		}

		$is_cacheable = (bool) preg_match( '#/_(search|mget|doc)#', $url );


		/**
		 * Filter if request is cacheable
		 * 
		 * @hook vip_search_cache_es_response
		 * 
		 * @param  $url  Remote request URL
		 * @param  $args Remote request arguments
		 * @return $is_cacheable bool New cacheable status
		 */
		return apply_filters( 'vip_search_cache_es_response', $is_cacheable, $url, $args );
	}

	/**
	 * Handle failed requests 
	 *
	 * @param mixed $request
	 * @param mixed $response
	 * @param array $query
	 * @param string $statsd_prefix
	 * @param string $type
	 * @param mixed $cached_request
	 * @return void
	 */
	public function ep_handle_failed_request( $request, $response, $query, $statsd_prefix, $type, $cached_request = null ) {
		// Not real failed requests, we should not be logging.
		$skiplist = [
			'index_exists',
			'get',
		];
		if ( in_array( $type, $skiplist, true ) ) {
			return;
		}

		$is_cli = defined( 'WP_CLI' ) && WP_CLI;

		if ( is_wp_error( $request ) ) {
			$encoded_request = $request->get_error_messages();
		} else {
			// TODO: Report actual request, once we make sure all its fields are sanitized and publishable
			$encoded_request = 'Not an error.';
		}

		if ( is_wp_error( $response ) ) {
			$error_messages = $response->get_error_messages();

			foreach ( $error_messages as $error_message ) {
				$stat = $this->is_curl_timeout( $error_message ) ? '.timeout' : '.error';

				$this->maybe_increment_stat( $statsd_prefix . $stat );
			}

			$error_message = implode( ';', $error_messages );
			$error_type    = $cached_request ? 'search_http_error_with_fallback' : 'search_http_error';
		} else {
			$response_body_json = wp_remote_retrieve_body( $response );
			$response_body      = json_decode( $response_body_json, true );
			if ( isset( $response_body['error']['reason'] ) ) {
				$error_message = $response_body['error']['reason'];
			} else {
				$response_code    = wp_remote_retrieve_response_code( $response );
				$response_message = wp_remote_retrieve_response_message( $response );
				$error_message    = $response_code && ! empty( $response_message ) ? 
				(string) $response_code . ' ' . $response_message : 'Unknown Elasticsearch query error';
			}

			$this->maybe_increment_stat( $statsd_prefix . '.error' );

			$error_type = 'search_query_error';
		}

		if ( ! $is_cli ) {
			global $wp;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$request_url_for_logging = esc_url_raw( add_query_arg( $wp->query_vars, home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
		}
		$this->logger->log(
			'error',
			$error_type,
			$error_message,
			[
				'error_type' => ! is_wp_error( $response ) && isset( $response_body['error']['type'] ) ? $response_body['error']['type'] : 'Unknown error type',
				'root_cause' => ! is_wp_error( $response ) && isset( $response_body['error']['root_cause']['reason'] ) ? $response_body['error']['root_cause']['reason'] : null,
				'query'      => $this->sanitize_ep_query_for_logging( $query ),
				'backtrace'  => wp_debug_backtrace_summary(),   // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
				'is_cli'     => $is_cli,
				'request'    => $encoded_request,
				'response'   => $response_body ?? null,
				'url'        => $request_url_for_logging ?? null,
			]
		);
	}

	/**
	 * Given an ElasticPress query object, strip out anything that shouldn't be logged
	 * Or format parts of the query object to be more readable.
	 */
	public function sanitize_ep_query_for_logging( $query ) {
		if ( isset( $query['args']['headers']['Authorization'] ) ) {
			$query['args']['headers']['Authorization'] = '<redacted>';
		}

		// Try to parse the body if possible to make it better readable in the log entry.
		if ( isset( $query['args']['body'] ) ) {
			$decoded_body          = json_decode( $query['args']['body'] );
			$query['args']['body'] = ! json_last_error() ? $decoded_body : $query['args']['body'];
		}

		return $query;
	}

	/**
	 * Given an error message, determine if it's from curl error 28(timeout)
	 */
	private function is_curl_timeout( $error_message ) {
		return false !== strpos( strtolower( $error_message ), 'curl error 28' );
	}

	public function get_http_timeout_for_query( $query, $args ) {
		$is_cli  = defined( 'WP_CLI' ) && WP_CLI;
		$timeout = $is_cli ? self::GLOBAL_QUERY_TIMEOUT_CLI_SEC : self::GLOBAL_QUERY_TIMEOUT_WEB_SEC;

		$query_path      = wp_parse_url( $query['url'], PHP_URL_PATH );
		$is_post_request = false;

		if ( isset( $args['method'] ) && 0 === strcasecmp( 'POST', $args['method'] ) ) {
			$is_post_request = true;
		}

		// Bulk index request so increase timeout
		if ( wp_endswith( $query_path, '_bulk' ) || wp_endswith( $query_path, '_open' ) ) {
			$timeout = 5;

			if ( $is_cli && $is_post_request ) {
				$timeout = 30;
			} elseif ( \is_admin() && $is_post_request ) {
				$timeout = 15;
			}
		}

		return $timeout;
	}

	public function filter__ep_feature_active( $active, $feature_settings, $feature ) {
		$disabled_features = array(
			'documents',
		);

		if ( in_array( $feature->slug, $disabled_features, true ) ) {
			return false;
		}

		return $active;
	}

	/**
	 * Separate plugin enabling from querying the index
	 *
	 * This function determines if VIP Search should take over queries (search, 'ep_integrate' => true, and 'es' => true)
	 *
	 * The integration can be tested at any time by setting an `es` query argument (?vip-search-enabled=true).
	 *
	 * When the index is ready to serve requests in production, the `VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION`
	 * constant should be set to `true`, which will enable query integration for all requests
	 */
	public static function is_query_integration_enabled() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET[ self::QUERY_INTEGRATION_FORCE_ENABLE_KEY ] ) ) {
			return true;
		}

		// Legacy constant name
		$query_integration_enabled_legacy = defined( 'VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_ELASTICSEARCH_QUERY_INTEGRATION;

		$query_integration_enabled = defined( 'VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION' ) && true === VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION;

		$enabled_by_constant = ( $query_integration_enabled || $query_integration_enabled_legacy );

		if ( $enabled_by_constant ) {
			return true;
		}

		$option_value = get_option( 'vip_enable_vip_search_query_integration' );

		$enabled_by_option = in_array( $option_value, array( true, 'true', 'yes', 1, '1' ), true );

		if ( $enabled_by_option ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the site is in "network" mode, meaning subsites should be indexed into the same index
	 *
	 */
	public static function is_network_mode() {
		// NOTE - Not using strict equality check here so that we match EP
		return defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;
	}

	public static function ep_skip_query_integration( $skip, $query = null ) {
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

		// Bypass a bug in EP Facets that causes aggregations to be run on the main query
		// This is intended to be a temporary workaround until a better fix is made
		$bypassed_on_main_query_site_ids = [
			1284,
			1286,
		];

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			if ( in_array( VIP_GO_APP_ID, $bypassed_on_main_query_site_ids, true ) ) {
				// Prevent integration on non-search main queries (Facets can wrongly enable itself here)
				if ( $query && $query->is_main_query() && ! $query->is_search() ) {
					return true;
				}
			}
		}

		$integration_enabled = self::is_query_integration_enabled();

		// The filter is checking if we should _skip_ query integration...so if it's _not_ enabled
		return ! $integration_enabled;
	}

	/**
	 * Filter for ep_skip_query_integration that enabled rate limiting. Should be run last
	 *
	 * Honor any previous filters that skip query integration. If query integration is
	 * continuing, check if the query is past the ratelimiting threshold. If it is, send
	 * roughly half of the queries received to the database and half through ElasticPress.
	 *
	 * @param $skip current ep_skip_query_integration value
	 * @return bool new value of ep_skip_query_integration
	 */
	public function rate_limit_ep_query_integration( $skip ) {
		// Honor previous filters that skip query integration
		if ( $skip ) {
			return true;
		}

		// If the query count has exceeded the maximum
		// only allow half of the queries to use VIP Search
		if ( self::query_count_incr() > self::$max_query_count ) {
			// Go first so that cache entries aren't set yet for first occurrence.
			$this->maybe_log_query_ratelimiting_start();

			$this->handle_query_limiting_start_timestamp();

			$this->maybe_alert_for_prolonged_query_limiting();

			// Should be roughly half over time
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- rand() is OK, don't need a cryptographically secure value
			if ( self::$query_db_fallback_value >= rand( 1, 10 ) ) {
				$this->record_ratelimited_query_stat();
				return true;
			}
		} else {
			$this->clear_query_limiting_start_timestamp();
		}

		return false;
	}

	public function record_ratelimited_query_stat() {
		$indexable = $this->indexables->get( 'post' );

		if ( ! $indexable ) {
			return;
		}

		$statsd_mode = 'query_ratelimited';

		$url  = $this->get_current_host();
		$stat = $this->get_statsd_prefix( $url, $statsd_mode );

		$this->maybe_increment_stat( $stat );
	}

	public function maybe_alert_for_average_queue_time() {
		$indexable = $this->indexables->get( 'post' );

		if ( ! $indexable ) {
			return;
		}

		$queue_stats = $this->queue->get_queue_stats();

		if ( $queue_stats->average_wait_time > self::STALE_QUEUE_WAIT_LIMIT ) {
			$cached_queue_count = wp_cache_get( 'vip_search_queue_count_alert', 'vip' );
			if ( false === $cached_queue_count || ( $cached_queue_count && $cached_queue_count <= $queue_stats->queue_count ) ) {
				// Alert if we don't have a queue count value cached OR if the queue count isn't decreasing.
				$message = sprintf(
					'Average index queue wait time for application %d - %s is currently %d seconds. There are %d items in the queue and the oldest item is %d seconds old',
					FILES_CLIENT_SITE_ID,
					home_url(),
					$queue_stats->average_wait_time,
					$queue_stats->queue_count,
					$queue_stats->longest_wait_time
				);
				$this->alerts->send_to_chat( self::SEARCH_ALERT_SLACK_CHAT, $message, self::SEARCH_ALERT_LEVEL );

				wp_cache_set( 'vip_search_queue_count_alert', $queue_stats->queue_count, 'vip', 45 * MINUTE_IN_SECONDS );
			}
		}
	}

	public function maybe_alert_for_prolonged_query_limiting() {
		$query_limiting_start = wp_cache_get( self::QUERY_RATE_LIMITED_START_CACHE_KEY, self::SEARCH_CACHE_GROUP );

		if ( false === $query_limiting_start ) {
			return;
		}

		$query_limiting_time = $this->get_time() - $query_limiting_start;

		if ( $query_limiting_time < self::QUERY_RATE_LIMITED_ALERT_LIMIT ) {
			return;
		}

		$message = sprintf(
			'Application %d - %s has had its Elasticsearch queries rate limited for %d seconds. Half of traffic is diverted to the database when queries are rate limited.',
			FILES_CLIENT_SITE_ID,
			home_url(),
			$query_limiting_time
		);

		$this->alerts->send_to_chat( self::SEARCH_ALERT_SLACK_CHAT, $message, self::SEARCH_ALERT_LEVEL );

		trigger_error( $message, \E_USER_WARNING ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		\Automattic\VIP\Logstash\log2logstash(
			array(
				'severity' => 'warning',
				'feature'  => 'search_query_rate_limiting',
				'message'  => $message,
			)
		);
	}

	/**
	 * Alerts if field count of the sites post index is too high
	 */
	public function maybe_alert_for_field_count() {
		$indexable = $this->indexables->get( 'post' );

		if ( ! $indexable ) {
			return;
		}

		$current_field_count = $this->get_current_field_count( $indexable );

		if ( $current_field_count > self::POST_FIELD_COUNT_LIMIT ) {
			$message = sprintf(
				'The field count for post index for application %d - %s is too damn high - %d',
				FILES_CLIENT_SITE_ID,
				home_url(),
				$current_field_count
			);
			$this->alerts->send_to_chat( self::SEARCH_ALERT_SLACK_CHAT, $message, self::SEARCH_ALERT_LEVEL );
		}
	}

	/**
	 * Get the current field count of an indexable
	 *
	 * @param \ElasticPress\Indexable $indexable The indexable you want to get the current index field count of
	 * @return null|int The current field count
	 */
	public function get_current_field_count( \ElasticPress\Indexable $indexable ) {
		if ( ! $indexable || ! $indexable->index_exists() ) {
			return null;
		}

		$index_name = $indexable->get_index_name();
		$path       = "$index_name/_mapping";

		// Send a request to get all current mappings
		$raw = \ElasticPress\Elasticsearch::factory()->remote_request( $path );

		// Elasticsearch responses are in JSON
		$body = json_decode( $raw['body'], true );

		// If JSON wasn't parsed correctly
		if ( ! is_array( $body ) || empty( $body ) ) {
			return null;
		}

		// If JSON structure indicates an error
		if ( array_key_exists( 'error', $body ) ) {
			return null;
		}

		$fields = array();

		// The occurrences of 'type' in the results is equal to the number of fields in use.
		// Since the assoc array can be pretty deeply nested, array_walk_recursive and a type check was used
		array_walk_recursive( $body, function( $value, $key, $fields ) {
			if ( 'type' === $key ) {
				array_push( $fields[0], $key ); // Why reference to [0]? It doesn't want to work otherwise presently.
			}
		}, array( &$fields ) );

		return count( $fields );
	}

	/**
	 * Filter for ep_pre_request_host
	 *
	 * Return the next host in our enpoint list if it's defined. Otherwise, return the last host.
	 */
	public function filter__ep_pre_request_host( $host, $failures ) {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			return $host;
		}

		if ( ! is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return $host;
		}

		if ( 0 === count( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return $host;
		}

		return $this->get_next_host( $failures );
	}

	/**
	 * Return the next host in the list based on the current host index
	 */
	public function get_next_host( $failures ) {
		$this->current_host_index += $failures;

		return $this->get_current_host();
	}

	/**
	 * Given a list of hosts, randomly select one for load balancing purposes.
	 */
	public function get_random_host( $hosts ) {
		if ( ! $hosts || ! is_array( $hosts ) ) {
			return null;
		}

		return $hosts[ array_rand( $hosts ) ];
	}

	public function filter__ep_valid_response( $response ) {
		/*
		 * Manually set a header to indicate the search results are from elasticSearch
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! headers_sent() && isset( $_GET['ep_debug'] ) ) {
			header( 'X-ElasticPress-Search-Valid-Response: true' );
		}
		return $response;
	}

	/**
	 * Given the current facet taxonomies size and a taxonomy, determine the facet taxonomy size
	 */
	public function filter__ep_facet_taxonomies_size() {
		return 5;
	}

	/**
	 * Remove the search module from active Jetpack modules
	 */
	public function filter__jetpack_active_modules( $modules ) {
		// Flatten the array back down now that may have removed values from the middle (to keep indexes correct)
		return array_values( array_filter( $modules, function( $module ) {
			if ( 'search' === $module ) {
				return false;
			}
			return true;
		} ) ); // phpcs:ignore WordPress.WhiteSpace.DisallowInlineTabs.NonIndentTabsUsed
	}

	/**
	 * Remove the search widget from Jetpack widget include list
	 */
	public function filter__jetpack_widgets_to_include( $widgets ) {
		if ( ! is_array( $widgets ) ) {
			return $widgets;
		}

		foreach ( $widgets as $index => $file ) {
			// If the Search widget is included and it's active on a site, it will automatically re-enable the Search module,
			// even though we filtered it to off earlier, so we need to prevent it from loading
			if ( wp_endswith( $file, '/jetpack/modules/widgets/search.php' ) ) {
				unset( $widgets[ $index ] );
			}
		}

		// Flatten the array back down now that may have removed values from the middle (to keep indexes correct)
		return array_values( $widgets );
	}

	/**
	 * Filter for formatted_args in post queries (adjust the final query arguments before it gets passed to the request handler)
	 *
	 * @param array $formatted_args the prepared query arguments (payload)
	 * @param array $args query vars
	 * @param array $wp_query an instance of WP_Query
	 * @return array
	 */
	public function filter__ep_post_formatted_args( $formatted_args, $args, $wp_query ) {
		// Check if track_total_hits is set
		// Don't override it if it is
		if ( ! array_key_exists( 'track_total_hits', $formatted_args ) ) {
			$formatted_args['track_total_hits'] = true;
		}

		// TODO: remove temporary rollout environment check
		// Force the timeout for post search queries.
		$is_prod        = defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'production' === VIP_GO_APP_ENVIRONMENT;
		$global_timeout = defined( 'WP_CLI' ) && WP_CLI ? self::GLOBAL_QUERY_TIMEOUT_CLI_SEC : self::GLOBAL_QUERY_TIMEOUT_WEB_SEC;
		if ( ! isset( $formatted_args['timeout'] ) && apply_filters( 'vip_search_force_global_timeout', ! $is_prod ) ) {
			$formatted_args['timeout'] = sprintf( '%ds', $global_timeout );
		}

		return $formatted_args;
	}

	/**
	 * Set the default number of shards in the index settings
	 * 
	 */
	public function filter__ep_default_index_number_of_shards() {
		return 1;
	}

	/**
	 * Set the number of shards in the index settings for the post Indexable
	 *
	 * NOTE - this can only be changed during index creation, not on an existing index
	 * 
	 * @param $mapping Mapping
	 * @return $mapping New Mapping
	 */
	public function filter__ep_post_mapping( $mapping ) {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		if ( ! $indexable ) {
			return $mapping;
		}
		$types    = $indexable->get_indexable_post_types();
		$statuses = $indexable->get_indexable_post_status();

		$posts_count = 0;

		foreach ( $types as $type ) {
			$counts = wp_count_posts( $type );
			foreach ( $counts as $status => $count ) {
				if ( ! in_array( $status, $statuses, true ) ) {
					continue;
				}
				$posts_count += $count;
			}
		}

		if ( $posts_count > self::POST_SHARD_THRESHOLD ) {
			$mapping['settings']['index.number_of_shards'] = 4;
		}

		return $mapping;
	}

	/**
	 * Set the number of shards in the index settings for the user Indexable
	 *
	 * NOTE - this can only be changed during index creation, not on an existing index
	 * 
	 * @param $mapping Mapping
	 * @return $mapping New Mapping
	 */
	public function filter__ep_user_mapping( $mapping ) {
		$users_count = count_users();

		if ( isset( $users_count->total_users ) && ( $users_count->total_users > self::USER_SHARD_THRESHOLD ) ) {
			$mapping['settings']['index.number_of_shards'] = 4;
		}

		return $mapping;
	}

	/**
	 * Set the number of replicas for the index
	 */
	public function filter__ep_default_index_number_of_replicas() {
		return 1;
	}

	/**
	 * Given an ES url, determine the "mode" of the request for stats purposes
	 *
	 * Possible modes (matching wp.com) are manage|analyze|status|langdetect|index|delete_query|get|scroll|search
	 */
	public function get_statsd_request_mode_for_request( $url, $args ) {
		$parsed = wp_parse_url( $url );

		$path   = explode( '/', $parsed['path'] );
		$method = strtolower( $args['method'] ) ?? 'post';

		// NOTE - Not doing a switch() b/c the meaningful part of URI is not always in same spot

		if ( '_search' === end( $path ) ) {
			return 'search';
		}

		// Individual documents
		if ( '_doc' === $path[ count( $path ) - 2 ] ) {
			if ( 'delete' === $method ) {
				return 'delete';
			}

			if ( 'get' === $method ) {
				return 'get';
			}

			if ( 'put' === $method ) {
				return 'index';
			}
		}

		// Multi-get
		if ( '_mget' === end( $path ) ) {
			return 'get';
		}

		// Creating new docs
		if ( '_create' === $path[ count( $path ) - 2 ] ) {
			if ( 'put' === $method || 'post' === $method ) {
				return 'index';
			}
		}

		if ( '_doc' === end( $path ) && 'post' === $method ) {
			return 'index';
		}

		// Updating existing doc (supports partial update)
		if ( '_update' === $path[ count( $path ) - 2 ] ) {
			return 'index';
		}

		// Bulk indexing
		if ( $this->is_bulk_url( $url ) ) {
			return 'index';
		}

		// Unknown
		return 'other';
	}

	public function is_bulk_url( string $url ) {
		$parsed = wp_parse_url( $url );

		$path = explode( '/', $parsed['path'] );

		return '_bulk' === end( $path );
	}

	/**
	 * Given an ES url, determine the index name of the request
	 */
	public function get_index_name_for_url( $url ) {
		$parsed = wp_parse_url( trim( $url ) );

		$path = explode( '/', trim( $parsed['path'], '/' ) );

		// Index name is _usually_ the first part of the path
		$index_name = $path[0];

		// If it starts with underscore, then we didn't detect the index name and should return null
		if ( wp_startswith( $index_name, '_' ) ) {
			return null;
		}

		return $index_name;
	}

	/**
	 * Get the statsd stat prefix for a given "mode"
	 */
	public function get_statsd_prefix( $url, $mode = 'other' ) {
		$key_parts = array(
			'com.wordpress', // Global prefix
			'elasticsearch', // Service name
		);

		$host = wp_parse_url( $url, \PHP_URL_HOST );
		$port = wp_parse_url( $url, \PHP_URL_PORT );

		// Assume all host names are in the format es-ha-$dc.vipv2.net
		$matches = array();
		if ( preg_match( '/^es-ha[-.](.*)\.vipv2\.net$/', (string) $host, $matches ) ) {
			$key_parts[] = $matches[1]; // DC of ES node
			$key_parts[] = 'ha' . $port . '_vipgo'; // HA endpoint e.g. ha9235_vipgo
		} else {
			$key_parts[] = 'unknown';
			$key_parts[] = 'unknown';
		}

		// Break up tracking based on mode
		$key_parts[] = $mode;

		// returns prefix only e.g. 'com.wordpress.elasticsearch.bur.9235_vipgo.search'
		return implode( '.', $key_parts );
	}

	/**
	 * Filter for formatted_args in queries
	 */
	public function filter__ep_formatted_args( $formatted_args, $args ) {
		// Check for expected structure, ie: this filters first
		if ( ! isset( $formatted_args['query']['bool']['should'][0]['multi_match'] ) ) {
			return $formatted_args;
		}

		if ( defined( 'VIP_GO_APP_ID' ) ) {
			$allow_exact_search_site_ids = array(
				1284,
			);

			// Only allow exact search for whitelisted site ids
			if ( ! in_array( VIP_GO_APP_ID, $allow_exact_search_site_ids, true ) ) {
				return $formatted_args;
			}
		}

		// Replace base 'should' with 'must' and then remove the 'should' from formatted args
		$formatted_args['query']['bool']['must']                               = $formatted_args['query']['bool']['should'];
		$formatted_args['query']['bool']['must'][0]['multi_match']['operator'] = 'AND';
		unset( $formatted_args['query']['bool']['should'] );

		return $formatted_args;
	}

	public function truncate_search_string_length( &$query ) {
		if ( $query->is_search() ) {
			$search = $query->get( 's' );

			$truncated_search = substr( $search, 0, self::MAX_SEARCH_LENGTH );

			$query->set( 's', $truncated_search );
		}
	}

	/**
	 * Filter for setting decay function for date relevancy in ElasticPress
	 */
	public function filter__epwr_decay_function() {
		return 'gauss';
	}

	/**
	 * Filter for setting scale for date relevancy in ElasticPress
	 */
	public function filter__epwr_scale() {
		return '360d';
	}

	/**
	 * Filter for setting decay for date relevancy in ElasticPress
	 */
	public function filter__epwr_decay() {
		return .9;
	}

	/**
	 * Filter for setting offset for date relevancy in ElasticPress
	 */
	public function filter__epwr_offset() {
		return '0d';
	}

	/**
	 * Filter for setting score mode for date relevancy in ElasticPress
	 */
	public function filter__epwr_score_mode() {
		return 'multiply';
	}

	/**
	 * Filter for setting boost mode for date relevancy in ElasticPress
	 */
	public function filter__epwr_boost_mode() {
		return 'multiply';
	}

	/**
	 * Filter for setting weight for date relevancy in ElasticPress
	 */
	public function filter__epwr_weight() {
		return 0.001;
	}

	/**
	 * Get current Elasticsearch host
	 *
	 * @return {string|WP_Error} Returns the host on success or a WP_Error on failure
	 */
	public function get_current_host() {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			if ( defined( 'EP_HOST' ) ) {
				return EP_HOST;
			}

			return new \WP_Error( 'vip-search-no-host-found', 'No Elasticsearch hosts found' );
		}

		if ( ! is_array( VIP_ELASTICSEARCH_ENDPOINTS ) ) {
			return VIP_ELASTICSEARCH_ENDPOINTS;
		}

		if ( ! is_int( $this->current_host_index ) ) {
			$this->current_host_index = 0;
		}

		$this->current_host_index = $this->current_host_index % count( VIP_ELASTICSEARCH_ENDPOINTS );

		return VIP_ELASTICSEARCH_ENDPOINTS[ $this->current_host_index ];
	}

	/**
	 * Filter for which taxonomies are allowed to be indexed
	 */
	public function filter__ep_sync_taxonomies( $current_taxonomies, $post ) {
		if ( ! is_array( $current_taxonomies ) ) {
			$current_taxonomies = array();
		}

		// The ep_sync_taxonomies filter is a plain array of taxonomy objects...we implement this filter for convienence to prevent
		// needing to traverse the array to see if taxonomies need added or removed
		$taxonomy_names = array_unique( wp_list_pluck( $current_taxonomies, 'name' ) );

		/**
		 * Filter taxonomies to be synced with a post
		 *
		 * @hook vip_search_post_taxonomies_allow_list
		 * @param  {array} $taxonomy_names The current list of taxonomy names to sync with the post
		 * @param  {WP_Post} Post object
		 * @return  {array} New array of taxonomy names to sync with the post
		 */
		$filtered_taxonomy_names = array_unique( apply_filters( 'vip_search_post_taxonomies_allow_list', $taxonomy_names, $post ) );

		return array_map( 'get_taxonomy', $filtered_taxonomy_names );
	}

	/**
	 * Filter for reducing post meta for indexing to only the allow list
	 */
	public function filter__ep_prepare_meta_data( $current_meta, $post ) {
		if ( defined( 'FILES_CLIENT_SITE_ID' ) && in_array( FILES_CLIENT_SITE_ID, self::DISABLE_POST_META_ALLOW_LIST, true ) ) {
			return $current_meta;
		}

		if ( ! is_array( $current_meta ) ) {
			return $current_meta;
		}

		if ( \is_wp_error( $post ) || ! is_object( $post ) ) {
			return $current_meta;
		}

		$client_post_meta_allow_list = $this->get_post_meta_allow_list( $post );

		// Since we're comparing result of get_post_meta(as $current_meta), we need to do an array_intersect_key since $current_meta should be an assoc array
		$client_post_meta_allow_list_assoc = array_flip( $client_post_meta_allow_list );

		// Only include meta that matches the allow list
		$new_meta = array_intersect_key( $current_meta, $client_post_meta_allow_list_assoc );

		return $new_meta;
	}

	/**
	 * Hook for WP CLI before_add_command:elasticpress
	 */
	public function abort_elasticpress_add_command( $addition ) {
		$addition->abort( 'elasticpress command aliased to vip-search' );
	}

	/**
	 * Limit the maximum field limit from ElasticPress to 20000
	 *
	 * @param {int} $field_limit The current max field count
	 * @return {int} The new max field count
	 */
	public function limit_field_limit( $field_limit ) {
		if ( ! is_int( $field_limit ) ) {
			$field_limit = intval( $field_limit );
		}

		if ( 20000 < $field_limit ) {
			_doing_it_wrong( 'limit_field_limit', "ep_total_field_limit was set to $field_limit. Maximum value is 20000.", '5.4.2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$field_limit = 20000;
		}

		return $field_limit;
	}

	/**
	 * Check if meta is on allow list. If it isn't, set ep_skip_post_meta_sync to false
	 *
	 * @param bool $skip_sync The current value of whether the sync should be skipped or not
	 * @param WP_Post $post The post that's attempting to be reindexed
	 * @param int|array $meta_id Meta id.
	 * @param string $meta_key Meta key.
	 * @return bool The new value of whether the sync should be skipped or not
	 */
	public function filter__ep_skip_post_meta_sync( $skip_sync, $post, $meta_id, $meta_key ) {
		// Respect previous skip values
		if ( true === $skip_sync ) {
			return true;
		}

		// If post meta allow list is disabled for this site, skip the allow list check
		if ( defined( 'FILES_CLIENT_SITE_ID' ) && in_array( FILES_CLIENT_SITE_ID, self::DISABLE_POST_META_ALLOW_LIST, true ) ) {
			return $skip_sync;
		}

		// If post is invalid, respect current sync value
		if ( is_null( $post ) ) {
			return $skip_sync;
		}

		$post_meta_allow_list = $this->get_post_meta_allow_list( $post );

		if ( ! in_array( $meta_key, $post_meta_allow_list, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Adds default values for vip_search_post_meta_allow_list by combining vip-search defaults with jetpack values (to ease migration).
	 *
	 * @param {array} $keys Input post meta keys.
	 * @return {array} Post meta keys enhanced by default values.
	 */
	public function filter__vip_search_post_meta_allow_list_defaults( $keys ) {
		if ( ! is_array( $keys ) ) {
			$keys = [];
		}

		if ( $this->is_jetpack_migration() ) {
			$default_jetpack = \apply_filters( 'jetpack_sync_post_meta_whitelist', self::JETPACK_POST_META_DEFAULT_ALLOW_LIST );

			$keys = array_merge( $default_jetpack, $keys );
		}

		return array_merge( self::POST_META_DEFAULT_ALLOW_LIST, $keys );
	}

	public function is_jetpack_migration() {
		return defined( 'VIP_SEARCH_MIGRATION_SOURCE' ) && 'jetpack' === VIP_SEARCH_MIGRATION_SOURCE;
	}

	/**
	 * Processes vip_search_post_meta_allow_list. This method handles assoc array conversion if needed.
	 *
	 * @param WP_Post $post The post whose meta data is being prepared.
	 * @return array The new allow list for post_meta_indexing.
	 */
	public function get_post_meta_allow_list( $post ) {
		/**
		 * Filters the allow list used for post meta indexing
		 *
		 * @hook vip_search_post_meta_allow_list
		 * @param array $current_allow_list The current allow list for post meta indexing either as a list of post meta keys or as an associative array( e.g.: array( 'key' => true ); )
		 * @param WP_Post $post The post whose meta data is being prepared
		 * @return array $new_allow_list The new allow list for post_meta_indexing
		 */
		$post_meta_allow_list = \apply_filters( 'vip_search_post_meta_allow_list', [], $post );

		// If post meta allow list is not an array, treat it like an empty array.
		if ( ! is_array( $post_meta_allow_list ) ) {
			$post_meta_allow_list = array();
		}

		// If post meta allow list is an associative array
		if ( array_keys( $post_meta_allow_list ) !== range( 0, count( $post_meta_allow_list ) - 1 ) ) {
			/*
			 * Filter out values not set to true since the current format of the allow list as an associative array is:
			 *
			 * array (
			 * 		'key' => true,
			 * );
			 *
			 * which means that anything besides true should logically be discarded
			 */
			$post_meta_allow_list = array_filter(
				$post_meta_allow_list,
				function( $value ) {
					return true === $value;
				}
			);

			$post_meta_allow_list = array_keys( $post_meta_allow_list );
		}

		return $post_meta_allow_list;
	}

	public function filter__ep_prepare_meta_allowed_protected_keys( $keys, $post ) {
		$vip_search_allow_list_keys = $this->get_post_meta_allow_list( $post );

		return array_merge( $keys, $vip_search_allow_list_keys );
	}

	/**
	 * Hooks into the ep_$indexable_mapping hooks to add things like allocation rules
	 *
	 * Note that this hook receives the mapping and settings together
	 */
	public function filter__ep_indexable_mapping( $mapping ) {
		if ( ! is_array( $mapping['settings'] ) ) {
			return $mapping;
		}

		$origin_datacenter = $this->get_index_routing_allocation_include_dc();

		if ( $origin_datacenter ) {
			// We want all indexes to live in the site's origin datacenter
			$mapping['settings']['index.routing.allocation.include.dc'] = $origin_datacenter;
		}

		return $mapping;
	}

	public function get_index_routing_allocation_include_dc() {
		$dc = defined( 'VIP_ORIGIN_DATACENTER' ) ? VIP_ORIGIN_DATACENTER : $this->get_origin_dc_from_es_endpoint( $this->get_current_host() );

		$dc = strtolower( $dc );

		if ( ! in_array( $dc, self::ALLOWED_DATACENTERS, true ) ) {
			return null;
		}

		return $dc;
	}

	/**
	 * @return string|null
	 */
	public function get_origin_dc_from_es_endpoint( $url ) {
		$dc = '';

		if ( ! $url ) {
			return null;
		}

		$matches = array();

		// The url from the VIP_ELASTICSEARCH_ENDPOINTS constant can contain a port - so parse out just the hostname
		$host = wp_parse_url( $url, \PHP_URL_HOST );

		if ( preg_match( '/^es-ha\.(.*)\.vipv2\.net$/', $host, $matches ) ) {
			$dc = $matches[1];
		}

		$dc = strtolower( $dc );

		return $dc;
	}

	/**
	 * Since we've established that enabling the protected content feature causes attachments
	 * to be indexed, we should ensure that 'attachment' is in the indexable post types if
	 * protected content is enabled.
	 *
	 * @param array $indexable_post_types Current list indexable post types in VIP Search.
	 * @return array New list of indexable post types in VIP Search.
	 */
	public function add_attachment_to_ep_indexable_post_types( $indexable_post_types ) {
		if ( ! is_array( $indexable_post_types ) ) {
			return $indexable_post_types;
		}

		if ( ! isset( $indexable_post_types['attachment'] ) ) {
			$indexable_post_types['attachment'] = 'attachment';
		}

		return $indexable_post_types;
	}

	/**
	 * Increment the number of queries that have been passed through VIP Search
	 */
	private static function query_count_incr() {
		if ( false === wp_cache_get( self::QUERY_COUNT_CACHE_KEY, self::SEARCH_CACHE_GROUP ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( self::QUERY_COUNT_CACHE_KEY, 0, self::SEARCH_CACHE_GROUP, self::$query_count_ttl );
		}

		return wp_cache_incr( self::QUERY_COUNT_CACHE_KEY, 1, self::SEARCH_CACHE_GROUP );
	}

	/**
	 * Checks if the query limiting start timestamp is set, set it otherwise\
	 */
	public function handle_query_limiting_start_timestamp() {
		if ( false === wp_cache_get( self::QUERY_RATE_LIMITED_START_CACHE_KEY, self::SEARCH_CACHE_GROUP ) ) {
			$start_timestamp = $this->get_time();
			wp_cache_set( self::QUERY_RATE_LIMITED_START_CACHE_KEY, $start_timestamp, self::SEARCH_CACHE_GROUP );
		}
	}

	public function clear_query_limiting_start_timestamp() {
		wp_cache_delete( self::QUERY_RATE_LIMITED_START_CACHE_KEY, self::SEARCH_CACHE_GROUP );
	}

	/**
	 * Apply sampling to stats that are incremented to keep stat sending in check.
	 *
	 * @param $stat string The stat to be possibly incremented.
	 */
	public function maybe_increment_stat( $stat ) {
		if ( ! is_string( $stat ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- rand() is OK, don't need a cryptographically secure value
		if ( self::$stat_sampling_drop_value <= rand( 1, 10 ) ) {
			return;
		}

		$this->statsd->increment( $stat );
	}

	/**
	 * Apply sampling to timing stats to keep stat sending in check.
	 *
	 * @param $stat string $the stat to be possibly updated.
	 * @param $duration int The timing duration to possibly update the stat with.
	 */
	public function maybe_send_timing_stat( $stat, $duration ) {
		if ( ! is_string( $stat ) ) {
			return;
		}

		if ( ! is_numeric( $duration ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand -- rand() is OK, don't need a cryptographically secure value
		if ( self::$stat_sampling_drop_value <= rand( 1, 10 ) ) {
			return;
		}

		$duration = intval( $duration );

		$this->statsd->timing( $stat, $duration );
	}

	/**
	 * When query rate limting first begins, log this information and surface as a PHP warning
	 */
	public function maybe_log_query_ratelimiting_start() {
		if ( false === wp_cache_get( self::QUERY_RATE_LIMITED_START_CACHE_KEY, self::SEARCH_CACHE_GROUP ) ) {
			$message = sprintf(
				'Application %d - %s has triggered Elasticsearch query rate limiting, which will last up to %d seconds. Subsequent or repeat occurrences are possible. Half of traffic is diverted to the database when queries are rate limited.',
				FILES_CLIENT_SITE_ID,
				\home_url(),
				self::$query_count_ttl
			);

			$this->logger->log( 'warning', 'search_query_rate_limiting', $message );
		}
	}

	/**
	 * Check if the protected content feature is enabled in ElasticPress.
	 */
	public function is_protected_content_enabled() {
		$protected_content_feature = \ElasticPress\Features::factory()->get_registered_feature( 'protected_content' );

		if ( false === $protected_content_feature ) {
			return false;
		}

		return $protected_content_feature->is_active();
	}

	public function limit_max_result_window( $current_value ) {
		return min( $current_value, self::MAX_RESULT_WINDOW );
	}

	/**
	 * Determine whether the rate-limiting is in effect
	 */
	public static function is_rate_limited(): bool {
		return intval( wp_cache_get( self::QUERY_COUNT_CACHE_KEY, self::SEARCH_CACHE_GROUP ) ) > self::$max_query_count;
	}

	/**
	 * Get the total number of queries over the last rate-limiting window from object cache.
	 *
	 * @return integer
	 */
	public static function get_query_count(): int {
		return (int) wp_cache_get( self::QUERY_COUNT_CACHE_KEY, self::SEARCH_CACHE_GROUP );
	}

	/**
	 * Filter out default algorithm version to be used.
	 *
	 * @return string
	 */
	public function filter__ep_search_algorithm_version() {
		return '3.5';
	}

	public function maybe_change_index_version() {
		$indexables = $this->indexables->get_all();

		foreach ( $indexables as $indexable ) {
			$query_force_version_argument = sprintf( self::QUERY_FORCE_VERSION_PATTERN, $indexable->slug );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET[ $query_force_version_argument ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$normalized_version = $this->versioning->normalize_version_number( $indexable, $_GET[ $query_force_version_argument ] );

			if ( is_wp_error( $normalized_version ) ) {
				continue;
			}

			$this->versioning->set_current_version_number( $indexable, $normalized_version );
		}
	}

	public function exclude_es_query_reserved_names( $taxonomies ) {
		return array_merge( $taxonomies, self::ES_QUERY_RESERVED_NAMES );
	}

	/**
	 * Do not sync if index does not exist for Indexable.
	 * 
	 * @param {boolean} $kill Whether to kill the sync or not.
	 * @param {string} $indexable_slug Indexable slug.
	 * 
	 * @return bool 
	 */
	public function do_not_sync_if_no_index( $kill, $indexable_slug ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );
		if ( $indexable && ! $indexable->index_exists() ) {
			$kill = true;
		}
		return $kill;
	}

	public function action__init() {
		remove_action( 'wp_initialize_site', [ \ElasticPress\Indexables::factory()->get( 'post' )->sync_manager, 'action_create_blog_index' ] );
	}
}
