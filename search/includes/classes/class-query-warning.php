<?php

namespace Automattic\VIP\Search;

class Query_Warning {
	public const DEFAULT_SLOW_THRESHOLD_MS = 200;
	public const DEFAULT_DEDUPE_WINDOW_S   = 300;
	public const DEFAULT_BUDGET            = 5;

	private const MIN_SLOW_THRESHOLD_MS = 1;
	private const MAX_SLOW_THRESHOLD_MS = 5000;
	private const MIN_DEDUPE_WINDOW_S   = 60;
	private const MAX_DEDUPE_WINDOW_S   = 3600;
	private const MIN_BUDGET            = 1;
	private const MAX_BUDGET            = 100;
	private const CACHE_GROUP           = 'vip_search';

	/** @var Query_Classifier */
	private $classifier;

	/** @var callable */
	private $clock;

	/** @var callable */
	private $persistent_cache;

	public function __construct( ?Query_Classifier $classifier = null, ?callable $clock = null, ?callable $persistent_cache = null ) {
		$this->classifier       = $classifier ?? new Query_Classifier();
		$this->clock            = $clock ?? 'time';
		$this->persistent_cache = $persistent_cache ?? 'wp_using_ext_object_cache';
	}

	/**
	 * @param array|string $request_body Elasticsearch request body.
	 * @param array        $response_body Decoded Elasticsearch response.
	 * @param float        $request_ms Total Elasticsearch HTTP duration.
	 * @param array|null   $backtrace Optional backtrace for deterministic tests.
	 */
	public function maybe_emit( $request_body, array $response_body, float $request_ms, ?array $backtrace = null ): bool {
		try {
			/**
			 * Filters the total Elasticsearch HTTP duration that triggers a slow-query warning.
			 *
			 * @param int $threshold Duration in milliseconds.
			 */
			$threshold = $this->bounded_int( apply_filters( 'vip_search_slow_query_threshold_ms', self::DEFAULT_SLOW_THRESHOLD_MS ), self::DEFAULT_SLOW_THRESHOLD_MS, self::MIN_SLOW_THRESHOLD_MS, self::MAX_SLOW_THRESHOLD_MS );

			/**
			 * Filters how long identical query-family violations are deduplicated.
			 *
			 * @param int $window Duration in seconds.
			 */
			$window = $this->bounded_int( apply_filters( 'vip_search_query_warning_dedupe_window_s', self::DEFAULT_DEDUPE_WINDOW_S ), self::DEFAULT_DEDUPE_WINDOW_S, self::MIN_DEDUPE_WINDOW_S, self::MAX_DEDUPE_WINDOW_S );

			/**
			 * Filters the number of logical warning pairs admitted per application/blog and window.
			 *
			 * @param int $budget Maximum logical pairs.
			 */
			$budget  = $this->bounded_int( apply_filters( 'vip_search_query_warning_budget', self::DEFAULT_BUDGET ), self::DEFAULT_BUDGET, self::MIN_BUDGET, self::MAX_BUDGET );
			$decoded = $this->decode_body( $request_body );
			$scope   = null === $decoded ? Query_Classifier::SCOPE_UNKNOWN : $this->classifier->scope( $decoded );
			$types   = [];

			if ( $request_ms > $threshold ) {
				$types[] = 'slow_query';
			}

			if ( Query_Classifier::SCOPE_UNBOUNDED === $scope ) {
				$types[] = 'unbounded_query';
			}

			if ( [] === $types ) {
				return false;
			}

			if ( true !== call_user_func( $this->persistent_cache ) ) {
				return false;
			}

			sort( $types, SORT_STRING );
			$classified = null === $decoded
				? [
					'scope'     => Query_Classifier::SCOPE_UNKNOWN,
					'structure' => [ '_body' => 'invalid' ],
				]
				: $this->classifier->classify( $decoded );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Arguments are excluded and only customer-relative paths are retained.
			$origin      = $this->origin( $backtrace ?? debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) );
			$source      = $this->request_source();
			$context     = [
				'request_ms'       => (int) ceil( $request_ms ),
				'request_limit_ms' => $threshold,
				'engine_ms'        => isset( $response_body['took'] ) && is_numeric( $response_body['took'] ) ? (int) round( $response_body['took'] ) : null,
				'requested'        => isset( $decoded['size'] ) && is_numeric( $decoded['size'] ) ? (int) $decoded['size'] : null,
				'returned'         => isset( $response_body['hits']['hits'] ) && is_array( $response_body['hits']['hits'] ) ? count( $response_body['hits']['hits'] ) : null,
				'total_hits'       => $this->total_hits( $response_body ),
				'query_scope'      => $classified['scope'],
				'url'              => $source['url'] ? substr( $source['url'], 0, 500 ) : null,
				'source'           => $source['source'],
				'origin'           => $origin['display'],
			];
			$family_hash = $this->family_hash( $classified, $origin['key'] );
			$warning_id  = 'VSQ-' . strtoupper( substr( $family_hash, 0, 8 ) );

			if ( ! $this->should_emit( $family_hash, $types, $window, $budget ) ) {
				return false;
			}

			$technical = $this->technical_message( $warning_id, $types, $context, $window );
			$human     = $this->human_message( $warning_id, $types, $context, $window );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional customer-visible plain-text warning; dynamic values are sanitized during formatting.
			trigger_error( $technical, E_USER_WARNING );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- Intentional customer-visible plain-text warning; dynamic values are sanitized during formatting.
			trigger_error( $human, E_USER_WARNING );

			return true;
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	/** @param mixed $value Filtered value. */
	private function bounded_int( $value, int $default, int $minimum, int $maximum ): int {
		$validated = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $validated || $validated < $minimum || $validated > $maximum ) {
			return $default;
		}

		return $validated;
	}

	/** @param array|string $request_body Elasticsearch request body. */
	private function decode_body( $request_body ): ?array {
		if ( is_array( $request_body ) ) {
			return $request_body;
		}

		$decoded = is_string( $request_body ) ? json_decode( $request_body, true ) : null;

		return is_array( $decoded ) ? $decoded : null;
	}

	private function total_hits( array $response_body ): ?int {
		$total = $response_body['hits']['total'] ?? null;

		if ( is_array( $total ) ) {
			$total = $total['value'] ?? null;
		}

		return is_numeric( $total ) ? (int) $total : null;
	}

	/** @return array{url:?string,source:?string} */
	private function request_source(): array {
		if ( defined( 'WP_CLI' ) && true === constant( 'WP_CLI' ) ) {
			return [
				'url'    => null,
				'source' => 'wp_cli',
			];
		}

		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return [
				'url'    => null,
				'source' => 'wp_cron',
			];
		}

		if ( isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Both values are constrained immediately below.
			$host = preg_replace( '/[^A-Za-z0-9.\-:\[\]]/', '', wp_unslash( (string) $_SERVER['HTTP_HOST'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Control characters are removed before esc_url_raw().
			$uri = preg_replace( '/[\x00-\x1F\x7F]/', '', wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
			$url = esc_url_raw( ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri, [ 'http', 'https' ] );

			if ( '' !== $url ) {
				return [
					'url'    => $url,
					'source' => null,
				];
			}
		}

		return [
			'url'    => null,
			'source' => 'unknown',
		];
	}

	/** @return array{display:?string,key:string} */
	private function origin( array $backtrace ): array {
		foreach ( $backtrace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}

			$file = str_replace( '\\', '/', (string) $frame['file'] );
			if ( str_contains( $file, '/vendor/' ) || str_contains( $file, '/wp-content/mu-plugins/' ) ) {
				continue;
			}

			if ( ! preg_match( '#/(wp-content/(?:client-mu-plugins|plugins|themes)/.+)$#', $file, $matches ) ) {
				continue;
			}

			$relative = sanitize_text_field( $matches[1] );
			$line     = isset( $frame['line'] ) ? max( 0, (int) $frame['line'] ) : 0;
			$callable = ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . ( $frame['function'] ?? '' );

			return [
				'display' => $relative . ( $line > 0 ? ':' . $line : '' ),
				'key'     => $relative . '|' . sanitize_text_field( $callable ) . '|' . $line,
			];
		}

		return [
			'display' => null,
			'key'     => 'unknown',
		];
	}

	protected function family_hash( array $classified, string $origin_key ): string {
		$identity = [
			'application_id' => defined( 'FILES_CLIENT_SITE_ID' ) ? (string) constant( 'FILES_CLIENT_SITE_ID' ) : 'unknown',
			'blog_id'        => (string) get_current_blog_id(),
			'origin'         => $origin_key,
			'query_scope'    => $classified['scope'],
			'structure'      => $classified['structure'],
		];

		return hash( 'sha256', wp_json_encode( $identity ) );
	}

	private function should_emit( string $family_hash, array $types, int $window, int $budget ): bool {
		$signature  = hash( 'sha256', $family_hash . '|' . implode( ',', $types ) );
		$dedupe_key = 'query_warning_dedupe:' . $signature;

		if ( true !== $this->cache_add( $dedupe_key, 1, $window ) ) {
			return false;
		}

		$bucket     = (int) floor( call_user_func( $this->clock ) / $window );
		$scope      = ( defined( 'FILES_CLIENT_SITE_ID' ) ? (string) constant( 'FILES_CLIENT_SITE_ID' ) : 'unknown' ) . '|' . get_current_blog_id();
		$budget_key = 'query_warning_budget:' . hash( 'sha256', $scope . '|' . $bucket );

		if ( true === $this->cache_add( $budget_key, 1, $window + 1 ) ) {
			return true;
		}

		$count = $this->cache_incr( $budget_key );

		return is_int( $count ) && $count <= $budget;
	}

	protected function cache_add( string $key, int $value, int $ttl ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- The validated warning window is intentionally passed as the TTL.
		return wp_cache_add( $key, $value, self::CACHE_GROUP, $ttl );
	}

	/** @return int|false */
	protected function cache_incr( string $key ) {
		return wp_cache_incr( $key, 1, self::CACHE_GROUP );
	}

	private function technical_message( string $warning_id, array $types, array $context, int $window ): string {
		$location = null !== $context['url']
			? 'url="' . $this->quoted_value( $context['url'] ) . '"'
			: 'source="' . $this->quoted_value( $context['source'] ) . '"';

		return sprintf(
			'VIP_SEARCH_QUERY_WARNING v=1 warning_id=%s types=%s request_ms=%s request_limit_ms=%s engine_ms=%s requested=%s returned=%s total_hits=%s query_scope=%s %s deduplicated=true dedupe_window_s=%d',
			$warning_id,
			implode( ',', $types ),
			$this->numeric_value( $context['request_ms'] ),
			$this->numeric_value( $context['request_limit_ms'] ),
			$this->numeric_value( $context['engine_ms'] ),
			$this->numeric_value( $context['requested'] ),
			$this->numeric_value( $context['returned'] ),
			$this->numeric_value( $context['total_hits'] ),
			$context['query_scope'],
			$location,
			$window
		);
	}

	private function human_message( string $warning_id, array $types, array $context, int $window ): string {
		$subject = null !== $context['url']
			? 'A request to ' . $context['url']
			: $this->source_subject( $context['source'] );
		$origin  = null !== $context['origin']
			? ' originating from ' . $context['origin'] . '.'
			: ' during this request.';
		$repeat  = sprintf(
			'Similar occurrences are deduplicated for %s, so this warning may represent repeated requests. Warning ID: %s.',
			$this->window_phrase( $window ),
			$warning_id
		);

		if ( [ 'slow_query', 'unbounded_query' ] === $types ) {
			return sprintf(
				'%s triggered a potentially expensive VIP Search query%s The query took %d ms, above the configured warning threshold of %d ms, and did not contain a limiting search condition. An unbounded query may search the entire index and become more expensive as the site grows. Review the query and add an appropriate search term or filter. %s',
				$subject,
				$origin,
				$context['request_ms'],
				$context['request_limit_ms'],
				$repeat
			);
		}

		if ( [ 'slow_query' ] === $types ) {
			return sprintf(
				'%s triggered a slow VIP Search query%s The query took %d ms, above the configured warning threshold of %d ms. Review how this query is constructed and whether it runs more often than necessary. %s',
				$subject,
				$origin,
				$context['request_ms'],
				$context['request_limit_ms'],
				$repeat
			);
		}

		return sprintf(
			'%s triggered an unbounded VIP Search query%s The query did not contain a limiting search condition and may search the entire index, becoming more expensive as the site grows. Review the query and add an appropriate search term or filter. %s',
			$subject,
			$origin,
			$repeat
		);
	}

	/** @param int|null $value Numeric value. */
	private function numeric_value( $value ): string {
		return null === $value ? 'unknown' : (string) $value;
	}

	private function quoted_value( string $value ): string {
		return str_replace( [ '\\', '"', "\r", "\n" ], [ '\\\\', '\\"', '', '' ], $value );
	}

	private function source_subject( string $source ): string {
		if ( 'wp_cli' === $source ) {
			return 'A WP-CLI process';
		}

		if ( 'wp_cron' === $source ) {
			return 'A scheduled WordPress task';
		}

		return 'A non-HTTP WordPress process';
	}

	private function window_phrase( int $window ): string {
		if ( 0 === $window % MINUTE_IN_SECONDS ) {
			$minutes = (int) ( $window / MINUTE_IN_SECONDS );
			$words   = [
				1  => 'one',
				2  => 'two',
				3  => 'three',
				4  => 'four',
				5  => 'five',
				6  => 'six',
				7  => 'seven',
				8  => 'eight',
				9  => 'nine',
				10 => 'ten',
			];
			$number  = $words[ $minutes ] ?? (string) $minutes;

			return $number . ( 1 === $minutes ? ' minute' : ' minutes' );
		}

		return $window . ' seconds';
	}
}
