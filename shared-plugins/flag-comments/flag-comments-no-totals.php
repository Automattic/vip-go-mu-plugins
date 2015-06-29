<?php
/*
Plugin Name: Flag Comments: No Total Count (For Better Performance)
Plugin URI: http://watershedstudio.com/portfolio/software-development/flag-comments/
Description: Flag comments for moderator action.
Author: Watershed Studio, LLC
Author URI: http://watershedstudio.com/
Version: 1.7
*/

class Flag_Comments {

	// default cap necessary to flag a comment
	var $flag_cap = 'read';
	var $flag_comments_flag_markup;
	var $flag_comments_flagged_markup;
	var $nonce_key;
	var $throttle_count = 10; // $throttle_count is the number of flags to throttle ...
	var $throttle_time = 14400; // ... in $throttle_time seconds.
	var $throttle_time_floor;

	function Flag_Comments() {
		$this->__construct();
	}

	function __construct() {
		add_action('admin_head', array(&$this, 'admin_head'));
		add_action('admin_menu', array(&$this, 'add_admin_menu'));
		add_action('flag_comment_link', array(&$this, 'flag_comment_link'));
		add_action('init', array(&$this, 'init'));
		add_action('wp_ajax_unflagcomment', array(&$this, 'unflagcomment'));
		add_action('wp_head', array(&$this, 'header'));
		add_action('wp_set_comment_status', array(&$this, 'moderated_comment'), 10, 2);

		// set the capability one needs to flag a comment, either from db or default
		$flag_cap = get_option('flag_comments_cap');
		$this->flag_cap = $flag_cap;

		// set the markup for the flag comment link
		$flag_comments_flag_markup = get_option('flag_comments_flag_markup');
		$flag_comments_flagged_markup = get_option('flag_comments_flagged_markup');
		$this->flag_comments_flag_markup = ( empty( $flag_comments_flag_markup ) ) ? '<a href="%1$s" onclick="return false;">' . $this->__('Flag this comment') . '</a>' : $flag_comments_flag_markup;
		$this->flag_comments_flagged_markup = ( empty( $flag_comments_flagged_markup ) ) ? $this->__('Comment already flagged')  : $flag_comments_flagged_markup;
		$this->flag_comments_throttle_count = ( 1 > (int) get_option('flag_comments_throttle_count') ) ? 5 : (int) get_option('flag_comments_throttle_count'); 
		$this->flag_comments_throttle_minutes = ( 1 > (int) get_option('flag_comments_throttle_minutes') ) ? 1 : (int) get_option('flag_comments_throttle_minutes');
		$this->nonce_key = substr(md5(get_bloginfo('siteurl')), -12, 10);

		$this->throttle_time = 60 * (int) $this->flag_comments_throttle_minutes;
		$this->throttle_time_floor = time() - $this->throttle_time;
	}

	function __($text = '') {
		return __($text, 'flag-comments-plugin');
	}

	function _e($text = '') {
		echo $this->__($text);
	}

	function check_for_flagged_comment() {
		if ( isset( $_REQUEST['flagged-comment'] ) ) :
			check_admin_referer('flag_comment-flag' .  $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
			$comment_id = (int) $_REQUEST['flagged-comment-id'];
			if ( ! empty( $comment_id ) ) {
				$this->flag_comment($comment_id);
				$comment_data = get_comment($comment_id);
				$link = get_permalink( $comment_data->comment_post_ID ) . '#comment-' . $comment_id;
				wp_redirect($link);
				die();
			}
		endif;
	}

	function add_admin_menu() {
		//$flagged_count = $this->get_flagged_comment_count();
		add_options_page( $this->__('Flag Comments'), $this->__('Flag Comments'), 'edit_posts', 'flag-comments', array(&$this, 'menu'));
		$flagged_manage = add_submenu_page( 'edit-comments.php', sprintf($this->__('Flagged Comments')), sprintf($this->__('Flagged Comments')), 'edit_posts', 'flagged-comments', array(&$this, 'manage'));
		add_action('load-' . $flagged_manage, create_function('','
			wp_enqueue_script("admin-comments");
			wp_enqueue_script("admin-forms");
			wp_enqueue_script("wp-ajax");'
		));
	}

	function admin_head() {
		?><script type="text/javascript">
		// <![CDATA[
			jQuery(function(j) {
				j('a.unflag-comment').click(
					function(e) {
						var url = j(this).attr('href');
						var data = {commentID: j(this).attr('id').replace('unflag-comment-id-',''), ajax: 1}
						j.post(url, data, function(resp) {
							if ( -1 != resp.search(/tr#comment-\d*/) ) {
								j(resp).animate( { backgroundColor: '#e7e7d3' }, 'fast').fadeOut('fast');
							}
						});
						return false;
					}
				);
				j('#check-all-wrap').html('<input type="checkbox" id="check-all-checkbox" />');
				j('#check-all-checkbox').click(function(e) {
					j('#the-comment-list input[name="unflag-comments[]"]').each(function(i) {
						this.checked = ! this.checked;
					});
				});
			});
		// ]]>
		</script><?php
	}

	/**
	 * Delete *all* flags before $timestamp
	 * @param int $timestamp The time in UNIX epoch before which all flags should be deleted.
	 */
	function delete_old_flags($timestamp = 0) {
		$timestamp = ( 0 == $timestamp ) ? $this->throttle_time_floor : $timestamp;
		// actually, the flags can't be any older than a week, otherwise the db could get overloaded
		$one_week_ago = time() - 604800;
		$this->flag_comment_timestamp = ( $one_week_ago > $timestamp ) ? $one_week_ago : $timestamp;

		// this filter is a necessary hack to get the right range of timestamps
		add_filter('list_terms_exclusions', array(&$this, 'delete_old_flags_filter'));
		wp_cache_delete('get_terms', 'terms');
		$delete_terms = get_terms('comment_flag_time', array('fields' => 'ids'));
		remove_filter('list_terms_exclusions', array(&$this, 'delete_old_flags_filter'));
		foreach( (array) $delete_terms as $term_id ) {
			wp_delete_term( $term_id, 'comment_flag_time');
		}
		
	}
		function delete_old_flags_filter($w = '') {
			return " $w AND t.name < $this->flag_comment_timestamp";
		}

	function flag_comment($comment_id = 0) {
		global $wpdb;
		$user = wp_get_current_user();
		$ip_address = trim($_SERVER['REMOTE_ADDR']);

		// only flag if this user hasn't already flagged the comment
		$already = $this->get_user_flag_status($comment_id, $user->ID);
		// if $approvals is not empty, then this comment has already been approved and so cannot be re-flagged
		$approvals = wp_get_object_terms($comment_id, 'comment_flag_approved', array('fields' => 'ids'));

		if ( empty( $approvals ) && empty( $already ) && ! $this->ip_throttled($ip_address) ) :
			$this->record_ip_flagging($ip_address, $comment_id);
			$flag_count = $this->get_comment_flags($comment_id);
			$flag_count++;
			$wpdb->query($wpdb->prepare("UPDATE $wpdb->comments SET comment_karma = %d WHERE comment_ID = %d", $flag_count, $comment_id));
			// if comment at threshold moderate it
			if ( $this->get_threshold() == $flag_count ) {
				$this->moderate($comment_id);
			}
		endif;
		// sets the user's flag status, even if the comment not really flagged (for user feedback)
		$this->set_user_flag_status( $comment_id, $user->ID );
		
		// delete old flags
		add_action('shutdown', array(&$this, 'delete_old_flags'));
	}

	function get_comment_flags($comment_id = 0) {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare("SELECT comment_karma FROM $wpdb->comments WHERE comment_ID = %d LIMIT 1", $comment_id));
	}

	/* 
	 * The link for flagging comments 
	 */
	function flag_comment_link($comment_id = 0) {
		$comment_id = ( empty( $comment_id ) ) ? get_comment_ID() : $comment_id;
		if ( empty( $comment_id ) ) {
			return false;
		}
		$comment_data = get_commentdata($comment_id);
		$user = wp_get_current_user();

		// check that the user can vote
		if ( ! empty( $this->flag_cap ) && ! current_user_can($this->flag_cap) ) {
			return false;
		} 
		$already = $this->get_user_flag_status($comment_id, $user->ID);
		// if already flagged
		if ( ! empty( $already ) ) {
			$link = $this->flag_comments_flagged_markup;
		} else {
			$link = add_query_arg(array('_wpnonce' => $this->nonce_key, 'flagged-comment' => 1, 'flagged-comment-id' => $comment_id), get_permalink( $comment_data->comment_post_ID )) . '#comment-' . $comment_id;
			$link = sprintf($this->flag_comments_flag_markup, $link);
		}
		echo '<div class="flag-comment">' . $link . '</div>';
	}

	function get_threshold() {
		$th = (int) get_option('flag_comments_threshold');
		$th = ( empty( $th ) ) ? 5 : $th;
		return $th;
	}

	function header() {
		if ( is_single() ) {
			?><script type="text/javascript">
			// <![CDATA[
			(function () {
				var aE = function( o, t, f ) {
					if (o.addEventListener)
						o.addEventListener(t, f, false);
					else if (o.attachEvent) 
						o.attachEvent('on' + t, f);
				}		

				var confirmSubmit = function() {
					if ( confirm('<?php $this->_e('Are you sure that you want to flag this comment?') ?>') ) {
						return true;
					}
					else 
						return false;
				}

				var init = function() {
					var links = document.getElementsByTagName('a');
					var nonce = '<?php echo wp_create_nonce('flag_comment-flag' . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']); ?>';
					var nonce_key = '<?php echo $this->nonce_key ?>';
					var re = new RegExp(nonce_key, 'gi');
					for ( var i = 0; i < links.length; i++ ) {
						var href = links[i].getAttribute('href');
						// if it's a flagging link
						if ( re.exec(href) ) {
							// replace with correct nonce
							links[i].setAttribute('href', href.replace(re, nonce));
							links[i].onclick = confirmSubmit;
						}
					}
				}



				aE(window, 'load', init);
			}).call(this);
			//]]>
			</script><?php
		}
	}

	function init() {
		/**
		 * Register taxonomy. Flags are kept as taxonomy data.
		 * Basically, each IP address doing the flagging is kept as a comment_flag_ip taxonomy, with each flag event from that IP a comment_flag_time taxonomy term
		 */
		// the parent IP address
		register_taxonomy( 'comment_flag_ip', 'comment', array('hierarchical' => true, 'rewrite' => false, 'query_var' => false) );
		// the child flagging times
		register_taxonomy( 'comment_flag_time', 'comment', array('hierarchical' => false, 'update_count_callback' => array(&$this, 'update_ip_flag_count'), 'rewrite' => false, 'query_var' => false) );
		
		// the approved comments (cannot be re-flagged)
		register_taxonomy( 'comment_flag_approved', 'comment', array('hierarchical' => false, 'rewrite' => false, 'query_var' => false) );


		$this->check_for_flagged_comment();

		if ( is_admin() && isset($_REQUEST['unflagit']) ) {
			check_admin_referer('bulk-comments', 'bulk-comments-nonce');
			foreach( (array) $_POST['unflag-comments'] as $comment_id ) {
				$this->unflagcomment($comment_id, false);
			}
		} elseif ( is_admin() && 'unflagcomment' == $_REQUEST['action'] ) {
			do_action('wp_ajax_unflagcomment');
		}

	}

	function get_comment_row( $comment_id, $comment_status, $checkbox = true ) {
		global $comment, $post;
		$comment = get_comment( $comment_id );
		$post = get_post($comment->comment_post_ID);
		$authordata = get_userdata($post->post_author);

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$post_link = sprintf('<a href="%1$s">%2$s</a>', get_comment_link(), get_the_title($comment->comment_post_ID));
			$edit_link = sprintf('<a class="row-title" href="%1$s" title="%2$s">%3$s</a>',
				add_query_arg(array('c' => $comment->comment_ID, 'page' => 'flagged-comments'), get_option('siteurl') . '/wp-admin/'),
				$this->__('Edit comment'),
				get_comment_author());
		} else {
			$post_link = get_the_title($comment->comment_post_ID);
			$edit_link = get_comment_author();
		}
		
		$author_url = get_comment_author_url();
		if ( 'http://' == $author_url )
			$author_url = '';
		$author_url_display = $author_url;
		if ( strlen($author_url_display) > 50 )
			$author_url_display = substr($author_url_display, 0, 49) . '...';

		$ptime = date('G', strtotime( $comment->comment_date ) );
		if ( ( abs(time() - $ptime) ) < 86400 )
			$ptime = sprintf( $this->__('%s ago'), human_time_diff( $ptime ) );
		else
			$ptime = mysql2date($this->__('Y/m/d \a\t g:i A'), $comment->comment_date );

		$url_args = array(
			'c' => $comment->comment_ID,
			'p' => $comment->comment_post_ID,
			'page' => 'flagged-comments',
		);
		$base_url = get_option('siteurl') . '/wp-admin/comment.php';
		$delete_url 	= add_query_arg( array_merge($url_args, 
			array('action' => 'deletecomment', '_wpnonce' => wp_create_nonce( "delete-comment_$comment->comment_ID" )) ), $base_url); 
		$approve_url 	= add_query_arg( array_merge($url_args, 
			array('action' => 'approvecomment', '_wpnonce' => wp_create_nonce( "approve-comment_$comment->comment_ID" )) ), $base_url); 
		$unapprove_url 	= add_query_arg( array_merge($url_args, 
			array('action' => 'unapprovecomment', '_wpnonce' => wp_create_nonce( "unapprove-comment_$comment->comment_ID" )) ), $base_url); 
		$spam_url 	=  add_query_arg('dt', 'spam', $delete_url);
		$unflag_url 	= add_query_arg( array_merge($url_args, 
			array('action' => 'unflagcomment', '_wpnonce' => wp_create_nonce( "unflag-comment_$comment->comment_ID" )) ), get_option('siteurl') . '/wp-admin/edit-comments.php'); 
		?>
		<tr id="comment-<?php echo $comment->comment_ID; ?>" class='unapproved'>
			<?php if ( $checkbox ) : ?>
				<td class="check-column"><?php if ( current_user_can('edit_post', $comment->comment_post_ID) ) : ?>
					<input type="checkbox" name="unflag-comments[]" value="<?php comment_ID(); ?>" />
				<?php endif; ?></td>
			<?php endif; ?>
			<td class="comment">
			<p class="comment-author"><strong><?php echo $edit_link; ?></strong><br />
				<?php if ( !empty($author_url) ) : ?>
					<a href="<?php echo $author_url ?>"><?php echo $author_url_display; ?></a> |
				<?php endif; ?>
				<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
					<?php if ( !empty($comment->comment_author_email) ): ?>
						<?php comment_author_email_link() ?> |
					<?php endif; ?>
					<a href="edit-comments.php?s=<?php comment_author_IP() ?>&amp;mode=detail"><?php comment_author_IP() ?></a>
				<?php endif; //current_user_can?>    
			</p>
			<?php comment_text(); ?>
			<p><?php printf($this->__('From %1$s, %2$s'), $post_link, $ptime) ?></p>
			</td>
			<td><?php comment_date($this->__('Y/m/d')); ?></td>
			<td class="action-links">
			<?php

			$actions = array();
			$url = '<a href="%1$s" class="%5$s:the-comment-list:comment-' . get_comment_ID() . ':%2$s%3$s" title="%4$s"%6$s>%4$s</a>';
			$actions['unflag'] = sprintf($url, $unflag_url, '', '', $this->__('Un-flag'), 'unflag-comment ', ' id="unflag-comment-id-' . get_comment_ID() . '"') . ' | ';

			if ( 'moderated' == $comment_status ) {
				$actions['approve'] = sprintf($url, $approve_url, 'e7e7d3', ':action=dim-comment', $this->__('Approve'), 'dim', '') . ' | ';
			} elseif ( 'approved' == $comment_status ) {
				$actions['unapprove'] = sprintf($url, $unapprove_url, 'e7e7d3', ':action=dim-comment', $this->__('Unapprove'), 'dim', '') . ' | ';
			}
			$actions['spam'] = sprintf($url, $spam_url, '', ':spam=1', $this->__('Spam'), 'delete', '') . ' | ';
			$actions['delete'] = sprintf($url, $delete_url, '', ' delete', $this->__('Delete'), 'delete', '');
			if ( current_user_can('edit_post', $comment->comment_post_ID) ) {
				foreach ( $actions as $action => $link ) {
						echo "<span class='$action'>$link</span>";
					}
			}
			?>
			</td>
		</tr>
		<?php
	}

	function get_flagged_comment_count() {
		global $wpdb;
		$threshold = $this->get_threshold();
		$count = (int) $wpdb->get_var("SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_karma >= $threshold");
		return $count;
	}

	function get_flagged_comments($start = 0, $num = 20) {
		global $wpdb;
		$threshold = $this->get_threshold();
		return $wpdb->get_results( "SELECT * FROM $wpdb->comments USE INDEX (comment_date_gmt) WHERE comment_karma >= $threshold ORDER BY comment_date_gmt DESC LIMIT $start, $num" );
	}

	function manage() {
		$comments = $this->get_flagged_comments( 0, 25 );
		?>
		<div class="wrap">
		<h2><?php $this->_e('Flagged Comments'); ?></h2>
		<form id="comments-form" action="" method="post">

		<div class="tablenav">

		<div class="alignleft">
			<input type="submit" value="<?php $this->_e('Un-Flag Checked'); ?>" name="approveit" class="button-secondary" />
			<input type="hidden" value="1" name="unflagit" id="unflagit" />
			<?php do_action('manage_comments_nav', $comment_status); ?>
			<?php wp_nonce_field('bulk-comments', 'bulk-comments-nonce'); ?>
			</div>
			<br class="clear" />
		</div>

		<br class="clear" />
		<?php
		if ($comments) :
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th scope="col" class="check-column" id="check-all-wrap"></th>
					<th scope="col"><?php $this->_e('Comment') ?></th>
					<th scope="col"><?php $this->_e('Date') ?></th>
					<th scope="col" class="action-links"><?php _e('Actions') ?></th>
				</tr>
			</thead>
		<tbody id="the-comment-list" class="list:comment">
		<?php
			foreach ($comments as $comment)
				$this->get_comment_row( $comment->comment_ID, $comment_status );
		?>
		</tbody>
		</table>

		</form>
		<?php 
		endif; // if $comments

	}

	function menu() {
		$msg = '';
		if ( isset( $_POST['flag-comments-settings'] ) ) {
			check_admin_referer('flag_comments-settings' .  $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);	
			if ( update_option('flag_comments_threshold', (int) $_POST['flag-comments-threshold']) ) {
				$msg .= '<p>' . $this->__('Threshold saved.') . '</p>';
			}
			if ( update_option('flag_comments_cap', sanitize_user((string) $_POST['flag-comments-caps'])) ) {
				$msg .= '<p>' . $this->__('Flagging permissions capability saved.') . '</p>';
			}
			if ( update_option('flag_comments_flag_markup', trim(stripslashes($_POST['flag-comments-flag-markup']))) ) {
				$msg .= '<p>' . $this->__('Flagging markup saved.') . '</p>';
			}
			if ( update_option('flag_comments_flagged_markup', trim(stripslashes($_POST['flag-comments-flagged-markup']))) ) {
				$msg .= '<p>' . $this->__('Flagged comment markup saved.') . '</p>';
			}
			if ( update_option('flag_comments_throttle_count', (int) $_POST['flag-comments-throttle-count']) ) {
				$msg .= '<p>' . $this->__('Flagging throttle count saved.') . '</p>';
			}
			if ( update_option('flag_comments_throttle_minutes', (int) $_POST['flag-comments-throttle-minutes']) ) {
				$msg .= '<p>' . $this->__('Flagging throttle minutes saved.') . '</p>';
			}
			$this->flag_cap = get_option('flag_comments_cap');
			$this->flag_comments_flag_markup = get_option('flag_comments_flag_markup');
			$this->flag_comments_flagged_markup = get_option('flag_comments_flagged_markup');
			$this->flag_comments_throttle_count = (int) get_option('flag_comments_throttle_count');
			$this->flag_comments_throttle_minutes = (int) get_option('flag_comments_throttle_minutes');

			if ( empty( $msg ) ) {
				$msg .= '<p>' . $this->__('No changes made.') . '</p>';
			}
		}
		if ( ! empty( $msg ) ) {
			?>
			<div id="message" class="updated fade">
				<?php echo $msg ?>
			</div>
			<?php
		}
		?>
		<div class="wrap">
			<h2><?php $this->_e('Flag Comments Settings') ?></h2>
			<form method="post" action="">
			<input type="hidden" value="1" name="flag-comments-settings" id="flag-comments-settings" />
			<?php wp_nonce_field('flag_comments-settings' .  $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']); ?>
			<fieldset class="options"><legend><?php $this->_e('Flag Threshold') ?></legend>
			<label for="flag-comments-threshold"> <?php $this->_e('Enter the number of flags necessary for a comment to become flagged:') ?>
				<input size="5" type="text" value="<?php echo $this->get_threshold() ?>" name="flag-comments-threshold" id="flag-comments-threshold" />
			</label>
			</fieldset>

			<fieldset class="options"><legend><?php $this->_e('Flagging Permissions') ?></legend>
			<label for="flag-comments-caps"> <p><?php printf($this->__('Enter the minimum <a href="%s">WordPress user capability</a> someone must have to flag a comment, or leave this field blank to allow everyone to flag a comment.  For example, to require that users be at least subscribers to flag comments, enter "read."'), 'http://codex.wordpress.org/Roles_and_Capabilities') ?></p>
				<input size="20" type="text" value="<?php echo $this->flag_cap ?>" name="flag-comments-caps" id="flag-comments-caps" />
			</label>
			</fieldset>

			<fieldset class="options"><legend><?php $this->_e('Flagging Throttling') ?></legend>
			<p><?php printf($this->__('Prevent users from a single IP address from flagging more than %1$s comments in %2$s minutes.'),
				'<input size="10" type="text" value="' . $this->flag_comments_throttle_count . '" name="flag-comments-throttle-count" id="flag-comments-throttle-count" />',
				'<input size="10" type="text" value="' . $this->flag_comments_throttle_minutes . '" name="flag-comments-throttle-minutes" id="flag-comments-throttle-minutes" />'
			); ?></p>
			</fieldset>

			<fieldset class="options"><legend><?php $this->_e('Comment Flagging Markup') ?></legend>
			<label for="flag-comments-flag-markup"> <p><?php $this->_e('Enter the markup for flagging a comment. <code>&#37;1&#36;s</code> indicates the voting link.') ?></p></label>
				<textarea name="flag-comments-flag-markup" id="flag-comments-flag-markup" cols="60" rows="10"><?php echo htmlentities( trim( stripslashes( $this->flag_comments_flag_markup ) ) ); ?></textarea>
			<label for="flag-comments-flagged-markup"> <p><?php $this->_e('Enter the markup for an already-flagged comment.') ?></p></label>
				<textarea name="flag-comments-flagged-markup" id="flag-comments-flagged-markup" cols="60" rows="10"><?php echo htmlentities( trim( $this->flag_comments_flagged_markup ) ); ?></textarea>

			<p class="submit"><input type="submit" name="submit" value="<?php $this->_e('Save &raquo;') ?>" /></p>
			</form>

		</div>
		<?php
	}

	function moderate($comment_id = 0) {
		global $wpdb;
		if ( empty( $comment_id ) ) {
			return false;
		}
		// put comment in moderation queue
		$query = $wpdb->prepare("UPDATE $wpdb->comments SET comment_approved = '0' WHERE comment_ID = %d", $comment_id);
		$wpdb->query($query);
		// recount comment total
		$comment_data = get_comment($comment_id);
		
		wp_update_comment_count($comment_data->comment_post_ID);
		// email admin about comment
		$this->notify($comment_id);
	}

	function moderated_comment($comment_id = 0, $comment_status = 0) {
		global $wpdb;
		if ( empty( $comment_id ) ) 
			return false;
		if ( 'approve' == $comment_status ) {
			$wpdb->query(sprintf("DELETE FROM $wpdb->usermeta WHERE meta_key = '%sflaggedcomment%d'", $wpdb->prefix, $comment_id)); 
			$wpdb->query(sprintf("UPDATE $wpdb->comments SET comment_karma = '0' WHERE comment_ID = '%d'", $comment_id));

			// delete flag records
			$flags = wp_get_object_terms($comment_id, 'comment_flag_time', array('fields' => 'ids'));
			$parent_ips = array();
			foreach( (array) $flags as $term_id ) {
				$termdata = get_term($term_id, 'comment_flag_time');
				$parent_ips[] = $termdata->parent;
				wp_delete_term( $term_id, 'comment_flag_time');
			}
			$parent_ips = array_unique($parent_ips);
			
			// see if there are any child flags remainig for the parents' ip addresses
			foreach( (array) $parent_ips as $parent_term_id ) {
				$child_flags = get_terms('comment_flag_time', array('child_of' => $parent_term_id));
				// if no child flags, delete the parent
				if ( empty($child_flags) ) {
					wp_delete_term( $parent_term_id, 'comment_flag_ip');
				}
			}

			// let's make sure that we're dealing only with previously flagged comments
			if ( ! empty( $flags ) ) {
				// add it to the list of comments already approved
				$approving_user = wp_get_current_user();
				$user = ( ! empty( $approving_user ) && ! empty( $approving_user->ID ) ) ? (int) $approving_user->ID : $_SERVER['REMOTE_ADDR'];
				wp_set_object_terms($comment_id, array($user), 'comment_flag_approved');
			}
		}
	}

	function notify($comment_id = 0) {
		global $wpdb;
		if ( empty( $comment_id ) )
			return false;

		if( get_option( "moderation_notify" ) == 0 )
			return true;

		$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$comment_id' LIMIT 1");
		$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");

		$comment_author_domain = @gethostbyaddr($comment->comment_author_IP);
		$comments_waiting = $wpdb->get_var("SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'");

		$notify_message  = sprintf( $this->__('A comment on the post #%1$s "%2$s" has been flagged.'), $post->ID, $post->post_title ) . "\r\n";
		$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
		$notify_message .= sprintf( $this->__('Author : %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
		$notify_message .= sprintf( $this->__('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
		$notify_message .= sprintf( $this->__('URL    : %s'), $comment->comment_author_url ) . "\r\n";
		$notify_message .= sprintf( $this->__('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s'), $comment->comment_author_IP ) . "\r\n";
		$notify_message .= $this->__('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
		$notify_message .= sprintf( $this->__('Approve it: %s'),  get_option('siteurl')."/wp-admin/comment.php?action=mac&c=$comment_id" ) . "\r\n";
		$notify_message .= sprintf( $this->__('Delete it: %s'), get_option('siteurl')."/wp-admin/comment.php?action=cdc&c=$comment_id" ) . "\r\n";
		$notify_message .= sprintf( $this->__('Spam it: %s'), get_option('siteurl')."/wp-admin/comment.php?action=cdc&dt=spam&c=$comment_id" ) . "\r\n";
		$notify_message .= get_option('siteurl') . "/wp-admin/moderation.php\r\n";

		$subject = sprintf( $this->__('[%1$s] Comment flagged on "%2$s"'), get_option('blogname'), $post->post_title );
		$admin_email = get_option('admin_email');

		@wp_mail($admin_email, $subject, $notify_message);

		return true;
	}

	function get_user_flag_status($comment_id = 0, $user_id = 0) {
		global $wpdb;
		$flagged = false;
		if ( empty( $comment_id ) ) {
			return false;
		}
		if ( empty( $user_id ) ) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}
		// use cookies ( to include non-logged-in users )
		$flagged = (int) $_COOKIE[$wpdb->prefix . 'flaggedcomment' . $comment_id];
		if ( $flagged )
			return true;

		if ( ! empty( $user_id ) ) {
		// if a logged-in user
			$flagged = get_user_option('flaggedcomment' . $comment_id, $user_id);
		}
		if ( $flagged )
			return true;
		else 
			return false;
	}

	function set_user_flag_status($comment_id = 0, $user_id = 0) {
		global $wpdb;
		if ( empty( $comment_id ) ) {
			return false;
		}
		if ( empty( $user_id ) ) {
			$user = wp_get_current_user();
			$user_id = $user->ID;
		}
		// use cookies for everyone
		$cookiepath = COOKIEPATH;
		$sitecookiepath = SITECOOKIEPATH;
		$expire = time() + 31536000;

		setcookie($wpdb->prefix . 'flaggedcomment' . $comment_id, 1, $expire, $cookiepath, COOKIE_DOMAIN);

		if ( $cookiepath != $sitecookiepath ) {
			setcookie($wpdb->prefix . 'flaggedcomment' . $comment_id, 1, $expire, $sitecookiepath, COOKIE_DOMAIN);
		}
		

		if ( ! empty( $user_id ) ) {
		// if a logged-in user 
			update_user_option( $user_id, 'flaggedcomment' . $comment_id, 1);
		}

	}

	/**
	 * Checks whether a given IP address should be throttled
	 * @param ip The IP address
	 * @return bool True if the IP address should be throttled
	 */
	function ip_throttled($ip = '') {
		$flags_from_ip = get_terms('comment_flag_ip', array('fields' => 'ids', 'hide_empty' => false, 'name__like' => $ip));
		if ( empty( $flags_from_ip ) ) {
			return false;
		} else {
			// this filter is a necessary hack to get the right range of timestamps
			add_filter('list_terms_exclusions', array(&$this, 'ip_throttled_filter'));
			$children = get_terms('comment_flag_time', array('fields' => 'ids', 'child_of' => $flags_from_ip[0]));
			remove_filter('list_terms_exclusions', array(&$this, 'ip_throttled_filter'));
			$count = (int) count($children);
			if ( $count > $this->flag_comments_throttle_count ) {
				return true;
			} else {
				return false;
			}
		}
	}
		function ip_throttled_filter($w = '') {
			return " $w AND t.name > $this->throttle_time_floor";
		}

	/**
	 * Record an IP address's flagging
	 */
	function record_ip_flagging($ip = '', $comment_id = 0) {
		// Create the IP address term if it doesn't exist, otherwise returns existing
		$result = wp_insert_term($ip, 'comment_flag_ip');
		// should overwrite the $term_id and $term_taxonomy_id with the correct values
		extract($result, EXTR_OVERWRITE);
		$term_id = (int) $term_id;
	
		// add timestamp object as child of the IP address
		$time_term = wp_insert_term(time(), 'comment_flag_time', array('parent' => $term_id));
		$time_term_id = ( empty( $time_term ) || empty( $time_term['term_id'] ) ) ? 0 : $time_term['term_id'];

		// associate this term with the comment
		wp_set_object_terms($comment_id, array($time_term_id), 'comment_flag_time', true);
	}

	function unflagcomment($comment_id = 0, $nonce_check = true) {
		$comment_id = ( ! empty( $_REQUEST['commentID'] ) ) ? (int) $_REQUEST['commentID'] : (int) $comment_id;
		if ( ! empty( $comment_id ) && current_user_can('edit_posts') ) {
			if ( $nonce_check ) {
				check_admin_referer("unflag-comment_$comment_id");
			}
			$this->moderated_comment($comment_id, 'approve');
			if ( ! empty( $_POST['ajax'] ) ) {
				die('tr#comment-' . $comment_id);
			}
		}
	}

	/**
	 * Update the count for the comment_flag_ip taxonomy item.
	 * In other words, update how many times a given ip has flagged a given comment
	 */
	function update_ip_flag_count($term_ids = array()) {
		global $wpdb;
		foreach( (array) $term_ids as $term ) {
			// standard count updates lifted from wp_update_term_count_now()
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term) );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );

			// 
		}

	}
}

$flag_comments_plugin = new Flag_Comments();

?>
