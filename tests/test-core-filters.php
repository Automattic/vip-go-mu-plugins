<?php

class Core_Filters_Test extends WP_UnitTestCase {
	public function test_vip_only_https_origins(): void {
		$input = [
			0 => 'http://example.com',
			1 => 'https://example.com',
		];

		$expected = [
			1 => 'https://example.com',
		];

		$actual = vip_only_https_origins( $input );
		self::assertSame( $expected, $actual );
	}
}
