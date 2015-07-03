<?php
/*  
	Copyright 2007-2011  John Havlik  (email : mtekkmonkey@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

//The breadcrumb class
class bcn_breadcrumb
{
	//Our member variables
	//The main text that will be shown
	protected $title;
	//Boolean, is this element linked
	protected $linked;
	//Linked anchor contents, null if $linked == false
	protected $anchor;
	//Global prefix, outside of link tags
	protected $prefix;
	//Global suffix, outside of link tags
	protected $suffix;
	//The type of this breadcrumb
	public $type;
	/**
	 * The enhanced default constructor
	 * 
	 * @return 
	 * @param string $title[optional]
	 * @param string $prefix[optional]
	 * @param string $suffix[optional]
	 * @param string $anchor[optional]
	 * @param bool $linked[optional]
	 */
	public function bcn_breadcrumb($title = '', $prefix = '', $suffix = '', $anchor = NULL, $linked = false)
	{
		//Set the title
		$this->title = __($title, 'breadcrumb_navxt');
		//Set the prefix
		$this->prefix = __($prefix, 'breadcrumb_navxt');
		//Set the suffix
		$this->suffix = __($suffix, 'breadcrumb_navxt');
		//Default state of unlinked
		$this->linked = $linked;
		//Always NULL if unlinked
		$this->anchor = $anchor;
		//null out the type, it's not fully used yet
		$this->type = NULL;
	}
	/**
	 * Function to set the protected title member
	 * 
	 * @param string $title
	 */
	public function set_title($title)
	{
		//Set the title
		$this->title = apply_filters('bcn_breadcrumb_title', __($title, 'breadcrumb_navxt'));
	}
	/**
	 * Function to set the protected prefix member
	 * 
	 * @param string $prefix
	 */
	public function set_prefix($prefix)
	{
		//Set the prefix
		$this->prefix = __($prefix, 'breadcrumb_navxt');
	}
	/**
	 * Function to set the protected suffix member
	 * 
	 * @param string $suffix
	 */
	public function set_suffix($suffix)
	{
		//Set the suffix
		$this->suffix = __($suffix, 'breadcrumb_navxt');
	}
	/**
	 * Function to get the protected title member
	 * @return $this->title
	 */
	public function get_title()
	{
		//Return the title
		return $this->title;
	}
	/**
	 * Function to get the protected prefix member
	 * @return $this->prefix
	 */
	public function get_prefix()
	{
		//Return the prefix
		return $this->prefix;
	}
	/**
	 * Function to get the protected suffix member
	 * @return $this->suffix
	 */
	public function get_suffix()
	{
		//Return the suffix
		return $this->suffix;
	}
	/**
	 * Sets the anchor attribute for the breadcrumb, will set $linked to true
	 * 
	 * @param string $template the anchor template to use
	 * @param string $url the url to replace the %link% tag in the anchor
	 * 
	 */
	public function set_anchor($template, $url)
	{
		// Sanity check.
		if( ! is_string( $url ) )
			$url = '';		

		// Set a safe template if none was specified.
		if( ! is_string( $template ) || $template == '' )
			$template = __( '<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt' );

		//Set the anchor, we strip tangs from the title to prevent html validation problems
		$this->anchor = str_replace('%title%', strip_tags($this->title), str_replace('%link%', $url, __($template, 'breadcrumb_navxt')));
		//Set linked to true since we called this function
		$this->linked = true;
	}
	/**
	 * This function will intelligently trim the title to the value passed in through $max_length.
	 * 
	 * @param int $max_length of the title.
	 */
	public function title_trim($max_length)
	{
		//Make sure that we are not making it longer with that ellipse
		if((mb_strlen($this->title) + 3) > $max_length)
		{
			//Trim the title
			$this->title = mb_substr($this->title, 0, $max_length - 1);
			//Make sure we can split at a space, but we want to limmit to cutting at max an additional 25%
			if(mb_strpos($this->title, ' ', .75 * $max_length) > 0)
			{
				//Don't split mid word
				while(mb_substr($this->title,-1) != ' ')
				{
					$this->title = mb_substr($this->title, 0, -1);
				}
			}
			//Remove the whitespace at the end and add the hellip
			$this->title = rtrim($this->title) . '&hellip;';
		}
	}
	/**
	 * Assembles the parts of the breadcrumb into a html string
	 * 
	 * @return string The compiled breadcrumb string
	 * @param bool $linked[optional] Allow the output to contain anchors?
	 */
	public function assemble($linked = true)
	{
		//Place in the breadcrumb's elements
		$breadcrumb_str = $this->prefix;
		//If we are linked we'll need to do up the link
		if($this->linked && $linked && $this->anchor)
		{
			$breadcrumb_str .= $this->anchor . $this->title . '</a>';
		}
		//Otherwise we just slip in the title
		else
		{
			$breadcrumb_str .= $this->title;
		}
		//Return the assembled string
		return $breadcrumb_str . $this->suffix;
	}
}

//The trail class
class bcn_breadcrumb_trail
{
	//Our member variables
	public $version = '3.9.0';
	//An array of breadcrumbs
	public $trail = array();
	//The options
	public $opt;
	//Default constructor
	function bcn_breadcrumb_trail()
	{
		//Load the translation domain as the next part needs it		
		load_plugin_textdomain($domain = 'breadcrumb_navxt', false, 'breadcrumb-navxt/languages');
		//Initilize with default option values
		$this->opt = array
		(
			//Should the mainsite be shown
			'mainsite_display' => true,
			//Title displayed when for the main site
			'mainsite_title' => __('Home', 'breadcrumb_navxt'),
			//The anchor template for the main site, this is global, two keywords are available %link% and %title%
			'mainsite_anchor' => __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt'),
			//The prefix for mainsite breadcrumbs, placed inside of current_item prefix
			'mainsite_prefix' => '',
			//The prefix for mainsite breadcrumbs, placed inside of current_item prefix
			'mainsite_suffix' => '',
			//Should the home page be shown
			'home_display' => true,
			//Title displayed when is_home() returns true
			'home_title' => __('Blog', 'breadcrumb_navxt'),
			//The anchor template for the home page, this is global, two keywords are available %link% and %title%
			'home_anchor' => __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt'),
			//Should the blog page be shown globally
			'blog_display' => true,
			//The anchor template for the blog page only in static front page mode, this is global, two keywords are available %link% and %title%
			'blog_anchor' => __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt'),
			//The prefix for home breadcrumbs, placed inside of current_item prefix
			'home_prefix' => '',
			//The suffix for home breadcrumbs, placed inside of current_item suffix
			'home_suffix' => '',
			//Separator that is placed between each item in the breadcrumb trial, but not placed before
			//the first and not after the last breadcrumb
			'separator' => ' &gt; ',
			//The maximum title lenght
			'max_title_length' => 0,
			//Current item options, really only applies to static pages and posts unless other current items are linked
			'current_item_linked' => false,
			//The anchor template for current items, this is global, two keywords are available %link% and %title%
			'current_item_anchor' => __('<a title="Reload the current page." href="%link%">', 'breadcrumb_navxt'),
			//The prefix for current items allows separate styling of the current location breadcrumb
			'current_item_prefix' => '',
			//The suffix for current items allows separate styling of the current location breadcrumb
			'current_item_suffix' => '',
			//Static page options
			//The prefix for page breadcrumbs, place on all page elements and inside of current_item prefix
			'post_page_prefix' => '',
			//The suffix for page breadcrumbs, place on all page elements and inside of current_item suffix
			'post_page_suffix' => '',
			//The anchor template for page breadcrumbs, two keywords are available %link% and %title%
			'post_page_anchor' => __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt'),
			//Just a link to the page on front property
			'post_page_root' => get_option('page_on_front'),
			//Paged options
			//The prefix for paged breadcrumbs, place on all page elements and inside of current_item prefix
			'paged_prefix' => '',
			//The suffix for paged breadcrumbs, place on all page elements and inside of current_item suffix
			'paged_suffix' => '',
			//Should we try filling out paged information
			'paged_display' => false,
			//The post options previously singleblogpost
			//The prefix for post breadcrumbs, place on all page elements and inside of current_item prefix
			'post_post_prefix' => '',
			//The suffix for post breadcrumbs, place on all page elements and inside of current_item suffix
			'post_post_suffix' => '',
			//The anchor template for post breadcrumbs, two keywords are available %link% and %title%
			'post_post_anchor' => __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt'),
			//Just a link for the page for posts
			'post_post_root' => get_option('page_for_posts'),
			//Should the trail include the taxonomy of the post
			'post_post_taxonomy_display' => true,
			//What taxonomy should be shown leading to the post, tag or category
			'post_post_taxonomy_type' => 'category',
			//Attachment settings
			//The prefix for attachment breadcrumbs, place on all page elements and inside of current_item prefix
			'attachment_prefix' => '',
			//The suffix for attachment breadcrumbs, place on all page elements and inside of current_item suffix
			'attachment_suffix' => '',
			//404 page settings
			//The prefix for 404 breadcrumbs, place on all page elements and inside of current_item prefix
			'404_prefix' => '',
			//The suffix for 404 breadcrumbs, place on all page elements and inside of current_item suffix
			'404_suffix' => '',
			//The text to be shown in the breadcrumb for a 404 page
			'404_title' => __('404', 'breadcrumb_navxt'),
			//Search page options
			//The prefix for search breadcrumbs, place on all page elements and inside of current_item prefix
			'search_prefix' => __('Search results for &#39;', 'breadcrumb_navxt'),
			//The suffix for search breadcrumbs, place on all page elements and inside of current_item suffix
			'search_suffix' => '&#39;',
			//The anchor template for search breadcrumbs, two keywords are available %link% and %title%
			'search_anchor' => __('<a title="Go to the first page of search results for %title%." href="%link%">', 'breadcrumb_navxt'),
			//Tag related stuff
			//The prefix for tag breadcrumbs, place on all page elements and inside of current_item prefix
			'post_tag_prefix' => '',
			//The suffix for tag breadcrumbs, place on all page elements and inside of current_item suffix
			'post_tag_suffix' => '',
			//The anchor template for tag breadcrumbs, two keywords are available %link% and %title%
			'post_tag_anchor' => __('<a title="Go to the %title% tag archives." href="%link%">', 'breadcrumb_navxt'),
			//Author page stuff
			//The prefix for author breadcrumbs, place on all page elements and inside of current_item prefix
			'author_prefix' => __('Articles by: ', 'breadcrumb_navxt'),
			//The suffix for author breadcrumbs, place on all page elements and inside of current_item suffix
			'author_suffix' => '',
			//The anchor template for author breadcrumbs, two keywords are available %link% and %title%
			'author_anchor' => __('<a title="Go to the first page of posts by %title%." href="%link%">', 'breadcrumb_navxt'),
			//Which of the various WordPress display types should the author breadcrumb display
			'author_name' => 'display_name',
			//Category stuff
			//The prefix for category breadcrumbs, place on all page elements and inside of current_item prefix
			'category_prefix' => '',
			//The suffix for category breadcrumbs, place on all page elements and inside of current_item suffix
			'category_suffix' => '',
			//The anchor template for category breadcrumbs, two keywords are available %link% and %title%
			'category_anchor' => __('<a title="Go to the %title% category archives." href="%link%">', 'breadcrumb_navxt'),
			//Archives related settings
			//Prefix for category archives, place inside of both the current_item prefix and the category_prefix
			'archive_category_prefix' => __('Archive by category &#39;', 'breadcrumb_navxt'),
			//Suffix for category archives, place inside of both the current_item suffix and the category_suffix
			'archive_category_suffix' => '&#39;',
			//Prefix for tag archives, place inside of the current_item prefix
			'archive_post_tag_prefix' => __('Archive by tag &#39;', 'breadcrumb_navxt'),
			//Suffix for tag archives, place inside of the current_item suffix
			'archive_post_tag_suffix' => '&#39;',
			'date_anchor' => __('<a title="Go to the %title% archives." href="%link%">', 'breadcrumb_navxt'),
			//Prefix for date archives, place inside of the current_item prefix
			'archive_date_prefix' => '',
			//Suffix for date archives, place inside of the current_item suffix
			'archive_date_suffix' => ''
		);
	}
	/**
	 * Adds a breadcrumb to the breadcrumb trail
	 * 
	 * @return pointer to the just added Breadcrumb
	 * @param bcn_breadcrumb $object Breadcrumb to add to the trail
	 */
	function &add(bcn_breadcrumb $object)
	{
		$this->trail[] = $object;
		//Return the just added object
		return $this->trail[count($this->trail) - 1];
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a search page.
	 */
	function do_search()
	{
		global $s;
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb(esc_html($s), $this->opt['search_prefix'], $this->opt['search_suffix']));
		//If we're paged, let's link to the first page
		if(is_paged() && $this->opt['paged_display'])
		{
			//Figure out the hyperlink for the anchor
			$url = get_settings('home') . '?s=' . str_replace(' ', '+', esc_html($s));
			//Figure out the anchor for the search
			$breadcrumb->set_anchor($this->opt['search_anchor'], $url);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for an author page.
	 */
	function do_author()
	{
		global $author;
		//Get the Author name, note it is an object
		$curauth = (isset($_GET['author_name'])) ? get_userdatabylogin($author_name) : get_userdata(intval($author));
		//Setup array of valid author_name values
		$valid_author_name = array('display_name', 'nickname', 'first_name', 'last_name');
		//This translation allows us to easily select the display type later on
		$author_name = $this->opt['author_name'];
		//Make sure user picks only safe values
		if(in_array($author_name, $valid_author_name))
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb(apply_filters('the_author', $curauth->$author_name),
				$this->opt['author_prefix'], $this->opt['author_suffix']));
			if(is_paged() && $this->opt['paged_display'])
			{
				$breadcrumb->set_anchor($this->opt['author_anchor'], get_author_posts_url($curauth->ID));
			}
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This function fills breadcrumbs for any post taxonomy.
	 * @param int $id The id of the post to figure out the taxonomy for.
	 * @param sting $type The post type of the post to figure out the taxonomy for.
	 */
	function post_taxonomy($id, $type)
	{
		//Check to see if breadcrumbs for the taxonomy of the post needs to be generated
		if($this->opt['post_' . $type . '_taxonomy_display'])
		{
			//Check if we have a date 'taxonomy' request
			if($this->opt['post_' . $type . '_taxonomy_type'] == 'date')
			{
				$this->do_archive_by_date();
			}
			//Handle all hierarchical taxonomies, including categories
			else if(is_taxonomy_hierarchical($this->opt['post_' . $type . '_taxonomy_type']))
			{
				//Fill a temporary object with the terms
				$bcn_object = get_the_terms($id, $this->opt['post_' . $type . '_taxonomy_type']);
				if(is_array($bcn_object))
				{
					//Now find which one has a parent, pick the first one that does
					$bcn_use_term = key($bcn_object);
					foreach($bcn_object as $key=>$object)
					{
						//We want the first term hiearchy
						if($object->parent > 0)
						{
							$bcn_use_term = $key;
							//We found our first term hiearchy, can exit loop now
							break;
						}
					}
					//Fill out the term hiearchy
					$this->term_parents($bcn_object[$bcn_use_term]->term_id, $this->opt['post_' . $type . '_taxonomy_type']);
				}
			}
			//Handle the use of hierarchical posts as the 'taxonomy'
			else if(is_post_type_hierarchical($this->opt['post_' . $type . '_taxonomy_type']))
			{
				//Done with the current item, now on to the parents
				$bcn_frontpage = get_option('page_on_front');
				//If there is a parent page let's find it
				if($post->post_parent && $id != $post->post_parent && $bcn_frontpage != $post->post_parent)
				{
					$this->post_parents($post->post_parent, $bcn_frontpage);
				}
			}
			//Handle the rest of the taxonomies, including tags
			else
			{
				$this->post_terms($id, $this->opt['post_' . $type . '_taxonomy_type']);
			}
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for the terms of a post
	 * @param int $id The id of the post to find the terms for.
	 * @param string $taxonomy The name of the taxonomy that the term belongs to
	 * 
	 * @TODO	Need to implement this cleaner, fix up the entire tag_ thing, as this is now generic
	 */
	function post_terms($id, $taxonomy)
	{
		//Add new breadcrumb to the trail
		$this->trail[] = new bcn_breadcrumb();
		//Figure out where we placed the crumb, make a nice pointer to it
		$bcn_breadcrumb = &$this->trail[count($this->trail) - 1];
		//Fills a temporary object with the terms for the post
		$bcn_object = get_the_terms($id, $taxonomy);
		//Only process if we have tags
		if(is_array($bcn_object))
		{
			$is_first = true;
			//Loop through all of the term results
			foreach($bcn_object as $term)
			{
				//Run through a filter for good measure
				$term->name = apply_filters("get_$taxonomy", $term->name);
				//Everything but the first term needs a comma separator
				if($is_first == false)
				{
					$bcn_breadcrumb->set_title($bcn_breadcrumb->get_title() . ', ');
				}
				//This is a bit hackish, but it compiles the term anchor and appends it to the current breadcrumb title
				$bcn_breadcrumb->set_title($bcn_breadcrumb->get_title() . $this->opt[$taxonomy . '_prefix'] . str_replace('%title%', $term->name, str_replace('%link%', get_term_link($term, $taxonomy), $this->opt[$taxonomy . '_anchor'])) .
					$term->name . '</a>' . $this->opt[$taxonomy . '_suffix']);
				$is_first = false;
			}
		}
		else
		{
			//If there are no tags, then we set the title to "Untagged"
			$bcn_breadcrumb->set_title(__('Un' . $taxonomy->name, 'breadcrumb_navxt'));
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This recursive functions fills the trail with breadcrumbs for parent terms.
	 * @param int $id The id of the term.
	 * @param string $taxonomy The name of the taxonomy that the term belongs to
	 */
	function term_parents($id, $taxonomy)
	{
		global $post;
		//Get the current category object, filter applied within this call
		$term = &get_term($id, $taxonomy);
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb($term->name, $this->opt[$taxonomy . '_prefix'], $this->opt[$taxonomy . '_suffix']));
		//Figure out the anchor for the term
		$breadcrumb->set_anchor($this->opt[$taxonomy . '_anchor'], get_term_link($term, $taxonomy));
		//Make sure the id is valid, and that we won't end up spinning in a loop
		if($term->parent && $term->parent != $id)
		{
			//Figure out the rest of the term hiearchy via recursion
			$this->term_parents($term->parent, $taxonomy);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This recursive functions fills the trail with breadcrumbs for parent posts/pages.
	 * @param int $id The id of the parent page.
	 * @param int $frontpage The id of the front page.
	 */
	function post_parents($id, $frontpage)
	{
		//Use WordPress API, though a bit heavier than the old method, this will ensure compatibility with other plug-ins
		$parent = get_post($id);
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb(get_the_title($id), $this->opt['post_' . $parent->post_type . '_prefix'], 
			$this->opt['post_' . $parent->post_type . '_suffix']));
		//Assign the anchor properties
		$breadcrumb->set_anchor($this->opt['post_' . $parent->post_type . '_anchor'], get_permalink($id));
		//Make sure the id is valid, and that we won't end up spinning in a loop
		if($parent->post_parent >= 0 && $parent->post_parent != false && $id != $parent->post_parent && $frontpage != $parent->post_parent)
		{
			//If valid, recursively call this function
			$this->post_parents($parent->post_parent, $frontpage);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a hierarchical post/page.
	 */
	function do_post_hierarchical()
	{
		global $post, $page;
		//Place the breadcrumb in the trail, uses the bcn_breadcrumb constructor to set the title, prefix, and suffix
		$breadcrumb = $this->add(new bcn_breadcrumb(get_the_title(), $this->opt['post_' . $post->post_type . '_prefix'], $this->opt['post_' . $post->post_type . '_suffix']));
		if($page > 0 && $this->opt['paged_display'])
		{
			$breadcrumb->set_anchor($this->opt['post_page_anchor'], get_permalink());
		}
		//Done with the current item, now on to the parents
		$bcn_frontpage = get_option('page_on_front');
		//If there is a parent page let's find it
		if($post->post_parent && $post->ID != $post->post_parent && $bcn_frontpage != $post->post_parent)
		{
			$this->post_parents($post->post_parent, $bcn_frontpage);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a post.
	 */
	function do_post_flat()
	{
		global $post, $page;
		//Place the breadcrumb in the trail, uses the bcn_breadcrumb constructor to set the title, prefix, and suffix
		$breadcrumb = $this->add(new bcn_breadcrumb(get_the_title(), $this->opt['post_' . $post->post_type . '_prefix'], $this->opt['post_' . $post->post_type . '_suffix']));
		if($page > 0 && $this->opt['paged_display'])
		{
			$breadcrumb->set_anchor($this->opt['post_post_anchor'], get_permalink());
		}
		//Handle the post's taxonomy
		$this->post_taxonomy($post->ID, $post->post_type);
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for an attachment page.
	 */
	function do_attachment()
	{
		global $post;
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$this->trail[] = new bcn_breadcrumb(get_the_title(), $this->opt['attachment_prefix'], $this->opt['attachment_suffix']);
		//Get the parent's information
		$parent = get_post($post->post_parent);
		//We need to treat flat and hiearchical post attachment hierachies differently
		if(is_post_type_hierarchical($parent->post_type))
		{
			//Grab the page on front ID for post_parents
			$frontpage = get_option('page_on_front');
			//Place the rest of the page hierachy
			$this->post_parents($post->post_parent, $frontpage);
		}
		else
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb(get_the_title($post->post_parent),
				$this->opt['post_post_prefix'], $this->opt['post_post_suffix']));
			//Assign the anchor properties
			$breadcrumb->set_anchor($this->opt['post_post_anchor'], get_permalink($post->post_parent));
			//Handle the post's taxonomy
			$this->post_taxonomy($post->post_parent, $parent->post_type);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a hierarchical taxonomy (e.g. category) archive.
	 */
	function do_archive_by_term_hierarchical()
	{
		global $wp_query;
		//Simmilar to using $post, but for things $post doesn't cover
		$term = $wp_query->get_queried_object();
		//Run through a filter for good measure
		$term->name = apply_filters('get_' . $term->taxonomy, $term->name);
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb($term->name, $this->opt[$term->taxonomy . '_prefix'] . $this->opt['archive_' . $term->taxonomy . '_prefix'],
			$this->opt['archive_' . $term->taxonomy . '_suffix'] . $this->opt[$term->taxonomy . '_suffix']));
		//If we're paged, let's link to the first page
		if(is_paged() && $this->opt['paged_display'])
		{
			//Figure out the anchor for current category
			$breadcrumb->set_anchor($this->opt[$term->taxonomy . '_anchor'], get_term_link($term, $term->taxonomy));
		}
		//Get parents of current category
		if($term->parent)
		{
			$this->term_parents($term->parent, $term->taxonomy);
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a flat taxonomy (e.g. tag) archive.
	 */
	function do_archive_by_term_flat()
	{
		global $wp_query;
		//Simmilar to using $post, but for things $post doesn't cover
		$term = $wp_query->get_queried_object();
		//Run through a filter for good measure
		$term->name = apply_filters('get_' . $term->taxonomy, $term->name);
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb($term->name, $this->opt['archive_' . $term->taxonomy . '_prefix'] . $this->opt[$term->taxonomy . '_prefix'], 
			$this->opt[$term->taxonomy . '_suffix'] . $this->opt['archive_' . $term->taxonomy . '_suffix']));
		//If we're paged, let's link to the first page
		if(is_paged() && $this->opt['paged_display'])
		{
			//Figure out the anchor for current category
			$breadcrumb->set_anchor($this->opt[$term->taxonomy . '_anchor'], get_term_link($term, $term->taxonomy));
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for a date archive.
	 */
	function do_archive_by_date()
	{
		global $wp_query;
		//First deal with the day breadcrumb
		if(is_day() || is_single())
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb(get_the_time('d'), $this->opt['archive_date_prefix'], $this->opt['archive_date_suffix']));
			//If we're paged, let's link to the first page
			if(is_paged() && $this->opt['paged_display'] || is_single())
			{
				//Deal with the anchor
				$breadcrumb->set_anchor($this->opt['date_anchor'], get_day_link(get_the_time('Y'), get_the_time('m'), get_the_time('d')));
			}
		}
		//Now deal with the month breadcrumb
		if(is_month() || is_day() || is_single())
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb(get_the_time('F'), $this->opt['archive_date_prefix'], $this->opt['archive_date_suffix']));
			//If we're paged, or not in the archive by month let's link to the first archive by month page
			if(is_day() || is_single() || (is_month() && is_paged() && $this->opt['paged_display']))
			{
				//Deal with the anchor
				$breadcrumb->set_anchor($this->opt['date_anchor'], get_month_link(get_the_time('Y'), get_the_time('m')));
			}
		}
		//Place the year breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb(get_the_time('Y'), $this->opt['archive_date_prefix'], $this->opt['archive_date_suffix']));
		//If we're paged, or not in the archive by year let's link to the first archive by year page
		if(is_day() || is_month() || is_single() || (is_paged() && $this->opt['paged_display']))
		{
			//Deal with the anchor
			$breadcrumb->set_anchor($this->opt['date_anchor'], get_year_link(get_the_time('Y')));
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for the front page.
	 */
	function do_front_page()
	{
		global $post, $current_site;
		//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
		$breadcrumb = $this->add(new bcn_breadcrumb($this->opt['home_title'], $this->opt['home_prefix'], $this->opt['home_suffix']));
		//If we have a multi site and are not on the main site we may need to add a breadcrumb for the main site
		if($this->opt['mainsite_display'] && !is_main_site())
		{
			//Place the main site breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb($this->opt['mainsite_title'], $this->opt['mainsite_prefix'], $this->opt['mainsite_suffix']));
			//Deal with the anchor
			$breadcrumb->set_anchor($this->opt['mainsite_anchor'], get_home_url($current_site->blog_id));
		}
		//If we're paged, let's link to the first page
		if(is_paged() && $this->opt['paged_display'])
		{
			//Figure out the anchor for home page
			$breadcrumb->set_anchor($this->opt['home_anchor'], get_home_url());
		}
	}
	/**
	 * A modified version of WordPress' function of the same name
	 * @param object $object the post or taxonomy object used to attempt to find the title
	 * @return string the title
	 */
	function post_type_archive_title($object)
	{
		if(isset($object->labels->name))
		{
			return apply_filters('post_type_archive_title', $object->labels->name);
		}
	}
	/**
	 * Determines if a post type is a built in type or not
	 * 
	 * @param string $post_type the name of the post type
	 * @return bool
	 */
	function is_builtin($post_type)
	{
		$type = get_post_type_object($post_type);
		return $type->_builtin;
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for the home page.
	 */
	function do_home()
	{
		global $post, $wp_query, $wp_taxonomies, $current_site;
		//Simmilar to using $post, but for things $post doesn't cover
		$type = $wp_query->get_queried_object();
		//We need to do special things for custom post types
		if(is_singular() && !$this->is_builtin($type->post_type))
		{
			//This will assign a ID for root page of a custom post
			if(is_numeric($this->opt['post_' . $type->post_type . '_root']))
			{
				$posts_id = $this->opt['post_' . $type->post_type . '_root'];
			}
		}
		//We need to do special things for custom post type archives, but not author or date archives
		else if(is_archive() && !is_author() && !is_date() && !is_post_type_archive() && !$this->is_builtin($wp_taxonomies[$type->taxonomy]->object_type[0]))
		//else if((is_tax() || is_category() || is_tag()) && !$this->is_builtin($wp_taxonomies[$type->taxonomy]->object_type[0]))
		{
			//This will assign a ID for root page of a custom post's taxonomy archive
			if(is_numeric($this->opt['post_' . $wp_taxonomies[$type->taxonomy]->object_type[0] . '_root']))
			{
				$posts_id = $this->opt['post_' . $wp_taxonomies[$type->taxonomy]->object_type[0] . '_root'];
			}
		}
		if(is_post_type_archive())
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb(post_type_archive_title('', false), $this->opt['post_page_prefix'],
				$this->opt['post_page_suffix']));
			if(is_paged() && $this->opt['paged_display'])
			{
				//Deal with the anchor
				$breadcrumb->set_anchor($this->opt['blog_anchor'], get_post_type_archive_link(get_post_type()));
			}
		}
		else if(isset($type->post_type) && !$this->is_builtin($type->post_type))
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb($this->post_type_archive_title(get_post_type_object($type->post_type)), $this->opt['post_page_prefix'],
				$this->opt['post_page_suffix']));
			//Deal with the anchor
			$breadcrumb->set_anchor($this->opt['blog_anchor'], get_post_type_archive_link($type->post_type));
		}
		else if(isset($type->taxonomy) && !$this->is_builtin($wp_taxonomies[$type->taxonomy]->object_type[0]))
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb($this->post_type_archive_title(get_post_type_object($wp_taxonomies[$type->taxonomy]->object_type[0])), $this->opt['post_page_prefix'],
				$this->opt['post_page_suffix']));
			//Deal with the anchor
			$breadcrumb->set_anchor($this->opt['blog_anchor'], get_post_type_archive_link($wp_taxonomies[$type->taxonomy]->object_type[0]));
		}
		//We only need the "blog" portion on members of the blog, and only if we're in a static frontpage environment
		if(isset($posts_id) || $this->opt['blog_display'] && get_option('show_on_front') == 'page' && (is_home() || is_post_type_archive() || is_single() || is_tax() || is_category() || is_tag()))
		{
			//If we entered here with a posts page, we need to set the id
			if(!isset($posts_id))
			{
				$posts_id = get_option('page_for_posts');
			}
			$frontpage_id = get_option('page_on_front');
			//We'll have to check if this ID is valid, e.g. user has specified a posts page
			if($posts_id && $posts_id != $frontpage_id)
			{
				//Get the blog page
				$bcn_post = get_post($posts_id);
				if(!is_post_type_archive())
				{
					//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
					$breadcrumb = $this->add(new bcn_breadcrumb(get_the_title($posts_id), $this->opt['post_page_prefix'],
						$this->opt['post_page_suffix']));
					//If we're not on the current item we need to setup the anchor
					if(!is_home()|| (is_paged() && $this->opt['paged_display']))
					{
						//Deal with the anchor
						$breadcrumb->set_anchor($this->opt['blog_anchor'], get_permalink($posts_id));
					}
				}
				//Done with the "root", now on to the parents
				//If there is a parent post let's find it
				if($bcn_post->post_parent && $bcn_post->ID != $bcn_post->post_parent && $frontpage_id != $bcn_post->post_parent)
				{
					$this->post_parents($bcn_post->post_parent, $frontpage_id);
				}
			}
		}
		//On everything else we need to link, but no current item (pre/suf)fixes
		if($this->opt['home_display'])
		{
			//Place the breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
			$breadcrumb = $this->add(new bcn_breadcrumb($this->opt['home_title'], $this->opt['home_prefix'], $this->opt['home_suffix']));
			//Deal with the anchor
			$breadcrumb->set_anchor($this->opt['home_anchor'], get_home_url());
			//If we have a multi site and are not on the main site we need to add a breadcrumb for the main site
			if($this->opt['mainsite_display'] && !is_main_site())
			{
				//Place the main site breadcrumb in the trail, uses the constructor to set the title, prefix, and suffix, get a pointer to it in return
				$breadcrumb = $this->add(new bcn_breadcrumb($this->opt['mainsite_title'], $this->opt['mainsite_prefix'], $this->opt['mainsite_suffix']));
				//Deal with the anchor
				$breadcrumb->set_anchor($this->opt['mainsite_anchor'], get_home_url($current_site->blog_id));
			}
		}
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for 404 pages.
	 */
	function do_404()
	{
		//Place the breadcrumb in the trail, uses the bcn_breadcrumb constructor to set the title, prefix, and suffix
		$this->trail[] = new bcn_breadcrumb($this->opt['404_title'], $this->opt['404_prefix'], $this->opt['404_suffix']);
	}
	/**
	 * A Breadcrumb Trail Filling Function
	 * 
	 * This functions fills a breadcrumb for paged pages.
	 */
	function do_paged()
	{
		global $paged, $page;
		//Need to switch between paged and page for archives and singular (posts)
		if($paged > 0)
		{
			$page_number = $paged;
		}
		else
		{
			$page_number = $page;
		}
		//Place the breadcrumb in the trail, uses the bcn_breadcrumb constructor to set the title, prefix, and suffix
		$this->trail[] = new bcn_breadcrumb($page_number, $this->opt['paged_prefix'], $this->opt['paged_suffix']);
	}
	/**
	 * Breadcrumb Trail Filling Function
	 * 
	 * This functions fills the breadcrumb trail.
	 */
	function fill()
	{
		global $wpdb, $post, $wp_query, $paged, $page;
		//Check to see if the trail is already populated
		if(count($this->trail) > 0)
		{
			//Exit early since we have breadcrumbs in the trail
			return null;
		}
		//Do any actions if necessary, we past through the current object instance to keep life simple
		do_action('bcn_before_fill', $this);
		//Need to grab the queried object here as multiple branches need it
		$queried_object = $wp_query->get_queried_object();
		//Do specific opperations for the various page types
		//Check if this isn't the first of a multi paged item
		if($this->opt['paged_display'] && (is_paged() || is_singular() && $page > 0))
		{
			$this->do_paged();
		}
		//For the front page, as it may also validate as a page, do it first
		if(is_front_page())
		{
			//Must have two seperate branches so that we don't evaluate it as a page
			if($this->opt['home_display'])
			{
				$this->do_front_page();
			}
		}
		//For posts
		else if(is_singular())
		{
			//For hierarchical posts
			if(is_page() || (is_post_type_hierarchical($queried_object->post_type) && !is_home()))
			{
				$this->do_post_hierarchical();
			}
			//For attachments
			else if(is_attachment())
			{
				$this->do_attachment();
			}
			//For flat posts
			else
			{
				$this->do_post_flat();
			}
		}
		//For searches
		else if(is_search())
		{
			$this->do_search();
		}
		//For author pages
		else if(is_author())
		{
			$this->do_author();
		}
		//For archives
		else if(is_archive())
		{
			//For date based archives
			if(is_date())
			{
				$this->do_archive_by_date();
			}
			/*else if(is_post_type_archive($queried_object->name))
			{
				echo "moo";
			}*/
			//For taxonomy based archives
			else if(is_category() || is_tag() || is_tax())
			{
				//For hierarchical taxonomy based archives
				if(is_taxonomy_hierarchical($queried_object->taxonomy))
				{
					$this->do_archive_by_term_hierarchical();
				}
				//For flat taxonomy based archives
				else
				{
					$this->do_archive_by_term_flat();
				}
			}
		}
		//For 404 pages
		else if(is_404())
		{
			$this->do_404();
		}
		//We always do the home link last, unless on the frontpage
		if(!is_front_page())
		{
			$this->do_home();
		}
		//Do any actions if necessary, we past through the current object instance to keep life simple
		do_action('bcn_after_fill', $this);
	}
	/**
	 * This function will either set the order of the trail to reverse key 
	 * order, or make sure it is forward key ordered.
	 * 
	 * @param bool $reverse[optional] Whether to reverse the trail or not.
	 */
	function order($reverse = false)
	{
		if($reverse)
		{
			//Since there may be multiple calls our trail may be in a non-standard order
			ksort($this->trail);
		}
		else
		{
			//For normal opperation we must reverse the array by key
			krsort($this->trail);
		}
	}
	/**
	 * Performs actions specific to current item breadcrumbs. It will wrap the prefix/suffix
	 * with the current_item_prefix and current_item_suffix. Additionally, it will link the
	 * current item if current_item_linked is set to true.
	 * 
	 * @param bcn_breadrumb $breadcrumb pointer to a bcn_breadcrumb object to opperate on
	 */
	function current_item($breadcrumb)
	{
		//We are misusing the breadcrumb type property here, but in 4.0 this will be unnecessary
		if($breadcrumb->type == null)
		{	
			//Prepend the current item prefix
			$breadcrumb->set_prefix($this->opt['current_item_prefix'] . $breadcrumb->get_prefix());
			//Append the current item suffix
			$breadcrumb->set_suffix($breadcrumb->get_suffix() . $this->opt['current_item_suffix']);
			//Set the breadcrumb's type to current_item
			$breadcrumb->type = 'current_item';
			//Link the current item, if required
			if($this->opt['current_item_linked'])
			{
				$breadcrumb->set_anchor($this->opt['current_item_anchor'], '');
			}
		}
	}
	/**
	 * This functions outputs or returns the breadcrumb trail in string form.
	 *
	 * @return void Void if Option to print out breadcrumb trail was chosen.
	 * @return string String-Data of breadcrumb trail.
	 * @param bool $return Whether to return data or to echo it.
	 * @param bool $linked[optional] Whether to allow hyperlinks in the trail or not.
	 * @param bool $reverse[optional] Whether to reverse the output or not. 
	 */
	function display($return = false, $linked = true, $reverse = false)
	{
		//Set trail order based on reverse flag
		$this->order($reverse);
		//Initilize the string which will hold the assembled trail
		$trail_str = '';
		//The main compiling loop
		foreach($this->trail as $key=>$breadcrumb)
		{
			//Must branch if we are reversing the output or not
			if($reverse)
			{
				//Add in the separator only if we are the 2nd or greater element
				if($key > 0)
				{
					$trail_str .= $this->opt['separator'];
				}
			}
			else
			{
				//Only show the separator when necessary
				if($key < count($this->trail) - 1)
				{
					$trail_str .= $this->opt['separator'];
				}
			}
			//If we are on the current item there are some things that must be done
			if($key === 0)
			{
				$this->current_item($breadcrumb);
			}
			//Trim titles, if needed
			if($this->opt['max_title_length'] > 0)
			{
				//Trim the breadcrumb's title
				$breadcrumb->title_trim($this->opt['max_title_length']);
			}
			//Place in the breadcrumb's assembled elements
			$trail_str .= $breadcrumb->assemble($linked);
		}
		//Should we return or echo the assembled trail?
		if($return)
		{
			return $trail_str;
		}
		else
		{
			//Giving credit where credit is due, please don't remove it
			$credits = "<!-- Breadcrumb NavXT " . $this->version . " -->\n";
			echo $credits . $trail_str;
		}
	}
	/**
	 * This functions outputs or returns the breadcrumb trail in list form.
	 *
	 * @return void Void if Option to print out breadcrumb trail was chosen.
	 * @return string String-Data of breadcrumb trail.
	 * @param bool $return Whether to return data or to echo it.
	 * @param bool $linked[optional] Whether to allow hyperlinks in the trail or not.
	 * @param bool $reverse[optional] Whether to reverse the output or not. 
	 */
	function display_list($return = false, $linked = true, $reverse = false)
	{
		//Set trail order based on reverse flag
		$this->order($reverse);
		//Initilize the string which will hold the assembled trail
		$trail_str = '';
		//The main compiling loop
		foreach($this->trail as $key=>$breadcrumb)
		{
			$trail_str .= '<li';
			//On the first run we need to add in a class for the home breadcrumb
			if($trail_str === '<li')
			{
				$trail_str .= ' class="home';
				if($key === 0)
				{
					$trail_str .= ' current_item';
				}
				$trail_str .= '"';
			}
			//If we are on the current item there are some things that must be done
			else if($key === 0)
			{
				$this->current_item($breadcrumb);
				//Add in a class for current_item
				$trail_str .= ' class="current_item"';
			}
			//Trim titles, if needed
			if($this->opt['max_title_length'] > 0)
			{
				//Trim the breadcrumb's title
				$breadcrumb->title_trim($this->opt['max_title_length']);
			}
			//Place in the breadcrumb's assembled elements
			$trail_str .= '>' . $breadcrumb->assemble($linked);
			$trail_str .= "</li>\n";
		}
		//Should we return or echo the assembled trail?
		if($return)
		{
			return $trail_str;
		}
		else
		{
			//Giving credit where credit is due, please don't remove it
			$credits = "<!-- Breadcrumb NavXT " . $this->version . " -->\n";
			echo $credits . $trail_str;
		}
	}
	function nested_loop($linked, $tag, $mode)
	{
		//Grab the current breadcrumb from the trail, move the iterator forward one
		if(list($key, $breadcrumb) = each($this->trail))
		{
			//If we are on the current item there are some things that must be done
			if($key === 0)
			{
				$this->current_item($breadcrumb);
			}
			//Trim titles, if needed
			if($this->opt['max_title_length'] > 0)
			{
				//Trim the breadcrumb's title
				$breadcrumb->title_trim($this->opt['max_title_length']);
			}
			if($mode === 'rdfa')	
			{
				return sprintf('%1$s<%2$s rel="v:child"><%2$s typeof="v:Breadcrumb">%3$s%4$s</%2$s></%2$s>', $this->opt['separator'], $tag, $breadcrumb->assemble($linked), $this->nested_loop($linked, $tag, $mode));
			}
			else
			{
				return sprintf('%1$s<%2$s itemprop="child" itemscope itemtype="http://data-vocabulary.org/Breadcrumb">%3$s%4$s</%2$s>', $this->opt['separator'], $tag, $breadcrumb->assemble($linked), $this->nested_loop($linked, $tag, $mode));
			}
		}
		else
		{
			return '';
		}
	}
	/**
	 * Breadcrumb Creation Function
	 * 
	 * This functions outputs or returns the breadcrumb trail in string form.
	 *
	 * @return void Void if Option to print out breadcrumb trail was chosen.
	 * @return string String-Data of breadcrumb trail.
	 * @param bool $return Whether to return data or to echo it.
	 * @param bool $linked[optional] Whether to allow hyperlinks in the trail or not.
	 * @param string $tag[optional] The tag to use for the nesting
	 * @param string $mode[optional] Whether to follow the rdfa or Microdata format
	 */
	function display_nested($return = false, $linked = true, $tag = 'span', $mode = 'rdfa')
	{
		//Set trail order based on reverse flag
		$this->order(false);
		//Makesure the iterator is pointing to the first element
		$breadcrumb = reset($this->trail);
		//Trim titles, if needed
		if($this->opt['max_title_length'] > 0)
		{
			//Trim the breadcrumb's title
			$breadcrumb->title_trim($this->opt['max_title_length']);
		}
		if($mode === 'rdfa')	
		{
			//Start up the recursive engine
			$trail_str = sprintf('<%1$s typeof="v:Breadcrumb">%2$s %3$s</%1$s>', $tag, $breadcrumb->assemble($linked), $this->nested_loop($linked, $tag, $mode));
		}
		else
		{
			//Start up the recursive engine
			$trail_str = sprintf('%2$s %3$s', $tag, $breadcrumb->assemble($linked), $this->nested_loop($linked, $tag, $mode));
		}
		//Should we return or echo the assembled trail?
		if($return)
		{
			return $trail_str;
		}
		else
		{
			//Giving credit where credit is due, please don't remove it
			$credits = "<!-- Breadcrumb NavXT " . $this->version . " -->\n";
			echo $credits . $trail_str;
		}
	}
}
