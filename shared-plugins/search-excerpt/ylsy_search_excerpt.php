<?php

/*
Plugin Name: Search Excerpt
Plugin URI: http://fucoder.com/code/search-excerpt/
Description: Modify <code>the_exceprt()</code> template code during search to return snippets containing the search phrase. Snippet extraction code stolen from <a href="http://drupal.org/">Drupal</a>'s search module. And patched by <a href="http://pobox.com/~jam/">Jam</a> to support Asian text.
Version: 1.2 $Rev: 19 $
Author: Scott Yang
Author URI: http://scott.yang.id.au/

*/

class SearchExcerpt {
    function get_content() {
        // Get the content of current post. We like to have the entire
        // content. If we call get_the_content() we'll only get the teaser +
        // page 1.
        global $post;
        
        // Password checking copied from
        // template-functions-post.php/get_the_content()
        // Search shouldn't match a passworded entry anyway.
        if ( post_password_required() ) {
                return get_the_password_form();
        }

        return $post->post_content;
    }
    
    function get_query($text) {
        static $last = null;
        static $lastsplit = null;

        if ($last == $text)
            return $lastsplit;

        // The dot, underscore and dash are simply removed. This allows
        // meaningful search behaviour with acronyms and URLs.
        $text = preg_replace('/[._-]+/', '', $text);

        // Process words
        $words = explode(' ', $text);

        // Save last keyword result
        $last = $text;
        $lastsplit = $words;

        return $words;
    }

    function highlight_excerpt($keys, $text) {
        $text = strip_tags($text);

        for ($i = 0; $i < sizeof($keys); $i ++)
            $keys[$i] = preg_quote($keys[$i], '/');

        $workkeys = $keys;

        // Extract a fragment per keyword for at most 4 keywords.  First we
        // collect ranges of text around each keyword, starting/ending at
        // spaces.  If the sum of all fragments is too short, we look for
        // second occurrences.
        $ranges = array();
        $included = array();
        $length = 0;
        while ($length < 256 && count($workkeys)) {
            foreach ($workkeys as $k => $key) {
                if (strlen($key) == 0) {
                    unset($workkeys[$k]);
                    continue;
                }
                if ($length >= 256) {
                    break;
                }
                // Remember occurrence of key so we can skip over it if more
                // occurrences are desired.
                if (!isset($included[$key])) {
                    $included[$key] = 0;
                }

                // NOTE: extra parameter for preg_match requires PHP 4.3.3
                if (preg_match('/'.$key.'/iu', $text, $match, 
                               PREG_OFFSET_CAPTURE, $included[$key])) 
                {
                    $p = $match[0][1];
                    $success = 0;
                    if (($q = strpos($text, ' ', max(0, $p - 60))) !== false && 
                         $q < $p)
                    {
                        $end = substr($text, $p, 80);
                        if (($s = strrpos($end, ' ')) !== false && $s > 0) {
                            $ranges[$q] = $p + $s;
                            $length += $p + $s - $q;
                            $included[$key] = $p + 1;
                            $success = 1;
                        }
                    }

                    if (!$success) {
                        // for the case of asian text without whitespace
                        $q = _jamul_find_1stbyte($text, max(0, $p - 60));
                        $q = _jamul_find_delimiter($text, $q);
                        $s = _jamul_find_1stbyte_reverse($text, $p + 80, $p);
                        $s = _jamul_find_delimiter($text, $s);
                        if (($s >= $p) && ($q <= $p)) {
                            $ranges[$q] = $s;
                            $length += $s - $q;
                            $included[$key] = $p + 1;
                        } else {
                            unset($workkeys[$k]);
                        }
                    }
                } else {
                    unset($workkeys[$k]);
                }
            }
        }

        // If we didn't find anything, return the beginning.
        if (sizeof($ranges) == 0)
            return '<p>' . _jamul_truncate($text, 256) . '&nbsp;...</p>';

        // Sort the text ranges by starting position.
        ksort($ranges);

        // Now we collapse overlapping text ranges into one. The sorting makes
        // it O(n).
        $newranges = array();
        foreach ($ranges as $from2 => $to2) {
            if (!isset($from1)) {
                $from1 = $from2;
                $to1 = $to2;
                continue;
            }
            if ($from2 <= $to1) {
                $to1 = max($to1, $to2);
            } else {
                $newranges[$from1] = $to1;
                $from1 = $from2;
                $to1 = $to2;
            }
        }
        $newranges[$from1] = $to1;

        // Fetch text
        $out = array();
        foreach ($newranges as $from => $to)
            $out[] = substr($text, $from, $to - $from);

        $text = (isset($newranges[0]) ? '' : '...&nbsp;').
            implode('&nbsp;...&nbsp;', $out).'&nbsp;...';
        $text = preg_replace('/('.implode('|', $keys) .')/iu', '<span class="search-excerpt">\0</span>', $text);
        return "<p>$text</p>";
    }

    function the_excerpt($text) {
        static $filter_deactivated = false;
        global $more;
        global $wp_query;

        // If we are not in a search - simply return the text unmodified.
        if (!is_search())
            return $text;

        // Deactivating some of the excerpt text.
        if (!$filter_deactivated) {
            remove_filter('the_excerpt', 'wpautop');
            $filter_deactivated = true;
        }

        // Get the whole document, not just the teaser.
        $more = 1;
        $query = SearchExcerpt::get_query($wp_query->query_vars['s']);
        $content = SearchExcerpt::get_content();

        return SearchExcerpt::highlight_excerpt($query, $content);
    }
}

// The number of bytes used when WordPress looking around to find delimiters
// (either a whitespace or a point where ASCII and other character switched).
// This also represents the number of bytes of few characters.
define('_JAMUL_LEN_SEARCH', 15);

function _jamul_find_1stbyte($string, $pos=0, $stop=-1) {
    $len = strlen($string);
    if ($stop < 0 || $stop > $len) {
        $stop = $len;
    }
    for (; $pos < $stop; $pos++) {
        if ((ord($string[$pos]) < 0x80) || (ord($string[$pos]) >= 0xC0)) {
            break;      // find 1st byte of multi-byte characters.
        }
    }
    return $pos;
}

function _jamul_find_1stbyte_reverse($string, $pos=-1, $stop=0) {
    $len = strlen($string);
    if ($pos < 0 || $pos >= $len) {
        $pos = $len - 1;
    }
    for (; $pos >= $stop; $pos--) {
        if ((ord($string[$pos]) < 0x80) || (ord($string[$pos]) >= 0xC0)) {
            break;      // find 1st byte of multi-byte characters.
        }
    }
    return $pos;
}

function _jamul_find_delimiter($string, $pos=0, $min = -1, $max=-1) {
    $len = strlen($string);
    if ($pos == 0 || $pos < 0 || $pos >= $len) {
        return $pos;
    }
    if ($min < 0) {
        $min = max(0, $pos - _JAMUL_LEN_SEARCH);
    }
    if ($max < 0 || $max >= $len) {
        $max = min($len - 1, $pos + _JAMUL_LEN_SEARCH);
    }
    if (ord($string[$pos]) < 0x80) {
        // Found ASCII character at the trimming point.  So, trying
        // to find new trimming point around $pos.  New trimming point
        // should be on a whitespace or the transition from ASCII to
        // other character.
        $pos3 = -1;
        for ($pos2 = $pos; $pos2 <= $max; $pos2++) {
            if ($string[$pos2] == ' ') {
                break;
            } else if ($pos3 < 0 && ord($string[$pos2]) >= 0x80) {
                $pos3 = $pos2;
            }
        }
        if ($pos2 > $max && $pos3 >= 0) {
            $pos2 = $pos3;
        }
        if ($pos2 > $max) {
            $pos3 = -1;
            for ($pos2 = $pos; $pos2 >= $min; $pos2--) {
                if ($string[$pos2] == ' ') {
                    break;
                } else if ($pos3 < 0 && ord($string[$pos2]) >= 0x80) {
                    $pos3 = $pos2 + 1;
                }
            }
            if ($pos2 < $min && $pos3 >= 0) {
                $pos2 = $pos3;
            }
        }
        if ($pos2 <= $max && $pos2 >= $min) {
            $pos = $pos2;
        }
    } else if ((ord($string[$pos]) >= 0x80) || (ord($string[$pos]) < 0xC0)) {
        $pos = _jamul_find_1stbyte($string, $pos, $max);
    }
    return $pos;
}

function _jamul_truncate($string, $byte) {
    $len = strlen($string);
    if ($len <= $byte)
        return $string;
    $byte = _jamul_find_1stbyte_reverse($string, $byte);
    return substr($string, 0, $byte);
}

// Add with priority=5 to ensure that it gets executed before wp_trim_excerpt
// in default filters.
add_filter('get_the_excerpt', array('SearchExcerpt', 'the_excerpt'), 5);

/*

History:

1.1 (2006-05-08)
- Merge in Jam's unicode fixes. 
  http://pobox.com/~jam/unix/wordpress/#plugins
- Try to be executed before wp_trim_excerpt() to avoid Aleksandar's issue.
- Use own get_content() function to bypass WP's pagination.

1.0 (2005-08-22)
- Initial release.

*/
?>
