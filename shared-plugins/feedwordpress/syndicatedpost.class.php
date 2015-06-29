<?php
require_once(dirname(__FILE__).'/feedtime.class.php');

/**
 * class SyndicatedPost: FeedWordPress uses to manage the conversion of
 * incoming items from the feed parser into posts for the WordPress
 * database. It contains several internal management methods primarily
 * of interest to someone working on the FeedWordPress source, as well
 * as some utility methods for extracting useful data from many
 * different feed formats, which may be useful to FeedWordPress users
 * who make use of feed data in PHP add-ons and filters.
 *
 * @version 2010.0531
 */
class SyndicatedPost {
	var $item = null;	// MagpieRSS representation
	var $entry = null;	// SimplePie_Item representation

	var $link = null;
	var $feed = null;
	var $feedmeta = null;
	
	var $xmlns = array ();

	var $post = array ();

	var $_freshness = null;
	var $_wp_id = null;

	/**
	 * SyndicatedPost constructor: Given a feed item and the source from
	 * which it was taken, prepare a post that can be inserted into the
	 * WordPress database on request, or updated in place if it has already
	 * been syndicated.
	 *
	 * @param array $item The item syndicated from the feed.
	 * @param SyndicatedLink $source The feed it was syndicated from.
	 */
	function SyndicatedPost ($item, $source) {
		if (is_array($item)
		and isset($item['simplepie'])
		and isset($item['magpie'])) :
			$this->entry = $item['simplepie'];
			$this->item = $item['magpie'];
			$item = $item['magpie'];
		else :
			$this->item = $item;
		endif;

		FeedWordPress::diagnostic('feed_items', 'Considering item ['.$this->guid().'] "'.$this->entry->get_title().'"');

		$this->link = $source;
		$this->feed = $source->magpie;
		$this->feedmeta = $source->settings;

		# Dealing with namespaces can get so ...
		$this->xmlns['forward'] = $source->magpie->_XMLNS_FAMILIAR;
		$this->xmlns['reverse'] = array();
		foreach ($this->xmlns['forward'] as $url => $ns) :
			if (!isset($this->xmlns['reverse'][$ns])) :
				$this->xmlns['reverse'][$ns] = array();
			endif;
			$this->xmlns['reverse'][$ns][] = $url; 
		endforeach;
		
		// Fucking SimplePie.
		$this->xmlns['reverse']['rss'][] = '';

		# These globals were originally an ugly kludge around a bug in
		# apply_filters from WordPress 1.5. The bug was fixed in 1.5.1,
		# and I sure hope at this point that nobody writing filters for
		# FeedWordPress is still relying on them.
		#
		# Anyway, I hereby declare them DEPRECATED as of 8 February
		# 2010. I'll probably remove the globals within 1-2 releases in
		# the interests of code hygiene and memory usage. If you
		# currently use them in your filters, I advise you switch off to
		# accessing the public members SyndicatedPost::feed and
		# SyndicatedPost::feedmeta.

		global $fwp_channel, $fwp_feedmeta;
		$fwp_channel = $this->feed; $fwp_feedmeta = $this->feedmeta;

		// Trigger global syndicated_item filter.
		$this->item = apply_filters('syndicated_item', $this->item, $this);
		
		// Allow for feed-specific syndicated_item filters.
		$this->item = apply_filters(
			"syndicated_item_".$source->uri(),
			$this->item,
			$this
		);

		# Filters can halt further processing by returning NULL
		if (is_null($this->item)) :
			$this->post = NULL;
		else :
			# Note that nothing is run through $wpdb->escape() here.
			# That's deliberate. The escaping is done at the point
			# of insertion, not here, to avoid double-escaping and
			# to avoid screwing with syndicated_post filters

			$this->post['post_title'] = apply_filters(
				'syndicated_item_title',
				$this->entry->get_title(), $this
			);

			$this->post['named']['author'] = apply_filters(
				'syndicated_item_author',
				$this->author(), $this
			);
			// This just gives us an alphanumeric name for the author.
			// We look up (or create) the numeric ID for the author
			// in SyndicatedPost::add().

			$this->post['post_content'] = apply_filters(
				'syndicated_item_content',
				$this->content(), $this
			);
			
			$excerpt = apply_filters('syndicated_item_excerpt', $this->excerpt(), $this);
			if (!empty($excerpt)):
				$this->post['post_excerpt'] = $excerpt;
			endif;
			
			$this->post['epoch']['issued'] = apply_filters('syndicated_item_published', $this->published(), $this);
			$this->post['epoch']['created'] = apply_filters('syndicated_item_created', $this->created(), $this);
			$this->post['epoch']['modified'] = apply_filters('syndicated_item_updated', $this->updated(), $this);

			// Dealing with timestamps in WordPress is so ...
			$offset = (int) get_option('gmt_offset') * 60 * 60;
			$this->post['post_date'] = gmdate('Y-m-d H:i:s', apply_filters('syndicated_item_published', $this->published(/*fallback=*/ true, /*default=*/ -1), $this) + $offset);
			$this->post['post_modified'] = gmdate('Y-m-d H:i:s', apply_filters('syndicated_item_updated', $this->updated(/*fallback=*/ true, /*default=*/ -1), $this) + $offset);
			$this->post['post_date_gmt'] = gmdate('Y-m-d H:i:s', apply_filters('syndicated_item_published', $this->published(/*fallback=*/ true, /*default=*/ -1), $this));
			$this->post['post_modified_gmt'] = gmdate('Y-m-d H:i:s', apply_filters('syndicated_item_updated', $this->updated(/*fallback=*/ true, /*default=*/ -1), $this));

			// Use feed-level preferences or the global default.
			$this->post['post_status'] = $this->link->syndicated_status('post', 'publish');
			$this->post['comment_status'] = $this->link->syndicated_status('comment', 'closed');
			$this->post['ping_status'] = $this->link->syndicated_status('ping', 'closed');

			// Unique ID (hopefully a unique tag: URI); failing that, the permalink
			$this->post['guid'] = apply_filters('syndicated_item_guid', $this->guid(), $this);

			// User-supplied custom settings to apply to each post. Do first so that FWP-generated custom settings will overwrite if necessary; thus preventing any munging
			$default_custom_settings = get_option('feedwordpress_custom_settings');
			if ($default_custom_settings and !is_array($default_custom_settings)) :
				$default_custom_settings = unserialize($default_custom_settings);
			endif;
			if (!is_array($default_custom_settings)) :
				$default_custom_settings = array();
			endif;
			
			$custom_settings = (isset($this->link->settings['postmeta']) ? $this->link->settings['postmeta'] : null);
			if ($custom_settings and !is_array($custom_settings)) :
				$custom_settings = unserialize($custom_settings);
			endif;
			if (!is_array($custom_settings)) :
				$custom_settings = array();
			endif;
			
			$postMetaIn = array_merge($default_custom_settings, $custom_settings);
			$postMetaOut = array();

			// Big ugly loop to do any element substitutions
			// that we may need.
			foreach ($postMetaIn as $key => $values) :
				if (is_string($values)) : $values = array($values); endif;
				
				$postMetaOut[$key] = array();
				foreach ($values as $value) :
					if (preg_match('/\$\( ([^)]+) \)/x', $value, $ref)) :
						$elements = $this->query($ref[1]);
						foreach ($elements as $element) :
							$postMetaOut[$key][] = str_replace(
								$ref[0],
								$element,
								$value
							);
						endforeach;
					else :
						$postMetaOut[$key][] = $value;	
					endif;
				endforeach;
			endforeach;

			foreach ($postMetaOut as $key => $values) :
				$this->post['meta'][$key] = array();
				foreach ($values as $value) :
					$this->post['meta'][$key][] = apply_filters("syndicated_post_meta_{$key}", $value, $this);
				endforeach;
			endforeach;

			// RSS 2.0 / Atom 1.0 enclosure support
			$enclosures = $this->entry->get_enclosures();
			if (is_array($enclosures)) : foreach ($enclosures as $enclosure) :
				$this->post['meta']['enclosure'][] =
					apply_filters('syndicated_item_enclosure_url', $enclosure->get_link(), $this)."\n".
					apply_filters('syndicated_item_enclosure_length', $enclosure->get_length(), $this)."\n".
					apply_filters('syndicated_item_enclosure_type', $enclosure->get_type(), $this);
			endforeach; endif;

			// In case you want to point back to the blog this was syndicated from
			if (isset($this->feed->channel['title'])) :
				$this->post['meta']['syndication_source'] = apply_filters('syndicated_item_source_title', $this->feed->channel['title'], $this);
			endif;

			if (isset($this->feed->channel['link'])) :
				$this->post['meta']['syndication_source_uri'] = apply_filters('syndicated_item_source_link', $this->feed->channel['link'], $this);
			endif;
			
			// Make use of atom:source data, if present in an aggregated feed
			if (isset($this->item['source_title'])) :
				$this->post['meta']['syndication_source_original'] = $this->item['source_title'];
			endif;

			if (isset($this->item['source_link'])) :
				$this->post['meta']['syndication_source_uri_original'] = $this->item['source_link'];
			endif;
			
			if (isset($this->item['source_id'])) :
				$this->post['meta']['syndication_source_id_original'] = $this->item['source_id'];
			endif;

			// Store information on human-readable and machine-readable comment URIs
			
			// Human-readable comment URI
			$commentLink = apply_filters('syndicated_item_comments', $this->comment_link(), $this);
			if (!is_null($commentLink)) : $this->post['meta']['rss:comments'] = $commentLink; endif;

			// Machine-readable content feed URI
			$commentFeed = apply_filters('syndicated_item_commentrss', $this->comment_feed(), $this);
			if (!is_null($commentFeed)) :	$this->post['meta']['wfw:commentRSS'] = $commentFeed; endif;
			// Yeah, yeah, now I know that it's supposed to be
			// wfw:commentRss. Oh well. Path dependence, sucka.

			// Store information to identify the feed that this came from
			if (isset($this->feedmeta['link/uri'])) :
				$this->post['meta']['syndication_feed'] = $this->feedmeta['link/uri'];
			endif;
			if (isset($this->feedmeta['link/id'])) :
				$this->post['meta']['syndication_feed_id'] = $this->feedmeta['link/id'];
			endif;

			if (isset($this->item['source_link_self'])) :
				$this->post['meta']['syndication_feed_original'] = $this->item['source_link_self'];
			endif;

			// In case you want to know the external permalink...
			$this->post['meta']['syndication_permalink'] = apply_filters('syndicated_item_link', $this->permalink());

			// Store a hash of the post content for checking whether something needs to be updated
			$this->post['meta']['syndication_item_hash'] = $this->update_hash();

			// Feed-by-feed options for author and category creation
			$this->post['named']['unfamiliar']['author'] = (isset($this->feedmeta['unfamiliar author']) ? $this->feedmeta['unfamiliar author'] : null);
			$this->post['named']['unfamiliar']['category'] = (isset($this->feedmeta['unfamiliar category']) ? $this->feedmeta['unfamiliar category'] : null);

			// Categories: start with default categories, if any
			$fc = get_option("feedwordpress_syndication_cats");
			if ($fc) :
				$this->post['named']['preset/category'] = explode("\n", $fc);
			else :
				$this->post['named']['preset/category'] = array();
			endif;

			if (isset($this->feedmeta['cats']) and is_array($this->feedmeta['cats'])) :
				$this->post['named']['preset/category'] = array_merge($this->post['named']['preset/category'], $this->feedmeta['cats']);
			endif;

			// Now add categories from the post, if we have 'em
			$this->post['named']['category'] = array();
			if ( isset($this->item['category#']) ) :
				for ($i = 1; $i <= $this->item['category#']; $i++) :
					$cat_idx = (($i > 1) ? "#{$i}" : "");
					$cat = $this->item["category{$cat_idx}"];

					if ( isset($this->feedmeta['cat_split']) and strlen($this->feedmeta['cat_split']) > 0) :
						$pcre = "\007".$this->feedmeta['cat_split']."\007";
						$this->post['named']['category'] = array_merge($this->post['named']['category'], preg_split($pcre, $cat, -1 /*=no limit*/, PREG_SPLIT_NO_EMPTY));
					else :
						$this->post['named']['category'][] = $cat;
					endif;
				endfor;
			endif;
			$this->post['named']['category'] = apply_filters('syndicated_item_categories', $this->post['named']['category'], $this);
			
			// Tags: start with default tags, if any
			$ft = get_option("feedwordpress_syndication_tags");
			if ($ft) :
				$this->post['tags_input'] = explode(FEEDWORDPRESS_CAT_SEPARATOR, $ft);
			else :
				$this->post['tags_input'] = array();
			endif;
			
			if (isset($this->feedmeta['tags']) and is_array($this->feedmeta['tags'])) :
				$this->post['tags_input'] = array_merge($this->post['tags_input'], $this->feedmeta['tags']);
			endif;

			$this->post['tags_input'] = apply_filters('syndicated_item_tags', $this->post['tags_input'], $this);

		endif;
	} /* SyndicatedPost::SyndicatedPost() */

	#####################################
	#### EXTRACT DATA FROM FEED ITEM ####
	#####################################
	
	/**
	 * SyndicatedPost::query uses an XPath-like syntax to query arbitrary
	 * elements within the syndicated item.
	 *
	 * @param string $path
	 * @returns array of string values representing contents of matching
	 * elements or attributes
	 */
	 function query ($path) {
	 	$urlHash = array();

	 	// Allow {url} notation for namespaces. URLs will contain : and /, so...
	 	preg_match_all('/{([^}]+)}/', $path, $match, PREG_SET_ORDER);
	 	foreach ($match as $ref) :
	 	 	$urlHash[md5($ref[1])] = $ref[1];
		endforeach;
	
		foreach ($urlHash as $hash => $url) :
			$path = str_replace('{'.$url.'}', '{#'.$hash.'}', $path); 
		endforeach;

		$path = explode('/', $path);
		foreach ($path as $index => $node) :
			if (preg_match('/{#([^}]+)}/', $node, $ref)) :
				if (isset($urlHash[$ref[1]])) :
					$path[$index] = str_replace(
						'{#'.$ref[1].'}',
						'{'.$urlHash[$ref[1]].'}',
						$node
					);
				endif;
			endif;
		endforeach;

		// Start out with a get_item_tags query.
		$node = '';
		while (strlen($node)==0 and !is_null($node)) :
			$node = array_shift($path);
		endwhile;
		
		switch ($node) :
		case 'feed' :
		case 'channel' :
			$method = "get_${node}_tags";
			$node = array_shift($path);
			break;
		case 'item' :
			$node = array_shift($path);
		default :
			$method = NULL;
		endswitch;

		$data = array();
		if (!is_null($node)) :
			list($namespaces, $element) = $this->xpath_extended_name($node);
			
			$matches = array();
			foreach ($namespaces as $ns) :
				if (!is_null($method)) :	
					$el = $this->link->simplepie->{$method}($ns, $element);
				else :
					$el = $this->entry->get_item_tags($ns, $element);
				endif;

				if (!is_null($el)) :
					$matches = array_merge($matches, $el);
				endif;
			endforeach;
			$data = $matches;
		
			$node = array_shift($path);
		endif;

		while (!is_null($node)) :
			if (strlen($node) > 0) :
				$matches = array();
		
				list($namespaces, $element) = $this->xpath_extended_name($node);
		
				if (preg_match('/^@(.*)$/', $element, $ref)) :
					$element = $ref[1];
					$axis = 'attribs';
				else :
					$axis = 'child';
				endif;

				foreach ($data as $datum) :
					foreach ($namespaces as $ns) :
						if (!is_string($datum)
						and isset($datum[$axis][$ns][$element])) :
							if (is_string($datum[$axis][$ns][$element])) :
								$matches[] = $datum[$axis][$ns][$element];
							else :
								$matches = array_merge($matches, $datum[$axis][$ns][$element]);
							endif;
						endif;
					endforeach;
				endforeach;
		
				$data = $matches;
			endif;
			$node = array_shift($path);
		endwhile;
	
		$matches = array();
		foreach ($data as $datum) :
			if (is_string($datum)) :
				$matches[] = $datum;
			elseif (isset($datum['data'])) :
				$matches[] = $datum['data'];
			endif;
		endforeach;
		return $matches;
	} /* SyndicatedPost::query() */

	function xpath_default_namespace () {
		// Get the default namespace.
		$type = $this->link->simplepie->get_type();
		if ($type & SIMPLEPIE_TYPE_ATOM_10) :
			$defaultNS = SIMPLEPIE_NAMESPACE_ATOM_10;
		elseif ($type & SIMPLEPIE_TYPE_ATOM_03) :
			$defaultNS = SIMPLEPIE_NAMESPACE_ATOM_03;
		elseif ($type & SIMPLEPIE_TYPE_RSS_090) :
			$defaultNS = SIMPLEPIE_NAMESPACE_RSS_090;
		elseif ($type & SIMPLEPIE_TYPE_RSS_10) :
			$defaultNS = SIMPLEPIE_NAMESPACE_RSS_10;
		elseif ($type & SIMPLEPIE_TYPE_RSS_20) :
			$defaultNS = SIMPLEPIE_NAMESPACE_RSS_20;
		else :
			$defaultNS = SIMPLEPIE_NAMESPACE_RSS_20;
		endif;
		return $defaultNS;	
	} /* SyndicatedPost::xpath_default_namespace() */
	
	function xpath_extended_name ($node) {
		$ns = NULL; $element = NULL;
		
		if (substr($node, 0, 1)=='@') :
			$attr = '@'; $node = substr($node, 1);
		else :
			$attr = '';
		endif;
				
		if (preg_match('/^{([^}]*)}(.*)$/', $node, $ref)) :
			$ns = array($ref[1]); $element = $ref[2];
		elseif (strpos($node, ':') !== FALSE) :
			list($xmlns, $element) = explode(':', $node, 2);
			if (isset($this->xmlns['reverse'][$xmlns])) :
				$ns = $this->xmlns['reverse'][$xmlns];
			else :
				$ns = array($xmlns);
			endif;
			
			// Fucking SimplePie. For attributes in default xmlns.
			if ($xmlns==$this->xmlns['forward'][$defaultNS[0]]) :
				$ns[] = '';
			endif;
		else :
			// Often in SimplePie, the default namespace gets stored
			// as an empty string rather than a URL.
			$ns = array($this->xpath_default_namespace(), '');
			$element = $node;
		endif;
		return array(array_unique($ns), $attr.$element);
	} /* SyndicatedPost::xpath_extended_name () */

	function content () {
		$content = NULL;
		if (isset($this->item['atom_content'])) :
			$content = $this->item['atom_content'];
		elseif (isset($this->item['xhtml']['body'])) :
			$content = $this->item['xhtml']['body'];
		elseif (isset($this->item['xhtml']['div'])) :
			$content = $this->item['xhtml']['div'];
		elseif (isset($this->item['content']['encoded']) and $this->item['content']['encoded']):
			$content = $this->item['content']['encoded'];
		elseif (isset($this->item['description'])):
			$content = $this->item['description'];
		endif;
		return $content;
	} /* SyndicatedPost::content() */

	function excerpt () {
		# Identify and sanitize excerpt: atom:summary, or rss:description
		$excerpt = $this->entry->get_description();
			
		# Many RSS feeds use rss:description, inadvisably, to
		# carry the entire post (typically with escaped HTML).
		# If that's what happened, we don't want the full
		# content for the excerpt.
		$content = $this->content();
		
		// Ignore whitespace, case, and tag cruft.
		$theExcerpt = preg_replace('/\s+/', '', strtolower(strip_tags($excerpt)));
		$theContent = preg_replace('/\s+/', '', strtolower(strip_Tags($content)));

		if ( empty($excerpt) or $theExcerpt == $theContent ) :
			# If content is available, generate an excerpt.
			if ( strlen(trim($content)) > 0 ) :
				$excerpt = strip_tags($content);
				if (strlen($excerpt) > 255) :
					$excerpt = substr($excerpt,0,252).'...';
				endif;
			endif;
		endif;
		return $excerpt;
	} /* SyndicatedPost::excerpt() */

	function permalink () {
		// Handles explicit <link> elements and also RSS 2.0 cases with
		// <guid isPermaLink="true">, etc. Hooray!
		$permalink = $this->entry->get_link();
		return $permalink;
	}

	function created () {
		$date = '';
		if (isset($this->item['dc']['created'])) :
			$date = $this->item['dc']['created'];
		elseif (isset($this->item['dcterms']['created'])) :
			$date = $this->item['dcterms']['created'];
		elseif (isset($this->item['created'])): // Atom 0.3
			$date = $this->item['created'];
		endif;

		$epoch = new FeedTime($date);
		return $epoch->timestamp();
	} /* SyndicatedPost::created() */

	function published ($fallback = true, $default = NULL) {
		$date = '';

		# RSS is a mess. Figure out whether we have a date in
		# <dc:date>, <issued>, <pubDate>, etc., and get it into Unix
		# epoch format for reformatting. If we can't find anything,
		# we'll use the last-updated time.
		if (isset($this->item['dc']['date'])):				// Dublin Core
			$date = $this->item['dc']['date'];
		elseif (isset($this->item['dcterms']['issued'])) :		// Dublin Core extensions
			$date = $this->item['dcterms']['issued'];
		elseif (isset($this->item['published'])) : 			// Atom 1.0
			$date = $this->item['published'];
		elseif (isset($this->item['issued'])): 				// Atom 0.3
			$date = $this->item['issued'];
		elseif (isset($this->item['pubdate'])):				// RSS 2.0
			$date = $this->item['pubdate'];
		endif;
		
		if (strlen($date) > 0) :
			$time = new FeedTime($date);
			$epoch = $time->timestamp();
		elseif ($fallback) :						// Fall back to <updated> / <modified> if present
			$epoch = $this->updated(/*fallback=*/ false, /*default=*/ $default);
		endif;
		
		# If everything failed, then default to the current time.
		if (is_null($epoch)) :
			if (-1 == $default) :
				$epoch = time();
			else :
				$epoch = $default;
			endif;
		endif;
		
		return $epoch;
	} /* SyndicatedPost::published() */

	function updated ($fallback = true, $default = -1) {
		$date = '';

		# As far as I know, only dcterms and Atom have reliable ways to
		# specify when something was *modified* last. If neither is
		# available, then we'll try to get the time of publication.
		if (isset($this->item['dc']['modified'])) : 			// Not really correct
			$date = $this->item['dc']['modified'];
		elseif (isset($this->item['dcterms']['modified'])) :		// Dublin Core extensions
			$date = $this->item['dcterms']['modified'];
		elseif (isset($this->item['modified'])):			// Atom 0.3
			$date = $this->item['modified'];
		elseif (isset($this->item['updated'])):				// Atom 1.0
			$date = $this->item['updated'];
		endif;
		
		if (strlen($date) > 0) :
			$time = new FeedTime($date);
			$epoch = $time->timestamp();
		elseif ($fallback) :						// Fall back to issued / dc:date
			$epoch = $this->published(/*fallback=*/ false, /*default=*/ $default);
		endif;
		
		# If everything failed, then default to the current time.
		if (is_null($epoch)) :
			if (-1 == $default) :
				$epoch = time();
			else :
				$epoch = $default;
			endif;
		endif;

		return $epoch;
	} /* SyndicatedPost::updated() */

	function update_hash () {
		return md5(serialize($this->item));
	} /* SyndicatedPost::update_hash() */

	function guid () {
		$guid = null;
		if (isset($this->item['id'])): 			// Atom 0.3 / 1.0
			$guid = $this->item['id'];
		elseif (isset($this->item['atom']['id'])) :	// Namespaced Atom
			$guid = $this->item['atom']['id'];
		elseif (isset($this->item['guid'])) :		// RSS 2.0
			$guid = $this->item['guid'];
		elseif (isset($this->item['dc']['identifier'])) :// yeah, right
			$guid = $this->item['dc']['identifier'];
		else :
			// The feed does not seem to have provided us with a
			// unique identifier, so we'll have to cobble together
			// a tag: URI that might work for us. The base of the
			// URI will be the host name of the feed source ...
			$bits = parse_url($this->feedmeta['link/uri']);
			$guid = 'tag:'.$bits['host'];

			// If we have a date of creation, then we can use that
			// to uniquely identify the item. (On the other hand, if
			// the feed producer was consicentious enough to
			// generate dates of creation, she probably also was
			// conscientious enough to generate unique identifiers.)
			if (!is_null($this->created())) :
				$guid .= '://post.'.date('YmdHis', $this->created());
			
			// Otherwise, use both the URI of the item, *and* the
			// item's title. We have to use both because titles are
			// often not unique, and sometimes links aren't unique
			// either (e.g. Bitch (S)HITLIST, Mozilla Dot Org news,
			// some podcasts). But it's rare to have *both* the same
			// title *and* the same link for two different items. So
			// this is about the best we can do.
			else :
				$guid .= '://'.md5($this->item['link'].'/'.$this->item['title']);
			endif;
		endif;
		return $guid;
	} /* SyndicatedPost::guid() */
	
	function author () {
		$author = array ();
		
		if (isset($this->item['author_name'])):
			$author['name'] = $this->item['author_name'];
		elseif (isset($this->item['dc']['creator'])):
			$author['name'] = $this->item['dc']['creator'];
		elseif (isset($this->item['dc']['contributor'])):
			$author['name'] = $this->item['dc']['contributor'];
		elseif (isset($this->feed->channel['dc']['creator'])) :
			$author['name'] = $this->feed->channel['dc']['creator'];
		elseif (isset($this->feed->channel['dc']['contributor'])) :
			$author['name'] = $this->feed->channel['dc']['contributor'];
		elseif (isset($this->feed->channel['author_name'])) :
			$author['name'] = $this->feed->channel['author_name'];
		elseif ($this->feed->is_rss() and isset($this->item['author'])) :
			// The author element in RSS is allegedly an
			// e-mail address, but lots of people don't use
			// it that way. So let's make of it what we can.
			$author = parse_email_with_realname($this->item['author']);
			
			if (!isset($author['name'])) :
				if (isset($author['email'])) :
					$author['name'] = $author['email'];
				else :
					$author['name'] = $this->feed->channel['title'];
				endif;
			endif;
		else :
			$author['name'] = $this->feed->channel['title'];
		endif;
		
		if (isset($this->item['author_email'])):
			$author['email'] = $this->item['author_email'];
		elseif (isset($this->feed->channel['author_email'])) :
			$author['email'] = $this->feed->channel['author_email'];
		endif;
		
		if (isset($this->item['author_url'])):
			$author['uri'] = $this->item['author_url'];
		elseif (isset($this->feed->channel['author_url'])) :
			$author['uri'] = $this->item['author_url'];
		elseif (isset($this->feed->channel['link'])) :
			$author['uri'] = $this->feed->channel['link'];
		endif;

		return $author;
	} /* SyndicatedPost::author() */

	/**
	 * SyndicatedPost::isTaggedAs: Test whether a feed item is
	 * tagged / categorized with a given string. Case and leading and
	 * trailing whitespace are ignored.
	 *
	 * @param string $tag Tag to check for
	 *
	 * @return bool Whether or not at least one of the categories / tags on 
	 *	$this->item is set to $tag (modulo case and leading and trailing
	 * 	whitespace)
	 */
	function isTaggedAs ($tag) {
		$desiredTag = strtolower(trim($tag)); // Normalize case and whitespace

		// Check to see if this is tagged with $tag
		$currentCategory = 'category';
		$currentCategoryNumber = 1;

		// If we have the new MagpieRSS, the number of category elements
		// on this item is stored under index "category#".
		if (isset($this->item['category#'])) :
			$numberOfCategories = (int) $this->item['category#'];
		
		// We REALLY shouldn't have the old and busted MagpieRSS, but in
		// case we do, it doesn't support multiple categories, but there
		// might still be a single value under the "category" index.
		elseif (isset($this->item['category'])) :
			$numberOfCategories = 1;

		// No standard category or tag elements on this feed item.
		else :
			$numberOfCategories = 0;

		endif;

		$isSoTagged = false; // Innocent until proven guilty

		// Loop through category elements; if there are multiple
		// elements, they are indexed as category, category#2,
		// category#3, ... category#N
		while ($currentCategoryNumber <= $numberOfCategories) :
			if ($desiredTag == strtolower(trim($this->item[$currentCategory]))) :
				$isSoTagged = true; // Got it!
				break;
			endif;

			$currentCategoryNumber += 1;
			$currentCategory = 'category#'.$currentCategoryNumber;
		endwhile;

		return $isSoTagged;
	} /* SyndicatedPost::isTaggedAs() */

	/**
	 * SyndicatedPost::enclosures: returns an array with any enclosures
	 * that may be attached to this syndicated item.
	 *
	 * @param string $type If you only want enclosures that match a certain
	 *	MIME type or group of MIME types, you can limit the enclosures
	 *	that will be returned to only those with a MIME type which
	 *	matches this regular expression.
	 * @return array
	 */
	function enclosures ($type = '/.*/') {
		$enclosures = array();

		if (isset($this->item['enclosure#'])) :
			// Loop through enclosure, enclosure#2, enclosure#3, ....
			for ($i = 1; $i <= $this->item['enclosure#']; $i++) :
				$eid = (($i > 1) ? "#{$id}" : "");

				// Does it match the type we want?
				if (preg_match($type, $this->item["enclosure{$eid}@type"])) :
					$enclosures[] = array(
						"url" => $this->item["enclosure{$eid}@url"],
						"type" => $this->item["enclosure{$eid}@type"],
						"length" => $this->item["enclosure{$eid}@length"],
					);
				endif;
			endfor;
		endif;
		return $enclosures;		
	} /* SyndicatedPost::enclosures() */

	function comment_link () {
		$url = null;
		
		// RSS 2.0 has a standard <comments> element:
		// "<comments> is an optional sub-element of <item>. If present,
		// it is the url of the comments page for the item."
		// <http://cyber.law.harvard.edu/rss/rss.html#ltcommentsgtSubelementOfLtitemgt>
		if (isset($this->item['comments'])) :
			$url = $this->item['comments'];
		endif;

		// The convention in Atom feeds is to use a standard <link>
		// element with @rel="replies" and @type="text/html".
		// Unfortunately, SimplePie_Item::get_links() allows us to filter
		// by the value of @rel, but not by the value of @type. *sigh*
		
		// Try Atom 1.0 first
		$linkElements = $this->entry->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_10, 'link');
		
		// Fall back and try Atom 0.3
		if (is_null($linkElements)) : $linkElements =  $this->entry->get_item_tags(SIMPLEPIE_NAMESPACE_ATOM_03, 'link'); endif;
		
		// Now loop through the elements, screening by @rel and @type
		if (is_array($linkElements)) : foreach ($linkElements as $link) :
			$rel = (isset($link['attribs']['']['rel']) ? $link['attribs']['']['rel'] : 'alternate');
			$type = (isset($link['attribs']['']['type']) ? $link['attribs']['']['type'] : NULL);
			$href = (isset($link['attribs']['']['href']) ? $link['attribs']['']['href'] : NULL);

			if (strtolower($rel)=='replies' and $type=='text/html' and !is_null($href)) :
				$url = $href;
			endif;
		endforeach; endif;

		return $url;
	}

	function comment_feed () {
		$feed = null;

		// Well Formed Web comment feeds extension for RSS 2.0
		// <http://www.sellsbrothers.com/spout/default.aspx?content=archive.htm#exposingRssComments>
		//
		// N.B.: Correct capitalization is wfw:commentRss, but
		// wfw:commentRSS is common in the wild (partly due to a typo in
		// the original spec). In any case, our item array is normalized
		// to all lowercase anyways.
		if (isset($this->item['wfw']['commentrss'])) :
			$feed = $this->item['wfw']['commentrss'];
		endif;

		// In Atom 1.0, the convention is to use a standard link element
		// with @rel="replies". Sometimes this is also used to pass a
		// link to the human-readable comments page, so we also need to
		// check link/@type for a feed MIME type.
		//
		// Which is why I'm not using the SimplePie_Item::get_links()
		// method here, incidentally: it doesn't allow you to filter by
		// @type. *sigh*
		if (isset($this->item['link_replies'])) :
			// There may be multiple <link rel="replies"> elements; feeds have a feed MIME type
			$N = isset($this->item['link_replies#']) ? $this->item['link_replies#'] : 1;
			for ($i = 1; $i <= $N; $i++) :
				$currentElement = 'link_replies'.(($i > 1) ? '#'.$i : '');
				if (isset($this->item[$currentElement.'@type'])
				and preg_match("\007application/(atom|rss|rdf)\+xml\007i", $this->item[$currentElement.'@type'])) :
					$feed = $this->item[$currentElement];
				endif;
			endfor;
		endif;
		return $feed;
	} /* SyndicatedPost::comment_feed() */

	##################################
	#### BUILT-IN CONTENT FILTERS ####
	##################################

	var $uri_attrs = array (
		array('a', 'href'),
		array('applet', 'codebase'),
		array('area', 'href'),
		array('blockquote', 'cite'),
		array('body', 'background'),
		array('del', 'cite'),
		array('form', 'action'),
		array('frame', 'longdesc'),
		array('frame', 'src'),
		array('iframe', 'longdesc'),
		array('iframe', 'src'),
		array('head', 'profile'),
		array('img', 'longdesc'),
		array('img', 'src'),
		array('img', 'usemap'),
		array('input', 'src'),
		array('input', 'usemap'),
		array('ins', 'cite'),
		array('link', 'href'),
		array('object', 'classid'),
		array('object', 'codebase'),
		array('object', 'data'),
		array('object', 'usemap'),
		array('q', 'cite'),
		array('script', 'src')
	); /* var SyndicatedPost::$uri_attrs */

	var $_base = null;

	function resolve_single_relative_uri ($refs) {
		$tag = FeedWordPressHTML::attributeMatch($refs);
		$url = SimplePie_Misc::absolutize_url($tag['value'], $this->_base);
		return $tag['prefix'] . $url . $tag['suffix'];
	} /* function SyndicatedPost::resolve_single_relative_uri() */

	function resolve_relative_uris ($content, $obj) {
		$set = $obj->link->setting('resolve relative', 'resolve_relative', 'yes');
		if ($set and $set != 'no') : 
			// Fallback: if we don't have anything better, use the
			// item link from the feed
			$obj->_base = $obj->item['link']; // Reset the base for resolving relative URIs

			// What we should do here, properly, is to use
			// SimplePie_Item::get_base() -- but that method is
			// currently broken. Or getting down and dirty in the
			// SimplePie representation of the content tags and
			// grabbing the xml_base member for the content element.
			// Maybe someday...

			foreach ($obj->uri_attrs as $pair) :
				list($tag, $attr) = $pair;
				$pattern = FeedWordPressHTML::attributeRegex($tag, $attr);
				$content = preg_replace_callback (
					$pattern,
					array(&$obj, 'resolve_single_relative_uri'),
					$content
				);
			endforeach;
		endif;
		
		return $content;
	} /* function SyndicatedPost::resolve_relative_uris () */

	var $strip_attrs = array (
		array('[a-z]+', 'target'),
//		array('[a-z]+', 'style'),
//		array('[a-z]+', 'on[a-z]+'),
	);

	function strip_attribute_from_tag ($refs) {
		$tag = FeedWordPressHTML::attributeMatch($refs);
		return $tag['before_attribute'].$tag['after_attribute'];
	}

	function sanitize_content ($content, $obj) {
		# This kind of sucks. I intend to replace it with
		# lib_filter sometime soon.
		foreach ($obj->strip_attrs as $pair):
			list($tag,$attr) = $pair;
			$pattern = FeedWordPressHTML::attributeRegex($tag, $attr);

			$content = preg_replace_callback (
				$pattern,
				array(&$obj, 'strip_attribute_from_tag'),
				$content
			);
		endforeach;
		return $content;
	} /* SyndicatedPost::sanitize() */

	#####################
	#### POST STATUS ####
	#####################

	/**
	 * SyndicatedPost::filtered: check whether or not this post has been
	 * screened out by a registered filter.
	 *
	 * @return bool TRUE iff post has been filtered out by a previous filter
	 */
	function filtered () {
		return is_null($this->post);
	} /* SyndicatedPost::filtered() */

	/**
	 * SyndicatedPost::freshness: check whether post is a new post to be
	 * inserted, a previously syndicated post that needs to be updated to
	 * match the latest revision, or a previously syndicated post that is
	 * still up-to-date.
	 *
	 * @return int A status code representing the freshness of the post
	 *	0 = post already syndicated; no update needed
	 *	1 = post already syndicated, but needs to be updated to latest
	 *	2 = post has not yet been syndicated; needs to be created
	 */
	function freshness () {
		if ($this->filtered()) : // This should never happen.
			FeedWordPress::critical_bug('SyndicatedPost', $this, __LINE__);
		endif;
		
		if (is_null($this->_freshness)) :
			/* CF: Needed to either find a way to get the guid from a WP call, 
			or we'll need to store the URL where it should be....in post_meta */
			/** rinatkhaziev: this was outdated and erroneous **/
			$fwp_guid = sanitize_post_field('guid', $this->guid(), null, 'db'); // jeffstieler: compare against what is actually stored
			$args = array(
				'meta_key' => '_fwp_guid',
				'meta_value' => $fwp_guid,
				'posts_per_page' => 1, // Only want the first one (there should always only be 1),
				'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' )
				
			);
			$posts = get_posts($args);
			if (!is_array($posts) or empty($posts)) {
				$this->_freshness = 2; // New content
			}
			else {
				$post = array_shift($posts);
				if (!is_object($post)) {
					$this->_freshness = 2; // we had an invalid post object, we need a new one. (shouldn't happen)
				}
				else {
					$post_id = $post->ID;
					$stored_update_hashes = $this->get_uncached_post_meta( $post_id, 'syndication_item_hash' );
					if (count($stored_update_hashes) > 0) :
						$stored_update_hash = $stored_update_hashes[0];
						$update_hash_changed = ($stored_update_hash != $this->update_hash());
					else :
						$update_hash_changed = true; // Can't find syndication meta-data
					endif;

					preg_match('/([0-9]+)-([0-9]+)-([0-9]+) ([0-9]+):([0-9]+):([0-9]+)/', $post->post_modified_gmt, $backref);

					$last_rev_ts = gmmktime($backref[4], $backref[5], $backref[6], $backref[2], $backref[3], $backref[1]);
					$updated_ts = $this->updated(/*fallback=*/ true, /*default=*/ NULL);

					$frozen_values = $this->get_uncached_post_meta( $post_id, '_syndication_freeze_updates' );
					$frozen_post = (count($frozen_values) > 0 and 'yes' == $frozen_values[0]);
					$frozen_feed = ('yes' == $this->link->setting('freeze updates', 'freeze_updates', NULL));

					// Check timestamps...
					$updated = (
						!is_null($updated_ts)
						and ($updated_ts > $last_rev_ts)
					);


					// Or the hash...
					$updated = ($updated or $update_hash_changed);

					// But only if the post is not frozen.
					$updated = (
						$updated
						and !$frozen_post
						and !$frozen_feed
					); 

					if ($updated) :
						$this->_freshness = 1; // Updated content
						$this->_wp_id = $post_id;
					else :
						$this->_freshness = 0; // Same old, same old
						$this->_wp_id = $post_id;
					endif;
				}
			}
		endif;
		return $this->_freshness;
	}

	#################################################
	#### INTERNAL STORAGE AND MANAGEMENT METHODS ####
	#################################################

	function wp_id () {
		if ($this->filtered()) : // This should never happen.
			FeedWordPress::critical_bug('SyndicatedPost', $this, __LINE__);
		endif;
		
		if (is_null($this->_wp_id) and is_null($this->_freshness)) :
			$fresh = $this->freshness(); // sets WP DB id in the process
		endif;
		return $this->_wp_id;
	}

	function store () {
		if ($this->filtered()) : // This should never happen.
			FeedWordPress::critical_bug('SyndicatedPost', $this, __LINE__);
		endif;
		
		$freshness = $this->freshness();
		if ($freshness > 0) :
			# -- Look up, or create, numeric ID for author
			$this->post['post_author'] = $this->author_id (
				FeedWordPress::on_unfamiliar('author', $this->post['named']['unfamiliar']['author'])
			);

			if (is_null($this->post['post_author'])) :
				$this->post = NULL;
			endif;
		endif;
		
		if (!$this->filtered() and $freshness > 0) :
			# -- Look up, or create, numeric ID for categories
			list($pcats, $ptags) = $this->category_ids (
				$this->post['named']['category'],
				FeedWordPress::on_unfamiliar('category', $this->post['named']['unfamiliar']['category']),
				/*tags_too=*/ true
			);

			$this->post['post_category'] = $pcats;
			$this->post['tags_input'] = array_merge($this->post['tags_input'], $ptags);

			if (is_null($this->post['post_category'])) :
				// filter mode on, no matching categories; drop the post
				$this->post = NULL;
			else :
				// filter mode off or at least one match; now add on the feed and global presets
				$this->post['post_category'] = array_merge (
					$this->post['post_category'],
					$this->category_ids (
						$this->post['named']['preset/category'],
						'default'
					)
				);

				if (count($this->post['post_category']) < 1) :
					$this->post['post_category'][] = 1; // Default to category 1 ("Uncategorized" / "General") if nothing else
				endif;
			endif;
		endif;

		if (!$this->filtered() and $freshness > 0) :
			unset($this->post['named']);
			$this->post = apply_filters('syndicated_post', $this->post, $this);

			// Allow for feed-specific syndicated_post filters.
			$this->post = apply_filters(
				"syndicated_post_".$this->link->uri(),
				$this->post,
				$this
			);
		endif;
		
		// Hook in early to make sure these get inserted if at all possible
		add_action(
			/*hook=*/ 'transition_post_status',
			/*callback=*/ array(&$this, 'add_rss_meta'),
			/*priority=*/ -10000, /* very early */
			/*arguments=*/ 3
		);

		if (!$this->filtered() and $freshness == 2) :
			// The item has not yet been added. So let's add it.
			FeedWordPress::diagnostic('syndicated_posts', 'Inserting new post "'.$this->post['post_title'].'"');

			$this->insert_new();
			do_action('post_syndicated_item', $this->wp_id(), $this);

			$ret = 'new';
		elseif (!$this->filtered() and $freshness == 1) :
			FeedWordPress::diagnostic('syndicated_posts', 'Updating existing post # '.$this->wp_id().', "'.$this->post['post_title'].'"');

			$this->post['ID'] = $this->wp_id();
			$this->update_existing();
			do_action('update_syndicated_item', $this->wp_id(), $this);

			$ret = 'updated';			
		else :
			$ret = false;
		endif;

		// Remove add_rss_meta hook
		remove_action(
			/*hook=*/ 'transition_post_status',
			/*callback=*/ array(&$this, 'add_rss_meta'),
			/*priority=*/ -10000, /* very early */
			/*arguments=*/ 3
		);

		return $ret;
	} /* function SyndicatedPost::store () */
	
	function insert_new () {
		global $wp_db_version;
		if ( !defined('VIP_CUSTOM_POSTING') )
			define( 'VIP_CUSTOM_POSTING', true );
				
		$dbpost = $this->normalize_post(/*new=*/ true);
		if (!is_null($dbpost)) :
			$dbpost['post_pingback'] = false; // Tell WP 2.1 and 2.2 not to process for pingbacks

			// This is a ridiculous kludge necessitated by WordPress 2.6 munging authorship meta-data
			add_action('_wp_put_post_revision', array($this, 'fix_revision_meta'));
			
			// Kludge to prevent kses filters from stripping the
			// content of posts when updating without a logged in
			// user who has `unfiltered_html` capability.
			kses_remove_filters();
			
			// CF: Filtering in our guid, post_modified, post_modified_gmt fields
			add_filter('wp_insert_post_data', array($this, 'add_post_info'));
			
			$this->_wp_id = wp_insert_post($dbpost);

			// Turn off ridiculous kludges #1 and #2
			remove_action('_wp_put_post_revision', array($this, 'fix_revision_meta'));
			kses_init_filters();
			remove_filter('wp_insert_post_data', array($this, 'add_post_info'));
			
			/* CF: Add the guid to the post's meta so that later on, 
			we can retrieve the post by its postmeta, and not 
			have to do a direct SQL call. */
			update_post_meta($this->_wp_id, '_fwp_guid', $dbpost['guid']);
			
			$this->validate_post_id($dbpost, array(__CLASS__, __FUNCTION__));
		endif;
	} /* SyndicatedPost::insert_new() */

	function update_existing () {
		
		// Why doesn't wp_insert_post already do this?
		$dbpost = $this->normalize_post(/*new=*/ false);
		if (!is_null($dbpost)) :
			$dbpost['post_pingback'] = false; // Tell WP 2.1 and 2.2 not to process for pingbacks

			// This is a ridiculous kludge necessitated by WordPress 2.6 munging authorship meta-data
			add_action('_wp_put_post_revision', array($this, 'fix_revision_meta'));

			// Kludge to prevent kses filters from stripping the
			// content of posts when updating without a logged in
			// user who has `unfiltered_html` capability.
			kses_remove_filters();
			add_filter('wp_insert_post_data', array($this, 'update_post_info'));

			// Don't munge status fields that the user may have reset manually
			if (function_exists('get_post_field')) :
				$doNotMunge = array('post_status', 'comment_status', 'ping_status');
				foreach ($doNotMunge as $field) :
					$dbpost[$field] = get_post_field($field, $this->wp_id());
				endforeach;
			endif;

			$this->_wp_id = wp_insert_post($dbpost);

			// Turn off ridiculous kludges #1 and #2
			remove_action('_wp_put_post_revision', array($this, 'fix_revision_meta'));
			kses_init_filters();
			remove_filter('wp_insert_post_data', array($this, 'update_post_info'));

			$this->validate_post_id($dbpost, array(__CLASS__, __FUNCTION__));
		endif;
	} /* SyndicatedPost::update_existing() */

	/**
	 * SyndicatedPost::normalize_post()
	 *
	 * @param bool $new If true, this post is to be inserted anew. If false, it is an update of an existing post.
	 * @return array A normalized representation of the post ready to be inserted into the database or sent to the WordPress API functions
	 */
	function normalize_post ($new = true) {
		$out = array();

		// Why doesn't wp_insert_post already do this?
		foreach ($this->post as $key => $value) :
			// For DB sanitization, no post ID needs passed
			$out[$key] = sanitize_post_field($key, $this->post[$key], null, 'db');
		endforeach;

		// May not always have a post excerpt
		$excerpt = isset( $out['post_excerpt'] ) ? $out['post_excerpt'] : '';

		if (strlen($out['post_title'].$out['post_content'].$excerpt) == 0) :
			// FIXME: Option for filtering out empty posts
		endif;
		if (strlen($out['post_title'])==0) :
			$offset = (int) get_option('gmt_offset') * 60 * 60;
			$out['post_title'] =
				$this->post['meta']['syndication_source']
				.' '.gmdate('Y-m-d H:i:s', $this->published() + $offset);
			// FIXME: Option for what to fill a blank title with...
		endif;

		// WPCOM: never allow 1 (super admin) as the author
		if ( function_exists( 'wpcom_get_blog_owner' ) && 1 == $out['post_author'] )
			$out['post_author'] = wpcom_get_blog_owner();

		return $out;
	}

	/**
	 * SyndicatedPost::validate_post_id()
	 *
	 * @param array $dbpost An array representing the post we attempted to insert or update
	 * @param mixed $ns A string or array representing the namespace (class, method) whence this method was called.
	 */
	function validate_post_id ($dbpost, $ns) {
		if (is_array($ns)) : $ns = implode('::', $ns);
		else : $ns = (string) $ns; endif;
		
		// This should never happen.
		if (!is_numeric($this->_wp_id) or ($this->_wp_id == 0)) :
			FeedWordPress::critical_bug(
				/*name=*/ $ns.'::_wp_id',
				/*var =*/ array(
					"\$this->_wp_id" => $this->_wp_id,
					"\$dbpost" => $dbpost,
					"\$this" => $this
				),
				/*line # =*/ __LINE__
			);
		endif;
	} /* SyndicatedPost::validate_post_id() */
	
	/**
	 * SyndicatedPost::fix_revision_meta() - Fixes the way WP 2.6+ screws up
	 * meta-data (authorship, etc.) when storing revisions of an updated
	 * syndicated post.
	 *
	 * In their infinite wisdom, the WordPress coders have made it completely
	 * impossible for a plugin that uses wp_insert_post() to set certain
	 * meta-data (such as the author) when you store an old revision of an
	 * updated post. Instead, it uses the WordPress defaults (= currently
	 * active user ID if the process is running with a user logged in, or
	 * = #0 if there is no user logged in). This results in bogus authorship
	 * data for revisions that are syndicated from off the feed, unless we
	 * use a ridiculous kludge like this to end-run the munging of meta-data
	 * by _wp_put_post_revision.
	 *
	 * @param int $revision_id The revision ID to fix up meta-data
	 */
	function fix_revision_meta ($revision_id) {
		$post_author = (int) $this->post['post_author'];
		$revision_id = (int) $revision_id;
		
		$post = get_post($revision_id, ARRAY_A);
		if (!empty($post)) {
			$post['post_author'] = $this->post['post_author'];
			wp_insert_post($post);
		}
	} /* SyndicatedPost::fix_revision_meta () */
	 
	/**
	 * Filters in the guid change and modified timestamp info
	 *
	 * @return array post data.
	 **/
	function add_post_info($data, $postarr = array()) {
		$dbpost = $this->normalize_post(/*new=*/ true);
		if (!is_null($dbpost)) {
			$fields = array(
				'guid',
				'post_modified',
				'post_modified_gmt'
			);
			foreach ($fields as $field) {
				// For DB sanitization, no post ID needs passed
				if( ! empty( $dbpost[ $field ] ) )
					$data[$field] = sanitize_post_field($field, $dbpost[$field], null, 'db');
			}
		}
		return $data;
	}
	
	/**
	 * Filters in the modified timestamp info
	 *
	 * @return array post data.
	 **/
	function update_post_info($data) {
		$dbpost = $this->normalize_post(/*new=*/ false);
		if (!is_null($dbpost)) {
			$fields = array(
				'post_modified',
				'post_modified_gmt'
			);
			foreach ($fields as $field) {
				// For DB sanitization, no post ID needs passed
				if( ! empty( $dbpost[ $field ] ) )
					$data[$field] = sanitize_post_field($field, $dbpost[$field], null, 'db');
			}
		}
		return $data;
	}
	
	// SyndicatedPost::add_rss_meta: adds interesting meta-data to each entry
	// using the space for custom keys. The set of keys and values to add is
	// specified by the keys and values of $post['meta']. This is used to
	// store anything that the WordPress user might want to access from a
	// template concerning the post's original source that isn't provided
	// for by standard WP meta-data (i.e., any interesting data about the
	// syndicated post other than author, title, timestamp, categories, and
	// guid). It's also used to hook into WordPress's support for
	// enclosures.
	function add_rss_meta ($new_status, $old_status, $post) {
		FeedWordPress::diagnostic('syndicated_posts:meta_data', 'Adding post meta-data: {'.implode(", ", array_keys($this->post['meta'])).'}');

		// not saving the post we're processing; bail.
		if ( $post->ID != $this->wp_id() )
			return;

		if ( is_array($this->post) and isset($this->post['meta']) and is_array($this->post['meta']) ) :
			$postId = $post->ID;
			
			// Aggregated posts should NOT send out pingbacks.
			// WordPress 2.1-2.2 claim you can tell them not to
			// using $post_pingback, but they don't listen, so we
			// make sure here.
			$result = delete_post_meta($postId, '_pingme');

			foreach ( $this->post['meta'] as $key => $values ) :
				// If this is an update, clear out the old
				// values to avoid duplication.
				$result = delete_post_meta($postId, $key);

				// Allow for either a single value or an array
				if (!is_array($values)) $values = array($values);
				foreach ( $values as $value ) :
				FeedWordPress::diagnostic('syndicated_posts:meta_data', "Adding post meta-datum to post [$postId]: [$key] = ".FeedWordPress::val($value, /*no newlines=*/ true));
					add_post_meta($postId, $key, $value, /*unique=*/ false);
				endforeach;
			endforeach;
		endif;
	} /* SyndicatedPost::add_rss_meta () */

	// SyndicatedPost::author_id (): get the ID for an author name from
	// the feed. Create the author if necessary.
	function author_id ($unfamiliar_author = 'create') {
		$a = $this->author();
		$author = $a['name'];
		$email = (isset($a['email']) ? $a['email'] : NULL);
		$authorUrl = (isset($a['uri']) ? $a['uri'] : NULL);

		$match_author_by_email = !('yes' == get_option("feedwordpress_do_not_match_author_by_email"));
		if ($match_author_by_email and !FeedWordPress::is_null_email($email)) :
			$test_email = $email;
		else :
			$test_email = NULL;
		endif;

		// Never can be too careful...
		$login = sanitize_user($author, /*strict=*/ true);
		$login = apply_filters('pre_user_login', $login);

		$nice_author = sanitize_title($author);
		$nice_author = apply_filters('pre_user_nicename', $nice_author);

		// Do our escape just how $wpdb does it for a string
		$author = addslashes($author);
		
		// Check for an existing author rule....
		if (isset($this->link->settings['map authors']['name'][strtolower(trim($author))])) :
			$author_rule = $this->link->settings['map authors']['name'][strtolower(trim($author))];
		else :
			$author_rule = NULL;
		endif;

		// User name is mapped to a particular author. If that author ID exists, use it.
		if (is_numeric($author_rule) and get_userdata((int) $author_rule)) :
			$id = (int) $author_rule;

		// User name is filtered out
		elseif ('filter' == $author_rule) :
			$id = NULL;
		
		else :
			// Check the database for an existing author record that might fit
			$id = NULL;

			// First try the user core data table.
			if ($user = get_user_by('login', trim(strtolower($login)))) {
				$id = $user->ID;
			}
			else if ($user = get_user_by('email', trim(strtolower($test_email)))) {
				$id = $user->ID;
			}
			else if ($user = get_user_by('slug', trim(strtolower($nice_author)))) {
				$id = $user->ID;
			}
			
			/* CF: No WP functionality to accomplish this, we're not going 
			to try the description field right now */
			// If that fails, look for aliases in the user meta data table
			// if (empty($id)) :
			// 	$reg_author = $wpdb->escape(preg_quote($author));
			// 	$id = $wpdb->get_var(
			// 	"SELECT user_id FROM $wpdb->usermeta
			// 	WHERE
			// 		(meta_key = 'description' AND TRIM(LCASE(meta_value)) = TRIM(LCASE('$author')))
			// 		OR (
			// 			meta_key = 'description'
			// 			AND TRIM(LCASE(meta_value))
			// 			RLIKE CONCAT(
			// 				'(^|\\n)a\\.?k\\.?a\\.?( |\\t)*:?( |\\t)*',
			// 				TRIM(LCASE('$reg_author')),
			// 				'( |\\t|\\r)*(\\n|\$)'
			// 			)
			// 		)
			// 	");
			// endif;

			// ... if you don't find one, then do what you need to do
			if (is_null($id)) :
				if (is_numeric($unfamiliar_author) and get_userdata((int) $unfamiliar_author)) :
					$id = (int) $unfamiliar_author;
				
				// Do the default, assign to admin
				else :
					$id = 1;
				endif;
			endif;
		endif;

		if ($id) :
			$this->link->settings['map authors']['name'][strtolower(trim($author))] = $id;
		endif;
		return $id;	
	} // function SyndicatedPost::author_id ()

	// look up (and create) category ids from a list of categories
	function category_ids ($cats, $unfamiliar_category = 'create', $tags_too = false) {
		// We need to normalize whitespace because (1) trailing
		// whitespace can cause PHP and MySQL not to see eye to eye on
		// VARCHAR comparisons for some versions of MySQL (cf.
		// <http://dev.mysql.com/doc/mysql/en/char.html>), and (2)
		// because I doubt most people want to make a semantic
		// distinction between 'Computers' and 'Computers  '
		$cats = array_map('trim', $cats);

		$tags = array();

		$cat_ids = array ();
		foreach ($cats as $cat_name) :
			if (preg_match('/^{#([0-9]+)}$/', $cat_name, $backref)) :
				$cat_id = (int) $backref[1];
				if (term_exists($cat_id, 'category')) :
					$cat_ids[] = $cat_id;
				elseif (get_category($cat_id)) :
					$cat_ids[] = $cat_id;
				endif;
			elseif (strlen($cat_name) > 0) :
				$esc = addslashes($cat_name);
				$resc = addslashes(preg_quote($cat_name));
				
				$cat_id = term_exists($cat_name, 'category');
				if ($cat_id) :
					$cat_ids[] = $cat_id['term_id'];
				/* CF: No WP way to do this, we'll revisit this portion if so desired, 
				but it's an edge-case to use this feature anyways */
				// There must be a better way to do this...
				// elseif ($results = $wpdb->get_results(
				// 	"SELECT	term_id
				// 	FROM $wpdb->term_taxonomy
				// 	WHERE
				// 		LOWER(description) RLIKE
				// 		CONCAT('(^|\\n)a\\.?k\\.?a\\.?( |\\t)*:?( |\\t)*', LOWER('{$resc}'), '( |\\t|\\r)*(\\n|\$)')"
				// )) :
				// 	foreach ($results as $term) :
				// 		$cat_ids[] = (int) $term->term_id;
				// 	endforeach;
				elseif ('tag'==$unfamiliar_category) :
					$tags[] = $cat_name;
				elseif ('create'===$unfamiliar_category) :
					$term = wp_insert_term($cat_name, 'category');
					if (!is_wp_error($term)) :
						$cat_ids[] = $term['term_id'];
					endif;
				endif;
			endif;
		endforeach;

		if ((count($cat_ids) == 0) and ($unfamiliar_category === 'filter')) :
			$cat_ids = NULL; // Drop the post
		else :
			$cat_ids = array_unique($cat_ids);
		endif;
		
		if ($tags_too) : $ret = array($cat_ids, $tags);
		else : $ret = $cat_ids;
		endif;

		return $ret;
	} // function SyndicatedPost::category_ids ()

	function use_api ($tag) {
		global $wp_db_version;
		switch ($tag) :
		case 'wp_insert_post':
			// Before 2.2, wp_insert_post does too much of the wrong stuff to use it
			// In 1.5 it was such a resource hog it would make PHP segfault on big updates
			$ret = (isset($wp_db_version) and $wp_db_version > FWP_SCHEMA_21);
			break;
		case 'post_status_pending':
			$ret = (isset($wp_db_version) and $wp_db_version > FWP_SCHEMA_23);
			break;
		endswitch;
		return $ret;		
	} // function SyndicatedPost::use_api ()

	function get_uncached_post_meta( $post_id, $meta_key, $single = false ) {
		if ( function_exists( 'wpcom_uncached_get_post_meta' ) )
			return wpcom_uncached_get_post_meta( $post_id, $meta_key, $single );
		else
			return get_post_meta( $post_id, $meta_key, $single );
	}

} /* class SyndicatedPost */

