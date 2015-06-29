<?php

class SkParsingTest extends WP_UnitTestCase {

	function testParseScrollInput() {
		$this->assertEquals(ScrollKit::parse_scroll_id('https://www.scrollkit.com/s/1IqDfAD/any/thing'), '1IqDfAD');
		$this->assertEquals(ScrollKit::parse_scroll_id('https://www.scrollkit.com/s/1IqDfAD/edit/'), '1IqDfAD');
		$this->assertEquals(ScrollKit::parse_scroll_id('https://www.scrollkit.com/s/1IqDfAD/edit'), '1IqDfAD');
		$this->assertEquals(ScrollKit::parse_scroll_id('http://www.scrollkit.com/s/1IqDfAD/'), '1IqDfAD');
		$this->assertEquals(ScrollKit::parse_scroll_id('http://www.scrollkit.com/s/1IqDfAD'), '1IqDfAD');
		$this->assertEquals(ScrollKit::parse_scroll_id('1IqDfAD'), '1IqDfAD');
	}

}
