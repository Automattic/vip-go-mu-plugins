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

require_once( __DIR__ . '/files/acl/acl.php' );

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
			return 1073741824; // pow( 2, 30 )
		});

		if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) && true === VIP_FILESYSTEM_USE_STREAM_WRAPPER ) {
			$this->init_vip_filesystem();
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

	function init_photon() {
		$this->init_jetpack_photon_filters();
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

		// We need to make sure Photon downsize runs in `is_admin` context
		add_filter( 'jetpack_photon_admin_allow_image_downsize', '__return_true' );

		// If Photon isn't active, we need to init the necessary filters.
		// This takes care of rewriting intermediate images for us.
		Jetpack_Photon::instance();
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
