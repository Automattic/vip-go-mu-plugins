<?php
require_once(ABSPATH . WPINC . '/class-feed.php');

class FeedWordPress_Content_Type_Sniffer extends SimplePie_Content_Type_Sniffer {
	/**
	 * Get the Content-Type of the specified file
	 *
	 * @access public
	 * @return string Filtered content type 
	 */
	function get_type () {
		$contentType = null;
		$charset = null;
		if (isset($this->file->headers['content-type'])) :
			if (!is_array($this->file->headers['content-type'])) :
				$this->file->headers['content-type'] = array($this->file->headers['content-type']);
			endif;

			foreach ($this->file->headers['content-type'] as $type) :
				$parts = array_map('trim', split(";", $type, 2));
				if (isset($parts[1])) :
					$type = $parts[0];
					$charset = $parts[1];
				endif;
					
				if (preg_match(
					'!(application|text)/((atom|rss|rdf)\+)?xml!',
					$type,
					$ref
				)) :
					$contentType = $ref[0];
				endif;
			endforeach;
				
			$outHeader = array();
			if (!is_null($contentType)) :
				$outHeader[] = $contentType;
			else :
				$outHeader[] = 'text/xml'; // Generic
			endif;
			if (!is_null($charset)) :
				$outHeader[] = $charset;
			endif;
			
			$this->file->headers['content-type'] = implode("; ", $outHeader);
		else :
			// The default SimplePie behavior seems to be to return
			// text/plain if it can't find a Content-Type header.
			// The default SimplePie behavior sucks. Particularly
			// since SimplePie gets so draconian about Content-Type.
			// And since the WP SimplePie seems to drop Content-Type
			// from cached copies for some unfortunate reason.
			$this->file->headers['content-type'] = 'text/xml'; // Generic
		endif;
		return parent::get_type();
	} /* FeedWordPress_Content_Type_Sniffer::get_type() */
} /* class FeedWordPress_Content_Type_Sniffer */

