<?php
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink

namespace Automattic\VIP\Core\Privacy;

use WP_Error;

// Disable the VIP Privacy policy by default while we work on the rollout.
// Priority is set to 0 to allow for easier overrides.
add_filter( 'vip_show_login_privacy_policy', '__return_false', 0 );

// Display a link to the VIP/Automattic Privacy Policy if the site doesn't already define one.
add_action( 'the_privacy_policy_link', __NAMESPACE__ . '\the_vip_privacy_policy_link', PHP_INT_MAX ); // Hook in later so we don't override existing filters

function the_vip_privacy_policy_link( $link ) {
	// Don't change if the link has already been rendered.
	if ( $link ) {
		return $link;
	}

	// Allow customers to opt-out of the privacy notice.
	$show_vip_privacy_policy = apply_filters( 'vip_show_login_privacy_policy', true );
	if ( ! $show_vip_privacy_policy ) {
		return '';
	}

	$link = sprintf(
		'%s<br/><a href="https://automattic.com/privacy/">%s</a>',
		esc_html__( 'Powered by WordPress VIP' ),
		esc_html__( 'Privacy Policy' )
	);

	return $link;
}

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
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
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
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
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
		$zip = new \ZipArchive; // phpcs:ignore WordPress.Classes.ClassInstantiation.MissingParenthesis
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
}

function _upload_archive_file( $archive_path ) {
	// For local usage, skip the remote upload.
	// The file is already in the uploads folder.
	if ( true !== WPCOM_IS_VIP_ENV ) {
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require WPMU_PLUGIN_DIR . '/files/class-api-client.php';
	}

	// Build the `/wp-content/` version of the exports path since `LOCAL_UPLOADS` gives us a `/tmp` path.
	// Hard-coded and full of assumptions for now.
	// TODO: need a cleaner approach for this. Can probably borrow `WP_Filesystem_VIP_Uploads::sanitize_uploads_path()`.
	$archive_file      = basename( $archive_path );
	$exports_url       = wp_privacy_exports_url();
	$wp_content_strpos = strpos( $exports_url, '/wp-content/uploads/' );
	$upload_path       = trailingslashit( substr( $exports_url, $wp_content_strpos ) ) . $archive_file;

	$api_client    = \Automattic\VIP\Files\new_api_client();
	$upload_result = $api_client->upload_file( $archive_path, $upload_path );

	// Delete the local copy of the archive since it's been uploaded.
	unlink( $archive_path );

	return $upload_result;
}

function _delete_archive_file( $archive_url ) {
	$archive_path = wp_parse_url( $archive_url, PHP_URL_PATH );

	// For local usage, just delete locally.
	if ( true !== WPCOM_IS_VIP_ENV ) {
		unlink( WP_CONTENT_DIR . $archive_path );
		return true;
	}

	if ( ! class_exists( 'Automattic\VIP\Files\Api_Client' ) ) {
		require WPMU_PLUGIN_DIR . '/files/class-api-client.php';
	}

	$api_client = \Automattic\VIP\Files\new_api_client();
	return $api_client->delete_file( $archive_path );
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
	$file_urls = $wpdb->get_col(
		$wpdb->prepare( "SELECT pm.meta_value FROM $wpdb->postmeta AS pm
			INNER JOIN $wpdb->postmeta AS expiry
				ON expiry.post_id = pm.post_id
				AND expiry.meta_key = '_vip_export_generated_time'
				AND expiry.meta_value <= %d
			WHERE pm.meta_key = '_export_file_url'
			LIMIT 100", $expiration_timestamp 
		)
	);

	if ( empty( $file_urls ) ) {
		return;
	}

	foreach ( $file_urls as $file_url ) {
		$delete_result = _delete_archive_file( $file_url );
		if ( is_wp_error( $delete_result ) ) {
			/* translators: 1: archive file URL 2: error message */
			$message = sprintf( __( 'Failed to delete expired personal data export (%1$s): %2$s' ), $file_url, $delete_result->get_error_message() );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( esc_html( $message ), E_USER_WARNING );
		}
	}
}
