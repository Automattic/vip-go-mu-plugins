<?php

class SanitizeTest extends WP_UnitTestCase {

	function testSanitizeArray() {
		$badArray = array(
			'"; DROP TABLE xxx',
			'javascript:alert("hi");',
		);

		$sanitized = array(
			'http://;DROPTABLExxx',
		);

		$this->assertEquals(ScrollKit::sanitize_url_array($badArray), $sanitized);
	}

}

