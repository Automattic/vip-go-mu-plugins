<?php

namespace Automattic\VIP\SMS;

use WP_Error;

define( 'MAX_SMS_LENGTH', 140 );
define( 'SMS_FROM_NUMBER', '+14159695849' );
define( 'TWILIO_BASE_URL', 'https://api.twilio.com/2010-04-01' );
define( 'TWILIO_ACCOUNT', 'ACe16d3eaebadd491f285297e03b4d3234' );

/**
 * Send an SMS message to the specified numbers using the Twilio API
 *
 * @param int|array Nnumber(s) to send an SMS to. Accepts formatted and unformatted US numbers, e.g. +14155551212, (415) 555-1212 or 415-555-1212.
 * @param string Message to send. Longer than 140 chars will be split.
 *
 * @link http://www.twilio.com/docs/api/rest/sending-sms
 */
function send_sms( $to, $message ) {

	$counter_pattern = ' (%s of %s)';
	$counter_length  = strlen( $counter_pattern ); // (01 of 02)

	// Split message if > 140, otherwise Twilio freaks out
	if ( MAX_SMS_LENGTH < strlen( $message ) ) {
		$message = split_words( $message, MAX_SMS_LENGTH - $counter_length );
	}
	// Cast as an array, just in case
	$message = (array) $message;
	$to      = (array) $to;

	foreach ( $to as $to_number ) {
		$counter        = 0;
		$total_messages = count( $message );

		foreach ( $message as $message_split ) {
			$counter++;

			// Add a counter if we're sending more than one message
			if ( 1 < $total_messages ) {
				$message_split .= sprintf( $counter_pattern, $counter, $total_messages );
			}

			$body = array(
				'From' => SMS_FROM_NUMBER,
				'To'   => $to_number,
				'Body' => $message_split,
			);

			$result = send_single_sms_via_rest( $body );
			if ( is_wp_error( $result ) ) {
				$masked_number = substr( $to_number, 0, ( (int) strlen( $to_number ) / 1.5 ) ) . 'xxx';
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( sprintf( 'Failed to send SMS to %s: %s #vip-go-sms-error', esc_html( $masked_number ), esc_html( $result->get_error_message() ) ), E_USER_WARNING );
			}
		}
	}
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

/**
 * Split string into group of lines containing only words,
 * having maximum line length $max characters
 *
 * @param string $longtext
 * @param integer $max,
 * @return array - lines of words with $max line length
 *
 * @author Daniel Petrovic
 * @link http://www.php.net/manual/en/function.preg-split.php#108090
 */
function split_words( $longtext, $max = 1 ) {
	// spaces or commas are not considered to be words
	// between '[' and ']' can be put all characters considered to be
	// word separators
	$words        = preg_split( '/[\s,]+/', $longtext, 0, PREG_SPLIT_NO_EMPTY );
	$add_line     = false;
	$current_line = '';
	$lines        = array();

	do {
		$word = next( $words );
		$wlen = strlen( $word );
		if ( $wlen > $max ) {
			continue;
		}
		$current_line = ltrim( $current_line );
		$llen         = strlen( $current_line );
		if ( ! $wlen && $llen ) {
			$lines[] = ltrim( $current_line );
			break;
		}
		$add_line = ( $llen + $wlen + 1 > $max );
		if ( $add_line && $llen ) {
			$lines[]      = $current_line;
			$current_line = $word;
		} else {
			$current_line .= ' ' . $word;
		}
	} while ( $word );

	return $lines;
}
