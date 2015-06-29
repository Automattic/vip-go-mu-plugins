<?php
# class SyndicatedLink: represents a syndication feed stored within the
# WordPress database
#
# To keep things compact and editable from within WordPress, we use all the
# links under a particular category in the WordPress "Blogroll" for the list of
# feeds to syndicate. "Contributors" is the category used by default; you can
# configure that under Options --> Syndication.
#
# Fields used are:
#
# *	link_rss: the URI of the Atom/RSS feed to syndicate
#
# *	link_notes: user-configurable options, with keys and values
#	like so:
#
#		key: value
#		cats: computers\nweb
#		feed/key: value
#
#	Keys that start with "feed/" are gleaned from the data supplied
#	by the feed itself, and will be overwritten with each update.
#
#	Values have linebreak characters escaped with C-style
#	backslashes (so, for example, a newline becomes "\n").
#
#	The value of `cats` is used as a newline-separated list of
#	default categories for any post coming from a particular feed. 
#	(In the example above, any posts from this feed will be placed
#	in the "computers" and "web" categories--*in addition to* any
#	categories that may already be applied to the posts.)
#
#	Values of keys in link_notes are accessible from templates using
#	the function `get_feed_meta($key)` if this plugin is activated.

require_once(dirname(__FILE__).'/magpiefromsimplepie.class.php');

class SyndicatedLink {
	var $id = null;
	var $link = null;
	var $settings = array ();
	var $simplepie = null;
	var $magpie = null;

	function SyndicatedLink ($link) {
		if (is_object($link)) :
			$this->link = $link;
			$this->id = $link->link_id;
		else :
			$this->id = $link;
			$this->link = get_bookmark($link);
		endif;

		if (strlen($this->link->link_rss) > 0) :
			// Read off feed settings from link_notes
			$notes = explode("\n", $this->link->link_notes);
			foreach ($notes as $note):
				$pair = explode(": ", $note, 2);
				$key = (isset($pair[0]) ? $pair[0] : null);
				$value = (isset($pair[1]) ? $pair[1] : null);
				if (!is_null($key) and !is_null($value)) :
					// Unescape and trim() off the whitespace.
					// Thanks to Ray Lischner for pointing out the
					// need to trim off whitespace.
					$this->settings[$key] = stripcslashes (trim($value));
				endif;
			endforeach;

			// "Magic" feed settings
			$this->settings['link/uri'] = $this->link->link_rss;
			$this->settings['link/name'] = $this->link->link_name;
			$this->settings['link/id'] = $this->link->link_id;
			
			// `hardcode categories` and `unfamiliar categories` are deprecated in favor of `unfamiliar category`
			if (
				isset($this->settings['unfamiliar categories'])
				and !isset($this->settings['unfamiliar category'])
			) :
				$this->settings['unfamiliar category'] = $this->settings['unfamiliar categories'];
			endif;
			if (
				FeedWordPress::affirmative($this->settings, 'hardcode categories')
				and !isset($this->settings['unfamiliar category'])
			) :
				$this->settings['unfamiliar category'] = 'default';
			endif;

			// Set this up automagically for del.icio.us
			$bits = parse_url($this->link->link_rss);
			$tagspacers = array('del.icio.us', 'feeds.delicious.com');
			if (!isset($this->settings['cat_split']) and in_array($bits['host'], $tagspacers)) : 
				$this->settings['cat_split'] = '\s'; // Whitespace separates multiple tags in del.icio.us RSS feeds
			endif;

			if (isset($this->settings['cats'])):
				$this->settings['cats'] = preg_split(FEEDWORDPRESS_CAT_SEPARATOR_PATTERN, $this->settings['cats']);
			endif;
			if (isset($this->settings['tags'])):
				$this->settings['tags'] = preg_split(FEEDWORDPRESS_CAT_SEPARATOR_PATTERN, $this->settings['tags']);
			endif;
			
			if (isset($this->settings['map authors'])) :
				$author_rules = explode("\n\n", $this->settings['map authors']);
				$ma = array();
				foreach ($author_rules as $rule) :
					list($rule_type, $author_name, $author_action) = array_pad(explode("\n", $rule), 3, '');
					
					// Normalize for case and whitespace
					$rule_type = strtolower(trim($rule_type));
					$author_name = strtolower(trim($author_name));
					$author_action = strtolower(trim($author_action));
					
					$ma[$rule_type][$author_name] = $author_action;
				endforeach;
				$this->settings['map authors'] = $ma;
			endif;
		endif;
	} /* SyndicatedLink::SyndicatedLink () */
	
	function found () {
		return is_object($this->link) and !is_wp_error($this->link);
	} /* SyndicatedLink::found () */

	function stale () {
		/* CF: We have to be logged into the wp-admin to be able to insert
		posts with the standard WP functions (e.g., wp_insert_post(), etc.)
		So if we're not in the admin, then just say we're good to wait till
		someone is....then the post retrieval and creation will happen.  
		This type of check will also be placed in the feedwordpress.php 
		stale() method */
		if (!is_admin()) { return false; }


		$stale = true;
		if (isset($this->settings['update/hold']) and ($this->settings['update/hold']=='ping')) :
			$stale = false; // don't update on any timed updates; pings only
		elseif (isset($this->settings['update/hold']) and ($this->settings['update/hold']=='next')) :
			$stale = true; // update on the next timed update
		elseif (!isset($this->settings['update/ttl']) or !isset($this->settings['update/last'])) :
			$stale = true; // initial update
		else :
			$after = ((int) $this->settings['update/last'])
				+((int) $this->settings['update/ttl'] * 60);
			$stale = (time() >= $after);
		endif;
		return $stale;
	} /* SyndicatedLink::stale () */

	function poll ($crash_ts = NULL) {
		FeedWordPress::diagnostic('updated_feeds', 'Polling feed <'.$this->link->link_rss.'>');

		$this->simplepie = apply_filters(
			'syndicated_feed',
			FeedWordPress::fetch($this->link->link_rss),
			$this
		);
		
		// Filter compatibility mode
		if (is_wp_error($this->simplepie)) :
			$this->magpie = $this->simplepie;
		else :
			$this->magpie = new MagpieFromSimplePie($this->simplepie);
		endif;

		$new_count = NULL;

		$resume = FeedWordPress::affirmative($this->settings, 'update/unfinished');
		if ($resume) :
			// pick up where we left off
			$processed = array_map('trim', explode("\n", $this->settings['update/processed']));
		else :
			// begin at the beginning
			$processed = array();
		endif;

		if (is_wp_error($this->simplepie)) :
			$new_count = $this->simplepie;
			// Error; establish an error setting.
			$theError = array();
			$theError['ts'] = time();
			$theError['since'] = time();
			$theError['object'] = $this->simplepie;

			$oldError = $this->setting('update/error', NULL, NULL);
			if (is_string($oldError)) :
				$oldError = unserialize($oldError);
			endif;

			if (!is_null($oldError)) :
				// Copy over the in-error-since timestamp
				$theError['since'] = $oldError['since'];
				
				// If this is a repeat error, then we should take
				// a step back before we try to fetch it again.
				$this->settings['update/last'] = time();
				$this->settings['update/ttl'] = $this->automatic_ttl();
				$this->settings['update/ttl'] = apply_filters('syndicated_feed_ttl', $this->settings['update/ttl'], $this);
				$this->settings['update/ttl'] = apply_filters('syndicated_feed_ttl_from_error', $this->settings['update/ttl'], $this);

				$this->settings['update/timed'] = 'automatically';
			endif;
			
			$this->settings['update/error'] = serialize($theError);
			$this->save_settings(/*reload=*/ true);
		elseif (is_object($this->simplepie)) :
		
			// Grab our bookmark we're going to be updating
			$link = get_bookmark($this->id, ARRAY_A);
			if (is_array($link) && !empty($link)) {
				// Success; clear out error setting, if any.
				if (isset($this->settings['update/error'])) :
					unset($this->settings['update/error']);
				endif;

				$new_count = array('new' => 0, 'updated' => 0);

				# -- Update Link metadata live from feed
				$channel = $this->magpie->channel;

				
				if (!isset($channel['id'])) :
					$channel['id'] = $this->link->link_rss;
				endif;

				if (!$this->hardcode('url') and isset($channel['link'])) :
					$link['link_url'] = $channel['link'];
				endif;
				
				if (!$this->hardcode('name') and isset($channel['title'])) :
					$link['link_name'] = $channel['title'];
				endif;
				
				if (!$this->hardcode('description')) :
					if (isset($channel['tagline'])) :
						$link['link_description'] = $channel['tagline'];
					elseif (isset($channel['description'])) :
						$link['link_description'] = $channel['description'];
					endif;
				endif;


				$this->settings = array_merge($this->settings, $this->flatten_array($channel));

				$this->settings['update/last'] = time(); $ttl = $this->ttl();
				if (!is_null($ttl)) :
					$this->settings['update/ttl'] = $ttl;
					$this->settings['update/timed'] = 'feed';
				else :
					$this->settings['update/ttl'] = $this->automatic_ttl();
					$this->settings['update/timed'] = 'automatically';
				endif;
				$this->settings['update/ttl'] = apply_filters('syndicated_feed_ttl', $this->settings['update/ttl'], $this);

				if (!isset($this->settings['update/hold']) or $this->settings['update/hold']!='ping') :
					$this->settings['update/hold'] = 'scheduled';
				endif;

				$this->settings['update/unfinished'] = 'yes';

				$link['link_notes'] = $this->settings_to_notes();
				
				// Update the properties of the link from the feed information
				$result = wp_update_link($link);
				do_action('update_syndicated_feed', $this->id, $this);


				# -- Add new posts from feed and update any updated posts
				$crashed = false;

				$posts = apply_filters(
					'syndicated_feed_items',
					$this->magpie->originals,
					$this
				);
				if (is_array($posts)) :
					foreach ($posts as $key => $original) :
						$item = $this->magpie->items[$key];
						$post = new SyndicatedPost(array(
							'simplepie' => $original,
							'magpie' => $item,
						), $this);

						if (!$resume or !in_array(trim($post->guid()), $processed)) :
							$processed[] = $post->guid();
							if (!$post->filtered()) :
								$new = $post->store();
								if ( $new !== false ) $new_count[$new]++;
							endif;

							if (!is_null($crash_ts) and (time() > $crash_ts)) :
								$crashed = true;
								break;
							endif;
						endif;
						unset($post);
					endforeach;
				endif;
				$suffix = ($crashed ? 'crashed' : 'completed');
				do_action('update_syndicated_feed_items', $this->id, $this);
				do_action("update_syndicated_feed_items_${suffix}", $this->id, $this);

				// Copy back any changes to feed settings made in the course of updating (e.g. new author rules)
				$to_notes = $this->settings;

				$this->settings['update/processed'] = $processed;
				if (!$crashed) :
					$this->settings['update/unfinished'] = 'no';
				endif;

				$link['link_notes'] = $this->settings_to_notes();


				// Update the properties of the link from the feed information
				$result = wp_update_link($link);

				do_action("update_syndicated_feed_completed", $this->id, $this);
			}
		endif;
		
		return $new_count;
	} /* SyndicatedLink::poll() */

	/**
	 * Updates the URL for the feed syndicated by this link.
	 *
	 * @param string $url The new feed URL to use for this source.
	 * @return bool TRUE on success, FALSE on failure.
	 */
	function set_uri ($url) {
		if ($this->found()) :
			// Update link_rss
			$link = get_bookmark($this->id, ARRAY_A);
			$link['link_rss'] = $url;
			$result = wp_update_link($link);
			$ret = ($result ? true : false);
		else :
			$ret = false;
		endif;
		return $ret;
	} /* SyndicatedLink::set_uri () */

	function settings_to_notes () {
		$to_notes = $this->settings;

		unset($to_notes['link/id']); // Magic setting; don't save
		unset($to_notes['link/uri']); // Magic setting; don't save
		unset($to_notes['link/name']); // Magic setting; don't save
		unset($to_notes['hardcode categories']); // Deprecated
		unset($to_notes['unfamiliar categories']); // Deprecated

		// Collapse array settings
		if (isset($to_notes['update/processed']) and (is_array($to_notes['update/processed']))) :
			$to_notes['update/processed'] = implode("\n", $to_notes['update/processed']);
		endif;

		if (isset($to_notes['cats']) and is_array($to_notes['cats'])) :
			$to_notes['cats'] = implode(addcslashes(FEEDWORDPRESS_CAT_SEPARATOR, "\0..\37".'\\'), $to_notes['cats']);
		endif;
		if (isset($to_notes['tags']) and is_array($to_notes['tags'])) :
			$to_notes['tags'] = implode(addcslashes(FEEDWORDPRESS_CAT_SEPARATOR, "\0..\37".'\\'), $to_notes['tags']);
		endif;

		// Collapse the author mapping rule structure back into a flat string
		if (isset($to_notes['map authors'])) :
			$ma = array();
			foreach ($to_notes['map authors'] as $rule_type => $author_rules) :
				foreach ($author_rules as $author_name => $author_action) :
					$ma[] = $rule_type."\n".$author_name."\n".$author_action;
				endforeach;
			endforeach;
			$to_notes['map authors'] = implode("\n\n", $ma);
		endif;

		$notes = '';
		foreach ($to_notes as $key => $value) :
			$notes .= $key . ": ". addcslashes($value, "\0..\37".'\\') . "\n";
		endforeach;
		return $notes;
	} /* SyndicatedLink::settings_to_notes () */

	function save_settings ($reload = false) {
		$link = get_bookmark($this->id, ARRAY_A);
		
		// Save channel-level meta-data
		foreach (array('link_name', 'link_description', 'link_url') as $what) :
			$link[$what] = $this->link->{$what};
		endforeach;

		// Save settings to the notes field
		$link['link_notes'] = $this->settings_to_notes();

		$result = wp_update_link($link);
		
		if ($reload) :
			// force reload of link information from DB
			if (function_exists('clean_bookmark_cache')) :
				clean_bookmark_cache($this->id);
			endif;
		endif;
	} /* SyndicatedLink::save_settings () */

	/**
	 * Retrieves the value of a setting, allowing for a global setting to be
	 * used as a fallback, or a constant value, or both.
	 *
	 * @param string $name The link setting key
	 * @param mixed $fallback_global If the link setting is nonexistent or marked as a use-default value, fall back to the value of this global setting.
	 * @param mixed $fallback_value If the link setting and the global setting are nonexistent or marked as a use-default value, fall back to this constant value.
	 * @return bool TRUE on success, FALSE on failure.
	 */
	function setting ($name, $fallback_global = NULL, $fallback_value = NULL) {
		$ret = NULL;
		if (isset($this->settings[$name])) :
			$ret = $this->settings[$name];
		endif;
		
		$no_value = (
			is_null($ret)
			or (is_string($ret) and strtolower($ret)=='default')
		);

		if ($no_value and !is_null($fallback_global)) :
			$ret = get_option('feedwordpress_'.$fallback_global, /*default=*/ NULL);
		endif;

		$no_value = (
			is_null($ret)
			or (is_string($ret) and strtolower($ret)=='default')
		);

		if ($no_value and !is_null($fallback_value)) :
			$ret = $fallback_value;
		endif;
		return $ret;
	} /* SyndicatedLink::setting () */

	function uri () {
		return (is_object($this->link) ? $this->link->link_rss : NULL);
	} /* SyndicatedLink::uri () */

	function property_cascade ($fromFeed, $link_field, $setting, $simplepie_method) {
		$value = NULL;
		if ($fromFeed) :
			if (isset($this->settings[$setting])) :
				$value = $this->settings[$setting];
			elseif (is_object($this->simplepie)
			and method_exists($this->simplepie, $simplepie_method)) :
				$value = $this->simplepie->{$simplepie_method}();
			endif;
		else :
			$value = $this->link->{$link_field};
		endif;
		return $value;
	} /* SyndicatedLink::property_cascade () */
	
	function homepage ($fromFeed = true) {
		return $this->property_cascade($fromFeed, 'link_url', 'feed/link', 'get_link');
	} /* SyndicatedLink::homepage () */

	function name ($fromFeed = true) {
		return $this->property_cascade($fromFeed, 'link_name', 'feed/title', 'get_title');
	} /* SyndicatedLink::name () */

	function ttl () {
		if (is_object($this->magpie)) :
			$channel = $this->magpie->channel;
		else :
			$channel = array();
		endif;

		if (isset($channel['ttl'])) :
			// "ttl stands for time to live. It's a number of
			// minutes that indicates how long a channel can be
			// cached before refreshing from the source."
			// <http://blogs.law.harvard.edu/tech/rss#ltttlgtSubelementOfLtchannelgt>	
			$ret = $channel['ttl'];
		elseif (isset($channel['sy']['updatefrequency']) or isset($channel['sy']['updateperiod'])) :
			$period_minutes = array (
				'hourly' => 60, /* minutes in an hour */
				'daily' => 1440, /* minutes in a day */
				'weekly' => 10080, /* minutes in a week */
				'monthly' => 43200, /* minutes in  a month */
				'yearly' => 525600, /* minutes in a year */
			);

			// "sy:updatePeriod: Describes the period over which the
			// channel format is updated. Acceptable values are:
			// hourly, daily, weekly, monthly, yearly. If omitted,
			// daily is assumed." <http://web.resource.org/rss/1.0/modules/syndication/>
			if (isset($channel['sy']['updateperiod'])) : $period = $channel['sy']['updateperiod'];
			else : $period = 'daily';
			endif;
			
			// "sy:updateFrequency: Used to describe the frequency 
			// of updates in relation to the update period. A
			// positive integer indicates how many times in that
			// period the channel is updated. ... If omitted a value
			// of 1 is assumed." <http://web.resource.org/rss/1.0/modules/syndication/>
			if (isset($channel['sy']['updatefrequency'])) : $freq = (int) $channel['sy']['updatefrequency'];
			else : $freq = 1;
			endif;
			
			$ret = (int) ($period_minutes[$period] / $freq);
		else :
			$ret = NULL;
		endif;
		return $ret;
	} /* SyndicatedLink::ttl() */

	function automatic_ttl () {
		// spread out over a time interval for staggered updates
		$updateWindow = $this->setting('update/window', 'update_window', DEFAULT_UPDATE_PERIOD);
		if (!is_numeric($updateWindow) or ($updateWindow < 1)) :
			$updateWindow = DEFAULT_UPDATE_PERIOD;
		endif;

		$fudgedInterval = $updateWindow+rand(0, 2*($updateWindow/3));
		return apply_filters('syndicated_feed_automatic_ttl', $fudgedInterval, $this);
	} /* SyndicatedLink::automatic_ttl () */

	// SyndicatedLink::flatten_array (): flatten an array. Useful for
	// hierarchical and namespaced elements.
	//
	// Given an array which may contain array or object elements in it,
	// return a "flattened" array: a one-dimensional array of scalars
	// containing each of the scalar elements contained within the array
	// structure. Thus, for example, if $a['b']['c']['d'] == 'e', then the
	// returned array for FeedWordPress::flatten_array($a) will contain a key
	// $a['feed/b/c/d'] with value 'e'.
	function flatten_array ($arr, $prefix = 'feed/', $separator = '/') {
		$ret = array ();
		if (is_array($arr)) :
			foreach ($arr as $key => $value) :
				if (is_scalar($value)) :
					$ret[$prefix.$key] = $value;
				else :
					$ret = array_merge($ret, $this->flatten_array($value, $prefix.$key.$separator, $separator));
				endif;
			endforeach;
		endif;
		return $ret;
	} /* SyndicatedLink::flatten_array () */

	function hardcode ($what) {
		$default = get_option("feedwordpress_hardcode_$what");
		if ( $default === 'yes' ) :
			// If the default is to hardcode, then we want the
			// negation of negative(): TRUE by default and FALSE if
			// the setting is explicitly "no"
			$ret = !FeedWordPress::negative($this->settings, "hardcode $what");
		else :
			// If the default is NOT to hardcode, then we want
			// affirmative(): FALSE by default and TRUE if the
			// setting is explicitly "yes"
			$ret = FeedWordPress::affirmative($this->settings, "hardcode $what");
		endif;
		return $ret;
	} /* SyndicatedLink::hardcode () */

	function syndicated_status ($what, $default, $fallback = true) {
		// Use local setting if we have it
		if ( isset($this->settings["$what status"]) ) :
			$ret = $this->settings["$what status"];
		
		// Or fall back to global default if we can
		elseif ($fallback) :
			$ret = FeedWordPress::syndicated_status($what, $default);

		// Or use default value if we can't.
		else :
			$ret = $default;

		endif;

		return addslashes(trim(strtolower($ret)));
	} /* SyndicatedLink:syndicated_status () */
} // class SyndicatedLink

