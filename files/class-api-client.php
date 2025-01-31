<?php

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Automattic\VIP\Files;

use WP_Error;

require_once __DIR__ . '/class-curl-streamer.php';
require_once __DIR__ . '/class-api-cache.php';

function new_api_client() {
	return new API_Client(
		'https://' . constant( 'FILE_SERVICE_ENDPOINT' ),
		constant( 'FILES_CLIENT_SITE_ID' ),
		constant( 'FILES_ACCESS_TOKEN' ),
		API_Cache::get_instance()
	);
}

class API_Client {
	const DEFAULT_REQUEST_TIMEOUT = 10;

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

		$request_timeout = $this->calculate_upload_timeout( $file_size );

		$curl_streamer = new Curl_Streamer( $local_path );  // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_streamer
		$curl_streamer->init();

		$response = $this->call_api( $upload_path, 'PUT', [
			'headers' => [
				'Content-Type'   => $file_mime,
				'Content-Length' => $file_size,
				'Connection'     => 'Keep-Alive',
			],
			'timeout' => $request_timeout,
		] );

		$curl_streamer->deinit();

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

		// Reset file stats cache, if any.
		// Note: the ltrim is because we store the path without the leading slash but the API returns the path with it.
		$this->cache->remove_stats( ltrim( $response_data->filename, '/' ) );

		return $response_data->filename;
	}

	private function calculate_upload_timeout( $file_size ) {
		// Uploads take longer so we need a custom timeout.
		// Use default timeout plus 1 second per 500kb.
		return self::DEFAULT_REQUEST_TIMEOUT + intval( $file_size / ( 500 * KB_IN_BYTES ) );
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
			finfo_close( $finfo );
		}

		return is_string( $mime ) ? $mime : '';
	}

	public function get_file( $file_path ) {
		// check in cache first
		$file = $this->cache->get_file( $file_path );
		if ( $file ) {
			return $file;
		}

		$tmp_file = $this->cache->create_tmp_file();

		// Request args for wp_remote_request()
		$request_args = [
			'stream'   => true,
			'filename' => $tmp_file,
		];

		// Prevent webp => jpg transform from running
		if ( str_ends_with( strtok( $file_path, '?' ), '.webp' ) ) {
			$request_args['headers'] = [
				'Accept' => 'image/webp',
			];
		}

		// not in cache so get from API
		$response = $this->call_api( $file_path, 'GET', $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 404 === $response_code ) {
			/* translators: 1: file path */
			return new WP_Error( 'file-not-found', sprintf( __( 'The requested file `%1$s` does not exist (response code: 404)' ), $file_path ) );
		} elseif ( 200 !== $response_code ) {
			/* translators: 1: file path 2: HTTP status code */
			return new WP_Error( 'get_file-failed', sprintf( __( 'Failed to get file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		// save to cache
		$this->cache->cache_file( $file_path, $tmp_file );

		return $tmp_file;
	}

	public function get_file_content( $file_path ) {
		$file = $this->get_file( $file_path );

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

		return true;
	}

	public function is_file( $file_path, &$info = null ) {
		// check in cache first
		$stats = $this->cache->get_file_stats( $file_path );
		if ( $stats ) {
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
