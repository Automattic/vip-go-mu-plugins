<?php

/**
 * Interface for Two Factor SMS strategies.
 *
 * This interface defines the contract that all SMS strategies must implement
 * for sending and validating two-factor authentication tokens.
 *
 * @package Two_Factor
 */
interface Two_Factor_Twilio_SMS {

	/**
	 * Send a code to the user via SMS
	 *
	 * @param string $code Code to send to the user.
	 * @return bool|WP_Error true on success, or WP_Error on failure.
	 */
	public function send_code( string $code ): bool|WP_Error;

	/**
	 * Verify the code provided by the user.
	 *
	 * @param string $code User token.
	 * @return bool True if the code is valid, false otherwise.
	 */
	public function verify_code( string $code ): bool;

	/**
	 * Clean up any verification data associated with the user.
	 */
	public function cleanup_verification_data(): void;

	/**
	 * Check if the user has pending metadata.
	 */
	public function has_pending_metadata(): bool;
}
