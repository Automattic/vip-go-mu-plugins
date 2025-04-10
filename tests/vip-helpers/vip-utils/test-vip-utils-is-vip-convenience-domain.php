<?php

class VIP_Utils_Is_Vip_Convenience_Domain_Test extends WP_UnitTestCase {

	public function wpcom_vip_convenience_domains() {
		return [
			[ '', false ],
			[ 'example.go-vip.co', true ],
			[ 'example.go-vip.net', true ],
			[ 'develop.go-vip.company.com', false ],
		];
	}

	/**
	 * @dataProvider wpcom_vip_convenience_domains
	 */
	public function test__is_vip_convenience_domain( $domain, $expected ) {
		$this->assertEquals( $expected, is_vip_convenience_domain( $domain ) );
	}
}
