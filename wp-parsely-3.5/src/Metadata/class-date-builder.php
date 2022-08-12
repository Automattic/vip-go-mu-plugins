<?php
/**
 * Date Page Metadata Builder class
 *
 * @package Parsely
 * @since 3.4.0
 */

declare(strict_types=1);

namespace Parsely\Metadata;

/**
 * Implements abstract Metadata Builder class to generate the metadata array
 * for a date page.
 *
 * @since 3.4.0
 */
class Date_Builder extends Metadata_Builder {
	/**
	 * Generates the metadata object by calling the build_* methods and
	 * returns the value.
	 *
	 * @since 3.4.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_metadata(): array {
		$this->build_basic();
		$this->build_headline();
		$this->build_url();

		return $this->metadata;
	}

	/**
	 * Populates the `headline` field in the metadata object.
	 *
	 * @since 3.4.0
	 */
	private function build_headline(): void {
		if ( is_year() ) {
			/* translators: %s: Archive year */
			$this->metadata['headline'] = sprintf( __( 'Yearly Archive - %s', 'wp-parsely' ), get_the_time( 'Y' ) );
		} elseif ( is_month() ) {
			/* translators: %s: Archive month, formatted as F, Y */
			$this->metadata['headline'] = sprintf( __( 'Monthly Archive - %s', 'wp-parsely' ), get_the_time( 'F, Y' ) );
		} elseif ( is_day() ) {
			/* translators: %s: Archive day, formatted as F jS, Y */
			$this->metadata['headline'] = sprintf( __( 'Daily Archive - %s', 'wp-parsely' ), get_the_time( 'F jS, Y' ) );
		} elseif ( is_time() ) {
			/* translators: %s: Archive time, formatted as F jS g:i:s A */
			$this->metadata['headline'] = sprintf( __( 'Hourly, Minutely, or Secondly Archive - %s', 'wp-parsely' ), get_the_time( 'F jS g:i:s A' ) );
		}
	}
}
