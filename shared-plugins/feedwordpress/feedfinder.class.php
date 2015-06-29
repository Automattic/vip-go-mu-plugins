<?php
/**
 * class FeedFinder: find likely feeds using autodetection and/or guesswork
 * @version 2010.0622
 * @uses SimplePie_Misc
 */

require_once(ABSPATH . WPINC . '/class-simplepie.php');

class FeedFinder {
	var $uri = NULL;
	var $_cache_uri = NULL;
	
	var $verify = FALSE;
	
	var $_response = NULL;
	var $_data = NULL;
	var $_error = NULL;
	var $_head = NULL;

	# -- Recognition patterns
	var $_feed_types = array(
		'application/rss+xml',
		'text/xml',
		'application/atom+xml',
		'application/x.atom+xml',
                'application/x-atom+xml'
	);
	var $_feed_markers = array('\\<feed', '\\<rss', 'xmlns="http://purl.org/rss/1.0');
	var $_html_markers = array('\\<html');
	var $_obvious_feed_url = array('[./]rss', '[./]rdf', '[./]atom', '[./]feed', '\.xml');
	var $_maybe_feed_url = array ('rss', 'rdf', 'atom', 'feed', 'xml');

	function FeedFinder ($uri = NULL, $verify = TRUE) {
		$this->uri = $uri; $this->verify = $verify;
	} /* FeedFinder::FeedFinder () */

	function find ($uri = NULL) {
		$ret = array ();
		if (!is_null($this->data($uri))) {
			if ($this->is_feed($uri)) {
				$href = array($this->uri);
			} else {
				// Assume that we have HTML or XHTML (even if we don't, who's it gonna hurt?)
				// Autodiscovery is the preferred method
				$href = $this->_link_rel_feeds();
				
				// ... but we'll also take the little orange buttons
				$href = array_merge($href, $this->_a_href_feeds(TRUE));
				
				// If all that failed, look harder
				if (count($href) == 0) $href = $this->_a_href_feeds(FALSE);

				// Our search may turn up duplicate URIs. We only need to do any given URI once.
				// Props to Camilo <http://projects.radgeek.com/2008/12/14/feedwordpress-20081214/#comment-20090122160414>
				$href = array_unique($href);
			} /* if */
			
			// Try some clever URL little tricks before we go
			$href = array_merge($href, $this->_url_manipulation_feeds());
			$href = array_unique($href);

			// Verify feeds and resolve relative URIs
			foreach ($href as $u) {
				$the_uri = SimplePie_Misc::absolutize_url($u, $this->uri);
				if ($this->verify and ($u != $this->uri and $the_uri != $this->uri)) {
					$feed = new FeedFinder($the_uri);
					if ($feed->is_feed()) : $ret[] = $the_uri; endif;
					unset($feed);
				} else {
					$ret[] = $the_uri;
				}
			} /* foreach */
		} /* if */
	
		return array_values($ret);
	} /* FeedFinder::find () */

	function data ($uri = NULL) {
		$this->_get($uri);
		return $this->_data;
	}

	function status ($uri = NULL) {
		$this->_get($uri);
		
		if (!is_wp_error($this->_response) and isset($this->_response['response']['code'])) :
			$ret = $this->_response['response']['code'];
		else :
			$ret = NULL;
		endif;
		return $ret;
	}

	function error ($index = NULL) {
		$message = NULL;
		if (count($this->_error) > 0) :
			if (is_scalar($index) and !is_null($index)) :
				$message = $this->_error[$index];
			else :
				$message = implode(" / ", $this->_error)."\n";
			endif;
		endif;
		return $message;
	}
	
	function is_feed ($uri = NULL) {
		$data = $this->data($uri);

		return (
			preg_match (
				"\007(".implode('|',$this->_feed_markers).")\007i",
				$data
			) and !preg_match (
				"\007(".implode('|',$this->_html_markers).")\007i",
				$data
			)
		);
	} /* FeedFinder::is_feed () */

	# --- Private methods ---
	function _get ($uri = NULL) {
		if ($uri) $this->uri = $uri;

		// Is the result not yet cached?
		if ($this->_cache_uri !== $this->uri) :
			$headers['Connection'] = 'close';
			$headers['Accept'] = 'application/atom+xml application/rdf+xml application/rss+xml application/xml text/html */*';
			$headers['User-Agent'] = 'feedfinder/1.2 (compatible; PHP FeedFinder) +http://projects.radgeek.com/feedwordpress';

			// Use WordPress API function
			$client = wp_remote_request($this->uri, array(
				'headers' => $headers,
				'timeout' => FEEDWORDPRESS_FETCH_TIME_OUT,
			));

			$this->_response = $client;
			if (is_wp_error($client)) :
				$this->_data = NULL;
				$this->_error = $client->get_error_messages();
			else :
				$this->_data = $client['body'];
				$this->_error = NULL;
			endif;

			// Kilroy was here
			$this->_cache_uri = $this->uri;
		endif;
	} /* FeedFinder::_get () */

 	function _link_rel_feeds () {
		$links = $this->_tags('link');
		$link_count = count($links);

		// now figure out which one points to the RSS file
		$href = array ();
		for ($n=0; $n<$link_count; $n++) {
			if (strtolower($links[$n]['rel']) == 'alternate') {
				if (in_array(strtolower($links[$n]['type']), $this->_feed_types)) {
					$href[] = $links[$n]['href'];
				} /* if */
			} /* if */
		} /* for */
		return $href;
	}

	function _a_href_feeds ($obvious = TRUE) {
		$pattern = ($obvious ? $this->_obvious_feed_url : $this->_maybe_feed_url);

		$links = $this->_tags('a');
		$link_count = count($links);

		// now figure out which one points to the RSS file
		$href = array ();
		for ($n=0; $n<$link_count; $n++) {
			if (preg_match("\007(".implode('|',$pattern).")\007i", $links[$n]['href'])) {
				$href[] = $links[$n]['href'];
			} /* if */
		} /* for */
		return $href;
	}

	function _url_manipulation_feeds () {
		$href = array();
		
		// check for HTTP GET parameters that look feed-like.
		$bits = parse_url($this->uri);
		foreach (array('rss', 'rss2', 'atom', 'rdf') as $format) :
			if (isset($bits['query']) and (strlen($bits['query']) > 0)) :
				$newQuery = preg_replace('/([?&=])(rss2?|atom|rdf)/i', '$1'.$format, $bits['query']);
			else :
				$newQuery = NULL;
			endif;
			
			if (isset($bits['path']) and (strlen($bits['path']) > 0)) :
				$newPath = preg_replace('!([/.])(rss2?|atom|rdf)!i', '$1'.$format, $bits['path']);
			else :
				$newPath = NULL;
			endif;

			// Reassemble, check and return
			$credentials = '';
			if (isset($bits['user'])) :
				$credentials = $bits['user'];
				if (isset($bits['pass'])) :
					$credentials .= ':'.$bits['pass'];
				endif;
				$credentials .= '@';
			endif;
			
			// Variations on a theme
			$newUrl[0] = (''
				.(isset($bits['scheme']) ? $bits['scheme'].':' : '')
				.(isset($bits['host']) ? '//'.$credentials.$bits['host'] : '')
				.(!is_null($newPath) ? $newPath : '')
				.(!is_null($newQuery) ? '?'.$newQuery : '')
				.(isset($bits['fragment']) ? '#'.$bits['fragment'] : '')
			);
			$newUrl[1] = (''
				.(isset($bits['scheme']) ? $bits['scheme'].':' : '')
				.(isset($bits['host']) ? '//'.$credentials.$bits['host'] : '')
				.(!is_null($newPath) ? $newPath : '')
				.(isset($bits['query']) ? '?'.$bits['query'] : '')
				.(isset($bits['fragment']) ? '#'.$bits['fragment'] : '')
			);
			$newUrl[2] = (''
				.(isset($bits['scheme']) ? $bits['scheme'].':' : '')
				.(isset($bits['host']) ? '//'.$credentials.$bits['host'] : '')
				.(isset($bits['path']) ? $bits['path'] : '')
				.(!is_null($newQuery) ? '?'.$newQuery : '')
				.(isset($bits['fragment']) ? '#'.$bits['fragment'] : '')
			);
			$href = array_merge($href, $newUrl);
		endforeach;
		return array_unique($href);
	}

	function _tags ($tag) {
		$html = $this->data();
    
		// search through the HTML, save all <link> tags
		// and store each link's attributes in an associative array
		preg_match_all('/<'.$tag.'\s+(.*?)\s*\/?>/si', $html, $matches);
		$links = $matches[1];
		$ret = array();
		$link_count = count($links);
		for ($n=0; $n<$link_count; $n++) {
			$attributes = preg_split('/\s+/s', $links[$n]);
			foreach($attributes as $attribute) {
				$att = preg_split('/\s*=\s*/s', $attribute, 2);
				if (isset($att[1])) {
					$att[1] = preg_replace('/([\'"]?)(.*)\1/', '$2', $att[1]);
					$final_link[strtolower($att[0])] = $att[1];
				} /* if */
			} /* foreach */
			$ret[$n] = $final_link;
		} /* for */
		return $ret;
	}
} /* class FeedFinder */

