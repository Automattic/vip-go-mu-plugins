<?php
require_once(dirname(__FILE__) . '/admin-ui.php');

class FeedWordPressCategoriesPage extends FeedWordPressAdminPage {
	function FeedWordPressCategoriesPage ($link) {
		FeedWordPressAdminPage::FeedWordPressAdminPage('feedwordpresscategories', $link);
		$this->dispatch = 'feedwordpress_categories_settings';
		$this->filename = FWP_CATEGORIES_PAGE_SLUG;
	}
	
	/*static*/ function feed_categories_box ($page, $box = NULL) {

		$link = $page->link;

		$unfamiliar = array ('create'=>'','tag' => '', 'default'=>'','filter'=>'');
		if ($page->for_feed_settings()) :
			$unfamiliar['site-default'] = '';
			$ucKey = isset($link->settings["unfamiliar category"]) ? $link->settings["unfamiliar category"] : null;
			$ucDefault = 'site-default';
		else :
			$ucKey = FeedWordPress::on_unfamiliar('category');
			$ucDefault = 'create';
		endif;
	
		if (!is_string($ucKey) or !array_key_exists($ucKey, $unfamiliar)) :
			$ucKey = $ucDefault;
		endif;
		$unfamiliar[$ucKey] = ' checked="checked"';
		
		// Hey ho, let's go...
		?>
<table class="edit-form">
<tr>
<th scope="row">Unfamiliar categories:</th>
<td><p>When one of the categories on a syndicated post is a category that FeedWordPress has not encountered before ...</p>

<ul class="options">
<?php if ($page->for_feed_settings()) : ?>
<li><label><input type="radio" name="unfamiliar_category" value="site-default"<?php echo $unfamiliar['site-default']; ?> /> use the <a href="admin.php?page=<?php echo FWP_CATEGORIES_PAGE_SLUG; ?>">site-wide setting</a>
(currently <strong><?php echo FeedWordPress::on_unfamiliar('category'); ?></strong>)</label></li>
<?php endif; ?>

<li><label><input type="radio" name="unfamiliar_category" value="create"<?php echo $unfamiliar['create']; ?> /> create a new category</label></li>

<?php if (FeedWordPressCompatibility::post_tags()) : ?>
<li><label><input type="radio" name="unfamiliar_category" value="tag"<?php echo $unfamiliar['tag']; ?>/> create a new tag</label></li>
<?php endif; ?>

<li><label><input type="radio" name="unfamiliar_category" value="default"<?php echo $unfamiliar['default']; ?> /> don't create new categories<?php if (fwp_test_wp_version(FWP_SCHEMA_23)) : ?> or tags<?php endif; ?></label></li>
<li><label><input type="radio" name="unfamiliar_category" value="filter"<?php echo $unfamiliar['filter']; ?> /> don't create new categories<?php if (fwp_test_wp_version(FWP_SCHEMA_23)) : ?> or tags<?php endif; ?> and don't syndicate posts unless they match at least one familiar category</label></li>
</ul></td>
</tr>

<?php if ($page->for_feed_settings()) : ?>
<tr>
<th scope="row">Multiple categories:</th>
<td> 
<input type="text" size="20" id="cat_split" name="cat_split" value="<?php if (isset($link->settings['cat_split'])) : echo htmlspecialchars($link->settings['cat_split']); endif; ?>" />
<p class="setting-description">Enter a <a href="http://us.php.net/manual/en/reference.pcre.pattern.syntax.php">Perl-compatible regular expression</a> here if the feed provides multiple
categories in a single category element. The regular expression should match
the characters used to separate one category from the next. If the feed uses
spaces (like <a href="http://del.icio.us/">del.icio.us</a>), use the pattern "\s".
If the feed does not provide multiple categories in a single element, leave this
blank.</p></td>
</tr>
<?php endif; ?>
</table>
		<?php
	} /* FeedWordPressCategoriesPage::feed_categories_box() */

	function categories_box ($page, $box = NULL) {
		$link = $page->link;
		if ($page->for_feed_settings()) :
			$cats = (isset($link->settings['cats']) && is_array($link->settings['cats'])) ? $link->settings['cats'] : array();
		else :
			$cats = array_map('trim',
				preg_split(FEEDWORDPRESS_CAT_SEPARATOR_PATTERN, get_option('feedwordpress_syndication_cats'))
			);
		endif;
		$dogs = SyndicatedPost::category_ids($cats, /*unfamiliar=*/ NULL);

		fwp_category_box($dogs, 'all '.$page->these_posts_phrase());
	} /* FeedWordPressCategoriesPage::categories_box () */
	
	function tags_box ($page, $box = NULL) {
		$link = $page->link;
		if ($page->for_feed_settings()) :
			$tags = (isset($link->settings['tags']) && is_array($link->settings['tags'])) ? $link->settings['tags'] : array();
		else :
			$tags = array_map('trim',
				preg_split(FEEDWORDPRESS_CAT_SEPARATOR_PATTERN, get_option('feedwordpress_syndication_tags'))
			);
		endif;

		fwp_tags_box($tags, 'all '.$page->these_posts_phrase());
	} /* FeedWordPressCategoriesPage::tags_box () */
}

function fwp_categories_page () {
	global $wp_db_version;
	
	if (FeedWordPress::needs_upgrade()) :
		fwp_upgrade_page();
		return;
	endif;

	FeedWordPressCompatibility::validate_http_request(/*action=*/ 'feedwordpress_categories_settings', /*capability=*/ 'manage_links');

	$link = FeedWordPressAdminPage::submitted_link();

	$catsPage = new FeedWordPressCategoriesPage($link);

	$mesg = null;

	////////////////////////////////////////////////
	// Process POST request, if any /////////////////
	////////////////////////////////////////////////
	if (isset($GLOBALS['fwp_post']['save']) or isset($GLOBALS['fwp_post']['submit'])) :
		$saveCats = array();
		if (isset($GLOBALS['fwp_post']['post_category'])) :
			foreach ($GLOBALS['fwp_post']['post_category'] as $cat_id) :
				$saveCats[] = '{#'.$cat_id.'}';
			endforeach;
		endif;

		// Different variable names to cope with different WordPress AJAX UIs
		$syndicatedTags = array();
		if (isset($GLOBALS['fwp_post']['tax_input']['post_tag'])) :
			$syndicatedTags = explode(",", $GLOBALS['fwp_post']['tax_input']['post_tag']);
		elseif (isset($GLOBALS['fwp_post']['tags_input'])) :
			$syndicatedTags = explode(",", $GLOBALS['fwp_post']['tags_input']);
		endif;
		$syndicatedTags = array_map('trim', $syndicatedTags);

		if (is_object($link) and $link->found()) :
			$alter = array ();

			// Categories
			if (!empty($saveCats)) : $link->settings['cats'] = $saveCats;
			else : unset($link->settings['cats']);
			endif;

			// Tags
			$link->settings['tags'] = $syndicatedTags;

			// Unfamiliar categories
			if (isset($GLOBALS['fwp_post']["unfamiliar_category"])) :
				if ('site-default'==$GLOBALS['fwp_post']["unfamiliar_category"]) :
					unset($link->settings["unfamiliar category"]);
				else :
					$link->settings["unfamiliar category"] = $GLOBALS['fwp_post']["unfamiliar_category"];
				endif;
			endif;

			// Category spitting regex
			if (isset($GLOBALS['fwp_post']['cat_split'])) :
				if (strlen(trim($GLOBALS['fwp_post']['cat_split'])) > 0) :
					$link->settings['cat_split'] = trim($GLOBALS['fwp_post']['cat_split']);
				else :
					unset($link->settings['cat_split']);
				endif;
			endif;

			// Save settings
			$link->save_settings(/*reload=*/ true);
			$catsPage->updated = true;
			
			// Reset, reload
			$link_id = $link->id;
			unset($link);
			$link = new SyndicatedLink($link_id);
		else :
			// Categories
			if (!empty($saveCats)) :
				update_option('feedwordpress_syndication_cats', implode(FEEDWORDPRESS_CAT_SEPARATOR, $saveCats));
			else :
				delete_option('feedwordpress_syndication_cats');
			endif;
	
			// Tags
			if (!empty($syndicatedTags)) :
				update_option('feedwordpress_syndication_tags', implode(FEEDWORDPRESS_CAT_SEPARATOR, $syndicatedTags));
			else :
				delete_option('feedwordpress_syndication_tags');
			endif;

			update_option('feedwordpress_unfamiliar_category', $_REQUEST['unfamiliar_category']);

			$catsPage->updated = true;
		endif;
		
		do_action('feedwordpress_admin_page_categories_save', $GLOBALS['fwp_post'], $catsPage);
	else :
		$catsPage->updated = false;
	endif;

	////////////////////////////////////////////////
	// Prepare settings page ///////////////////////
	////////////////////////////////////////////////
	
	$catsPage->display_update_notice_if_updated('Syndicated categories'.FEEDWORDPRESS_AND_TAGS, $mesg);
	$catsPage->open_sheet('Categories'.FEEDWORDPRESS_AND_TAGS);
	?>
	<style type="text/css">
		table.edit-form th { width: 27%; vertical-align: top; }
		table.edit-form td { width: 73%; vertical-align: top; }
		table.edit-form td ul.options { margin: 0; padding: 0; list-style: none; }
	</style>

	<div id="post-body">
	<?php
	////////////////////////////////////////////////
	// Display settings boxes //////////////////////
	////////////////////////////////////////////////

	$boxes_by_methods = array(
		'feed_categories_box' => __('Feed Categories'.FEEDWORDPRESS_AND_TAGS),
		'categories_box' => array('title' => __('Categories'), 'id' => 'categorydiv'),
		'tags_box' => __('Tags'),
	);
	if (!FeedWordPressCompatibility::post_tags()) :
		unset($boxes_by_methods['tags_box']);
	endif;

	foreach ($boxes_by_methods as $method => $row) :
		if (is_array($row)) :
			$id = $row['id'];
			$title = $row['title'];
		else :
			$id = 'feedwordpress_'.$method;
			$title = $row;
		endif;

		fwp_add_meta_box(
			/*id=*/ $id,
			/*title=*/ $title,
			/*callback=*/ array('FeedWordPressCategoriesPage', $method),
			/*page=*/ $catsPage->meta_box_context(),
			/*context=*/ $catsPage->meta_box_context()
		);
	endforeach;
	do_action('feedwordpress_admin_page_categories_meta_boxes', $catsPage);
?>
	<div class="metabox-holder">
<?php
	fwp_do_meta_boxes($catsPage->meta_box_context(), $catsPage->meta_box_context(), $catsPage);
?>
	</div> <!-- class="metabox-holder" -->
	</div> <!-- id="post-body" -->
	<?php $catsPage->close_sheet(); ?>
<?php
} /* function fwp_categories_page () */

	fwp_categories_page();

