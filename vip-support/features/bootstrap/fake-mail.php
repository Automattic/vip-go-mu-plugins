<?php

define( 'WORDPRESS_FAKE_MAIL_DIVIDER', '%%===================%%' );
if ( ! defined( 'WORDPRESS_FAKE_MAIL_DIR' ) ) {
	define( 'WORDPRESS_FAKE_MAIL_DIR', getenv( 'WORDPRESS_FAKE_MAIL_DIR' ) );
}


/**
 * Fake sending email. In fact just write a file to the filesystem, so
 * a test service can read it.
 *
 * @param string|array $to Array or comma-separated list of email addresses to send message.
 * @param string $subject Email subject
 * @param string $message Message contents
 *
 * @return bool True if the email got sent (i.e. if the fake email file was written)
 */
function wp_mail( $to, $subject, $message ) {
	$file_name = sanitize_file_name( time() . microtime() . "-$to" );
	$file_path = trailingslashit( WORDPRESS_FAKE_MAIL_DIR ) . $file_name;
	$content  = "TO: $to" . PHP_EOL;
	$content .= "SUBJECT: $subject" . PHP_EOL;
	$content .= WORDPRESS_FAKE_MAIL_DIVIDER . PHP_EOL . $message;
	if ( ! is_dir( WORDPRESS_FAKE_MAIL_DIR ) ) {
		mkdir( WORDPRESS_FAKE_MAIL_DIR, true );
	}
	return (bool) file_put_contents( $file_path, $content );
}

/**
 * Parse a fake mail written by WordPress for testing purposes, and
 * return the "email" data.
 *
 * @param string $file The path to a fake mail file to parse
 *
 * @return array The email data, as an array with these fields: to, subject, body
 */
function a8c_vip_read_fake_mail( $file ) {
	$message = array();
	$file_contents = file_get_contents( $file );
	preg_match( '/^TO:(.*)$/mi', $file_contents, $to_matches );
	$message['to'] = trim( $to_matches[1] );
	preg_match( '/^SUBJECT:(.*)$/mi', $file_contents, $subj_matches );
	$message['subject'] = trim( $subj_matches[1] );
	$parts = explode( WORDPRESS_FAKE_MAIL_DIVIDER, $file_contents );
	$message['body'] = $parts[1];
	return $message;
}

/**
 * Get all fake mails sent to this address
 *
 * @param string $email_address The email address to get mail to
 *
 * @return array An array of fake email paths, first to last
 */
function a8c_vip_get_fake_mail_for( $email_address ) {
	$emails = array();
	// List contents of Fake Mail directory
	$file_pattern = WORDPRESS_FAKE_MAIL_DIR . '*' . $email_address . '*';
	foreach ( glob( $file_pattern ) as $email ) {
		$emails[] = $email;
	}
	return $emails;
}

/**
 * Get all fake mails sent to this address
 *
 * @param string $email_address The email address to get mail to
 *
 * @return array An array of fake email paths, first to last
 */
function a8c_vip_delete_fake_mail_for( $email_address = '' ) {
	foreach ( glob( WORDPRESS_FAKE_MAIL_DIR . '*' . $email_address . '*' ) as $email ) {
		unlink( $email );
	}
}
