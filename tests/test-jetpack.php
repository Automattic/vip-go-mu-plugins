<?php

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

class VIP_Go__Core__Default_VIP_Jetpack_Version extends WP_UnitTestCase {
	public function test__vip_default_jetpack_version() {
		global $wp_version;
		$saved_wp_version = $wp_version;

		$latest = '14.0';

		$versions_map = [
			// WordPress version => Jetpack version
			'6.1' => '12.5',
			'6.2' => '12.8',
			'6.3' => '13.1',
			'6.4' => '13.6',
			'6.5' => $latest,
			'6.6' => $latest,
		];

		foreach ( $versions_map as $wordpress_version => $jetpack_version ) {
			$wp_version = $wordpress_version;
			$this->assertEquals( vip_default_jetpack_version(), $jetpack_version );
		}

		// Reset back to original value.
		$wp_version = $saved_wp_version;
	}
}
