<?php
require_once(dirname(__FILE__) . '/admin-ui.php');

################################################################################
## ADMIN MENU ADD-ONS: implement Dashboard management pages ####################
################################################################################

define('FWP_UPDATE_CHECKED', 'Update Checked');
define('FWP_UNSUB_CHECKED', 'Unsubscribe');
define('FWP_DELETE_CHECKED', 'Delete');
define('FWP_RESUB_CHECKED', 'Re-subscribe');
define('FWP_SYNDICATE_NEW', 'Add →');

class FeedWordPressSyndicationPage extends FeedWordPressAdminPage {
	function FeedWordPressSyndicationPage () {
		FeedWordPressAdminPage::FeedWordPressAdminPage('feedwordpresssyndication', /*link=*/ NULL);

		// No over-arching form element
		$this->dispatch = NULL;
		$this->filename = FWP_SYNDICATION_PAGE_SLUG;
	} /* FeedWordPressSyndicationPage constructor */

	function has_link () { return false; }

	var $_sources = NULL;

	function sources ($visibility = 'Y') {
		if (is_null($this->_sources)) :
			$links = FeedWordPress::syndicated_links(array("hide_invisible" => false));
			$this->_sources = array("Y" => array(), "N" => array());
			foreach ($links as $link) :
				$this->_sources[$link->link_visible][] = $link;
			endforeach;
		endif;
		$ret = (
			array_key_exists($visibility, $this->_sources)
			? $this->_sources[$visibility]
			: $this->_sources
		);
		return $ret;
	} /* FeedWordPressSyndicationPage::sources() */

	function visibility_toggle () {
		$sources = $this->sources('*');

		$defaultVisibility = 'Y';
		if ((count($this->sources('N')) > 0)
		and (count($this->sources('Y'))==0)) :
			$defaultVisibility = 'N';
		endif;
		
		$visibility = (
			isset($_REQUEST['visibility'])
			? $_REQUEST['visibility']
			: $defaultVisibility
		);
		
		return $visibility;
	} /* FeedWordPressSyndicationPage::visibility_toggle() */

	function show_inactive () {
		return ($this->visibility_toggle() == 'N');
	}

	function updates_requested () {
		if (isset($_POST['update']) or isset($_POST['action']) or isset($_POST['update_uri'])) :
			 // Only do things with side-effects for HTTP POST or command line
			 $fwp_update_invoke = 'post';
		else :
			$fwp_update_invoke = 'get';
		endif;

		$update_set = array();
		if ($fwp_update_invoke != 'get') :
			if (isset($_POST['link_ids']) and is_array($_POST['link_ids']) and ($_POST['action']==FWP_UPDATE_CHECKED)) :
				$targets = get_bookmarks(array(
					'include' => implode(",",$_POST['link_ids'])
				));
				if (is_array($targets)) :
					foreach ($targets as $target) :
						$update_set[] = $target->link_rss;
					endforeach;
				else : // This should never happen
					FeedWordPress::critical_bug('fwp_syndication_manage_page::targets', $targets, __LINE__);
				endif;
			elseif (isset($_POST['update_uri'])) :
				$targets = $_POST['update_uri'];
				if (!is_array($targets)) :
					$targets = array($targets);
				endif;
				$update_set = $targets;
			endif;
		endif;
		return $update_set;
	}

	function display () {
		if (FeedWordPress::needs_upgrade()) :
			fwp_upgrade_page();
			return;
		endif;
		
		?>
		<?php
		$cont = true;
		$dispatcher = array(
			"feedfinder" => 'fwp_feedfinder_page',
			FWP_SYNDICATE_NEW => 'fwp_feedfinder_page',
			"switchfeed" => 'fwp_switchfeed_page',
			FWP_UNSUB_CHECKED => 'fwp_multidelete_page',
			FWP_DELETE_CHECKED => 'fwp_multidelete_page',
			'Unsubscribe' => 'fwp_multidelete_page',
			FWP_RESUB_CHECKED => 'fwp_multiundelete_page',
		);
		if (isset($_REQUEST['action']) and isset($dispatcher[$_REQUEST['action']])) :
			$method = $dispatcher[$_REQUEST['action']];
			$cont = call_user_func($method);
		endif;
		
		if ($cont):
		?>
		<style type="text/css">
			.heads-up {
				background-color: #d0d0d0;
				color: black;
				padding: 1.0em;
				margin: 0.5em 4.0em !important;
			}
			.update-form.with-donation {
				margin-right: 50%;
				min-height: 255px;
			}
			.donation-form, .donation-thanks {
				background-color: #ffffcc;
				text-align: left;
				padding: 0.5em 0.5em;
				border-left: thin dashed #777777;
				font-size: 70%;
				position: absolute;
				top: 0; bottom: 0; right: 0; left: auto;
				width: 50%;		
			}
			.donation-thanks {
				background-color: #ccffcc;
			}
			.donation-thanks .signature {
				text-align: right;
				font-style: italic;
			}
			.donation-form h4, .donation-thanks h4 {
				font-size: 10px;
				text-align: center;
				border-bottom: 1px solid #777777;
				margin: 0px;
			}
			.donation-form .donate  {
				text-align: center;
			}
			.donation-form .sod-off {
				padding-top: 0.5em;
				margin-top: 0.5em;
				border-top: thin solid #777777;
			}
			.feed-missing {
				background-color:#FFFFD0;
			}
			.unsubscribed tr {
				background-color: #FFE0E0;
			}
			.unsubscribed tr.alternate {
				background-color: #FFF0F0;
			}
			tr.feed-error {
				background-color: #FFFFD0;
			}
		</style>
		<?php
			$links = $this->sources('Y');
			$potential_updates = (!$this->show_inactive() and (count($this->sources('Y')) > 0));

			$this->open_sheet('Syndicated Sites');
			?>
			<div id="post-body">
			<?php
			if ($potential_updates
			or (count($this->updates_requested()) > 0)) :
				fwp_add_meta_box(
					/*id=*/ 'feedwordpress_update_box',
					/*title=*/ __('Update feeds now'),
					/*callback=*/ 'fwp_syndication_manage_page_update_box',
					/*page=*/ $this->meta_box_context(),
					/*context =*/ $this->meta_box_context()
				);
			endif;
			fwp_add_meta_box(
				/*id=*/ 'feedwordpress_feeds_box',
				/*title=*/ __('Syndicated sources'),
				/*callback=*/ 'fwp_syndication_manage_page_links_box',
				/*page=*/ $this->meta_box_context(),
				/*context =*/ $this->meta_box_context()
			);
			if (FeedWordPressCompatibility::test_version(0, FWP_SCHEMA_25)) :
				fwp_add_meta_box(
					/*id=*/ 'feedwordpress_add_feed_box',
					/*title=*/ 'Add a new syndicated source',
					/*callback=*/ 'fwp_syndication_manage_page_add_feed_box',
					/*page=*/ $this->meta_box_context(),
					/*context=*/ $this->meta_box_context()
				);
			endif;
					
			do_action('feedwordpress_admin_page_syndication_meta_boxes', $this);
		?>
			<div class="metabox-holder">		
			<?php
				fwp_do_meta_boxes($this->meta_box_context(), $this->meta_box_context(), $this);
			?>
			</div> <!-- class="metabox-holder" -->
			</div> <!-- id="post-body" -->
		
			<?php $this->close_sheet(/*dispatch=*/ NULL); ?>
		
			<div style="display: none">
			<div id="tags-input"></div> <!-- avoid JS error from WP 2.5 bug -->
			</div>
		<?php
		endif;
	} /* FeedWordPressSyndicationPage::display () */

	function bleg_thanks ($page, $box = NULL) {
		?>
		<div class="donation-thanks">
		<h4>Thank you!</h4>
		<p><strong>Thank you</strong> for your contribution to <a href="http://feedwordpress.radgeek.com/">FeedWordPress</a> development.
		Your generous gifts make ongoing support and development for
		FeedWordPress possible.</p>
		<p>If you have any questions about FeedWordPress, or if there
		is anything I can do to help make FeedWordPress more useful for
		you, please <a href="http://feedwordpress.radgeek.com/contact">contact me</a>
		and let me know what you're thinking about.</p>
		<p class="signature">&#8212;<a href="http://radgeek.com/">Charles Johnson</a>, Developer, <a href="http://feedwordpress.radgeek.com/">FeedWordPress</a>.</p>
		</div>
		<?php
	} /* FeedWordPressSyndicationPage::bleg_thanks () */

	function bleg_box ($page, $box = NULL) {
		?>
<div class="donation-form">
<h4>Keep FeedWordPress improving</h4>
<form action="https://www.paypal.com/cgi-bin/webscr" accept-charset="UTF-8" method="post"><div>
<p><a href="http://feedwordpress.radgeek.com/">FeedWordPress</a> makes syndication
simple and empowers you to stream content from all over the web into your
WordPress hub. That's got to be worth a few lattes. If you're finding FWP useful,
<a href="http://feedwordpress.radgeek.com/donate/">a modest gift</a>
is the best way to support steady progress on development, enhancements,
support, and documentation.</p>
<div class="donate">
<input type="hidden" name="business" value="commerce@radgeek.com"  />
<input type="hidden" name="cmd" value="_xclick"  />
<input type="hidden" name="item_name" value="FeedWordPress donation"  />
<input type="hidden" name="no_shipping" value="1"  />
<input type="hidden" name="return" value="<?php bloginfo('url'); ?>/wp-admin/admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>&amp;paid=yes"  />
<input type="hidden" name="currency_code" value="USD" />
<input type="hidden" name="notify_url" value="http://feedwordpress.radgeek.com/ipn/donation"  />
<input type="hidden" name="custom" value="1"  />
<input type="image" name="submit" src="https://www.paypal.com/en_GB/i/btn/btn_donate_SM.gif" alt="Donate through PayPal" />
</div>
</div></form>

<p>You can make a gift online (or
<a href="http://feedwordpress.radgeek.com/donation">set up an automatic
regular donation</a>) using an existing PayPal account or any major credit card.</p>

<div class="sod-off">
<form style="text-align: center" action="admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>" method="POST"><div>
<input class="button-primary" type="submit" name="maybe_later" value="Maybe Later" />
<input class="button-secondary" type="submit" name="go_away" value="Dismiss" />
</div></form>
</div>
</div> <!-- class="donation-form" -->
		<?php
	} /* FeedWordPressSyndicationPage::bleg_box () */

	/**
	 * Override the default display of a save-settings button and replace
	 * it with nothing.
	 */
	function interstitial () {
		/* NOOP */
	} /* FeedWordPressSyndicationPage::interstitial() */
} /* class FeedWordPressSyndicationPage */

function fwp_dashboard_update_if_requested ($object) {
	$update_set = $object->updates_requested();

	if (count($update_set) > 0) :
		shuffle($update_set); // randomize order for load balancing purposes...

		$feedwordpress = new FeedWordPress;
		add_action('feedwordpress_check_feed', 'update_feeds_mention');
		add_action('feedwordpress_check_feed_complete', 'update_feeds_finish', 10, 3);

		$crash_dt = (int) get_option('feedwordpress_update_time_limit');
		if ($crash_dt > 0) :
			$crash_ts = time() + $crash_dt;
		else :
			$crash_ts = NULL;
		endif;

		echo "<div class=\"update-results\">\n";
		echo "<ul>\n";
		$tdelta = NULL;
		foreach ($update_set as $uri) :
			if (!is_null($crash_ts) and (time() > $crash_ts)) :
				echo "<li><p><strong>Further updates postponed:</strong> update time limit of ".$crash_dt." second".(($crash_dt==1)?"":"s")." exceeded.</p></li>";
				break;
			endif;

			if ($uri == '*') : $uri = NULL; endif;
			$delta = $feedwordpress->update($uri, $crash_ts);
			if (!is_null($delta)) :
				if (is_null($tdelta)) :
					$tdelta = $delta;
				else :
					$tdelta['new'] += $delta['new'];
					$tdelta['updated'] += $delta['updated'];
				endif;
			else :
				echo "<li><p><strong>Error:</strong> There was a problem updating <a href=\"$uri\">$uri</a></p></li>\n";
			endif;
		endforeach;
		echo "</ul>\n";

		if (!is_null($tdelta)) :
			$mesg = array();
			if (isset($delta['new'])) : $mesg[] = ' '.$tdelta['new'].' new posts were syndicated'; endif;
			if (isset($delta['updated'])) : $mesg[] = ' '.$tdelta['updated'].' existing posts were updated'; endif;
			echo "<p>Update complete.".implode(' and', $mesg)."</p>";
			echo "\n"; flush();
		endif;
		echo "</div> <!-- class=\"updated\" -->\n";
	endif;
}

function fwp_syndication_manage_page_add_feed_box ($object = NULL, $box = NULL) {
	?>
	<form action="admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>" method="post">
	<div>
	<?php FeedWordPressCompatibility::stamp_nonce('feedwordpress_feeds'); ?>
	<label for="add-uri">Website or feed:</label>
	<input type="text" name="lookup" id="add-uri" value="URI" size="64" />
	<input type="hidden" name="action" value="feedfinder" />
	</div>
	<div class="submit"><input type="submit" value="<?php print FWP_SYNDICATE_NEW; ?>" /></div>
	</form>
	<?php
}

define('FEEDWORDPRESS_BLEG_MAYBE_LATER_OFFSET', (60 /*sec/min*/ * 60 /*min/hour*/ * 24 /*hour/day*/ * 31 /*days*/));
define('FEEDWORDPRESS_BLEG_ALREADY_PAID_OFFSET', (60 /*sec/min*/ * 60 /*min/hour*/ * 24 /*hour/day*/ * 183 /*days*/));
function fwp_syndication_manage_page_update_box ($object = NULL, $box = NULL) {
	$bleg_box_hidden = null;
	if (isset($_POST['maybe_later'])) :
		$bleg_box_hidden = time() + FEEDWORDPRESS_BLEG_MAYBE_LATER_OFFSET; 
	elseif (isset($_REQUEST['paid']) and $_REQUEST['paid'])  :
		$bleg_box_hidden = time() + FEEDWORDPRESS_BLEG_ALREADY_PAID_OFFSET; 
	elseif (isset($_POST['go_away'])) :
		$bleg_box_hidden = 'permanent';
	endif;

	if (!is_null($bleg_box_hidden)) :
		update_option('feedwordpress_bleg_box_hidden', $bleg_box_hidden);
	else :
		$bleg_box_hidden = get_option('feedwordpress_bleg_box_hidden');
	endif;
?>
	<?php
	$bleg_box_ready = (!$bleg_box_hidden or (is_numeric($bleg_box_hidden) and $bleg_box_hidden < time()));
	if (isset($_REQUEST['paid']) and $_REQUEST['paid']) :
		$object->bleg_thanks($subject, $box);
	elseif ($bleg_box_ready) :
		$object->bleg_box($object, $box);
	endif;
	?>

	<form
		action="admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>"
		method="POST"
		class="update-form<?php if ($bleg_box_ready) : ?> with-donation<?php endif; ?>"
	>
	<div><?php FeedWordPressCompatibility::stamp_nonce('feedwordpress_feeds'); ?></div>
	<p>Check currently scheduled feeds for new and updated posts.</p>

	<?php
	fwp_dashboard_update_if_requested($object);

	if (!get_option('feedwordpress_automatic_updates')) :
	?>
		<p class="heads-up"><strong>Note:</strong> Automatic updates are currently turned
		<strong>off</strong>. New posts from your feeds will not be syndicated
		until you manually check for them here. You can turn on automatic
		updates under <a href="admin.php?page=<?php echo FWP_FEEDS_PAGE_SLUG; ?>">Feed &amp; Update Settings<a></a>.</p>
	<?php 
	endif;
	?>

	<div class="submit"><?php if ($object->show_inactive()) : ?>
	<?php foreach ($object->updates_requested() as $req) : ?>
	<input type="hidden" name="update_uri[]" value="<?php print esc_html($req); ?>" />
	<?php endforeach; ?>
	<?php else : ?>
	<input type="hidden" name="update_uri" value="*" />
	<?php endif; ?>
	<input class="button-primary" type="submit" name="update" value="Update" /></div>
	
	<br style="clear: both" />
	</form>
<?php
} /* function fwp_syndication_manage_page_update_box () */

function fwp_syndication_manage_page_links_table_rows ($links, $visible = 'Y') {
	$subscribed = ('Y' == strtoupper($visible));
	if ($subscribed or (count($links) > 0)) :
	?>
	<table class="widefat<?php if (!$subscribed) : ?> unsubscribed<?php endif; ?>">
	<thead>
	<tr>
	<th class="check-column" scope="col"><input type="checkbox" /></th>
	<th scope="col"><?php _e('Name'); ?></th>
	<th scope="col"><?php _e('Feed'); ?></th>
	<th scope="col"><?php _e('Updated'); ?></th>
	</tr>
	</thead>

	<tbody>
<?php
		$alt_row = true; 
		if (count($links) > 0):
			foreach ($links as $link):
				$trClass = array();

				// Prep: Get last updated timestamp
				$sLink = new SyndicatedLink($link->link_id);
				if (!is_null($sLink->setting('update/last'))) :
					$lastUpdated = fwp_time_elapsed($sLink->setting('update/last'));
				else :
					$lastUpdated = __('None yet');
				endif;

				// Prep: get last error timestamp, if any
				if (is_null($sLink->setting('update/error'))) :
					$errorsSince = '';
				else :
					$trClass[] = 'feed-error';

					$theError = unserialize($sLink->setting('update/error'));
					
					$errorsSince = "<div class=\"returning-errors\">"
						."<p><strong>Returning errors</strong> since "
						.fwp_time_elapsed($theError['since'])
						."</p>"
						."<p>Most recent ("
						.fwp_time_elapsed($theError['ts'])
						."):<br/><code>"
						.implode("</code><br/><code>", $theError['object']->get_error_messages())
						."</code></p>"
						."</div>\n";
				endif;

				$nextUpdate = "<div style='font-style:italic;size:0.9em'>Ready for next update ";
				if (isset($sLink->settings['update/ttl']) and is_numeric($sLink->settings['update/ttl'])) :
					if (isset($sLink->settings['update/timed']) and $sLink->settings['update/timed']=='automatically') :
						$next = $sLink->settings['update/last'] + ((int) $sLink->settings['update/ttl'] * 60);
						$nextUpdate .= fwp_time_elapsed($next);
					else :
						$nextUpdate .= "every ".$sLink->settings['update/ttl']." minute".(($sLink->settings['update/ttl']!=1)?"s":"");
					endif;
				else:
					$nextUpdate .= "as soon as possible";
				endif;
				$nextUpdate .= "</div>";

				unset($sLink);
				
				$alt_row = !$alt_row;
				
				if ($alt_row) :
					$trClass[] = 'alternate';
				endif;
				?>
	<tr<?php echo ((count($trClass) > 0) ? ' class="'.implode(" ", $trClass).'"':''); ?>>
	<th class="check-column" scope="row"><input type="checkbox" name="link_ids[]" value="<?php echo $link->link_id; ?>" /></th>
				<?php
				$hrefPrefix = "admin.php?link_id={$link->link_id}&amp;page=";
				
				$caption = (
					(strlen($link->link_rss) > 0)
					? __('Switch Feed')
					: $caption=__('Find Feed')
				);
				?>
	<td>
	<strong><a href="<?php echo $hrefPrefix.FWP_FEEDS_PAGE_SLUG; ?>"><?php print esc_html($link->link_name); ?></a></strong>
	<div class="row-actions"><?php if ($subscribed) : ?>
	<div><strong>Settings &gt;</strong>
	<a href="<?php echo $hrefPrefix.FWP_FEEDS_PAGE_SLUG; ?>"><?php _e('Feed'); ?></a>
	| <a href="<?php echo $hrefPrefix.FWP_POSTS_PAGE_SLUG; ?>"><?php _e('Posts'); ?></a>
	| <a href="<?php echo $hrefPrefix.FWP_AUTHORS_PAGE_SLUG; ?>"><?php _e('Authors'); ?></a>
	| <a href="<?php echo $hrefPrefix.FWP_CATEGORIES_PAGE_SLUG; ?>"><?php print htmlspecialchars(__('Categories'.FEEDWORDPRESS_AND_TAGS)); ?></a></div>
	<?php endif; ?>

	<div><strong>Actions &gt;</strong>
	<?php if ($subscribed) : ?>
	<a href="<?php echo $hrefPrefix.FWP_SYNDICATION_PAGE_SLUG; ?>&amp;action=feedfinder"><?php echo $caption; ?></a>
	<?php else : ?>
	<a href="<?php echo $hrefPrefix.FWP_SYNDICATION_PAGE_SLUG; ?>&amp;action=<?php print FWP_RESUB_CHECKED; ?>"><?php _e('Re-subscribe'); ?></a>
	<?php endif; ?>
	| <a href="<?php echo $hrefPrefix.FWP_SYNDICATION_PAGE_SLUG; ?>&amp;action=Unsubscribe"><?php _e(($subscribed ? 'Unsubscribe' : 'Delete permanently')); ?></a>
	| <a href="<?php print esc_html($link->link_url); ?>"><?php _e('View')?></a></div>
	</div>
	</td>
				<?php if (strlen($link->link_rss) > 0): ?>
	<td><a href="<?php echo esc_html($link->link_rss); ?>"><?php echo esc_html(feedwordpress_display_url($link->link_rss, 32)); ?></a></td>
				<?php else: ?>
	<td class="feed-missing"><p><strong>no feed assigned</strong></p></td>
				<?php endif; ?>

	<td><?php print $lastUpdated; ?>
	<?php print $errorsSince; ?>
	<?php print $nextUpdate; ?>
	</td>
	</tr>
			<?php
			endforeach;
		else :
?>
<tr><td colspan="4"><p>There are no websites currently listed for syndication.</p></td></tr>
<?php
		endif;
?>
</tbody>
</table>
	<?php
	endif;
} /* function fwp_syndication_manage_page_links_table_rows () */

function fwp_syndication_manage_page_links_subsubsub ($sources, $showInactive) {
	$hrefPrefix = 'admin.php?page='.FWP_SYNDICATION_PAGE_SLUG;
	
	?>
	<ul class="subsubsub">
	<li><a <?php if (!$showInactive) : ?>class="current" <?php endif; ?>href="<?php print $hrefPrefix; ?>&amp;visibility=Y">Subscribed
	<span class="count">(<?php print count($sources['Y']); ?>)</span></a></li>
	<?php if ($showInactive or (count($sources['N']) > 0)) : ?>
	<li><a <?php if ($showInactive) : ?>class="current" <?php endif; ?>href="<?php print $hrefPrefix; ?>&amp;visibility=N">Inactive</a>
	<span class="count">(<?php print count($sources['N']); ?>)</span></a></li>
	<?php endif; ?>

	</ul> <!-- class="subsubsub" -->
	<?php
}

function fwp_syndication_manage_page_links_box ($object = NULL, $box = NULL) {
	$links = FeedWordPress::syndicated_links(array("hide_invisible" => false));
	$sources = $object->sources('*');

	$visibility = $object->visibility_toggle();
	$showInactive = $object->show_inactive();

	$hrefPrefix = 'admin.php?page='.FWP_SYNDICATION_PAGE_SLUG;
?>
	<form id="syndicated-links" action="<?php print $hrefPrefix; ?>&amp;visibility=<?php print $visibility; ?>" method="post">
	<div><?php FeedWordPressCompatibility::stamp_nonce('feedwordpress_feeds'); ?></div>
	<?php
	if (count($sources[$visibility]) > 0) :
		fwp_syndication_manage_page_links_subsubsub($sources, $showInactive);
	endif;

	if ($showInactive) : ?>
	<p style="clear: both; font-size: smaller; font-style: italic">FeedWordPress used to syndicate
	posts from these sources, but you have unsubscribed from them.</p>
	<?php
	endif;
	?>
	
	<div class="tablenav">
	<div class="alignright">
	<label for="add-uri">New source:</label>
	<input type="text" name="lookup" id="add-uri" value="Website or feed URI" />
	<?php FeedWordPressSettingsUI::magic_input_tip_js('add-uri'); ?>

	<input type="hidden" name="action" value="feedfinder" />
	<input type="submit" class="button-secondary" name="action" value="<?php print FWP_SYNDICATE_NEW; ?>" /></div>

<?php	if (count($sources[$visibility]) > 0) : ?>
	<div class="alignleft">
	<?php if ($showInactive) : ?>
	<input class="button-secondary" type="submit" name="action" value="<?php print FWP_RESUB_CHECKED; ?>" />
	<input class="button-secondary" type="submit" name="action" value="<?php print FWP_DELETE_CHECKED; ?>" />
	<?php else : ?>
	<input class="button-secondary" type="submit" name="action" value="<?php print FWP_UPDATE_CHECKED; ?>" />
	<input class="button-secondary delete" type="submit" name="action" value="<?php print FWP_UNSUB_CHECKED; ?>" />
	<?php endif ; ?>
	</div>

<?php	else : ?>
	<?php fwp_syndication_manage_page_links_subsubsub($sources, $showInactive); ?>
<?php 	endif; ?>

	<br class="clear" />
	</div>
	<br class="clear" />

	<?php
	fwp_syndication_manage_page_links_table_rows($sources[$visibility], $visibility);
	?>
	</form>
<?php
} /* function fwp_syndication_manage_page_links_box() */

function fwp_feedfinder_page () {
	global $post_source;
	
	$post_source = 'feedwordpress_feeds';
	
	// With action=feedfinder, this goes directly to the feedfinder page
	include_once(dirname(__FILE__) . '/feeds-page.php');
	return false;
} /* function fwp_feedfinder_page () */

function fwp_switchfeed_page () {
	global $wp_db_version;
	global $fwp_post;

	// If this is a POST, validate source and user credentials
	FeedWordPressCompatibility::validate_http_request(/*action=*/ 'feedwordpress_switchfeed', /*capability=*/ 'manage_links');

	$changed = false;
	if (!isset($fwp_post['Cancel'])):
		if (isset($fwp_post['save_link_id']) and ($fwp_post['save_link_id']=='*')) :
			$changed = true;
			$link_id = FeedWordPress::syndicate_link($fwp_post['feed_title'], $fwp_post['feed_link'], $fwp_post['feed']);
			if ($link_id):
				$existingLink = new SyndicatedLink($link_id);
			?>
<div class="updated"><p><a href="<?php print $fwp_post['feed_link']; ?>"><?php print esc_html($fwp_post['feed_title']); ?></a>
has been added as a contributing site, using the feed at
&lt;<a href="<?php print $fwp_post['feed']; ?>"><?php print esc_html($fwp_post['feed']); ?></a>&gt;.
| <a href="admin.php?page=<?php echo FWP_FEEDS_PAGE_SLUG; ?>&amp;link_id=<?php print $link_id; ?>">Configure settings</a>.</p></div>
<?php			else: ?>
<div class="updated"><p>There was a problem adding the feed. [SQL: <?php echo esc_html(mysql_error()); ?>]</p></div>
<?php			endif;
		elseif (isset($fwp_post['save_link_id'])):
			$existingLink = new SyndicatedLink($fwp_post['save_link_id']);
			$changed = $existingLink->set_uri($fwp_post['feed']);

			if ($changed):
				$home = $existingLink->homepage(/*from feed=*/ false);
				$name = $existingLink->name(/*from feed=*/ false);
				?> 
<div class="updated"><p>Feed for <a href="<?php echo esc_html($home); ?>"><?php echo esc_html($name); ?></a>
updated to &lt;<a href="<?php echo esc_html($fwp_post['feed']); ?>"><?php echo esc_html($fwp_post['feed']); ?></a>&gt;.</p></div>
				<?php
			endif;
		endif;
	endif;

	if (isset($existingLink)) :
		do_action('feedwordpress_admin_switchfeed', $fwp_post['feed'], $existingLink); 
	endif;
	
	if (!$changed) :
		?>
<div class="updated"><p>Nothing was changed.</p></div>
		<?php
	endif;
	return true; // Continue
}

function fwp_multiundelete_page () {
	// If this is a POST, validate source and user credentials
	FeedWordPressCompatibility::validate_http_request(/*action=*/ 'feedwordpress_feeds', /*capability=*/ 'manage_links');

	$link_ids = (isset($_REQUEST['link_ids']) ? $_REQUEST['link_ids'] : array());
	if (isset($_REQUEST['link_id'])) : array_push($link_ids, $_REQUEST['link_id']); endif;

	if (isset($GLOBALS['fwp_post']['confirm']) and $GLOBALS['fwp_post']['confirm']=='Undelete'):
		if (isset($GLOBALS['fwp_post']['link_action']) and is_array($GLOBALS['fwp_post']['link_action'])) :
			$actions = $GLOBALS['fwp_post']['link_action'];
		else :
			$actions = array();
		endif;

		$do_it = array(
			'unhide' => array(),
		);

		foreach ($actions as $link_id => $what) :
			$do_it[$what][] = $link_id;
		endforeach;

		$links = array();
		if (count($do_it['unhide']) > 0) :
			$links = get_bookmarks(array(
				'include' => implode(', ', $do_it['unhide']), 
				'hide_invisible' => 0, // to unhide, we need to get the links that aren't visible
			));
		endif;
		$errs = $success = array();
		foreach ($links as $linkdata) :
			$linkdata->link_visible = 'Y';
			$result = wp_update_link( (array) $linkdata );
			if (is_wp_error($result)) {
				$errs[] = get_error_message('db_update_error');
			} else {
				$success[] = 'resubscribing post: ' . $linkdata->link_id;
			}
		endforeach;
		
		if (count($success) > 0) :
			echo "<div class=\"updated\">\n";
			if (count($errs) > 0) :
				echo "There were some problems processing your ";
				echo "re-subscribe request. [SQL: ".implode('; ', $errs)."]";
			else :
				echo "Your re-subscribe request(s) have been processed.";
			endif;
			echo "</div>\n";
		endif;

		return true; // Continue on to Syndicated Sites listing
	else :
		$targets = array();
		if (is_array($link_ids) && count($link_ids) > 1) {
			$targets = get_bookmarks(array(
				'include' => implode(',', $link_ids),
				'hide_invisible' => 0,
			));
		}
		else if (count($link_ids) == 1) {
			$targets[] = get_bookmark(array_shift($link_ids));
		}
?>
<form action="admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>&visibility=Y" method="post">
<div class="wrap">
<?php FeedWordPressCompatibility::stamp_nonce('feedwordpress_feeds'); ?>
<input type="hidden" name="action" value="<?php print FWP_RESUB_CHECKED; ?>" />
<input type="hidden" name="confirm" value="Undelete" />

<h2>Re-subscribe to Syndicated Links:</h2>
<?php
	foreach ($targets as $cur_link) :
		$subscribed = ('Y' == strtoupper($cur_link->link_visible));
		$link_url = esc_html($cur_link->link_url);
		$link_name = esc_html($cur_link->link_name);
		$link_description = esc_html($cur_link->link_description);
		$link_rss = esc_html($cur_link->link_rss);
		
		if (!$subscribed) :
?>
<fieldset>
<legend><?php echo $link_name; ?></legend>
<table class="editform" width="100%" cellspacing="2" cellpadding="5">
<tr><th scope="row" width="20%"><?php _e('Feed URI:') ?></th>
<td width="80%"><a href="<?php echo $link_rss; ?>"><?php echo $link_rss; ?></a></td></tr>
<tr><th scope="row" width="20%"><?php _e('Short description:') ?></th>
<td width="80%"><?php echo $link_description; ?></span></td></tr>
<tr><th width="20%" scope="row"><?php _e('Homepage:') ?></th>
<td width="80%"><a href="<?php echo $link_url; ?>"><?php echo $link_url; ?></a></td></tr>
<tr style="vertical-align:top"><th width="20%" scope="row">Subscription <?php _e('Options') ?>:</th>
<td width="80%"><ul style="margin:0; padding: 0; list-style: none">
<li><input type="radio" id="unhide-<?php echo $cur_link->link_id; ?>"
name="link_action[<?php echo $cur_link->link_id; ?>]" value="unhide" checked="checked" />
<label for="unhide-<?php echo $cur_link->link_id; ?>">Turn back on the subscription
for this syndication source.</label></li>
<li><input type="radio" id="nothing-<?php echo $cur_link->link_id; ?>"
name="link_action[<?php echo $cur_link->link_id; ?>]" value="nothing" />
<label for="nothing-<?php echo $cur_link->link_id; ?>">Leave this feed as it is.
I changed my mind.</label></li>
</ul>
</table>
</fieldset>
<?php
		endif;
	endforeach;
?>

<div class="submit">
<input class="button-primary delete" type="submit" name="submit" value="<?php _e('Re-subscribe to selected feeds &raquo;') ?>" />
</div>
</div>
<?php
		return false; // Don't continue on to Syndicated Sites listing
	endif;
}

function fwp_multidelete_page () {
	// If this is a POST, validate source and user credentials
	FeedWordPressCompatibility::validate_http_request(/*action=*/ 'feedwordpress_feeds', /*capability=*/ 'manage_links');

	$link_ids = (isset($_REQUEST['link_ids']) ? $_REQUEST['link_ids'] : array());
	if (isset($_REQUEST['link_id'])) : array_push($link_ids, $_REQUEST['link_id']); endif;

	if (isset($GLOBALS['fwp_post']['confirm']) and $GLOBALS['fwp_post']['confirm']=='Delete'):
		if (isset($GLOBALS['fwp_post']['link_action']) and is_array($GLOBALS['fwp_post']['link_action'])) :
			$actions = $GLOBALS['fwp_post']['link_action'];
		else :
			$actions = array();
		endif;

		$do_it = array(
			'hide' => array(),
			'nuke' => array(),
			'delete' => array(),
		);

		foreach ($actions as $link_id => $what) :
			$do_it[$what][] = $link_id;
		endforeach;

		$alter = $errs = array();
		if (count($do_it['hide']) > 0) :
			$links = get_bookmarks(array(
				'include' => implode(',', $do_it['hide']),
			));
			if (is_array($links) && !empty($links)) {
				foreach ($links as $link) {
					$link->link_visible = 'N';
					$alter[] = 'hiding link: ' . $link->link_id;
					wp_update_link((array) $link);
				}
			}
		endif;

		if (count($do_it['nuke']) > 0) :
			foreach ($do_it['nuke'] as $nuke_id) {
				// Make a list of the items syndicated from this feed...
				$args = array(
					'posts_per_page' => -1,
					'numberposts' => 0,
					'meta_key' => 'syndication_feed_id',
					'meta_value' => $nuke_id,
				);
				$posts = get_posts($args);
				
				// ... and kill them all
				if (is_array($posts) && !empty($posts)) {
					foreach ($posts as $p) {
						$alter[] = 'deleting post: '.$p->ID;
						wp_delete_post($p->ID, true);
					}
				}
				
				// Then delete the link
				wp_delete_link( $nuke_id );
			}
		endif;

		if (count($do_it['delete']) > 0) :
			$deletem = "(".implode(', ', $do_it['delete']).")";
			foreach ($do_it['delete'] as $del_id) {
				// Make a list of the items syndicated from this feed...
				$args = array(
					'posts_per_page' => -1,
					'numberposts' => 0,
					'meta_key' => 'syndication_feed_id',
					'meta_value' => $del_id,
				);
				$posts = get_posts($args);
				
				// Make the items syndicated from this feed appear to be locally-authored
				if (is_array($posts) && !empty($posts)) {
					foreach ($posts as $p) {
						$alter[] = 'deleting postmeta for post: '.$p->ID;
						if (!delete_post_meta($p->ID, 'syndication_feed_id')) {
							$errs[] = 'Could not delete meta for post: '.$p->ID;
						}
					}
				}

				// Then delete the link
				wp_delete_link( $del_id );
			}
		endif;

		if (count($alter) > 0) :
			echo "<div class=\"updated\">\n";
			if (count($errs) > 0) :
				echo "There were some problems processing your ";
				echo "unsubscribe request. [Errors: ".implode('; ', $errs)."]";
			else :
				echo "Your unsubscribe request(s) have been processed.";
			endif;
			echo "</div>\n";
		endif;

		return true; // Continue on to Syndicated Sites listing
	else :
		if ( count($link_ids) > 0 ) {
			$targets = get_bookmarks(array(
				'include' => implode(',', $link_ids),
				'hide_invisible' => 0
			));
		} else {
			$targets = array();
		}
?>
<form action="admin.php?page=<?php echo FWP_SYNDICATION_PAGE_SLUG; ?>" method="post">
<div class="wrap">
<?php FeedWordPressCompatibility::stamp_nonce('feedwordpress_feeds'); ?>
<input type="hidden" name="action" value="Unsubscribe" />
<input type="hidden" name="confirm" value="Delete" />

<h2>Unsubscribe from Syndicated Links:</h2>
<?php	foreach ($targets as $link) :
		$subscribed = ('Y' == strtoupper($link->link_visible));
		$link_url = esc_html($link->link_url);
		$link_name = esc_html($link->link_name);
		$link_description = esc_html($link->link_description);
		$link_rss = esc_html($link->link_rss);
?>
<fieldset>
<legend><?php echo $link_name; ?></legend>
<table class="editform" width="100%" cellspacing="2" cellpadding="5">
<tr><th scope="row" width="20%"><?php _e('Feed URI:') ?></th>
<td width="80%"><a href="<?php echo $link_rss; ?>"><?php echo $link_rss; ?></a></td></tr>
<tr><th scope="row" width="20%"><?php _e('Short description:') ?></th>
<td width="80%"><?php echo $link_description; ?></span></td></tr>
<tr><th width="20%" scope="row"><?php _e('Homepage:') ?></th>
<td width="80%"><a href="<?php echo $link_url; ?>"><?php echo $link_url; ?></a></td></tr>
<tr style="vertical-align:top"><th width="20%" scope="row">Subscription <?php _e('Options') ?>:</th>
<td width="80%"><ul style="margin:0; padding: 0; list-style: none">
<?php if ($subscribed) : ?>
<li><input type="radio" id="hide-<?php echo $link->link_id; ?>"
name="link_action[<?php echo $link->link_id; ?>]" value="hide" checked="checked" />
<label for="hide-<?php echo $link->link_id; ?>">Turn off the subscription for this
syndicated link<br/><span style="font-size:smaller">(Keep the feed information
and all the posts from this feed in the database, but don't syndicate any
new posts from the feed.)</span></label></li>
<?php endif; ?>
<li><input type="radio" id="nuke-<?php echo $link->link_id; ?>"<?php if (!$subscribed) : ?> checked="checked"<?php endif; ?>
name="link_action[<?php echo $link->link_id; ?>]" value="nuke" />
<label for="nuke-<?php echo $link->link_id; ?>">Delete this syndicated link and all the
posts that were syndicated from it</label></li>
<li><input type="radio" id="delete-<?php echo $link->link_id; ?>"
name="link_action[<?php echo $link->link_id; ?>]" value="delete" />
<label for="delete-<?php echo $link->link_id; ?>">Delete this syndicated link, but
<em>keep</em> posts that were syndicated from it (as if they were authored
locally).</label></li>
<li><input type="radio" id="nothing-<?php echo $link->link_id; ?>"
name="link_action[<?php echo $link->link_id; ?>]" value="nothing" />
<label for="nothing-<?php echo $link->link_id; ?>">Keep this feed as it is. I changed
my mind.</label></li>
</ul>
</table>
</fieldset>
<?php	endforeach; ?>

<div class="submit">
<input class="delete" type="submit" name="submit" value="<?php _e('Unsubscribe from selected feeds &raquo;') ?>" />
</div>
</div>
<?php
		return false; // Don't continue on to Syndicated Sites listing
	endif;
}

	$syndicationPage = new FeedWordPressSyndicationPage;
	$syndicationPage->display();

