<?php
class SEO_Auto_Linker_Front extends SEO_Auto_Linker_Base
{
    /**
     * A prefix hash for our SEO autolinker replacements.
     *
     * @since   0.8
     */
    private static $hash;

    /*
     * Container for our post autolinker posts
     */
    private static $links;

    /*
     * Container to cache the links in so we don't have to query for the same post repeatedly 
     */
    private static $no_links_cache = array();

    /*
     * Container for our options
     */
    private static $opts;

    /*
     * Container for the current post's permalink
     */
    private static $permalink;

    /**
     * Container for word boundaries.
     *
     * @since   0.9
     * @access  private
     * @var     array
     */
    private static $boundaries = array();

    /*
     * Adds actions and filters and such
     *
     * @since 0.7
     */
    public static function init()
    {
        self::$hash = md5('seo-auto-linker');
        add_filter(
            'the_content',
            array(get_class(), 'content'),
            1
        );
    }

    /*
     * Main event.  Filters the conntent to add links
     *
     * @since 0.7
     */
    public static function content($content)
    {
        global $post;
        if(!self::allowed($post))
            return $content;

        self::setup_links($post);
        if(!self::$links)
            return $content;

        $header_replacements = array();
        $link_replacements = array();
        $other_replacements = array();
        $shortcode_replacements = array();
        $filtered = $content;

        preg_match_all('/' . get_shortcode_regex() . '/', $filtered, $scodes);
        if(!empty($scodes[0]))
        {
            $shortcode_replacements = self::gen_replacements($scodes[0], 'shortcode');
            $filtered = self::replace($shortcode_replacements, $filtered);
        }

        preg_match_all('/<h[1-6][^>]*>.+?<\/h[1-6]>/iu', $filtered, $headers);
        if(!empty($headers[0]))
        {
            $header_replacements = self::gen_replacements($headers[0], 'header');
            $filtered = self::replace($header_replacements, $filtered);
        }

        preg_match_all('/<(img|input)(.*?) \/?>/iu', $filtered, $others);
        if(!empty($others[0]))
        {
            $other_replacements = self::gen_replacements($others[0], 'others');
            $filtered = self::replace($other_replacements, $filtered);
        }

        foreach(self::$links as $count => $l)
        {
            if(apply_filters('seoal_should_continue', false, $l, $count))
            {
                continue;
            }

            preg_match_all(
                '/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/iu',
                $filtered,
                $links
            );
            if(!empty($links[0]))
            {
                $start = count($link_replacements);
                $tmp = self::gen_replacements($links[0], 'links', $start);
                $filtered = self::replace($tmp, $filtered);
                $link_replacements = array_merge(
                    $link_replacements,
                    $tmp
                );
            }

            $regex = self::get_kw_regex($l);
            $url = self::get_link_url($l);
            $max = self::get_link_max($l);

            if(
                !$regex || !$url || !$max ||
                ($url == self::$permalink && !self::self_links_allowed($l))
            ) continue;

            $cls = apply_filters('seoal_link_class', 'auto-link', $l);
            $target = self::get_link_target($l);
            $replace = sprintf(
                '$1<a href="%1$s" title="$2" target="%2$s" %3$s %4$s>$2</a>$3',
                esc_url($url),
                esc_attr($target),
                $cls ? 'class="' . esc_attr($cls) . '"' : '',
                self::is_nofollow($l) ? 'rel="nofollow"' : ''
            );

            $filtered = preg_replace(
                $regex,
                $replace,
                $filtered,
                absint($max)
            );
        }

        $filtered = apply_filters('seoal_pre_replace', $filtered, $post);

        $filtered = self::replace_bak($shortcode_replacements, $filtered);
        $filtered = self::replace_bak($header_replacements, $filtered);
        $filtered = self::replace_bak($link_replacements, $filtered);
        $filtered = self::replace_bak($other_replacements, $filtered);

        return apply_filters('seoal_post_replace', $filtered, $post);
    }

    /*
     * Determins whether or not a post can be editted
     */
    protected static function allowed($post)
    {
        $rv = true;

        if(
            (!is_singular() && apply_filters('seoal_only_single', true)) ||
            !in_the_loop()
        ) $rv = false;

        if(strpos($post->post_content, '<!--nolinks-->') !== false)
            $rv = false;

        self::$opts = get_option(self::SETTING, array());
        if(!isset(self::$opts['blacklist']))
            self::$opts['blacklist'] = array();

        self::$permalink = get_permalink($post);

        if(in_array(self::$permalink, self::$opts['blacklist']))
            $rv = false;

        return apply_filters('seoal_allowed', $rv, $post);
    }

    /*
     * Fetch all of the links posts
     *
     * @since 0.7
     */
    protected static function setup_links($post)
    {

        $links = apply_filters('pre_seoal_links', false, $post);
        if(false !== $links)
        {
            self::$links = $links;
            return;
        }

        if ( isset( self::$no_links_cache[$post->ID] ) && true == self::$no_links_cache[$post->ID] ) {
            self::$links = array();
            return;
        }

        $links = get_posts(array(
            'post_type'   => self::POST_TYPE,
            'numberposts' => apply_filters('seoal_number_links', -1),
            'meta_query'  => array(
                'relation' => 'AND',
                array(
                    'key'     => self::get_key("type_{$post->post_type}"),
                    'value'   => 'on',
                    'compare' => '='
                ),
                array(
                    'key'     => self::get_key('url'),
                    'compare' => 'EXISTS' // doesn't do anything, just a reminder
                ),
                array(
                    'key'     => self::get_key('keywords'),
                    'compare' => 'EXISTS' // doesn't do anything, just a reminder
                )
            ),
            'suppress_filters' => false,
        ));

        if ( empty( $links ) )
            self::$no_links_cache[$post->ID] = true;

        $rv = array();
        if($links)
        {
            foreach($links as $l)
            {
                $blacklist = self::get_meta($l, 'blacklist');
                if(!$blacklist || !in_array(self::$permalink, (array)$blacklist))
                    $rv[] = $l;
            }
        }
        self::$links = apply_filters('seoal_links', $rv, $post);
    }

    /*
     * Get the regex for a link
     *
     * @since 0.7
     */
    protected static function get_kw_regex($link)
    {
        $keywords = self::get_keywords($link);
        if(!$keywords)
            return false;

        list($ob, $cb) = self::get_boundaries($link);

        return sprintf(
            '/(%s)(%s)(%s)/ui',
            $ob,
            implode('|', $keywords),
            $cb
        );
    }

    /*
     * fetch the clean and sanitied keywords
     *
     * @since 0.7
     */
    protected static function get_keywords($link)
    {
        $keywords = self::get_meta($link, 'keywords');
        $kw_arr = explode(',', $keywords);
        $kw_arr = apply_filters('seoal_link_keywords', $kw_arr, $link);
        $kw_arr = array_map('trim', (array)$kw_arr);
        $kw_out = array();
        foreach($kw_arr as $kw)
        {
            // Second argument of `preg_quote`? Does not default to `/`
            $kw_out[] = preg_quote($kw, '/');
        }
        return $kw_out;
    }

    /*
     * Get the link URL for a keyword
     *
     * @since 0.7
     */
    protected static function get_link_url($link)
    {
        $meta = self::get_meta($link, 'url');
        return apply_filters('seoal_link_url', $meta, $link);
    }

    /*
     * Get the maximum number of time a link can be replaced
     *
     * @since 0.7
     */
    protected static function get_link_max($link)
    {
        $meta = self::get_meta($link, 'times');
        $meta = absint($meta) ? absint($meta) : 1;
        return apply_filters('seoal_link_max', $meta, $link);
    }

    /*
     * Get the target attribute for a given link
     *
     * @since 0.7.2
     * @return string The escaped target att
     */
    protected static function get_link_target($link)
    {
        $target = self::get_meta($link, 'target');
        $target = apply_filters('seoal_link_target', $target, $link);
        if(!in_array($target, array_keys(self::get_targets())))
        {
            $target = '_self';
        }
        return esc_attr($target);
    }

    /**
     * Check whether or not a link allows a given post to have links to itself.
     *
     * @since   0.85
     * @access  protected
     * @return  bool
     */
    protected static function self_links_allowed($link)
    {
        return apply_filters(
            'seoal_allow_self_links', 
            'on' == self::get_meta($link, 'self_links'),
            $link
        );
    }

    /**
     * check whether or not a link is nofollowed.
     *
     * @since   0.85
     * @access  protected
     * @return  bool
     */
    protected static function is_nofollow($link)
    {
        return apply_filters(
            'seoal_link_nofollow',
            'on' == self::get_meta($link, 'nofollow'),
            $link
        );
    }

    /*
     * Replace get meta
     *
     * @since 0.7
     */
    protected static function get_meta($post, $key='')
    {
        $res = apply_filters('seoal_pre_get_meta', false, $key, $post);
        if($res !== false)
        {
            return $res;
        }
        if(isset($post->ID))
        {
            $res = get_post_meta($post->ID, self::get_key($key), true);
        }
        else
        {
            $res = '';
        }
        return $res;
    }

    /*
     * Loop through a an array of matches and create an associative array of 
     * key value pairs to use for str replacements
     *
     * @todo Look into just hashing the entire array key with md5 or
     * something.  Might help avoid conflicts?
     *
     * @since 0.7
     */
    protected function gen_replacements($arr, $key, $start=0)
    {
        $rv = array();
        $h = self::$hash;
        foreach($arr as $a)
        {
            $rv["<!--{$h}-{$key}-{$start}-->"] = $a;
            $start++;
        }
        return $rv;
    }

    /*
     * Wrapper around str_replace
     *
     * @since 0.7
     */
    protected static function replace($arr, $content)
    {
        return str_replace(
            array_values($arr),
            array_keys($arr),
            $content
        );
    }

    protected static function replace_bak($arr, $content)
    {
        return str_replace(
            array_keys($arr),
            array_values($arr),
            $content
        );
    }

    /**
     * Get regex word boundaries.
     *
     * @since   0.9
     * @access  protected
     * @uses    get_option
     * @return  array
     */
    public static function get_boundaries($link)
    {
        $opts = get_option(self::SETTING, array());

        $alt_b = isset($opts['word_boundary']) && 'on' == $opts['word_boundary'];

        if(apply_filters('seoal_unicode_boundaries', $alt_b, $link))
        {
            $ob = '(?<!\pL)'; // Negative look behind (anything that isn't a unicode letter)
            $cb = '(?!\pL)'; // Negative look ahead (anything that isn't a unicode letter)
        }
        else
        {
            $ob = $cb = '\b';
        }

        // Don't change these unless you know what you're doing. Really.
        $ob = apply_filters('seoal_opening_word_boundary', $ob, $link);
        $cb = apply_filters('seoal_closing_word_boundary', $cb, $link);

        return array($ob, $cb);
    }
}

SEO_Auto_Linker_Front::init();
