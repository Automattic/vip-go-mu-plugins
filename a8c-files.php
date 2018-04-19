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

require_once( __DIR__ . '/files/class-path-utils.php' );

require_once( __DIR__ . '/files/init-filesystem.php' );

require_once( __DIR__ . '/files/class-vip-filesystem.php' );

/**
 * The class use to update attachment meta data
 */
require_once __DIR__ . '/files/class-meta-updater.php';

use Automattic\VIP\Files\Path_Utils;
use Automattic\VIP\Files\VIP_Filesystem;
use Automattic\VIP\Files\Meta_Updater;

class A8C_Files {

	/**
	 * The name of the scheduled cron event to update attachment metadata
	 */
	const CRON_EVENT_NAME = 'vip_update_attachment_filesizes';

	/**
	 * Option name to mark all attachment filesize update completed
	 */
	const OPT_ALL_FILESIZE_PROCESSED = 'vip_all_attachment_filesize_processed_v2';

	/**
	 * Option name to mark next index for starting the next batch of filesize updates.
	 */
	const OPT_NEXT_FILESIZE_INDEX = 'vip_next_attachment_filesize_index_v2';

	/**
	 * Option name for storing Max ID.
	 *
	 * We do not need to keep this updated as new attachments will already have file sizes
	 * included in their meta.
	 */
	const OPT_MAX_POST_ID = 'vip_attachment_max_post_id_v2';

	function __construct() {

		// Upload size limit is 1GB
		add_filter( 'upload_size_limit', function() {
			return GB_IN_BYTES;
		} );

		// Conditionally load either the new Stream Wrapper implementation or old school a8c-files.
		// The old school implementation will be phased out soon.
		if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) && true === VIP_FILESYSTEM_USE_STREAM_WRAPPER ) {
			$this->init_vip_filesystem();
		} else {
			$this->init_legacy_filesystem();
		}

		// Initialize Photon-specific filters.
		// Wait till `init` to make sure Jetpack and the Photon module are ready.
		add_action( 'init', array( $this, 'init_photon' ) );

		// ensure we always upload with year month folder layouts
		add_filter( 'pre_option_uploads_use_yearmonth_folders', function( $arg ) { return '1'; } );

		// ensure the correct upload URL is used even after switch_to_blog is called
		add_filter( 'option_upload_url_path', array( $this, 'upload_url_path' ), 10, 2 );

		// Conditionally schedule the attachment filesize metaata update job
		if ( defined( 'VIP_FILESYSTEM_SCHEDULE_FILESIZE_UPDATE' ) && true === VIP_FILESYSTEM_SCHEDULE_FILESIZE_UPDATE ) {
			// add new cron schedule for filesize update
			add_filter( 'cron_schedules', array( $this, 'filter_cron_schedules' ), 10, 1 );

			// Schedule meta update job
			$this->schedule_update_job();
		}
	}

	/**
	 * Initializes and wires up Stream Wrapper plugin.
	 */
	private function init_vip_filesystem() {
		$vip_filesystem = new VIP_Filesystem();
		$vip_filesystem->run();
	}

	/**
	 * Initializes our legacy filter-based approach to uploads.
	 */
	private function init_legacy_filesystem() {
		// Hooks for the mu-plugin WordPress Importer
		add_filter( 'load-importer-wordpress', array( &$this, 'check_to_download_file' ), 10 );
		add_filter( 'wp_insert_attachment_data', array( &$this, 'check_to_upload_file' ), 10, 2 );

		add_filter( 'wp_unique_filename', array( $this, 'filter_unique_filename' ), 10, 4 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'filter_filetype_check' ), 10, 4 );

		add_filter( 'upload_dir', array( &$this, 'get_upload_dir' ), 10, 1 );

		add_filter( 'wp_handle_upload', array( &$this, 'upload_file' ), 10, 2 );
		add_filter( 'wp_delete_file', array( &$this, 'delete_file' ), 20, 1 );

		add_filter( 'wp_save_image_file', array( &$this, 'save_image_file' ), 10, 5 );
		add_filter( 'wp_save_image_editor_file', array( &$this, 'save_image_file' ), 10, 5 );
	}

	function init_photon() {
		// Limit to certain contexts for the initial testing and roll-out.
		// This will be phased out and become the default eventually.
		$use_jetpack_photon = $this->use_jetpack_photon();
		if ( $use_jetpack_photon ) {
			$this->init_jetpack_photon_filters();
		} else {
			$this->init_vip_photon_filters();
		}
	}

	private function init_jetpack_photon_filters() {
		if ( ! class_exists( 'Jetpack_Photon' ) ) {
			trigger_error( 'Cannot initialize Photon filters as the Jetpack_Photon class is not loaded. Please verify that Jetpack is loaded and active to restore this functionality.', E_USER_WARNING );
			return;
		}

		// The files service has Photon capabilities, but is served from the same domain.
		// Force Jetpack to use the files service instead of the default Photon domains (`i*.wp.com`) for internal files.
		// Externally hosted files continue to use the remot Photon service.
		add_filter( 'jetpack_photon_domain', [ 'A8C_Files_Utils', 'filter_photon_domain' ], 10, 2 );

		// If Jetpack dev mode is enabled, jetpack_photon_url is short-circuited.
		// This results in all images being full size (which is not ideal)
		add_filter( 'jetpack_photon_development_mode', '__return_false', 9999 );

		if ( false === is_vip_go_srcset_enabled() ) {
			add_filter( 'wp_get_attachment_metadata', function ( $data, $post_id ) {
				if ( isset( $data['sizes'] ) ) {
					$data['sizes'] = array();
				}

				return $data;
			}, 10, 2 );
		}

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

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
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
		// If a unique filename callback was used, let's just use its results.
		// `wp_unique_filename` has fired this already so we don't need to actually call it.
		if ( $unique_filename_callback && is_callable( $unique_filename_callback ) ) {
			return $filename;
		}

		if ( '.tmp' === $ext || '/tmp/' === $dir ) {
			return $filename;
		}

		$ext = strtolower( $ext );

		$filename = $this->_sanitize_filename( $filename );

		$check = $this->_check_uniqueness_with_backend( $filename );

		if ( 200 == $check['http_code'] ) {
			$obj = json_decode( $check['content'] );
			if ( isset(  $obj->filename ) && basename( $obj->filename ) != basename( $filename ) ) {
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
		$filename = $this->_sanitize_filename( $filename );

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
	 */
	private function _sanitize_filename( $filename ) {
		return sanitize_file_name( $filename );
	}

	/**
	 * Common method to return a unique filename from the VIP Go File Service using the provided filename as a starting point
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
		if ( is_multisite() ) {
			$sanitized_file_path = Path_Utils::trim_leading_multisite_directory( $file_path, $this->get_upload_path() );
			if ( false !== $sanitized_file_path ) {
				$file_path = $sanitized_file_path;
				unset( $sanitized_file_path );
			}
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
			if ( is_multisite() ) {
				$sanitized_file_path = Path_Utils::trim_leading_multisite_directory( $file_path, $this->get_upload_path() );
				if ( false !== $sanitized_file_path ) {
					$file_path = $sanitized_file_path;
					unset( $sanitized_file_path );

					$details['url'] = $url_parts['scheme'] . '://' . $url_parts['host'] . $file_path;
				}
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
		// To ensure we don't needlessly fire off deletes for all sizes of the same image, of
		// which all except the first result in 404's, keep accounting of what we've deleted.
		static $deleted_uris = array();

		$url_parts = parse_url( $file_name );
		if ( false !== stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) )
			$file_uri = substr( $url_parts['path'], stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) + strlen( constant( 'LOCAL_UPLOADS' ) ) );
		else
			$file_uri = '/' . $url_parts['path'];

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
				);

		$delete_uri = $file_uri;
		$service_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();
		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) ) {
			$service_url .= '/sites/' . get_current_blog_id();
			$delete_uri = '/sites/' . get_current_blog_id() . $delete_uri;
		}

		if ( in_array( $delete_uri, $deleted_uris ) ) {
			// This file has already been successfully deleted from the file service in this request
			return;
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
			trigger_error( sprintf( __( 'Error deleting the file %s from the remote servers: Code %d' ), $file_uri, $http_code ), E_USER_WARNING );
			return;
		}

		// Set our static so we can later recall that this file has already been deleted and purged
		$deleted_uris[] = $delete_uri;

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

		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return $requests;
		}

		$uri = '/';
		if ( isset( $parsed['path'] ) ) {
			$uri = $parsed['path'];
		}
		if ( isset( $parsed['query'] ) ) {
			$uri .= $parsed['query'];
		}

		$requests = array();

		if ( defined( 'PURGE_SERVER_TYPE' ) && 'mangle' == PURGE_SERVER_TYPE ) {
			$data = array(
				'group' => 'vip-go',
				'scope' => 'global',
				'type'  => $method,
				'uri'   => $parsed['host'] . $uri,
				'cb'    => 'nil',
			);
			$json = json_encode( $data );

			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_URL, constant( 'PURGE_SERVER_URL' ) );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $json );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen( $json )
				) );
			curl_setopt( $curl, CURLOPT_TIMEOUT, 5 );
			curl_exec( $curl );
			$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			curl_close( $curl );

			if ( 200 != $http_code ) {
				trigger_error( sprintf( __( 'Error purging %s from the cache service: Code %d' ), $url, $http_code ), E_USER_WARNING );
			}
			return;
		}

		if ( ! isset( $file_cache_servers ) || empty( $file_cache_servers ) ) {
			trigger_error( sprintf( __( 'Error purging the file cache for %s: There are no file cache servers defined.' ), $url ), E_USER_WARNING );
			return $requests;
		}

		foreach ( $file_cache_servers as $server  ) {
			$server = explode( ':', $server[0] );
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

		// Don't bother resizing non-image (and non-existent) attachment.
		// We fallthrough to core's image_downsize but that bails as well.
		$is_img = wp_attachment_is_image( $id );
		if ( ! $is_img ) {
			return false;
		}

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

	/**
	 * Filter `cron_schedules` output
	 *
	 * Add a custom schedule for a 5 minute interval
	 *
	 * @param   array   $schedule
	 *
	 * @return  mixed
	 */
	public function filter_cron_schedules( $schedule ) {
		if ( isset( $schedule[ 'vip_five_minutes' ] ) ) {
			return $schedule;
		}

		// Not actually five minutes; we want it to run faster though to get through everything.
		$schedule['vip_five_minutes'] = [
			'interval' => 180,
			'display' => __( 'Once every 3 minutes, unlike what the slug says. Originally used to be 5 mins.' ),
		];

		return $schedule;
	}

	public function schedule_update_job() {
		if ( get_option( self::OPT_ALL_FILESIZE_PROCESSED ) ) {
			if ( wp_next_scheduled( self::CRON_EVENT_NAME ) ) {
				wp_clear_scheduled_hook( self::CRON_EVENT_NAME );
			}

			return;
		}

		if (! wp_next_scheduled ( self::CRON_EVENT_NAME )) {
			wp_schedule_event(time(), 'vip_five_minutes', self::CRON_EVENT_NAME );
		}

		add_action( self::CRON_EVENT_NAME, [ $this, 'update_attachment_meta' ] );
	}

	/**
	 * Cron job to update attachment metadata with file size
	 */
	public function update_attachment_meta() {
		wpcom_vip_irc(
			'#vip-go-filesize-updates',
			sprintf( 'Starting %s on %s... $vip-go-streams-debug',
				self::CRON_EVENT_NAME,
				home_url() ),
			5 );

		if ( get_option( self::OPT_ALL_FILESIZE_PROCESSED ) ) {
			// already done. Nothing to update
			wpcom_vip_irc(
				'#vip-go-filesize-updates',
				sprintf( 'Already completed updates on %s. Exiting %s... $vip-go-streams-debug',
					home_url(),
					self::CRON_EVENT_NAME ),
				5 );
			return;
		}

		$batch_size = 3000;
		if ( defined( 'VIP_FILESYSTEM_FILESIZE_UPDATE_BATCH_SIZE' ) ) {
			$batch_size = (int) VIP_FILESYSTEM_FILESIZE_UPDATE_BATCH_SIZE;
		}
		$updater = new Meta_Updater( $batch_size );

		$max_id = (int) get_option( self::OPT_MAX_POST_ID );
		if ( ! $max_id ) {
			$max_id = $updater->get_max_id();
			update_option( self::OPT_MAX_POST_ID, $max_id, false );
		}

		$num_lookups = 0;
		$max_lookups = 10;

		$orig_start_index = $start_index = get_option( self::OPT_NEXT_FILESIZE_INDEX, 0 );
		$end_index = $start_index + $batch_size;

		do {
			if ( $start_index > $max_id ) {
				// This means all attachments have been processed so marking as done
				update_option( self::OPT_ALL_FILESIZE_PROCESSED, 1 );

				wpcom_vip_irc(
					'#vip-go-filesize-updates',
					sprintf( 'Passed max ID (%d) on %s. Exiting %s... $vip-go-streams-debug',
						$max_id,
						home_url(),
						self::CRON_EVENT_NAME
					),
					5
				);

				return;
			}

			$attachments = $updater->get_attachments( $start_index, $end_index );

			// Bump the next index in case the cron job dies before we've processed everything
			update_option( self::OPT_NEXT_FILESIZE_INDEX, $start_index, false );

			$start_index = $end_index + 1;
			$end_index = $start_index + $batch_size;

			// Avoid infinite loops
			$num_lookups++;
			if ( $num_lookups >= $max_lookups ) {
				break;
			}
		} while ( empty( $attachments ) );

		if ( $attachments ) {
			$counts = $updater->update_attachments( $attachments );
		}

		// All done, update next index option
		wpcom_vip_irc(
			'#vip-go-filesize-updates',
			sprintf( 'Batch %d to %d (of %d) completed on %s. Processed %d attachments (%s) $vip-go-streams-debug',
				$orig_start_index, $start_index, $max_id, home_url(), count( $attachments ), json_encode( $counts ) ),
			5
		);

		update_option( self::OPT_NEXT_FILESIZE_INDEX, $start_index, false );
	}

}

class A8C_Files_Utils {
	public static function filter_photon_domain( $photon_url, $image_url ) {
		$home_url = home_url();
		$site_url = site_url();

		$image_url_parsed = parse_url( $image_url );
		$home_url_parsed = parse_url( $home_url );
		$site_url_parsed = parse_url( $site_url );

		if ( $image_url_parsed['host'] === $home_url_parsed['host'] ) {
			return $home_url;
		}

		if ( $image_url_parsed['host'] === $site_url_parsed['host'] ) {
			return $site_url;
		}

		if ( wp_endswith( $image_url_parsed['host'], '.go-vip.co' ) || wp_endswith( $image_url_parsed['host'], '.go-vip.net' ) ) {
			return $image_url_parsed['scheme'] . '://' . $image_url_parsed['host'];
		}

		return $photon_url;
	}

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

/**
 * Figure out whether srcset is enabled or not. Should be run on init action
 * earliest in order to allow clients to override this via theme's functions.php
 *
 * @return bool True if VIP Go File Service compatibile srcset solution is enabled.
 */
function is_vip_go_srcset_enabled() {
	// Allow override via querystring for easy testing
	if ( isset( $_GET['disable_vip_srcset'] ) ) {
		return '0' === $_GET['disable_vip_srcset'];
	}

	$enabled = true;

	/**
	 * Filters the default state of VIP Go File Service compatible srcset solution.
	 *
	 * @param bool True if the srcset solution is turned on, False otherwise.
	 */
	return (bool) apply_filters( 'vip_go_srcset_enabled', $enabled );
}

/**
 * Inject image sizes to attachment metadata.
 *
 * @param array $data          Attachment metadata.
 * @param int   $attachment_id Attachment's post ID.
 *
 * @return array Attachment metadata.
 */
function a8c_files_maybe_inject_image_sizes( $data, $attachment_id ) {
	// Can't do much if data is empty
	if ( empty( $data ) ) {
		return $data;
	}

	// Missing some critical data we need to determine sizes, so bail.
	if ( ! isset( $data['file'] )
		|| ! isset( $data['width'] )
		|| ! isset( $data['height'] ) ) {
		return $data;
	}

	static $cached_sizes = [];

	// Don't process image sizes that we already processed.
	if ( isset( $cached_sizes[ $attachment_id ] ) ) {
		$data['sizes'] = $cached_sizes[ $attachment_id ];
		return $data;
	}

	// Skip non-image attachments
	$mime_type = get_post_mime_type( $attachment_id );
	$attachment_is_image = preg_match( '!^image/!', $mime_type );
	if ( 1 !== $attachment_is_image ) {
		return $data;
	}

	if ( ! isset( $data['sizes'] ) || ! is_array( $data['sizes'] ) ) {
		$data['sizes'] = [];
	}

	$sizes_already_exist = false === empty( $data['sizes'] );

	global $_wp_additional_image_sizes;

	if ( is_array( $_wp_additional_image_sizes ) ) {
		$available_sizes = array_keys( $_wp_additional_image_sizes );
		$known_sizes     = array_keys( $data['sizes'] );
		$missing_sizes   = array_diff( $available_sizes, $known_sizes );

		if ( $sizes_already_exist && empty( $missing_sizes ) ) {
			return $data;
		}

		$new_sizes = array();

		foreach ( $missing_sizes as $size ) {
			$new_width          = (int) $_wp_additional_image_sizes[ $size ]['width'];
			$new_height         = (int) $_wp_additional_image_sizes[ $size ]['height'];
			$new_sizes[ $size ] = array(
				'file'      => basename( $data['file'] ),
				'width'     => $new_width,
				'height'    => $new_height,
				'mime_type' => $mime_type,
			);
		}

		if ( ! empty( $new_sizes ) ) {
			$data['sizes'] = array_merge( $data['sizes'], $new_sizes );
		}
	}

	$image_sizes = new Automattic\VIP\Files\ImageSizes( $attachment_id, $data );
	$data['sizes'] = $image_sizes->generate_sizes_meta();

	$cached_sizes[ $attachment_id ] = $data['sizes'];

	return $data;
}

if ( defined( 'FILES_CLIENT_SITE_ID' ) && defined( 'FILES_ACCESS_TOKEN' ) ) {
	// Kick things off
	a8c_files_init();

	// Disable automatic creation of intermediate image sizes.
	// We generate them on-the-fly on VIP.
	add_filter( 'intermediate_image_sizes', 'wpcom_intermediate_sizes' );
	add_filter( 'intermediate_image_sizes_advanced', 'wpcom_intermediate_sizes' );
	add_filter( 'fallback_intermediate_image_sizes', 'wpcom_intermediate_sizes' );

	// Conditionally load our srcset solution during our testing period.
	add_action( 'init', function () {
		if ( true !== is_vip_go_srcset_enabled() ) {
			return;
		}

		require_once( __DIR__ . '/files/class-image.php' );
		require_once( __DIR__ . '/files/class-image-sizes.php' );

		// Load the native VIP Go srcset solution on priority of 20, allowing other plugins to set sizes earlier.
		add_filter( 'wp_get_attachment_metadata', 'a8c_files_maybe_inject_image_sizes', 20, 2 );
	}, 10, 0 );
}

/**
 * WordPress 5.3 adds "big image" processing, for images over 2560px (by default).
 * This is not needed on VIP Go since we use Photon for dynamic image work.
 */
add_filter( 'big_image_size_threshold', '__return_false' );
