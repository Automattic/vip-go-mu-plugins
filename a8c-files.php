<?php
/*
Plugin Name: Automattic File Hosting Service
Description: Provides a hosted, distributed and fault tolerant files service
Author: Automattic
Version: 0.2
Author URI: http://automattic.com/
*/

/* Requires at least: 3.9.0
 * due to the dependancy on the filter 'wp_insert_attachment_data'
 * used to catch imports and push the files to the VIP MogileFS service
 */

if ( ! defined( 'FILE_SERVICE_ENDPOINT' ) )
	define( 'FILE_SERVICE_ENDPOINT', 'files.vipv2.net' );

define( 'LOCAL_UPLOADS', '/tmp/uploads' );

define( 'ALLOW_UNFILTERED_UPLOADS', false );

class A8C_Files {

	function __construct() {

		// Upload size limit is 1GB
		add_filter( 'upload_size_limit', function() {
			return 1073741824; // pow( 2, 30 )
		});

		// Hooks for the mu-plugin WordPress Importer
		add_filter( 'load-importer-wordpress', array( &$this, 'check_to_download_file' ), 10 );
		add_filter( 'wp_insert_attachment_data', array( &$this, 'check_to_upload_file' ), 10, 2 );

		add_filter( 'wp_unique_filename', array( $this, 'filter_unique_filename' ), 10, 4 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_filetype_check' ), 10, 4 );

		add_filter( 'upload_dir', array( &$this, 'get_upload_dir' ), 10, 1 );

		add_filter( 'wp_handle_upload', array( &$this, 'upload_file' ), 10, 2 );
		add_filter( 'wp_delete_file',   array( &$this, 'delete_file' ), 20, 1 );

		add_filter( 'wp_save_image_file',        array( &$this, 'save_image_file' ), 10, 5 );
		add_filter( 'wp_save_image_editor_file', array( &$this, 'save_image_file' ), 10, 5 );

		// Limit to certain contexts for the initial testing and roll-out.
		// This will be phased out and become the default eventually.
		$use_jetpack_photon = $this->use_jetpack_photon();
		if ( $use_jetpack_photon ) {
			$this->init_jetpack_photon_filters();
		} else {
			$this->init_vip_photon_filters();
		}

		// Automatic creation of intermediate image sizes is disabled via `wpcom_intermediate_sizes()`

		// ensure we always upload with year month folder layouts
		add_filter( 'pre_option_uploads_use_yearmonth_folders', function( $arg ) { return '1'; } );

		// ensure the correct upload URL is used even after switch_to_blog is called
		add_filter( 'option_upload_url_path', array( $this, 'upload_url_path' ), 10, 2 );
	}

	private function init_jetpack_photon_filters() {
		// The files service has Photon capabilities, but is served from the same domain.
		// Force Jetpack to use the files service instead of the default Photon domains (`i*.wp.com`) for internal files.
		// Externally hosted files continue to use the remot Photon service.
		add_filter( 'jetpack_photon_domain', function( $photon_url, $image_url ) {
			$home_url = home_url();
			if ( wp_startswith( $image_url, $home_url ) ) {
				return $home_url;
			}

			return $photon_url;
		}, 10, 2 );

		// If Jetpack dev mode is enabled, jetpack_photon_url is short-circuited.
		// This results in all images being full size (which is not ideal)
		add_filter( 'jetpack_photon_development_mode', '__return_false', 9999 );

		// The sizes metadata is not used and mostly useless on Go so let's empty it out.
		// This may need some revisiting for `srcset` handling.
		add_filter( 'wp_get_attachment_metadata', function( $data, $post_id ) {
			if ( isset( $data['sizes'] ) ) {
				$data['sizes'] = array();
			}
			return $data;
		}, 10, 2 );

		// This is our catch-all to strip dimensions from intermediate images in content.
		// Since this primarily only impacts post_content we do a little dance to add the filter early to `the_content` and then remove it later on in the same hook.
		add_filter( 'the_content', function( $content ) {
			add_filter( 'jetpack_photon_pre_image_url', [ 'A8C_Files_Utils', 'strip_dimensions_from_url_path' ] );
			return $content;
		}, 0 );

		add_filter( 'the_content', function( $content ) {
			remove_filter( 'jetpack_photon_pre_image_url', [ 'A8C_Files_Utils', 'strip_dimensions_from_url_path' ] );
			return $content;
		}, 9999999 ); // Jetpack hooks in at 6 9s (999999) so we do 7

		// If Photon isn't active, we need to init the necessary filters.
		// This takes care of rewriting intermediate images for us.
		Jetpack_Photon::instance();

		// Jetpack_Photon's downsize filter doesn't run when is_admin(), so we need to fallback to the Go filters.
		// This is temporary until Jetpack allows more easily running these filters for is_admin().
		if ( is_admin() ) {
			$this->init_vip_photon_filters();
		}
	}

	private function init_vip_photon_filters() {
		add_filter( 'image_downsize', array( &$this, 'image_resize' ), 5, 3 ); // Ensure this runs before Jetpack, when Photon is active
	}

	private function use_jetpack_photon() {
		if (  defined( 'WPCOM_VIP_USE_JETPACK_PHOTON' ) && true === WPCOM_VIP_USE_JETPACK_PHOTON ) {
			return true;
		}

		if ( isset( $_GET['jetpack-photon'] ) && 'yes' === $_GET['jetpack-photon'] ) {
			return true;
		}

		return false;
	}

	function check_to_upload_file( $data, $postarr ) {
		// Check if this is an import or a local image_editor->save
		if ( 0 < intval( $postarr['import_id'] ) || ! $this->attachment_file_exists( $postarr['guid'] ) ) {
			$url_parts = parse_url( $postarr['guid'] );
			$uploads = wp_upload_dir();
			if ( false !== $uploads['error'] )
				return $data;

			$dir_file_parts = explode( '/', $url_parts['path'] );
			if ( 3 > count( $dir_file_parts ) )
				return $data;

			$local_file_path = implode( '/', array_splice( $dir_file_parts, count( $dir_file_parts ) - 3 ) );
			$filename = constant( 'LOCAL_UPLOADS' ) . '/' . $local_file_path;

			$file = array(
				'file'  => $filename,
				'url'   => $this->get_files_service_hostname() . $url_parts['path'],
				'type'  => $postarr['post_mime_type'],
				'error' => 0,
			);

			$file = $this->upload_file( $file, 'attachment_import' );

			// did the file get renamed due to a name clash, if so record the change
			if ( basename( $url_parts['path'] ) != basename( $file['file'] ) ) {
				$data['guid'] = str_replace( basename( $url_parts['path'] ),
									basename( $file['file'] ), $data['guid'] );
			}
		}
		return $data;
	}

	// Supports the mu-plugin WordPress Importer
	// Ensures a local copy of the imported xml file is available for local reading at the final import step
	function check_to_download_file() {
		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
		if ( 2 !== $step )
			return;

		$this->id = (int) $_POST['import_id'];
		$file = get_attached_file( $this->id );
		if ( ! $file || file_exists( $file ) )
			return;

		$service_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();

		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$service_url .= '/sites/' . get_current_blog_id();
		}

		$file_url = str_ireplace( constant( 'LOCAL_UPLOADS' ),
						$service_url,
						$file );

		$opts = array(
				'http' => array(
					'method' => "GET",
					'header' => 'X-Client-Site-ID: ' . FILES_CLIENT_SITE_ID . "\r\n",
				)
			);

		$context = stream_context_create( $opts );
		$file_data = file_get_contents( $file_url, false, $context );

		if ( $file_data ) {
			$directory = pathinfo( $file )['dirname'];
			if ( ! file_exists( $directory ) )
				mkdir( $directory, 0777, true );
			file_put_contents( $file, $file_data );
			register_shutdown_function( 'unlink', $file );
		}
	}

	function save_image_file( $override, $filename, $image, $mime_type, $post_id ) {
		$return = $image->save( $filename, $mime_type );

		if ( ! $return || is_wp_error( $return ) || ! file_exists( $filename ) )
			return false;

		$url_parts = parse_url( $filename );
		if ( false !== stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) )
			$file_uri = substr( $url_parts['path'], stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) + strlen( constant( 'LOCAL_UPLOADS' ) ) );
		else
			$file_uri = '/' . $url_parts['path'];

		$service_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();
		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$service_url .= '/sites/' . get_current_blog_id();
		}

		$file = array(
				'file'  => $filename,
				'url'   => $service_url . $file_uri,
				'type'  => $mime_type,
				'error' => 0,
			);

		$this->upload_file( $file, 'editor_save' );

		return ( 0 === $file['error'] );
	}

	function get_upload_dir( $upload ) {
		$upload['path'] = constant( 'LOCAL_UPLOADS' ) . $upload['subdir'];
		$upload['basedir'] = constant( 'LOCAL_UPLOADS' );

		return $upload;
	}

	function attachment_file_exists( $file_url ) {
		$url_parts = parse_url( $file_url );
		$post_url = $this->get_files_service_hostname() . $url_parts['path'];

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
					'X-Action: file_exists',
				);

		$ch = curl_init( $post_url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );

		curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return ( 200 == $http_code );
	}

	/**
	 * Filter's the return value of `wp_unique_filename()`
	 */
	public function filter_unique_filename( $filename, $ext, $dir, $unique_filename_callback ) {
		if ( '.tmp' === $ext || '/tmp/' === $dir ) {
			return $filename;
		}

		$ext = strtolower( $ext );

		$filename = $this->_sanitize_filename( $filename, $ext );

		$check = $this->_check_uniqueness_with_backend( $filename );

		if ( 200 == $check['http_code'] ) {
			$obj = json_decode( $check['content'] );
			if ( isset(  $obj->filename ) && basename( $obj->filename ) != basename( $post_url ) ) {
				$filename = $obj->filename;
			}
		}

		return $filename;
	}

	/**
	 * Check filetype support against Mogile
	 *
	 * Leverages Mogile backend, which will return a 406 or other non-200 code if the filetype is unsupported
	 */
	public function filter_filetype_check( $filetype_data, $file, $filename, $mimes ) {
		$filename = $this->_sanitize_filename( $filename, '.' . pathinfo( $filename, PATHINFO_EXTENSION ) );

		$check = $this->_check_uniqueness_with_backend( $filename );

		// Setting `ext` and `type` to empty will fail the upload because Go doesn't allow unfiltered uploads
		// See `_wp_handle_upload()`
		if ( 200 != $check['http_code'] ) {
			$filetype_data['ext']             = '';
			$filetype_data['type']            = '';
			$filetype_data['proper_filename'] = false; // Never set this true, which leaves filename changing to dedicated methods in this class
		}

		return $filetype_data;
	}

	/**
	 * Ensure consistent filename sanitization
	 *
	 * Eventually, this should be `sanitize_file_name()` instead, but for legacy reasons, we go through this process
	 */
	private function _sanitize_filename( $filename, $ext ) {
		$filename = str_replace( $ext, '', $filename );
		$filename = str_replace( '%', '', sanitize_title_with_dashes( $filename ) ) . $ext;

		return $filename;
	}

	/**
	 * Common method to check Mogile backend for filename uniqueness
	 */
	private function _check_uniqueness_with_backend( $filename ) {
		$headers = array(
			'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
			'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
			'X-Action: unique_filename',
		);

		if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) ) {
			$file['error'] = $uploads['error'];
			return $file;
		}

		$url_parts = parse_url( $uploads['url'] . '/' . $filename );
		$file_path = $url_parts['path'];
		if ( is_multisite() &&
			preg_match( '/^\/[_0-9a-zA-Z-]+\/' . str_replace( '/', '\/', $this->get_upload_path() ) . '\/sites\/[0-9]+\//', $file_path ) ) {
			$file_path = preg_replace( '/^\/[_0-9a-zA-Z-]+/', '', $file_path );
		}

		$post_url = $this->get_files_service_hostname() . $file_path;

		$ch = curl_init( $post_url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );

		$content = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return compact( 'http_code', 'content' );
	}

	function upload_file( $details, $upload_type ) {
		if ( ! file_exists( $details['file'] ) ) {
			$details['error'] = sprintf( __( 'The specified local upload file does not exist.' ) );
			return $details;
		}

		if ( 'editor_save' == $upload_type ) {
			$post_url = $details['url'];
		} else {
			$url_parts = parse_url( $details['url'] );
			$file_path = $url_parts['path'];
			if ( is_multisite() &&
				preg_match( '/^\/[_0-9a-zA-Z-]+\/' . str_replace( '/', '\/', $this->get_upload_path() ) . '\/sites\/[0-9]+\//', $file_path ) ) {
				$file_path = preg_replace( '/^\/[_0-9a-zA-Z-]+/', '', $file_path );
				$details['url'] = $url_parts['scheme'] . '://' . $url_parts['host'] . $file_path;
			}
			$post_url = $this->get_files_service_hostname() . $file_path;
		}

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
					'Content-Type: ' . $details['type'],
					'Content-Length: ' . filesize( $details['file'] ),
					'Connection: Keep-Alive',
				);

		$stream = fopen( $details['file'], 'r' );
		$ch = curl_init( $post_url );

		curl_setopt( $ch, CURLOPT_PUT, true );
		curl_setopt( $ch, CURLOPT_INFILE, $stream );
		curl_setopt( $ch, CURLOPT_INFILESIZE, filesize( $details['file'] ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 + (int)( filesize( $details['file'] ) / 512000 ) ); // 10 plus 1 second per 500k

		curl_setopt( $ch, CURLOPT_READFUNCTION,
					function( $ch, $fd, $length ) use( $stream ) {
						$data = fread( $stream, $length );
						if ( null == $data )
							return 0;
						else
							return $data;
					});

		$ret_data = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		fclose( $stream );
		register_shutdown_function( 'unlink', $details['file'] );

		switch ( $http_code ) {
			case 200:
				if ( 0 < strlen( $ret_data ) ) {
					$obj = json_decode( $ret_data );
					if ( isset(  $obj->filename ) && basename( $obj->filename ) != basename( $post_url ) ) {
						$uploads = wp_upload_dir();
						if ( false === $uploads['error'] ) {
							@copy( $details['file'], $uploads['path'] . '/' . $obj->filename );
							register_shutdown_function( 'unlink', $uploads['path'] . '/' . $obj->filename );
						}
						$details['file'] = str_replace( basename( $post_url ), basename( $obj->filename ), $details['file'] );
					}
				}
				break;
			case 204:
				$details['error'] = sprintf( __( 'You have exceeded your file space quota.' ) );
				break;
			default:
				$details['error'] = sprintf( __( 'Error uploading the file to the remote servers: Code %d' ), $http_code );
				break;
		}

		return $details;
	}

	function delete_file( $file_name ) {
		$url_parts = parse_url( $file_name );
		if ( false !== stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) )
			$file_uri = substr( $url_parts['path'], stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) + strlen( constant( 'LOCAL_UPLOADS' ) ) );
		else
			$file_uri = '/' . $url_parts['path'];

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
				);

		$service_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();
		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$service_url .= '/sites/' . get_current_blog_id();
		}

		$ch = curl_init( $service_url . $file_uri );

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );

		curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( 200 != $http_code ) {
			error_log( sprintf( __( 'Error deleting the file from the remote servers: Code %d' ), $http_code ) );
			return;
		}

		// We successfully deleted the file, purge the file from the caches
		$invalidation_url = get_site_url() . '/' . $this->get_upload_path();
		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$invalidation_url .= '/sites/' . get_current_blog_id();
		}
		$invalidation_url .= $file_uri;

		$this->purge_file_cache( $invalidation_url, 'PURGE' );
	}

	private function purge_file_cache( $url, $method ) {
		global $file_cache_servers;

		$requests = array();

		if ( ! isset( $file_cache_servers ) || empty( $file_cache_servers ) )
			return $requests;

		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) )
			return $requests;

		foreach ( $file_cache_servers as $server  ) {
			$server = explode( ':', $server[0] );

			$uri = '/';
			if ( isset( $parsed['path'] ) )
				$uri = $parsed['path'];
			if ( isset( $parsed['query'] ) )
				$uri .= $parsed['query'];

			$requests[] = array(
				'ip'     => $server[0],
				'port'   => $server[1],
				'host'   => $parsed['host'],
				'uri'    => $uri,
				'method' => $method,
			);
		}

		$this->purge_cache_servers( $requests );
	}

	private function purge_cache_servers( $requests ) {
		$curl_multi = curl_multi_init();

		foreach ( $requests as $req ) {
			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, "http://{$req['ip']}{$req['uri']}" );
			curl_setopt( $curl, CURLOPT_PORT, $req['port'] );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$req['host']}" ) );
			curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $req['method'] );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_NOBODY, true );
			curl_setopt( $curl, CURLOPT_HEADER, true );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
			curl_multi_add_handle( $curl_multi, $curl );
		}

		$running = true;

		while ( $running ) {
			do {
				$result = curl_multi_exec( $curl_multi, $running );
			} while ( $result == CURLM_CALL_MULTI_PERFORM );

			if ( $result != CURLM_OK )
				error_log( 'curl_multi_exec() returned something different than CURLM_OK' );

			curl_multi_select( $curl_multi, 0.2 );
		}

		while ( $completed = curl_multi_info_read( $curl_multi ) ) {
			$info = curl_getinfo( $completed['handle'] );

			if ( ! $info['http_code'] && curl_error( $completed['handle'] ) )
				error_log( 'Error on: ' . $info['url'] . ' error: ' . curl_error( $completed['handle'] ) . "\n" );

			if ( '200' != $info['http_code'] )
				error_log( 'Request to ' . $info['url'] . ' returned HTTP code ' . $info['http_code'] . "\n" );

			curl_multi_remove_handle( $curl_multi, $completed['handle'] );
		}

		curl_multi_close( $curl_multi );
	}

	private function get_upload_path() {
		$upload_path = trim( get_option( 'upload_path' ) );
		if ( empty( $upload_path ) )
			return 'wp-content/uploads';
		else
			return $upload_path;
	}

	function get_files_service_hostname() {
		return 'https://' . FILE_SERVICE_ENDPOINT;
	}

	public function upload_url_path( $upload_url_path, $option ) {
		// No modifications needed outside multisite
		if ( false === is_multisite() ) {
			return $upload_url_path;
		}
		// Change the upload url path to site's URL + wp-content/uploads without trailing slash
		// Related core code: https://core.trac.wordpress.org/browser/tags/4.6.1/src/wp-includes/functions.php#L1929
		$upload_url_path = untrailingslashit( get_site_url( null, 'wp-content/uploads' ) );

		return $upload_url_path;
	}

	/**
	 * Image resizing service.  Takes place of image_downsize().
	 *
	 * @param bool $ignore Unused.
	 * @param int $id Attachment ID for image.
	 * @param array|string $size Optional, default is 'medium'. Size of image, either array or string.
	 * @return bool|array False on failure, array on success.
	 * @see image_downsize()
	 */
	function image_resize( $ignore, $id, $size ) {
		global $_wp_additional_image_sizes, $post;

		$content_width = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : null;
		$crop = false;
		$args = array();

		// For resize requests coming from an image's attachment page, override
		// the supplied $size and use the user-defined $content_width if the
		// theme-defined $content_width has been manually passed in.
		if ( is_attachment() && $id === $post->ID ) {
			if ( is_array( $size )
				 && ! empty ( $size )
				 && isset( $GLOBALS['content_width'] )
				 && $size[0] == $GLOBALS['content_width'] ) {
				$size = array( $content_width, $content_width );
			}
		}

		if ( 'tellyworth' == $size ) { // 'full' is reserved because some themes use it (see image_constrain_size_for_editor)
			$_max_w = 4096;
			$_max_h = 4096;
		} elseif ( 'thumbnail' == $size ) {
			$_max_w = get_option( 'thumbnail_size_w' );
			$_max_h = get_option( 'thumbnail_size_h' );
			if ( !$_max_w && !$_max_h ) {
				$_max_w = 128;
				$_max_h = 96;
			}
			if ( get_option( 'thumbnail_crop' ) )
				$crop = true;
		} elseif ( 'medium' == $size ) {
			$_max_w = get_option( 'medium_size_w' );
			$_max_h = get_option( 'medium_size_h' );
				if ( !$_max_w && !$_max_h ) {
					$_max_w = 300;
					$_max_h = 300;
				}
		} elseif ( 'large' == $size ) {
			$_max_w = get_option( 'large_size_w' );
			$_max_h = get_option( 'large_size_h' );
		} elseif ( is_array( $size ) ) {
			$_max_w = $w = $size[0];
			$_max_h = $h = $size[1];
		} elseif ( ! empty( $_wp_additional_image_sizes[$size] ) ) {
			$_max_w = $w = $_wp_additional_image_sizes[$size]['width'];
			$_max_h = $h = $_wp_additional_image_sizes[$size]['height'];
			$crop = $_wp_additional_image_sizes[$size]['crop'];
		} elseif ( $content_width > 0 ) {
			$_max_w = $content_width;
			$_max_h = 0;
		} else {
			$_max_w = 1024;
			$_max_h = 0;
		}

		// Constrain default image sizes to the theme's content width, if available.
		if ( $content_width > 0 && in_array( $size, array( 'thumbnail', 'medium', 'large' ) ) )
			$_max_w = min( $_max_w, $content_width );

		$resized = false;
		$img_url = wp_get_attachment_url( $id );

		/**
		 * Filter the original image Photon-compatible parameters before changes are
		 *
		 * @param array|string $args Array of Photon-compatible arguments.
		 * @param string $img_url Image URL.
		 */
		$args = apply_filters( 'vip_go_image_resize_pre_args', $args, $img_url );

		if ( ! $crop ) {
			$imagedata = wp_get_attachment_metadata( $id );

			if ( ! empty( $imagedata['width'] ) || ! empty( $imagedata['height'] ) ) {
				$h = $imagedata['height'];
				$w = $imagedata['width'];

				list ($w, $h) = wp_constrain_dimensions( $w, $h, $_max_w, $_max_h );
				if ( $w < $imagedata['width'] || $h < $imagedata['height'] )
					$resized = true;
			} else {
				$w = $_max_w;
				$h = $_max_h;
			}
		}

		if ( $crop ) {
			$constrain = false;

			$imagedata = wp_get_attachment_metadata( $id );
			if ( $imagedata ) {
				$w = $imagedata['width'];
				$h = $imagedata['height'];
			}

			if ( empty( $w ) )
				$w = $_max_w;

			if ( empty( $h ) )
				$h = $_max_h;

			// If the image width is bigger than the allowed max, scale it to match
			if ( $w >= $_max_w )
				$w = $_max_w;
			else
				$constrain = true;

			// If the image height is bigger than the allowed max, scale it to match
			if ( $h >= $_max_h )
				$h = $_max_h;
			else
				$constrain = true;

			if ( $constrain )
				list( $w, $h ) = wp_constrain_dimensions( $w, $h, $_max_w, $_max_h );

			$args['w'] = $w;
			$args['h'] = $h;

			$args['crop'] = '1';
			$resized = true;
		}
		// we want users to be able to resize full size images with tinymce.
		// the image_add_wh() filter will add the ?w= query string at display time.
		elseif ( 'full' != $size ) {
			$args['w'] = $w;
			$resized = true;
		}

		if ( is_array( $args ) ) {
			// Convert values that are arrays into strings
			foreach ( $args as $arg => $value ) {
				if ( is_array( $value ) ) {
					$args[ $arg ] = implode( ',', $value );
				}
			}
			// Encode values
			// See http://core.trac.wordpress.org/ticket/17923
			$args = rawurlencode_deep( $args );
		}
		$img_url = add_query_arg( $args, $img_url );

		return array( $img_url, $w, $h, $resized );
	}

}

class A8C_Files_Utils {
	public static function strip_dimensions_from_url_path( $url ) {
		$path = parse_url( $url, PHP_URL_PATH );

		if ( ! $path ) {
			return $url;
		}

		// Look for images ending with something like `-100x100.jpg`.
		// We include the period in the dimensions match to avoid double string replacing when the file looks something like `image-100x100-100x100.png` (we only catch the latter dimensions).
		$matched = preg_match( '#(-\d+x\d+\.)(jpg|jpeg|png|gif)$#i', $path, $parts );
		if ( $matched ) {
			// Strip off the dimensions and return the image
			$updated_url = str_replace( $parts[1], '.', $url );
			return $updated_url;
		}

		return $url;
	}	
}

function a8c_files_init() {
	new A8C_Files();
}

/**
 * Prevent WP from creating intermediate image sizes
 *
 * Function name parallels wpcom's implementation to accommodate existing code
 */
function wpcom_intermediate_sizes( $sizes ) {
	return __return_empty_array();
}

if ( defined( 'FILES_CLIENT_SITE_ID' ) && defined( 'FILES_ACCESS_TOKEN' ) ) {
	add_action( 'init', 'a8c_files_init' );
	add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
	add_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
}
