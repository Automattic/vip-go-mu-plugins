<?php

namespace Automattic\VIP\SMS;

use WP_Error;

define( 'SMS_FROM_NUMBER', '+14159695849' );
define( 'TWILIO_BASE_URL', 'https://api.twilio.com/2010-04-01' );
define( 'TWILIO_ACCOUNT', 'ACe16d3eaebadd491f285297e03b4d3234' );

/**
 * Send an SMS message to the specified numbers using the Twilio API
 *
 * @param int|array Number(s) to send an SMS to. Accepts formatted and unformatted US numbers, e.g. +14155551212, (415) 555-1212 or 415-555-1212.
 * @param string Message to send. Twilio splits long messages into segments that phones reassemble.
 *
 * @link http://www.twilio.com/docs/api/rest/sending-sms
 */
function send_sms( $to, $message ) {
	// A messaging service picks the right sender for each country, e.g. a local number or an alphanumeric sender ID.
	$sender = defined( 'VIP_TWILIO_MESSAGING_SERVICE_SID' )
		? array( 'MessagingServiceSid' => VIP_TWILIO_MESSAGING_SERVICE_SID )
		: array( 'From' => SMS_FROM_NUMBER );

	foreach ( (array) $to as $to_number ) {
		$body = array(
			'To'   => $to_number,
			'Body' => $message,
		);

		$result = send_single_sms_via_rest( $body + $sender );
		if ( is_wp_error( $result ) ) {
			$masked_number = substr( $to_number, 0, floor( strlen( $to_number ) / 1.5 ) ) . 'xxx';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'Failed to send SMS to %s: %s #vip-go-sms-error', esc_html( $masked_number ), esc_html( $result->get_error_message() ) ), E_USER_WARNING );
			return $result;
		}
	}
	return true;
}

function send_single_sms_via_rest( $body ) {
	$endpoint = get_rest_url( 'Messages.json' );
	$response = wp_remote_post( $endpoint, array(
		'body'    => $body,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( TWILIO_SID . ':' . TWILIO_SECRET ), // AccountSid:AuthToken
		),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body_raw = wp_remote_retrieve_body( $response );
	$body     = json_decode( $body_raw );

	$status_code = wp_remote_retrieve_response_code( $response );
	if ( $status_code >= 300 ) {
		if ( $body->code && $body->message ) {
			$error_code    = 'twilio-' . $body->code;
			$error_message = sprintf( 'Twilio API responded with error (%d): %s (%s)', $body->code, $body->message, $body->more_info );
		} else {
			$error_code    = 'http-' . $status_code;
			$error_message = sprintf( 'Twilio API responded with HTTP response code: %d', $status_code );
		}

		return new WP_Error( $error_code, $error_message );
	}

	$delivery_status = $body->status;
	if ( 'failed' === $delivery_status || 'undelivered' === $delivery_status ) {
		$error_code    = 'twilio-' . $body->error_code;
		$error_message = sprintf( 'Twilio API responded with error (%d): %s', $body->error_code, $body->error_message );

		return new WP_Error( $error_code, $error_message );
	}

	return true;
}

// action should be something like SMS/Messages.json or Calls.json
function get_rest_url( $action ) {
	return sprintf( '%s/Accounts/%s/%s', TWILIO_BASE_URL, TWILIO_ACCOUNT, $action );
}
