<?php

namespace Automattic\VIP\Core\Privacy;

use WP_Error;

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
	$request = wp_get_user_request_data( $request_id );

	// Get the export file URL.
	$exports_url      = wp_privacy_exports_url();
	$export_file_name = get_post_meta( $request_id, '_export_file_name', true );

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
	$temp_dir = get_temp_dir();

	$result = wp_mkdir_p( $exports_dir );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	// We don't care about the extrenuous index.html file.

	$stripped_email       = str_replace( '@', '-at-', $email_address );
	$stripped_email       = sanitize_title( $stripped_email ); // slugify the email address
	$obscura              = wp_generate_password( 32, false, false );
	$file_basename        = 'wp-personal-data-file-' . $stripped_email . '-' . $obscura;
	$html_report_filename = $file_basename . '.html';
	// Use temp_dir because we don't want the file generated remotely yet.
	$html_report_pathname = wp_normalize_path( $temp_dir . $html_report_filename );

	$file = fopen( $html_report_pathname, 'w' );
	if ( false === $file ) {
		wp_send_json_error( __( 'Unable to open export file (HTML report) for writing.' ) );
	}

	$title = sprintf(
		/* translators: %s: user's e-mail address */
		__( 'Personal Data Export for %s' ),
		$email_address
	);

	// Open HTML.
	fwrite( $file, "<!DOCTYPE html>\n" );
	fwrite( $file, "<html>\n" );

	// Head.
	fwrite( $file, "<head>\n" );
	fwrite( $file, "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />\n" );
	fwrite( $file, "<style type='text/css'>" );
	fwrite( $file, "body { color: black; font-family: Arial, sans-serif; font-size: 11pt; margin: 15px auto; width: 860px; }" );
	fwrite( $file, "table { background: #f0f0f0; border: 1px solid #ddd; margin-bottom: 20px; width: 100%; }" );
	fwrite( $file, "th { padding: 5px; text-align: left; width: 20%; }" );
	fwrite( $file, "td { padding: 5px; }" );
	fwrite( $file, "tr:nth-child(odd) { background-color: #fafafa; }" );
	fwrite( $file, "</style>" );
	fwrite( $file, "<title>" );
	fwrite( $file, esc_html( $title ) );
	fwrite( $file, "</title>" );
	fwrite( $file, "</head>\n" );

	// Body.
	fwrite( $file, "<body>\n" );

	// Heading.
	fwrite( $file, "<h1>" . esc_html__( 'Personal Data Export' ) . "</h1>" );

	// And now, all the Groups.
	$groups = get_post_meta( $request_id, '_export_data_grouped', true );

	// First, build an "About" group on the fly for this report.
	$about_group = array(
		'group_label' => __( 'About' ),
		'items'       => array(
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

	// Now, iterate over every group in $groups and have the formatter render it in HTML.
	foreach ( (array) $groups as $group_id => $group_data ) {
		fwrite( $file, wp_privacy_generate_personal_data_export_group_html( $group_data ) );
	}

	fwrite( $file, "</body>\n" );

	// Close HTML.
	fwrite( $file, "</html>\n" );
	fclose( $file );

	/*
	 * Now, generate the ZIP.
	 *
	 * If an archive has already been generated, then remove it and reuse the
	 * filename, to avoid breaking any URLs that may have been previously sent
	 * via email.
	 */
	$error = false;

	// This postmeta is used from version 5.4.
	$archive_filename = get_post_meta( $request_id, '_export_file_name', true );

	// These are used for backwards compatibility.
	$archive_url      = get_post_meta( $request_id, '_export_file_url', true );
	$archive_pathname = get_post_meta( $request_id, '_export_file_path', true );
	// If archive_filename exists, make sure to remove deprecated postmeta.
	if ( ! empty( $archive_filename ) ) {
		$archive_pathname = $exports_dir . $archive_filename;
		$archive_url      = $exports_url . $archive_filename;

		// Remove the deprecated postmeta.
		delete_post_meta( $request_id, '_export_file_url' );
		delete_post_meta( $request_id, '_export_file_path' );
	} elseif ( ! empty( $archive_pathname ) ) {
		// Check if archive_pathname exists. If not, create the new postmeta and remove the deprecated.
		$archive_filename = basename( $archive_pathname );
		$archive_url      = $exports_url . $archive_filename;

		// Add the new postmeta that is used since version 5.4.
		update_post_meta( $request_id, '_export_file_name', wp_normalize_path( $archive_filename ) );

		// Remove the deprecated postmeta.
		delete_post_meta( $request_id, '_export_file_url' );
		delete_post_meta( $request_id, '_export_file_path' );
	} else {
		// If there's no archive_filename or archive_pathname create a new one.
		$archive_filename = $file_basename . '.zip';
		$archive_url      = $exports_url . $archive_filename;
		$archive_pathname = $exports_dir . $archive_filename;

		// Add the new postmeta that is used since version 5.4.
		update_post_meta( $request_id, '_export_file_name', wp_normalize_path( $archive_filename ) );

		// Remove the deprecated postmeta.
		delete_post_meta( $request_id, '_export_file_url' );
		delete_post_meta( $request_id, '_export_file_path' );
	}

	// Track generated time to simplify deletions.
	// We can't currently iterate through files in the Files Service so we need a way to query exports by date.
	update_post_meta( $request_id, '_vip_export_generated_time', time() );

	$local_archive_pathname = $archive_pathname;
	// Hack: ZipArchive and PclZip don't support streams.
	// So, let's force the path to use a local one in the temp dir, which will work.
	// All other references (meta) will still use the correct stream URL. 
	if ( 0 === strpos( $local_archive_pathname, 'vip://' ) ) {
		$local_archive_pathname = get_temp_dir() . substr( $archive_pathname, 6 );

		// Create the folder path
		$local_archive_dirname = dirname( $local_archive_pathname );
		$local_archive_dir_created = wp_mkdir_p( $local_archive_dirname );
		if ( is_wp_error( $local_archive_dir_created ) ) {
			wp_send_json_error( $local_archive_dir_created->get_error_message() );
		}
	}

	// Note: core deletes the file if it exists, but we can just overwrite it when we upload.

	// ZipArchive may not be available across all applications.
	// Use it if it exists, otherwise fallback to PclZip.
	if ( class_exists( '\ZipArchive' ) ) {
		$zip_result = _ziparchive_create_file( $local_archive_pathname, $html_report_pathname );
	} else {
		$zip_result = _pclzip_create_file( $local_archive_pathname, $html_report_pathname );
	}

	// Remove the HTML file since it's not needed anymore.
	unlink( $html_report_pathname );

	if ( is_wp_error( $zip_result ) ) {
		/* translators: %s: error message */
		$error = sprintf( __( 'Unable to generate export file (archive) for writing: %s' ), $zip_result->get_error_message() );
	} else {
		/** This filter is documented in wp-admin/includes/file.php */
		do_action( 'wp_privacy_personal_data_export_file_created', $local_archive_pathname, $archive_url, $html_report_pathname, $request_id );

		$upload_result = _upload_archive_file( $local_archive_pathname );
		if ( is_wp_error( $upload_result ) ) {
			$error = sprintf( __( 'Failed to upload export file (archive): %s' ), $upload_result->get_error_message() );
		}
	}

	if ( $error ) {
		wp_send_json_error( $error );
	}
}

function _upload_archive_file( $archive_path ) {
	// For local usage, skip the remote upload.
	// The file is already in the uploads folder.
	if ( true !== WPCOM_IS_VIP_ENV ) {
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require( WPMU_PLUGIN_DIR . '/files/class-api-client.php' );
	}

	// Build the `/wp-content/` version of the exports path since `LOCAL_UPLOADS` gives us a `/tmp` path.
	// Hard-coded and full of assumptions for now.
	// TODO: need a cleaner approach for this. Can probably borrow `WP_Filesystem_VIP_Uploads::sanitize_uploads_path()`.
	$archive_file = basename( $archive_path );
	$exports_url = wp_privacy_exports_url();
	$wp_content_strpos = strpos( $exports_url, '/wp-content/uploads/' );
	$upload_path = trailingslashit( substr( $exports_url, $wp_content_strpos ) ) . $archive_file;

	$api_client = \Automattic\VIP\Files\new_api_client();
	$upload_result = $api_client->upload_file( $archive_path, $upload_path );

	// Delete the local copy of the archive since it's been uploaded.
	unlink( $archive_path );

	return $upload_result;
}

function _delete_archive_file( $archive_url ) {
	$archive_path = parse_url( $archive_url, PHP_URL_PATH );

	// For local usage, just delete locally.
	if ( true !== WPCOM_IS_VIP_ENV ) {
		unlink( WP_CONTENT_DIR . $archive_path );
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require( WPMU_PLUGIN_DIR . '/files/class-api-client.php' );
	}

	$api_client = \Automattic\VIP\Files\new_api_client();
	return $api_client->delete_file( $archive_path );
}

function _ziparchive_create_file( $archive_path, $html_report_path ) {
	$archive = new \ZipArchive;

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
		require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
	}

	$archive = new \PclZip( $archive_path );

	$result = $archive->create( [
		[
			PCLZIP_ATT_FILE_NAME => $html_report_path,
			PCLZIP_ATT_FILE_NEW_SHORT_NAME => 'index.html',
		]
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
	$expiration = apply_filters( 'wp_privacy_export_expiration', 3 * DAY_IN_SECONDS );
	$expiration_timestamp = time() - $expiration;

	// Direct query to avoid the unnecessary overhead of WP_Query.
	$sql = $wpdb->prepare( "SELECT pm.meta_value FROM $wpdb->postmeta AS pm
	INNER JOIN $wpdb->postmeta AS expiry
		ON expiry.post_id = pm.post_id
		AND expiry.meta_key = '_vip_export_generated_time'
		AND expiry.meta_value <= %d
	WHERE pm.meta_key = '_export_file_url'
	LIMIT 100", $expiration_timestamp );

	$file_urls = $wpdb->get_col( $sql );

	if ( empty( $file_urls ) ) {
		return;
	}

	foreach ( $file_urls as $file_url ) {
		$delete_result = _delete_archive_file( $file_url );
		if ( is_wp_error( $delete_result ) ) {
			/** translators: 1: archive file URL 2: error message */
			$message = sprintf( __( 'Failed to delete expired personal data export (%1$s): %2$s' ), $file_url, $delete_result->get_error_message() );

			trigger_error( $message, E_USER_WARNING );
		}
	}
}
