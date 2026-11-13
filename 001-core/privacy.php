<?php
/**
 * Core miscellaneous privacy compatibility.
 */

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink

namespace Automattic\VIP\Core\Privacy;

use WP_Error;

const EXPORT_LOCK_GROUP = 'vip_privacy_export_lock';
// Longer than generating or cleaning up an export takes, so the lock doesn't expire under its owner.
const EXPORT_LOCK_TTL = 300;

/**
 * Core privacy data export handler doesn't work by default on Go.
 *
 * It expects the `zip` module and `ZipArchive` to be available, which has to be explicitly enabled for apps.
 *
 * It also attempts to write/delete files directly in the `uploads` folder, which doesn't work because Files are hosted in our remote Files Service.
 *
 * The code here replaces the core handlers with our own implementation, which does work on Go sites.
 *
 * Note that we use `admin_init` to run late enough to remove the core action.
 */
add_action( 'admin_init', __NAMESPACE__ . '\init_privacy_compat' );

/**
 * Override the cron handler.
 *
 * Hook on `init` separately since cron context doesn't fire `admin_init` and we need a separate event.
 */
add_action( 'init', __NAMESPACE__ . '\init_privacy_compat_cleanup' );

function init_privacy_compat() {
	// Replace core's privacy data export handler with a custom one.
	remove_action( 'wp_privacy_personal_data_export_file', 'wp_privacy_generate_personal_data_export_file', 10 );
	add_action( 'wp_privacy_personal_data_export_file', __NAMESPACE__ . '\generate_personal_data_export_file' );
}

function init_privacy_compat_cleanup() {
	// Replace core's privacy data delete handler with a custom one.
	remove_action( 'wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files' );
	add_action( 'wp_privacy_delete_old_export_files', __NAMESPACE__ . '\delete_old_export_files' );
}

/**
 * This is largely a copy of core's `wp_privacy_generate_personal_data_export_file`
 *
 * And has been adapted to work with on Go:
 *  - It falls back to PclZip if ZipArchive is not available.
 *  - It tracks the generated date for an export in meta (which is then used for removal).
 *  - It uploads the generated zip to the Go Files Service.
 */
function generate_personal_data_export_file( $request_id ) {
	// Get the request.
	$request = wp_get_user_request( $request_id );

	if ( ! $request || 'export_personal_data' !== $request->action_name ) {
		wp_send_json_error( __( 'Invalid request ID when generating export file.' ) );
	}

	$email_address = $request->email;

	if ( ! is_email( $email_address ) ) {
		wp_send_json_error( __( 'Invalid email address when generating export file.' ) );
	}

	// Create the exports folder if needed.
	$exports_dir = wp_privacy_exports_dir();
	$exports_url = wp_privacy_exports_url();
	$temp_dir    = get_temp_dir();

	if ( ! wp_mkdir_p( $exports_dir ) ) {
		wp_send_json_error( __( 'Unable to create export folder.' ) );
	}

	// We don't care about the extrenuous index.html file.

	$stripped_email       = str_replace( '@', '-at-', $email_address );
	$stripped_email       = sanitize_title( $stripped_email ); // slugify the email address
	$obscura              = wp_generate_password( 32, false, false );
	$file_basename        = 'wp-personal-data-file-' . $stripped_email . '-' . $obscura;
	$html_report_filename = wp_unique_filename( $temp_dir, $file_basename . '.html' );
	$html_report_pathname = wp_normalize_path( $temp_dir . $html_report_filename );
	$json_report_filename = $file_basename . '.json';
	$json_report_pathname = wp_normalize_path( $temp_dir . $json_report_filename ); // Use temp_dir because we don't want the file generated remotely yet.

	/*
	 * Gather general data needed.
	 */

	// Title.
	$title = sprintf(
		/* translators: %s: User's email address. */
		__( 'Personal Data Export for %s' ),
		$email_address
	);

	// And now, all the Groups.
	$groups = get_post_meta( $request_id, '_export_data_grouped', true );

	// First, build an "About" group on the fly for this report.
	$about_group = array(
		/* translators: Header for the About section in a personal data export. */
		'group_label'       => _x( 'About', 'personal data group label' ),
		/* translators: Description for the About section in a personal data export. */
		'group_description' => _x( 'Overview of export report.', 'personal data group description' ),
		'items'             => array(
			'about-1' => array(
				array(
					'name'  => _x( 'Report generated for', 'email address' ),
					'value' => $email_address,
				),
				array(
					'name'  => _x( 'For site', 'website name' ),
					'value' => get_bloginfo( 'name' ),
				),
				array(
					'name'  => _x( 'At URL', 'website URL' ),
					'value' => get_bloginfo( 'url' ),
				),
				array(
					'name'  => _x( 'On', 'date/time' ),
					'value' => current_time( 'mysql' ),
				),
			),
		),
	);

	// Merge in the special about group.
	$groups = array_merge( array( 'about' => $about_group ), $groups );

	$groups_count = count( $groups );

	// Convert the groups to JSON format.
	$groups_json = wp_json_encode( $groups );

	/*
	 * Handle the JSON export.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$file = fopen( $json_report_pathname, 'w' );

	if ( false === $file ) {
		wp_send_json_error( __( 'Unable to open export file (JSON report) for writing.' ) );
	}

	fwrite( $file, '{' );
	fwrite( $file, '"' . $title . '":' );
	fwrite( $file, $groups_json );
	fwrite( $file, '}' );
	fclose( $file );

	/*
	 * Handle the HTML export.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$file = fopen( $html_report_pathname, 'w' );

	if ( false === $file ) {
		wp_send_json_error( __( 'Unable to open export file (HTML report) for writing.' ) );
	}

	fwrite( $file, "<!DOCTYPE html>\n" );
	fwrite( $file, "<html>\n" );
	fwrite( $file, "<head>\n" );
	fwrite( $file, "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n" );
	fwrite( $file, "<style type='text/css'>" );
	fwrite( $file, 'body { color: black; font-family: Arial, sans-serif; font-size: 11pt; margin: 15px auto; width: 860px; }' );
	fwrite( $file, 'table { background: #f0f0f0; border: 1px solid #ddd; margin-bottom: 20px; width: 100%; }' );
	fwrite( $file, 'th { padding: 5px; text-align: left; width: 20%; }' );
	fwrite( $file, 'td { padding: 5px; }' );
	fwrite( $file, 'tr:nth-child(odd) { background-color: #fafafa; }' );
	fwrite( $file, '.return-to-top { text-align: right; }' );
	fwrite( $file, '</style>' );
	fwrite( $file, '<title>' );
	fwrite( $file, esc_html( $title ) );
	fwrite( $file, '</title>' );
	fwrite( $file, "</head>\n" );
	fwrite( $file, "<body>\n" );
	fwrite( $file, '<h1 id="top">' . esc_html__( 'Personal Data Export' ) . '</h1>' );

	// Create TOC.
	if ( $groups_count > 1 ) {
		fwrite( $file, '<div id="table_of_contents">' );
		fwrite( $file, '<h2>' . esc_html__( 'Table of Contents' ) . '</h2>' );
		fwrite( $file, '<ul>' );
		foreach ( (array) $groups as $group_id => $group_data ) {
			$group_label       = esc_html( $group_data['group_label'] );
			$group_id_attr     = sanitize_title_with_dashes( $group_data['group_label'] . '-' . $group_id );
			$group_items_count = count( (array) $group_data['items'] );
			if ( $group_items_count > 1 ) {
				$group_label .= sprintf( ' <span class="count">(%d)</span>', $group_items_count );
			}
			fwrite( $file, '<li>' );
			fwrite( $file, '<a href="#' . esc_attr( $group_id_attr ) . '">' . $group_label . '</a>' );
			fwrite( $file, '</li>' );
		}
		fwrite( $file, '</ul>' );
		fwrite( $file, '</div>' );
	}

	// Now, iterate over every group in $groups and have the formatter render it in HTML.
	foreach ( (array) $groups as $group_id => $group_data ) {
		fwrite( $file, wp_privacy_generate_personal_data_export_group_html( $group_data, $group_id, $groups_count ) );
	}

	fwrite( $file, "</body>\n" );
	fwrite( $file, "</html>\n" );
	fclose( $file );

	/*
	 * Now, generate the ZIP.
	 *
	 * If an archive has already been generated, then remove it and reuse the filename,
	 * to avoid breaking any URLs that may have been previously sent via email.
	 */
	$error = false;

	// The archive keeps its file name, so generating it must not overlap with cleaning it up (or generating
	// it again). Wait out a cleanup, which only holds the lock for one delete; otherwise leave the existing
	// archive alone and let the admin retry.
	$lock = _lock_export( $request_id, 10 );
	if ( ! $lock ) {
		unlink( $json_report_pathname );
		unlink( $html_report_pathname );

		wp_send_json_error( __( 'This export is being generated or cleaned up. Please try again in a moment.' ) );
	}

	// Release it even if generation dies first, so a retry doesn't have to wait for it to expire.
	add_action( 'shutdown', function () use ( $request_id, $lock ) {
		_unlock_export( $request_id, $lock );
	} );

	// Another generation may have named the archive while this one waited for the lock.
	wp_cache_delete( $request_id, 'post_meta' );

	// This meta value is used from version 5.5.
	$archive_filename = get_post_meta( $request_id, '_export_file_name', true );

	// This one stored an absolute path and is used for backward compatibility.
	$archive_pathname = get_post_meta( $request_id, '_export_file_path', true );

	// If a filename meta exists, use it.
	if ( ! empty( $archive_filename ) ) {
		$archive_pathname = $exports_dir . $archive_filename;
	} elseif ( ! empty( $archive_pathname ) && is_file( $archive_pathname ) ) {
		// If a full path meta exists, use it and create the new meta value.
		$archive_filename = basename( $archive_pathname );

		update_post_meta( $request_id, '_export_file_name', $archive_filename );

		// Remove the back-compat meta values.
		delete_post_meta( $request_id, '_export_file_url' );
		delete_post_meta( $request_id, '_export_file_path' );
	} else {
		// If there's no filename or full path stored, create a new file.
		$archive_filename = $file_basename . '.zip';
		$archive_pathname = $exports_dir . $archive_filename;

		update_post_meta( $request_id, '_export_file_name', $archive_filename );
	}

	$archive_url = $exports_url . $archive_filename;

	if ( ! empty( $archive_pathname ) && is_file( $archive_pathname ) ) {
		wp_delete_file( $archive_pathname );
	}

	// Track generated time to simplify deletions.
	// We can't currently iterate through files in the Files Service so we need a way to query exports by date.
	update_post_meta( $request_id, '_vip_export_generated_time', time() );

	// Hack: ZipArchive and PclZip don't support streams.
	// So, let's force the path to use a local one in the temp dir, which will work.
	// All other references (meta) will still use the correct stream URL. 
	$local_archive_pathname = $archive_pathname;

	if ( 0 === strpos( $local_archive_pathname, 'vip://' ) ) {
		$local_archive_pathname = get_temp_dir() . substr( $archive_pathname, 6 );

		// Create the folder path.
		$local_archive_dirname     = dirname( $local_archive_pathname );
		$local_archive_dir_created = wp_mkdir_p( $local_archive_dirname );
		if ( is_wp_error( $local_archive_dir_created ) ) {
			/** @var WP_Error $local_archive_dir_created */
			wp_send_json_error( $local_archive_dir_created->get_error_message() );
		}
	}

	// Note: core deletes the file if it exists, but we can just overwrite it when we upload.

	// ZipArchive may not be available across all applications.
	// Use it if it exists, otherwise fallback to PclZip.
	if ( class_exists( '\ZipArchive' ) ) {
		$zip = new \ZipArchive(); // phpcs:ignore WordPress.Classes.ClassInstantiation.MissingParenthesis
		if ( true === $zip->open( $local_archive_pathname, \ZipArchive::CREATE ) ) {
			if ( ! $zip->addFile( $json_report_pathname, 'export.json' ) ) {
				$error = __( 'Unable to add data to JSON file.' );
			}

			if ( ! $zip->addFile( $html_report_pathname, 'index.html' ) ) {
				$error = __( 'Unable to add data to HTML file.' );
			}

			$zip->close();
		} else {
			$error = __( 'Unable to open export file (archive) for writing.' );
		}
	} else {
		$zip = _pclzip_create_file( $local_archive_pathname, $html_report_pathname );

		if ( is_wp_error( $zip ) ) {
			$error = __( 'Unable to open export file (archive) for writing.' );
		}
	}

	// Remove the JSON file.
	unlink( $json_report_pathname );

	// Remove the HTML file.
	unlink( $html_report_pathname );

	if ( $error ) {
		wp_send_json_error( $error );
	} else {
		/** This filter is documented in wp-admin/includes/file.php */
		do_action( 'wp_privacy_personal_data_export_file_created', $local_archive_pathname, $archive_url, $html_report_pathname, $request_id, $json_report_pathname );

		_upload_archive_file( $local_archive_pathname );
	}

	_unlock_export( $request_id, $lock );
}

/**
 * Lock an export request, so generating and cleaning up its archive can't overlap.
 *
 * @param int $request_id Export request ID.
 * @param int $wait       Seconds to wait for the lock.
 * @return string|false Token to release the lock with, or false if it wasn't acquired.
 */
function _lock_export( $request_id, $wait = 0 ) {
	$token    = wp_generate_uuid4();
	$deadline = microtime( true ) + $wait;

	// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- EXPORT_LOCK_TTL is 300.
	while ( ! wp_cache_add( (int) $request_id, $token, EXPORT_LOCK_GROUP, EXPORT_LOCK_TTL ) ) {
		if ( microtime( true ) >= $deadline ) {
			return false;
		}

		usleep( 250000 );
	}

	return $token;
}

/**
 * Release an export request's lock, unless it expired and someone else holds it now.
 *
 * @param int    $request_id Export request ID.
 * @param string $token      Token returned by `_lock_export()`.
 */
function _unlock_export( $request_id, $token ) {
	if ( wp_cache_get( (int) $request_id, EXPORT_LOCK_GROUP, true ) === $token ) {
		wp_cache_delete( (int) $request_id, EXPORT_LOCK_GROUP );
	}
}

function _upload_archive_file( $archive_path ) {
	// For local usage, skip the remote upload.
	// The file is already in the uploads folder.
	if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || true !== constant( 'WPCOM_IS_VIP_ENV' ) ) {
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require WPVIP_MU_PLUGIN_DIR . '/files/class-api-client.php';
	}

	// Build the path from the exports URL, since `LOCAL_UPLOADS` gives us a `/tmp` path.
	$upload_path = _get_files_service_path( wp_privacy_exports_url() . basename( $archive_path ) );

	$api_client    = \Automattic\VIP\Files\new_api_client();
	$upload_result = $api_client->upload_file( $archive_path, $upload_path );

	// Delete the local copy of the archive since it's been uploaded.
	unlink( $archive_path );

	return $upload_result;
}

/**
 * The Files Service path of an export file's URL.
 *
 * On a subdirectory multisite, a subsite's uploads URLs start with the subsite's directory, which isn't part of the file's path.
 *
 * @param string $url Export file URL.
 * @return string
 */
function _get_files_service_path( $url ) {
	$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
	$position = strpos( $path, '/wp-content/uploads/' );

	return false === $position ? $path : substr( $path, $position );
}

function _delete_archive_file( $archive_url ) {
	$archive_path = _get_files_service_path( $archive_url );

	// For local usage, just delete locally.
	if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || true !== constant( 'WPCOM_IS_VIP_ENV' ) ) {
		$local_path = wp_privacy_exports_dir() . wp_basename( $archive_path );
		if ( is_file( $local_path ) && ! unlink( $local_path ) ) {
			return new WP_Error( 'local-delete-failed', __( 'Unable to delete the local export file.' ) );
		}
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require WPVIP_MU_PLUGIN_DIR . '/files/class-api-client.php';
	}

	$api_client    = \Automattic\VIP\Files\new_api_client();
	$delete_result = $api_client->delete_file( $archive_path );

	// A file that is already gone counts as deleted, so it doesn't get retried forever.
	if ( is_wp_error( $delete_result ) && false === $api_client->is_file( $archive_path ) ) {
		return true;
	}

	return $delete_result;
}

function _ziparchive_create_file( $archive_path, $html_report_path ) {
	$archive = new \ZipArchive();

	$archive_created = $archive->open( $archive_path, \ZipArchive::CREATE );
	if ( true !== $archive_created ) {
		return new WP_Error( 'ziparchive-open-failed', __( 'Failed to create a `zip` file using `ZipArchive`' ) );
	}

	$file_added = $archive->addFile( $html_report_path, 'index.html' );
	if ( ! $file_added ) {
		$archive->close();

		return new WP_Error( 'ziparchive-add-failed', __( 'Unable to add data to export file.' ) );
	}

	$archive->close();

	return true;
}

function _pclzip_create_file( $archive_path, $html_report_path ) {
	if ( ! class_exists( 'PclZip' ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
	}

	$archive = new \PclZip( $archive_path );

	$result = $archive->create( [
		[
			PCLZIP_ATT_FILE_NAME           => $html_report_path,
			PCLZIP_ATT_FILE_NEW_SHORT_NAME => 'index.html',
		],
	], PCLZIP_OPT_REMOVE_ALL_PATH );
	if ( 0 === $result ) {
		return new WP_Error( 'pclzip-create-failed', __( 'Failed to create a `zip` file using `PclZip`' ) );
	}

	return true;
}

/**
 * This is very different from the core implementation.
 *
 * Rather than filesystem time stamps, we store the time in meta, and query against that to find old, expired requests.
 */
function delete_old_export_files() {
	global $wpdb;

	/** This filter is documented in wp-includes/functions.php */
	$expiration           = apply_filters( 'wp_privacy_export_expiration', 3 * DAY_IN_SECONDS );
	$expiration_timestamp = time() - $expiration;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- direct query to avoid the unnecessary overhead of WP_Query.
	$expired_exports = $wpdb->get_results(
		$wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta
			WHERE meta_key = '_vip_export_generated_time'
				AND meta_value <= %d
			LIMIT 100", $expiration_timestamp
		)
	);

	if ( empty( $expired_exports ) ) {
		return;
	}

	// The meta re-read below has to see a regeneration that just finished, so read it from the primary:
	// a lagging replica could still return the expired timestamp.
	if ( method_exists( $wpdb, 'send_reads_to_masters' ) ) {
		$wpdb->send_reads_to_masters();
	}

	foreach ( $expired_exports as $expired_export ) {
		$request_id = (int) $expired_export->post_id;

		// Skip exports that are being regenerated; the next run retries them.
		$lock = _lock_export( $request_id );
		if ( ! $lock ) {
			continue;
		}

		try {
			// A regeneration that finished since the query changed the timestamp and uploaded a new archive under the same name, so keep it.
			wp_cache_delete( $request_id, 'post_meta' );
			if ( get_post_meta( $request_id, '_vip_export_generated_time', true ) !== $expired_export->meta_value ) {
				continue;
			}

			// Exports since WordPress 5.5 store the file name; older ones stored the full URL.
			$file_name = get_post_meta( $request_id, '_export_file_name', true );
			$file_url  = $file_name ? wp_privacy_exports_url() . $file_name : get_post_meta( $request_id, '_export_file_url', true );

			$delete_result = $file_url ? _delete_archive_file( $file_url ) : true;
			if ( is_wp_error( $delete_result ) ) {
				// Log the request ID, not the file URL or API message: both contain the export's secret file name.
				/* translators: 1: export request ID 2: error code */
				$message = sprintf( __( 'Failed to delete expired personal data export for request %1$d: %2$s' ), $request_id, $delete_result->get_error_code() );

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( esc_html( $message ), E_USER_WARNING );

				// Keep the timestamp so the next run retries this export.
				continue;
			}

			// Stop selecting this export once its file is gone.
			delete_post_meta( $request_id, '_vip_export_generated_time', $expired_export->meta_value );
		} finally {
			_unlock_export( $request_id, $lock );
		}
	}
}

/**
 * Disable crawling for go-vip.co and go-vip.net domains.
 * 
 * @param string $output The robots.txt content.
 * @return string The modified robots.txt content.
 */
function vip_convenience_domain_robots_txt( $output ) {
	$host = $_SERVER['HTTP_HOST'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( is_vip_convenience_domain( strtolower( $_SERVER['HTTP_HOST'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$output  = "# Crawling is blocked for go-vip.co and go-vip.net domains\n";
		$output .= "User-agent: *\n";
		$output .= "Disallow: /\n";
	}

	return $output;
}
// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.robots_txt
add_filter( 'robots_txt', __NAMESPACE__ . '\vip_convenience_domain_robots_txt' );
