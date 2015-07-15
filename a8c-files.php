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

class A8C_Files {

	function __construct() {
		// Hooks for the mu-plugin WordPress Importer
		add_filter( 'load-importer-wordpress', array( &$this, 'check_to_download_file' ), 10 );
		add_filter( 'wp_insert_attachment_data', array( &$this, 'check_to_upload_file' ), 10, 2 );

		add_filter( 'wp_handle_upload_prefilter', array( &$this, 'get_unique_filename' ), 10, 1 );
		add_filter( 'upload_dir', array( &$this, 'get_upload_dir' ), 10, 1 );

		add_filter( 'wp_handle_upload', array( &$this, 'upload_file' ), 10, 2 );
		add_filter( 'wp_delete_file',   array( &$this, 'delete_file' ), 20, 1 );

		add_filter( 'wp_save_image_file',        array( &$this, 'save_image_file' ), 10, 5 );
		add_filter( 'wp_save_image_editor_file', array( &$this, 'save_image_editor_file' ), 10, 5 );

		add_filter( 'image_downsize', array( &$this, 'image_resize' ), 10, 3 );

		// disable the automatic creation of intermediate image sizes
		add_filter( 'intermediate_image_sizes',          function( $sizes ) { return array(); } );
		add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) { return array(); } );

		// ensure we always upload with year month folder layouts
		add_filter( 'pre_option_uploads_use_yearmonth_folders', function( $arg ) { return '1'; } );
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

		$file_url = str_ireplace( constant( 'LOCAL_UPLOADS' ),
						$this->get_files_service_hostname() . '/' . $this->get_upload_path(),
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

		$file = array(
				'file'  => $filename,
				'url'   => $this->get_files_service_hostname() . '/' .
							str_ireplace( constant( 'LOCAL_UPLOADS' ), $this->get_upload_path(), $filename ),
				'type'  => $mime_type,
				'error' => 0,
			);

		$this->upload_file( $file, 'editor_save' );

		return ( 0 === $file['error'] );
	}

	function save_image_editor_file( $override, $filename, $image, $mime_type, $post_id ) {
		return $this->save_image_file( $override, $filename, $image, $mime_type, $post_id );
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
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );

		curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return ( 200 == $http_code );
	}

	function get_unique_filename( $file ) {
		$filename = strtolower( $file['name'] );
		$info = pathinfo( $filename );
		$ext = $info['extension'];
		$name = basename( $filename, ".{$ext}" );

		if( $name === ".$ext" )
			$name = '';
		$number = '';
		if ( empty( $ext ) )
			$ext = '';
		else
			$ext = strtolower( ".$ext" );

		$filename = str_replace( $ext, '', $filename );
		$filename = str_replace( '%', '', sanitize_title_with_dashes( $filename ) ) . $ext;

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
					'X-Action: unique_filename',
				);

		if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) ) {
			$file['error'] = $uploads['error'];
			return $file;
		}

		$post_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path() .
					$uploads['subdir'] . '/' . $file['name'];

		$ch = curl_init( $post_url );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );

		$content = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( 200 == $http_code ) {
			$obj = json_decode( $content );
			if ( isset(  $obj->filename ) && basename( $obj->filename ) != basename( $post_url ) )
				$file['name'] = $obj->filename;
		} else if ( 406 == $http_code ) {
			$file['error'] = __( 'The file type you uploaded is not supported.' );
		} else {
			$file['error'] = sprintf( __( 'Error getting the file name from the remote servers: Code %d' ), $http_code );
		}

		return $file;
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
			$post_url = $this->get_files_service_hostname() . $url_parts['path'];
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
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

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
		$service_url = $this->get_files_service_hostname() . '/' . $this->get_upload_path();
		if ( false !== stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) )
			$file_uri = substr( $url_parts['path'], stripos( $url_parts['path'], constant( 'LOCAL_UPLOADS' ) ) + strlen( constant( 'LOCAL_UPLOADS' ) ) );
		else
			$file_uri = '/' . $url_parts['path'];

		$headers = array(
					'X-Client-Site-ID: ' . constant( 'FILES_CLIENT_SITE_ID' ),
					'X-Access-Token: ' . constant( 'FILES_ACCESS_TOKEN' ),
				);

		$ch = curl_init( $service_url . $file_uri );

		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

		curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( 200 != $http_code ) {
			error_log( sprintf( __( 'Error deleting the file from the remote servers: Code %d' ), $http_code ) );
			return;
		}

		// We successfully deleted the file, purge the file from the caches
		$file_url = get_site_url() . '/' . $this->get_upload_path() . $file_uri;
		$this->purge_file_cache( $file_url, 'PURGE' );
	}

	private function purge_file_cache( $url, $method ) {
		global $file_cache_servers;

		if ( ! isset( $file_cache_servers ) || empty( $file_cache_servers ) )
			return $requests;

		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) )
			return $requests;

		$requests = array();

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

			$img_url = add_query_arg( 'w', $w, $img_url );
			$img_url = add_query_arg( 'h', $h, $img_url );

			$img_url = add_query_arg( 'crop', '1', $img_url );
			$resized = true;
		}
		// we want users to be able to resize full size images with tinymce.
		// the image_add_wh() filter will add the ?w= query string at display time.
		elseif ( 'full' != $size ) {
			$img_url = add_query_arg( 'w', $w, $img_url );
		}

		return array( $img_url, $w, $h, $resized );
	}

}

function a8c_files_init() {
	new A8C_Files();
}

if ( defined( 'FILES_CLIENT_SITE_ID' ) && defined( 'FILES_ACCESS_TOKEN' ) )
	add_action( 'init', 'a8c_files_init' );

