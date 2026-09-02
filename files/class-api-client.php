<?php

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Automattic\VIP\Files;

use WP_Error;

require_once __DIR__ . '/class-curl-streamer.php';
require_once __DIR__ . '/class-api-cache.php';

function new_api_client() {
	return new API_Client(
		constant( 'FILE_SERVICE_URI' ),
		constant( 'FILES_CLIENT_SITE_ID' ),
		constant( 'FILES_ACCESS_TOKEN' ),
		API_Cache::get_instance()
	);
}

class API_Client {
	const DEFAULT_REQUEST_TIMEOUT = 10;

	/**
	 * A download averaging less than this for DOWNLOAD_STALL_TIMEOUT seconds is
	 * treated as stalled and aborted, so a dead or crawling connection does not
	 * hold the request open for the whole download timeout.
	 *
	 * 256 KiB/s is half the rate calculate_transfer_timeout() already assumes
	 * (1 second per 500 KB), so a transfer that sustains it fits its own budget
	 * and the guard is more tolerant than that budget, not less.
	 */
	const DOWNLOAD_MIN_BYTES_PER_SECOND = 256 * 1024;
	const DOWNLOAD_STALL_TIMEOUT        = 30;

	/**
	 * Ceiling for a download of unknown size inside a web request, in seconds.
	 *
	 * With no cached size the timeout would otherwise scale from the upload limit,
	 * which on the platform is 4 GB and yields over two hours. A web worker must
	 * not be held that long; WP-CLI and cron keep the full size-derived value
	 * because long transfers are legitimate there.
	 */
	const DOWNLOAD_COLD_TIMEOUT_WEB = 300;

	private $user_agent;
	private $api_base;
	private $files_site_id;
	private $files_token;

	/**
	 * @var API_Cache
	 */
	private $cache;

	public function __construct( $api_base, $files_site_id, $files_token, $cache ) {
		$api_base       = untrailingslashit( $api_base );
		$this->api_base = $api_base;

		$this->files_site_id = $files_site_id;
		$this->files_token   = $files_token;

		// Add some context to the UA to simplify debugging issues
		if ( defined( 'DOING_CRON' ) && constant( 'DOING_CRON' ) ) {
			// current_filter may not be totally accurate but still better than nothing
			$current_context = sprintf( 'Cron (%s)', current_filter() );
		} elseif ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
			$current_context = 'WP_CLI';
		} else {
			$current_context = add_query_arg( [] );
		}
		$this->user_agent = sprintf( 'WPVIP/%s/Files;%s', get_bloginfo( 'version' ), esc_html( $current_context ) );

		$this->cache = $cache;
	}

	protected function is_valid_path( $path ) {
		$path = ltrim( $path, '/\\' );
		return 0 === strpos( $path, 'wp-content/uploads/' );
	}

	public function get_api_url( $path ) {
		$path = ltrim( $path, '/\\' );
		return $this->api_base . '/' . $path;
	}

	private function call_api( $path, $method, $request_args = [] ) {
		$is_valid_path = $this->is_valid_path( $path );
		if ( ! $is_valid_path ) {
			/* translators: 1: file path */
			return new WP_Error( 'invalid-path', sprintf( __( 'The specified file path (`%s`) does not begin with `/wp-content/uploads/`.' ), $path ) );
		}

		$request_url = $this->get_api_url( $path );

		$headers = [
			'X-Client-Site-ID' => $this->files_site_id,
			'X-Access-Token'   => $this->files_token,
		];

		if ( isset( $request_args['headers'] ) ) {
			$headers = array_merge( $headers, $request_args['headers'] );
		}

		$timeout = $request_args['timeout'] ?? self::DEFAULT_REQUEST_TIMEOUT;

		$request_args = array_merge( $request_args, [
			'method'     => $method,
			'headers'    => $headers,
			'timeout'    => $timeout,
			'user-agent' => $this->user_agent,
		] );

		$response = wp_remote_request( $request_url, $request_args );

		// Debug log
		if ( defined( 'VIP_FILESYSTEM_STREAM_WRAPPER_DEBUG' ) &&
			true === constant( 'VIP_FILESYSTEM_STREAM_WRAPPER_DEBUG' ) ) {
			$this->log_request( $path, $method, $request_args );
		}

		return $response;
	}

	/**
	 * Make a request with extra cURL options applied to that request only.
	 *
	 * WordPress exposes no argument for arbitrary cURL options, so they have to be
	 * attached through the global `http_api_curl` hook. That hook stays registered
	 * until it is removed, so the removal must survive an exception; otherwise the
	 * options would leak onto every later outbound request in this PHP request.
	 *
	 * @param string $path         Files Service path.
	 * @param string $method       HTTP method.
	 * @param array  $request_args Arguments for wp_remote_request().
	 * @param array  $curl_options CURLOPT_* constant => value. An empty array skips the hook.
	 * @return array|\WP_Error
	 */
	private function call_api_with_curl_options( $path, $method, $request_args, array $curl_options ) {
		if ( empty( $curl_options ) ) {
			return $this->call_api( $path, $method, $request_args );
		}

		$apply_options = function ( $curl_handle ) use ( $curl_options ) {
			foreach ( $curl_options as $option => $value ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- WordPress has no argument for these.
				curl_setopt( $curl_handle, $option, $value );
			}
		};

		add_action( 'http_api_curl', $apply_options );
		try {
			return $this->call_api( $path, $method, $request_args );
		} finally {
			remove_action( 'http_api_curl', $apply_options );
		}
	}

	public function upload_file( $local_path, $upload_path ) {
		if ( ! file_exists( $local_path ) ) {
			/* translators: 1: local file path 2: remote upload path */
			return new WP_Error( 'upload_file-failed-invalid_path', sprintf( __( 'Failed to upload file `%1$s` to `%2$s`; the file does not exist.' ), $local_path, $upload_path ) );
		}

		// Clear stat caches for the file.
		// The various stat-related functions below are cached.
		// The cached values can then lead to unexpected behavior even after the file has changed (e.g. in Curl_Streamer).
		clearstatcache( false, $local_path );

		$file_size = filesize( $local_path );
		$file_mime = self::detect_mime_type( $local_path );

		$request_timeout = $this->calculate_transfer_timeout( $file_size );

		$curl_streamer = new Curl_Streamer( $local_path );  // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_streamer
		$curl_streamer->init();
		try {
			// `init()` registers a global `http_api_curl` callback that attaches this
			// file as the request body. If an exception escaped before `deinit()`, that
			// callback would stay registered and stream this file into every later
			// outbound request in the same PHP request.
			$response = $this->call_api( $upload_path, 'PUT', [
				'headers' => [
					'Content-Type'   => $file_mime,
					'Content-Length' => $file_size,
					'Connection'     => 'Keep-Alive',
				],
				'timeout' => $request_timeout,
			] );
		} finally {
			$curl_streamer->deinit();
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 204 === $response_code ) {
			/* translators: 1: local file path 2: remote upload path */
			return new WP_Error( 'upload_file-failed-quota_reached', __( 'Failed to upload file `%1$s` to `%2$s`; file space quota has been exceeded.' ), $local_path, $upload_path );
		} elseif ( 200 !== $response_code ) {
			/* translators: 1: local file path 2: remote upload path 3: HTTP status code */
			return new WP_Error( 'upload_file-failed', sprintf( __( 'Failed to upload file `%1$s` to `%2$s` (response code: %3$d)' ), $local_path, $upload_path, $response_code ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body );

		if ( ! $response_data ) {
			/* translators: 1: local file path 2: remote upload path 3: response body */
			return new WP_Error( 'upload_file-failed-json_decode-error', sprintf( __( 'Failed to process response data after file upload for `%1$s` to `%2$s` (body: %3$s)' ), $local_path, $upload_path, $response_body ) );
		}

		// response looks like {"filename":"/wp-content/uploads/path/to/file.ext"}
		// save to cache
		$this->cache->copy_to_cache( $response_data->filename, $local_path );

		if ( isset( $response_data->mtime, $response_data->size ) ) {
			$this->cache->cache_file_stats( $response_data->filename, [
				'mtime' => (int) $response_data->mtime,
				'size'  => (int) $response_data->size,
			] );
		} else {
			// Older Files Service versions do not return metadata after an upload.
			$this->cache->remove_stats( $response_data->filename );
		}

		return $response_data->filename;
	}

	private function calculate_transfer_timeout( $file_size ) {
		// Transfers take longer than metadata calls so we need a custom timeout.
		// Use default timeout plus 1 second per 500kb.
		return self::DEFAULT_REQUEST_TIMEOUT + intval( $file_size / ( 500 * KB_IN_BYTES ) );
	}

	/**
	 * Size the download timeout from what we already know about the file.
	 *
	 * A download reports its size only once the response arrives, so there is
	 * nothing to scale from on a cold read. Reuse a cached size when a previous
	 * stat or read supplied one, and otherwise allow what the largest permitted
	 * upload would get, capped at DOWNLOAD_COLD_TIMEOUT_WEB for web requests.
	 * The stall guard aborts a dead or crawling connection well before either
	 * limit, so these ceilings only apply to a transfer still making progress.
	 */
	private function calculate_download_timeout( $file_path ) {
		$stats_found = false;
		$stats       = $this->cache->get_file_stats( $file_path, $stats_found );

		if ( $stats_found && is_array( $stats ) && isset( $stats['size'] ) ) {
			return $this->calculate_transfer_timeout( (int) $stats['size'] );
		}

		$timeout = $this->calculate_transfer_timeout( (int) wp_max_upload_size() );

		if ( ! $this->is_long_running_context() ) {
			$timeout = min( $timeout, self::DOWNLOAD_COLD_TIMEOUT_WEB );
		}

		return $timeout;
	}

	/**
	 * Whether this process may legitimately spend minutes on one transfer.
	 *
	 * @return bool True under WP-CLI or cron, false for a web request.
	 */
	protected function is_long_running_context(): bool {
		return ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) )
			|| ( defined( 'DOING_CRON' ) && constant( 'DOING_CRON' ) );
	}

	private static function detect_mime_type( string $filename ): string {
		/**
		 * `wp_check_filetype()` indirectly calls `wp_get_current_user()`, which is loaded from `pluggable.php`
		 * `pluggable.php` is loaded after all plugins. Therefore, if a plugin creates a file under `wp-content/uploads`
		 * before `pluggable.php` is loaded, we should not call `wp_check_filetype()` because it will generate
		 * a fatal error.
		 */
		if ( function_exists( 'wp_get_current_user' ) ) {
			$info = wp_check_filetype( $filename );
			$mime = $info['type'] ?? '';
		} else {
			$mime = '';
		}

		if ( empty( $mime ) && extension_loaded( 'fileinfo' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $filename );
			if ( \PHP_VERSION_ID < 80500 ) {
				finfo_close( $finfo );
			}
		}

		return is_string( $mime ) ? $mime : '';
	}

	public function get_file( $file_path ) {
		// check in cache first
		$file = $this->cache->get_file( $file_path );
		if ( $file ) {
			return $file;
		}

		// A stat, read or delete in this request already learned the file is
		// missing; trust that the same way is_file() does instead of asking again.
		$stats_found = false;
		$stats       = $this->cache->get_file_stats( $file_path, $stats_found );
		if ( $stats_found && false === $stats ) {
			/* translators: 1: file path */
			return new WP_Error( 'file-not-found', sprintf( __( 'The requested file `%1$s` does not exist (response code: 404)' ), $file_path ) );
		}

		$tmp_file = $this->cache->create_tmp_file();

		// Request args for wp_remote_request()
		// A separate `file_exists` request used to run first, both to detect a missing
		// file and to size the timeout. The download reports both by itself, so asking
		// twice only added a round trip to every cold read.
		$request_args = [
			'stream'   => true,
			'filename' => $tmp_file,
			'timeout'  => $this->calculate_download_timeout( $file_path ),
		];

		// Prevent webp => jpg transform from running
		if ( str_ends_with( strtok( $file_path, '?' ), '.webp' ) ) {
			$request_args['headers'] = [
				'Accept' => 'image/webp',
			];
		}

		// The timeout above is a ceiling. On its own it would keep a stalled or
		// crawling download open for that whole ceiling, so pair it with a minimum
		// transfer rate that aborts such a connection within the stall window.
		$curl_options = [];
		if ( defined( 'CURLOPT_LOW_SPEED_LIMIT' ) && defined( 'CURLOPT_LOW_SPEED_TIME' ) ) {
			$curl_options[ CURLOPT_LOW_SPEED_LIMIT ] = self::DOWNLOAD_MIN_BYTES_PER_SECOND;
			$curl_options[ CURLOPT_LOW_SPEED_TIME ]  = self::DOWNLOAD_STALL_TIMEOUT;
		}

		// not in cache so get from API
		$response = $this->call_api_with_curl_options( $file_path, 'GET', $request_args, $curl_options );

		// The temp file only becomes the cache's responsibility once the download
		// succeeded. On any other outcome it would otherwise outlive the request:
		// tempnam() already created it, and the transport writes error bodies into it.
		if ( is_wp_error( $response ) ) {
			$this->discard_tmp_file( $tmp_file );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 404 === $response_code ) {
			$this->discard_tmp_file( $tmp_file );
			// Remember the absence so a later existence check costs nothing.
			$this->cache->cache_file_stats( $file_path, false );
			/* translators: 1: file path */
			return new WP_Error( 'file-not-found', sprintf( __( 'The requested file `%1$s` does not exist (response code: 404)' ), $file_path ) );
		} elseif ( 200 !== $response_code ) {
			$this->discard_tmp_file( $tmp_file );
			/* translators: 1: file path 2: HTTP status code */
			return new WP_Error( 'get_file-failed', sprintf( __( 'Failed to get file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		// Derive the metadata from the download instead of asking for it separately.
		// The downloaded file is authoritative for size: Content-Length can describe
		// the encoded transfer rather than the bytes written to disk.
		clearstatcache( false, $tmp_file );
		$last_modified = wp_remote_retrieve_header( $response, 'last-modified' );
		$mtime         = $last_modified ? strtotime( $last_modified ) : false;
		$this->cache->cache_file_stats( $file_path, [
			'size'  => (int) filesize( $tmp_file ),
			'mtime' => false !== $mtime ? $mtime : (int) filemtime( $tmp_file ),
		] );

		// save to cache
		$this->cache->cache_file( $file_path, $tmp_file );

		return $tmp_file;
	}

	/**
	 * Remove a temp file that never made it into the cache.
	 */
	private function discard_tmp_file( $tmp_file ) {
		clearstatcache( false, $tmp_file );
		if ( is_file( $tmp_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			unlink( $tmp_file );
		}
	}

	public function get_file_content( $file_path ) {
		$file = $this->get_file( $file_path );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- the file is local
		return file_get_contents( $file );
	}

	public function delete_file( $file_path ) {
		$response = $this->call_api( $file_path, 'DELETE', [
			'timeout' => 3,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			/* translators: 1: file path 2: HTTP status code */
			return new WP_Error( 'delete_file-failed', sprintf( __( 'Failed to delete file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		$this->cache->remove_file( $file_path );
		$this->cache->cache_file_stats( $file_path, false );

		return true;
	}

	/**
	 * @param string $file_path File path to check
	 * @param array|null &$info Optional variable to store file info
	 * @return WP_Error|bool    true if file exists, false if not, or WP_Error on failure
	 */
	public function is_file( $file_path, &$info = null ) {
		// check in cache first
		$stats_found = false;
		$stats       = $this->cache->get_file_stats( $file_path, $stats_found );
		if ( $stats_found && false === $stats ) {
			return false;
		}

		if ( $stats_found && ! empty( $stats ) ) {
			$info = $stats;
			return true;
		}

		$response = $this->call_api( $file_path, 'GET', [
			'timeout' => 2,
			'headers' => [
				'X-Action' => 'file_exists',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			$response_body = wp_remote_retrieve_body( $response );
			$info          = json_decode( $response_body, true );

			// cache file info
			$this->cache->cache_file_stats( $file_path, $info );

			return true;
		} elseif ( 404 === $response_code ) {
			$this->cache->cache_file_stats( $file_path, false );
			return false;
		}

		/* translators: 1: file path 2: HTTP status code */
		return new WP_Error( 'is_file-failed', sprintf( __( 'Failed to check if file `%1$s` exists (response code: %2$d)' ), $file_path, $response_code ) );
	}

	/**
	 * Explicitly caches file stat data
	 *
	 * Basically an interface to API_Cache::cache_file_stats();
	 */
	public function cache_file_stats( $file_path, $info ) {
		$this->cache->cache_file_stats( $file_path, $info );
	}

	/**
	 * Use the filesystem API to generate a unique filename based on
	 * provided file path
	 *
	 * @param string    $file_path
	 *
	 * @return string|WP_Error New unique filename
	 */
	public function get_unique_filename( $file_path ) {
		$response = $this->call_api( $file_path, 'GET', [
			'timeout' => 2,
			'headers' => [
				'X-Action' => 'unique_filename',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 503 === $response_code ) {
			return new WP_Error(
				'file-service-readonly',
				__( 'Uploads are temporarily disabled due to platform maintenance. Please try again in a few minutes.' )
			);
		}

		if ( 200 !== $response_code ) {
			return new WP_Error( 'invalid-file-type',
				// translators: 1 - file path, 2 - HTTP response code
				sprintf( __( 'Failed to generate new unique file name `%1$s` (response code: %2$d)' ), $file_path, $response_code )
			);
		}

		$content = wp_remote_retrieve_body( $response );
		$obj     = json_decode( $content );

		return $obj->filename;
	}

	// Allow E_USER_NOTICE to be logged since WP blocks it by default.
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	private function allow_E_USER_NOTICE() {
		static $updated_error_reporting = false;
		if ( ! $updated_error_reporting ) {
			$current_reporting_level = error_reporting();                   // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			error_reporting( $current_reporting_level | E_USER_NOTICE );    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			$updated_error_reporting = true;
		}
	}

	private function log_request( $path, $method, $request_args ) {
		$this->allow_E_USER_NOTICE();

		$x_action = '';

		if ( isset( $request_args['headers'] ) && isset( $request_args['headers']['X-Action'] ) ) {
			$x_action = ' | X-Action:' . $request_args['headers']['X-Action'];
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error(
			sprintf( 'method:%s | path:%s%s #vip-go-streams-debug',
				esc_html( $method ),
				esc_html( $path ),
				esc_html( $x_action )
			), E_USER_NOTICE
		);
	}
}
