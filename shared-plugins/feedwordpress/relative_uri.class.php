<?php
# Relative URI static class: PHP class for resolving relative URLs
#
# This class is derived (under the terms of the GPL) from URL Class 0.3 by
# Keyvan Minoukadeh <keyvan@k1m.com>, which is great but more than we need
# for FeedWordPress's purposes. The class has been stripped down to a single
# public method: Relative_URI::resolve($url, $base), which resolves the URI in
# $url relative to the URI in $base

class Relative_URI
{
	// Resolve relative URI in $url against the base URI in $base. If $base
	// is not supplied, then we use the REQUEST_URI of this script.
	//
	// I'm hoping this method reflects RFC 2396 Section 5.2
	function resolve ($url, $base = NULL)
	{
		if (is_null($base)):
			$base = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		endif;

		$base = Relative_URI::_encode(trim($base));
		$uri_parts = Relative_URI::_parse_url($base);

		$url = Relative_URI::_encode(trim($url));
		$parts = Relative_URI::_parse_url($url);

		$uri_parts['fragment'] = (isset($parts['fragment']) ? $parts['fragment'] : null);
		$uri_parts['query'] = (isset($parts['query']) ? $parts['query'] : null);

		// if path is empty, and scheme, host, and query are undefined,
		// the URL is referring the base URL
		
		if (($parts['path'] == '') && !isset($parts['scheme']) && !isset($parts['host']) && !isset($parts['query'])) {
			// If the URI is empty or only a fragment, return the base URI
			return $base . (isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
		} elseif (isset($parts['scheme'])) {
			// If the scheme is set, then the URI is absolute.
			return $url;
		} elseif (isset($parts['host'])) {
			$uri_parts['host'] = $parts['host'];
			$uri_parts['path'] = $parts['path'];
		} else {
			// We have a relative path but not a host.

			// start ugly fix:
			// prepend slash to path if base host is set, base path is not set, and url path is not absolute
			if ($uri_parts['host'] && ($uri_parts['path'] == '')
			&& (strlen($parts['path']) > 0)
			&& (substr($parts['path'], 0, 1) != '/')) {
				$parts['path'] = '/'.$parts['path'];
			} // end ugly fix
			
			if (substr($parts['path'], 0, 1) == '/') {
				$uri_parts['path'] = $parts['path'];
			} else {
				// copy base path excluding any characters after the last (right-most) slash character
				$buffer = substr($uri_parts['path'], 0, (int)strrpos($uri_parts['path'], '/')+1);
				// append relative path
				$buffer .= $parts['path'];
				// remove "./" where "." is a complete path segment.
				$buffer = str_replace('/./', '/', $buffer);
				if (substr($buffer, 0, 2) == './') {
				    $buffer = substr($buffer, 2);
				}
				// if buffer ends with "." as a complete path segment, remove it
				if (substr($buffer, -2) == '/.') {
				    $buffer = substr($buffer, 0, -1);
				}
				// remove "<segment>/../" where <segment> is a complete path segment not equal to ".."
				$search_finished = false;
				$segment = explode('/', $buffer);
				while (!$search_finished) {
				    for ($x=0; $x+1 < count($segment);) {
					if (($segment[$x] != '') && ($segment[$x] != '..') && ($segment[$x+1] == '..')) {
					    if ($x+2 == count($segment)) $segment[] = '';
					    unset($segment[$x], $segment[$x+1]);
					    $segment = array_values($segment);
					    continue 2;
					} else {
					    $x++;
					}
				    }
				    $search_finished = true;
				}
				$buffer = (count($segment) == 1) ? '/' : implode('/', $segment);
				$uri_parts['path'] = $buffer;

			}
		}

		// If we've gotten to this point, we can try to put the pieces
		// back together.
		$ret = '';
		if (isset($uri_parts['scheme'])) $ret .= $uri_parts['scheme'].':';
		if (isset($uri_parts['user'])) {
			$ret .= $uri_parts['user'];
			if (isset($uri_parts['pass'])) $ret .= ':'.$uri_parts['parts'];
			$ret .= '@';
		}
		if (isset($uri_parts['host'])) {
			$ret .= '//'.$uri_parts['host'];
			if (isset($uri_parts['port'])) $ret .= ':'.$uri_parts['port'];
		}
		$ret .= $uri_parts['path'];
		if (isset($uri_parts['query'])) $ret .= '?'.$uri_parts['query'];
		if (isset($uri_parts['fragment'])) $ret .= '#'.$uri_parts['fragment'];

		return $ret;
    }

    /**
    * Parse URL
    *
    * Regular expression grabbed from RFC 2396 Appendix B. 
    * This is a replacement for PHPs builtin parse_url().
    * @param string $url
    * @access private
    * @return array
    */
    function _parse_url($url)
    {
	// I'm using this pattern instead of parse_url() as there's a few strings where parse_url() 
	// generates a warning.
	if (preg_match('!^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?!', $url, $match)) {
	    $parts = array();
	    if ($match[1] != '') $parts['scheme'] = $match[2];
	    if ($match[3] != '') $parts['auth'] = $match[4];
	    // parse auth
	    if (isset($parts['auth'])) {
		// store user info
		if (($at_pos = strpos($parts['auth'], '@')) !== false) {
		    $userinfo = explode(':', substr($parts['auth'], 0, $at_pos), 2);
		    $parts['user'] = $userinfo[0];
		    if (isset($userinfo[1])) $parts['pass'] = $userinfo[1];
		    $parts['auth'] = substr($parts['auth'], $at_pos+1);
		}
		// get port number
		if ($port_pos = strrpos($parts['auth'], ':')) {
		    $parts['host'] = substr($parts['auth'], 0, $port_pos);
		    $parts['port'] = (int)substr($parts['auth'], $port_pos+1);
		    if ($parts['port'] < 1) $parts['port'] = null;
		} else {
		    $parts['host'] = $parts['auth'];
		}
	    }
	    unset($parts['auth']);
	    $parts['path'] = $match[5];
	    if (isset($match[6]) && ($match[6] != '')) $parts['query'] = $match[7];
	    if (isset($match[8]) && ($match[8] != '')) $parts['fragment'] = $match[9];
	    return $parts;
	}
	// shouldn't reach here
	return array('path'=>'');
    }

    function _encode($string)
    {
	static $replace = array();
	if (!count($replace)) {
	    $find = array(32, 34, 60, 62, 123, 124, 125, 91, 92, 93, 94, 96, 127);
	    $find = array_merge(range(0, 31), $find);
	    $find = array_map('chr', $find);
	    foreach ($find as $char) {
		$replace[$char] = '%'.bin2hex($char);
	    }
	}
	// escape control characters and a few other characters
	$encoded = strtr($string, $replace);
	// remove any character outside the hex range: 21 - 7E (see www.asciitable.com)
	return preg_replace('/[^\x21-\x7e]/', '', $encoded);
    }
} // class Relative_URI

