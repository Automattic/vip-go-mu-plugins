<?php

// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

class VIP_Go__Core__Default_VIP_Jetpack_Version extends WP_UnitTestCase {
	public function test__vip_default_jetpack_version() {
		$versions_map = [
			// WordPress version => Jetpack version
			'5.8.6' => '10.9',
			'5.9.0' => '11.4',
			'6.0.0' => '11.8',
		];

		foreach ( $versions_map as $wordpress_version => $jetpack_version ) {
			global $wp_version;
			$wp_version = $wordpress_version;

			$this->assertEquals( vip_default_jetpack_version(), $jetpack_version );
		}
	}
}