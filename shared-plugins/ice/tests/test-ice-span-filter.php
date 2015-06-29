<?php
require_once dirname( __FILE__ ) . '/../class-ice-span-filter.php';

class Test_ICE_Span_Filter extends PHPUnit_Framework_TestCase {

	var $tracking_classes = array( 'ins' => 'ice-wp-ins', 'del' => 'ice-wp-del' );

	function filter( $text ) {
		$span_filter = new ICE_Span_Filter( $text, $this->tracking_classes );
		return $span_filter->filter();
	}

	function get_tokens( $text ) {
		$span_filter = new ICE_Span_Filter( $text, $this->tracking_classes );
		return $span_filter->get_tokens();
	}

	function test_construct() {
		$span_filter = new ICE_Span_Filter( 'baba', array( 'ins' => 'ins', 'del' => 'del' ) );
	}

	function test_filter_doesnt_change_the_text_if_no_spans() {
		$this->assertEquals( 'baba', $this->filter( 'baba' ) );
	}

	function test_filter_should_remove_del_spans_from_text() {
		$this->assertEquals( '', $this->filter( '<span class="ice-wp-del">baba</span>' ) );
	}

	function test_get_tokens_should_return_the_text_if_no_spans() {
		$this->assertEquals( array( (object)array( 'id' => ICE_Span_Filter::TOKEN_TEXT, 'text' => 'baba' ) ), $this->get_tokens( 'baba' ) );
	}

	function test_get_tokens_should_guess_del_token() {
		$text = '<span class="ice-wp-del">baba</span>';
		$tokens = array(
			(object)array( 'id' => ICE_Span_Filter::TOKEN_DEL, 'text' => '<span class="ice-wp-del">' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_TEXT, 'text' => 'baba' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_CLOSING, 'text' => '</span>' ),
		);
		$this->assertEquals( $tokens, $this->get_tokens( $text ) );
	}

	function test_get_tokens_should_guess_ins_token() {
		$text = '<span class="ice-wp-del">baba</span><span class="ice-wp-ins">dyado</span>';
		$tokens = array(
			(object)array( 'id' => ICE_Span_Filter::TOKEN_DEL, 'text' => '<span class="ice-wp-del">' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_TEXT, 'text' => 'baba' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_CLOSING, 'text' => '</span>' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_INS, 'text' => '<span class="ice-wp-ins">' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_TEXT, 'text' => 'dyado' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_CLOSING, 'text' => '</span>' ),
		);
		$this->assertEquals( $tokens, $this->get_tokens( $text ) );
	}

	function test_get_css_classes_should_work_with_multiple_classes() {
		$this->assertTrue( in_array( 'ice-wp-ins', ICE_Span_Filter::get_css_classes( '<span class="baba dyado ice-wp-ins">' ), true ) );
	}

	function test_get_css_classes_should_work_with_extra_whitespace() {
		$this->assertTrue( in_array( 'ice-wp-ins', ICE_Span_Filter::get_css_classes( '<span class="   baba    dyado  ice-wp-ins   ">' ), true ) );
	}

	function test_get_css_classes_should_work_if_there_are_other_attributes() {
		$this->assertTrue( in_array( 'ice-wp-ins', ICE_Span_Filter::get_css_classes( '<span title="mumu" class="ice-wp-ins" id="baba">' ), true ) );
	}

	function test_get_css_classes_should_work_with_single_and_double_quotes() {
		$this->assertEquals( ICE_Span_Filter::get_css_classes( '<span class="baba dyado">' ), ICE_Span_Filter::get_css_classes( "<span class='baba dyado'>" ) );
	}

	function test_get_token_should_guess_normal_spans() {
		$text = '<span>baba</span>';
		$tokens = array(
			(object)array( 'id' => ICE_Span_Filter::TOKEN_SPAN, 'text' => '<span>' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_TEXT, 'text' => 'baba' ),
			(object)array( 'id' => ICE_Span_Filter::TOKEN_CLOSING, 'text' => '</span>' ),
		);
		$this->assertEquals( $tokens, $this->get_tokens( $text ) );
	}

	function test_filter_should_keep_ins_contents() {
		$this->assertEquals( 'baba', $this->filter( '<span class="ice-wp-ins">baba</span>' ) );
	}

	function test_filter_should_keep_both_tag_and_contents_for_normal_spans() {
		$this->assertEquals( '<span>baba</span>', $this->filter( '<span>baba</span>' ) );
	}

	function test_filter_should_filter_del_spans_inside_normal_spans() {
		$this->assertEquals( '<span>baba</span>', $this->filter( '<span>ba<span class="ice-wp-del">no!</span>ba</span>') );
	}

	function test_filter_should_filter_ins_spans_inside_normal_spans() {
		$this->assertEquals( '<span>baxxxba</span>', $this->filter( '<span>ba<span class="ice-wp-ins">xxx</span>ba</span>') );
	}

	function test_filter_should_leave_both_ins_if_they_are_nested() {
		$this->assertEquals( 'I am a warrior', $this->filter( '<span class="ice-wp-ins">I am <span class="ice-wp-ins">a</span> warrior</span>') );
	}

	function test_filter_should_filter_del_spans_in_ins_spans() {
		$this->assertEquals( 'I am  warrior', $this->filter( '<span class="ice-wp-ins">I am <span class="ice-wp-del">a</span> warrior</span>') );
	}

	function test_filter_should_keep_whitespace() {
		$this->assertEquals( "Para0\tEnd\n\nPara1  ", $this->filter( "Para0\t<span class='ice-wp-ins'>End</span>\n\nPara1 <span class='ice-wp-del'>No end</span> " ) );
	}

	function test_filter_multiple_nested_spans() {
		$in = '<span class="ice-wp-ins" >test </span><span class="ice-wp-ins">test <span class="ice-wp-ins">test </span></span><span class="ice-wp-ins" style="color: #3366ff;">test <span class="ice-wp-ins">test</span></span><span class="ice-wp-ins"> <span class="ice-wp-ins"><span style="color: #3366ff;">test </span><span class="ice-wp-ins"><span style="color: #ff0000;">test </span><span class="ice-wp-ins"><span style="color: #ff0000;">test </span><span class="ice-wp-ins"><span style="color: #999999;">test </span><span class="ice-wp-ins"><span style="color: #339966;">test </span><span class="ice-wp-ins"><span style="color: #3366ff;">test</span> <span class="ice-wp-ins">test <span class="ice-wp-ins">test <span class="ice-wp-ins">t<span class="ice-wp-del">est </span><span class="ice-wp-ins"><span class="ice-wp-del">test </span><span class="ice-wp-ins"><span class="ice-wp-del">test tes</span>t test test</span></span></span></span></span></span></span></span></span></span></span></span>';
		$out = 'test test test test test <span style="color: #3366ff;">test </span><span style="color: #ff0000;">test </span><span style="color: #ff0000;">test </span><span style="color: #999999;">test </span><span style="color: #339966;">test </span><span style="color: #3366ff;">test</span> test test tt test test';
		$this->assertEquals( $out, $this->filter($in) );
	}
}

