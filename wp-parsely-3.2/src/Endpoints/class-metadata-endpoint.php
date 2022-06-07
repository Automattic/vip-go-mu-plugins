<?php
/**
 * Metadata endpoint abstract class
 *
 * @package Parsely\Endpoints
 * @since 3.2.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use Parsely\Parsely;

/**
 * Metadata endpoint classes are expected to implement the remaining functions of the class.
 *
 * @since 3.2.0
 */
abstract class Metadata_Endpoint {
	protected const FIELD_NAME = 'parsely';

	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	protected $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Function to start up the class and enqueue necessary actions.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	abstract public function run(): void;

	/**
	 * Registers the metadata fields on the appropriate resource types.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	abstract public function register_meta(): void;

	/**
	 * Get the metadata in string format.
	 *
	 * @since 3.2.0
	 *
	 * @param string $meta_type `json_ld` or `repeated_metas`.
	 * @return string The metadata as HTML code.
	 */
	public function get_rendered_meta( string $meta_type ): string {
		ob_start();
		$this->parsely->render_metadata( $meta_type );
		$out = ob_get_clean();

		if ( false === $out ) {
			return '';
		}

		return trim( $out );
	}
}
