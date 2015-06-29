<?php
class UpdatedPostsControl {
	var $page;
	function UpdatedPostsControl ($page) {
		$this->page =& $page;
	} /* UpdatedPostsControl constructor */

	function display () {
		$setting = array(
			'yes' => 'leave unmodified',
			'no' => 'update to match',
		);

		$global_freeze_updates = get_option('feedwordpress_freeze_updates', 'no');		
		if ($this->page->for_feed_settings()) :
			$aFeed = 'this feed';
			$freeze_updates = $this->page->link->setting('freeze updates', NULL, 'default');
		else :
			$aFeed = 'a syndicated feed';
			$freeze_updates = $global_freeze_updates;
		endif;
	?>
		<tr>
		<th scope="row"><?php _e('Updated posts:') ?></th>
		<td><p>When <?php print $aFeed; ?> includes updated content for
		a post that was already syndicated, should the syndicated copy
		of the post be updated to match the revised version?</p>
		<ul>
		<?php if ($this->page->for_feed_settings()) : ?>
		<li><label><input type="radio" name="freeze_updates" value="default" <?php print ($freeze_updates=='default') ? 'checked="checked"':'' ?> /> Use the <a href="admin.php?page=<?php echo FWP_POSTS_PAGE_SLUG; ?>">site-wide setting</a> (currently: <strong><?php print $setting[$global_freeze_updates]; ?></strong>)</label></li>
		<?php endif; ?>
		<li><label><input type="radio" name="freeze_updates" value="no" <?php print ($freeze_updates!='yes' and $freeze_updates!='default') ? 'checked="checked"':'' ?> /> Yes, update the syndicated copy to match</label></li>
		<li><label><input type="radio" name="freeze_updates" value="yes" <?php print ($freeze_updates=='yes') ? 'checked="checked"':'' ?>  /> No, leave the syndicated copy unmodified</label></li>
		</ul></td>
		</tr>
	<?php		
	} /* UpdatedPostsControl::display() */
	
	function accept_POST ($post) {
		if ($this->page->for_feed_settings()) :
			if (isset($post['freeze_updates'])) :
				$this->page->link->settings['freeze updates'] = $post['freeze_updates'];
			endif;
		else :
			// Updated posts
			if (isset($post['freeze_updates'])) :
				update_option('feedwordpress_freeze_updates', $post['freeze_updates']);
			endif;
		endif;
	} /* UpdatedPostsControl::accept_POST() */
} /* class UpdatedPostsControl */


