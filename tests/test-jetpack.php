<?php

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

class VIP_Go__Core__Default_VIP_Jetpack_Version extends WP_UnitTestCase {
	public function test__vip_default_jetpack_version() {
		global $wp_version;
		$saved_wp_version = $wp_version;

		$latest = '16.3';

		$versions_map = [
			// WordPress version => Jetpack version
			'6.5' => '14.0',
			'6.6' => '14.5',
			'6.7' => '15.4',
			'6.8' => '15.7',
			'6.9' => '16.1',
			'7.0' => $latest,
		];

		foreach ( $versions_map as $wordpress_version => $jetpack_version ) {
			$wp_version = $wordpress_version;
			$this->assertEquals( vip_default_jetpack_version(), $jetpack_version );
		}

		// Reset back to original value.
		$wp_version = $saved_wp_version;
	}

	public function compatibility_requirements(): array {
		return [
			'local WordPress too old' => [ 'local', '6.9', '7.4', 'requires WordPress 6.9 or newer' ],
			'local PHP too old'       => [ 'local', '6.8', '99.0', 'requires PHP 99.0 or newer' ],
			'local compatible'        => [ 'local', '6.8', '7.4', '' ],
			'hosted mapping'          => [ 'production', '6.9', '99.0', '' ],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider compatibility_requirements
	 */
	public function test_jetpack_compatibility_requirements( $environment, $wordpress, $php, $expected ): void {
		global $wp_version;
		$wp_version = '6.8';
		define( 'WP_ENVIRONMENT_TYPE', $environment );
		$path = wp_tempnam( 'jetpack.php' );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Temporary plugin header read by the real WordPress parser.
		file_put_contents( $path, "<?php\n/*\nRequires at least: $wordpress\nRequires PHP: $php\n*/" );
		try {
			$error = vip_jetpack_compatibility_error( $path );
		} finally {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Remove the test-owned temporary header.
			unlink( $path );
		}
		$this->assertSame( $expected, $error );
	}
}
