<?php
/**
 * Parse.ly Content Helper class
 *
 * @package Parsely
 * @since 3.5.0
 */

declare(strict_types=1);

namespace Parsely;

/**
 * Parse.ly Content Helper.
 *
 * @since 3.5.0
 */
class Content_Helper {

	/**
	 * Inserts the Content Helper into the WordPress Post Editor.
	 *
	 * @since 3.5.0
	 */
	public function run(): void {
		$content_helper_asset = require plugin_dir_path( PARSELY_FILE ) . 'build/content-helper.asset.php';

		wp_enqueue_script(
			'wp-parsely-block-content-helper',
			plugin_dir_url( PARSELY_FILE ) . 'build/content-helper.js',
			$content_helper_asset['dependencies'],
			$content_helper_asset['version'],
			true
		);

		wp_enqueue_style(
			'wp-parsely-block-content-helper',
			plugin_dir_url( PARSELY_FILE ) . 'build/content-helper.css',
			array(),
			$content_helper_asset['version']
		);
	}

}
