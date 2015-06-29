<?php 
class FeedWordPressHTML {
	function attributeRegex ($tag, $attr) {
		return ":(
		(<($tag)\s+[^>]*)
		($attr)=
		)
		(
			\s*(\"|')
			(((?!\\6).)*)
			\\6([^>]*>)
		|
			\s*(((?!/>)[^\s>])*)
			([^>]*>)
		)
		:ix";
	} /* function FeedWordPressHTML::attributeRegex () */

	function attributeMatch ($matches) {
		$suffix = (isset($matches[12]) ? $matches[12] : $matches[9]);
		$value = (isset($matches[10]) ? $matches[10] : $matches[7]);

		return array(
		"tag" => $matches[3],
		"attribute" => $matches[4],
		"value" => $value,
		"quote" => $matches[6],
		"prefix" => $matches[1].$matches[6],
		"suffix" => $matches[6].$suffix,
		"before_attribute" => $matches[2],
		"after_attribute" => $suffix,
		);
	} /* function FeedWordPressHTML::attributeMatch () */
} /* class FeedWordPressHTML */

