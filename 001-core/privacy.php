<?php

namespace Automattic\VIP\Core\Privacy;

add_action( 'muplugins_loaded', __NAMESPACE__ . '\init_privacy_compat' );

function init_privacy_compat() {
	// Replace core's privacy data export handler with a custom one.
	remove_action( 'wp_privacy_personal_data_export_file', 'wp_privacy_generate_personal_data_export_file', 10 );
	add_action( 'wp_privacy_personal_data_export_file', __NAMESPACE__ . '\generate_personal_data_export_file' );

	// Replace core's privacy data delete handler with a custom one.
	remove_action( 'wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files' );
	add_action( 'wp_privacy_delete_old_export_files', __NAMESPACE__ . '\delete_old_export_files' );
}

function generate_personal_data_export_file( $request_id ) {
	$request = wp_get_user_request_data( $request_id );

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

	$result = wp_mkdir_p( $exports_dir );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	// Protect export folder from browsing.
	$index_pathname = $exports_dir . 'index.html';
	if ( ! file_exists( $index_pathname ) ) {
		$file = fopen( $index_pathname, 'w' );
		if ( false === $file ) {
			wp_send_json_error( __( 'Unable to protect export folder from browsing.' ) );
		}
		fwrite( $file, 'Silence is golden.' );
		fclose( $file );
	}

	$stripped_email       = str_replace( '@', '-at-', $email_address );
	$stripped_email       = sanitize_title( $stripped_email ); // slugify the email address
	$obscura              = wp_generate_password( 32, false, false );
	$file_basename        = 'wp-personal-data-file-' . $stripped_email . '-' . $obscura;
	$html_report_filename = $file_basename . '.html';
	$html_report_pathname = wp_normalize_path( $exports_dir . $html_report_filename );
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
	$error            = false;
	$archive_url      = get_post_meta( $request_id, '_export_file_url', true );
	$archive_pathname = get_post_meta( $request_id, '_export_file_path', true );

	if ( empty( $archive_pathname ) || empty( $archive_url ) ) {
		$archive_filename = $file_basename . '.zip';
		$archive_pathname = $exports_dir . $archive_filename;
		$archive_url      = $exports_url . $archive_filename;

		update_post_meta( $request_id, '_export_file_url', $archive_url );
		update_post_meta( $request_id, '_export_file_path', wp_normalize_path( $archive_pathname ) );
	}

	// Track generated time to simplify deletions.
	// We can't currently iterate through files in the Files Service so we need a way to query exports by date.
	update_post_meta( $request_id, '_vip_export_generated_time', time() );

	// Note: core deletes the file, but we can just overwrite it when we upload.

	// ZipArchive may not be available across all applications.
	// Use it if it exists, otherwise fallback to PclZip.
	if ( class_exists( 'ZipArchive' ) ) {
		$zip_result = _ziparchive_create_file( $archive_pathname, $html_report_pathname );
	} else {
		$zip_result = _pclzip_create_file( $archive_pathname, $html_report_pathname );
	}

	if ( is_wp_error( $zip_result ) ) {
		$error = sprintf( __( 'Unable to generate export file (archive) for writing: %s' ), $zip_result->get_error_message() );
	} else {
		/** This filter is documented in wp-admin/includes/file.php */
		do_action( 'wp_privacy_personal_data_export_file_created', $archive_pathname, $archive_url, $html_report_pathname, $request_id );
	}

	// And remove the HTML file.
	unlink( $html_report_pathname );

	if ( $error ) {
		wp_send_json_error( $error );
	}
}

function _ziparchive_create_file( $archive_path, $html_report_path ) {
	$archive = new ZipArchive;

	$archive_created = $archive->open( $archive_path, ZipArchive::CREATE );
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

	$archive = new PclZip( $archive_path );

	$result = $archive->create( [ $html_report_path ] );
	if ( 0 === $result ) {
		return new WP_Error( 'pclzip-create-failed', __( 'Failed to create a `zip` file using `PclZip`' ) );
	}

	return true;
}

function delete_old_export_files() {
	require_once( ABSPATH . 'wp-admin/includes/file.php' );

	$exports_dir  = wp_privacy_exports_dir();
	$export_files = list_files( $exports_dir, 100, array( 'index.html' ) );

	/**
	 * Filters the lifetime, in seconds, of a personal data export file.
	 *
	 * By default, the lifetime is 3 days. Once the file reaches that age, it will automatically
	 * be deleted by a cron job.
	 *
	 * @since 4.9.6
	 *
	 * @param int $expiration The expiration age of the export, in seconds.
	 */
	$expiration = apply_filters( 'wp_privacy_export_expiration', 3 * DAY_IN_SECONDS );

	foreach ( (array) $export_files as $export_file ) {
		$file_age_in_seconds = time() - filemtime( $export_file );

		if ( $expiration < $file_age_in_seconds ) {
			unlink( $export_file );
		}
	}
}
