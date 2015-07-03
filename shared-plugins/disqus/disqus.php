<?php
/*
Plugin Name: Disqus Comment System
Plugin URI: http://disqus.com/
Description: The Disqus comment system replaces your WordPress comment system with your comments hosted and powered by Disqus. Head over to the Comments admin page to set up your DISQUS Comment System.
Author: Disqus <team@disqus.com>
Version: 2.40-WPCOM
Author URI: http://disqus.com/
*/

require_once( dirname(__FILE__). '/lib/wpapi.php');

define('DISQUS_URL',			'//disqus.com/');
define('DISQUS_API_URL',		'http://disqus.com/api/');
define('DISQUS_DOMAIN',			'disqus.com');
define('DISQUS_IMPORTER_URL',	'http://import.disqus.net/');
define('DISQUS_MEDIA_URL',		'//disqus.com/media/');
define('DISQUS_RSS_PATH',		'/latest.rss');
define('DISQUS_CAN_EXPORT',		is_file(dirname(__FILE__) . '/export.php'));

function dsq_plugin_basename($file) {
	$file = dirname($file);
	// From WP2.5 wp-includes/plugin.php:plugin_basename()
	$file = str_replace('\\','/',$file); // sanitize for Win32 installs
	$file = preg_replace('|/+|','/', $file); // remove any duplicate slash
	$file = preg_replace('|^.*/' . PLUGINDIR . '/|','',$file); // get relative path from plugins dir
	if ( strstr($file, '/') === false ) {
		return $file;
	}

	$pieces = explode('/', $file);
	return !empty($pieces[count($pieces)-1]) ? $pieces[count($pieces)-1] : $pieces[count($pieces)-2];
}

if ( !defined('WP_CONTENT_URL') ) {
	define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
}
if ( !defined('PLUGINDIR') ) {
	define('PLUGINDIR', 'wp-content/plugins'); // Relative to ABSPATH.  For back compat.
}

define('DSQ_PLUGIN_URL', WP_CONTENT_URL . '/themes/vip/plugins/' . dsq_plugin_basename(__FILE__));

/**
 * Disqus WordPress plugin version.
 *
 * @global	string	$dsq_version
 * @since	1.0
 */
$dsq_version = '2.40';
$mt_dsq_version = '2.01';
/**
 * Response from Disqus get_thread API call for comments template.
 *
 * @global	string	$dsq_response
 * @since	1.0
 */
$dsq_response = '';
/**
 * Comment sort option.
 *
 * @global	string	$dsq_sort
 * @since	1.0
 */
$dsq_sort = 1;
/**
 * Flag to determine whether or not the comment count script has been embedded.
 *
 * @global	string	$dsq_cc_script_embedded
 * @since	1.0
 */
$dsq_cc_script_embedded = false;
/**
 * Disqus API instance.
 *
 * @global	string	$dsq_api
 * @since	1.0
 */
$dsq_api = new DisqusWordPressAPI(get_option('disqus_forum_url'), get_option('disqus_api_key'));
/**
 * DISQUS is_feed check.
 *
 * @global	bool	$dsq_is_feed
 * @since	2.2
 */
$dsq_is_feed = false;

/**
 * DISQUS currently unsupported dev toggle to output comments for this query.
 *
 * @global	bool	$dsq_comments_for_query
 * @since	?
 */
$DSQ_QUERY_COMMENTS = false;

/**
 * DISQUS array to store post_ids from WP_Query for comment JS output.
 *
 * @global	array	$DSQ_QUERY_POST_IDS
 * @since	2.2
 */
$DSQ_QUERY_POST_IDS = array();

/**
 * Helper functions.
 */

function dsq_is_installed() {
	return get_option('disqus_forum_url') && get_option('disqus_api_key');
}

function dsq_can_replace() {
	global $id, $post;

	$replace = get_option('disqus_replace');
	$retval  = true;

	if ( 'draft' == $post->post_status || ! get_option('disqus_forum_url') ) {
		$retval = false;

	} elseif ( 'all' == $replace ) {
		$retval = true;

	} else {
		if ( !isset($post->comment_count) ) {
			$num_comments = 0;

		} else {
			if ( 'empty' == $replace ) {
				// Only get count of comments, not including pings.

				// If there are comments, make sure there are comments (that are not track/pingbacks)
				if ( $post->comment_count > 0 ) {
					// Yuck, this causes a DB query for each post.  This can be
					// replaced with a lighter query, but this is still not optimal.
					$comments = get_approved_comments($post->ID);
					foreach ( $comments as $comment ) {
						if ( $comment->comment_type != 'trackback' && $comment->comment_type != 'pingback' ) {
							$num_comments++;
						}
					}
				} else {
					$num_comments = 0;
				}

			} else {
				$num_comments = $post->comment_count;
			}
		}

		$retval = ('empty' == $replace && 0 == $num_comments) || ('closed' == $replace && 'closed' == $post->comment_status);
	}

	// Filter added by VIP
	return apply_filters( 'dsq_can_replace', $retval ); 
}

function dsq_manage_dialog($message, $error = false) {
	global $wp_version;

	echo '<div '
		. ( $error ? 'id="disqus_warning" ' : '')
		. 'class="updated fade'
		. ( (version_compare($wp_version, '2.5', '<') && $error) ? '-ff0000' : '' )
		. '"><p><strong>'
		. $message
		. '</strong></p></div>';
}

function dsq_sync_comments($post, $comments) {
	global $wpdb;
	
	wp_set_current_user(0);
	
	// Get last_comment_date id for $post with Disqus metadata
	// (This is the date that is stored in the Disqus DB.)
	$last_comment_date = $wpdb->get_var($wpdb->prepare('SELECT max(comment_date) FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE \'Disqus/%%\'', $post->ID));
	if ( $last_comment_date ) {
		$last_comment_date = strtotime($last_comment_date);
	}

	if ( !$last_comment_date ) {
		$last_comment_date = 0;
	}

	foreach ( $comments as $comment ) {
		$ts = strtotime($comment->created_at);
		if ( $comment->imported ) {
			continue;
		} else if ( $ts <= $last_comment_date ) {
			// If comment date of comment is <= last_comment_date, skip comment.
			continue;
		} else {
			// Else, insert_comment
			$commentdata = array(
				'comment_post_ID' => $post->ID,
				'comment_date' => $comment->created_at,
				'comment_date_gmt' => $comment->created_at,
				'comment_content' => apply_filters('pre_comment_content', $comment->message),
				'comment_approved' => 1,
				'comment_agent' => 'Disqus/1.0:' . intval($comment->id),
				'comment_type' => '',
			);
			if ($comment->is_anonymous) {
				$commentdata['comment_author'] = $comment->anonymous_author->name;
				$commentdata['comment_author_email'] = $comment->anonymous_author->email;
				$commentdata['comment_author_url'] = $comment->anonymous_author->url;
				$commentdata['comment_author_IP'] = $comment->anonymous_author->ip_address;
			}
			else {
				$commentdata['comment_author'] = $comment->author->display_name;
				$commentdata['comment_author_email'] = $comment->author->email;
				$commentdata['comment_author_url'] = $comment->author->url;
				$commentdata['comment_author_IP'] = $comment->author->ip_address;
			}
			$commentdata = wp_filter_comment($commentdata);
			wp_insert_comment($commentdata);
		}
	}

	if( isset($_POST['dsq_api_key']) && $_POST['dsq_api_key'] == get_option('disqus_api_key') ) {
		if( isset($_GET['dsq_sync_action']) && isset($_GET['dsq_sync_comment_id']) ) {
			$comment_parts = explode('=', $_GET['dsq_sync_comment_id']);

			if (!($comment_id = intval($comment_parts[1])) > 0) {
				return;
			}
			
			if( 'wp_id' != $comment_parts[0] ) {
				$comment_id = $wpdb->get_var($wpdb->prepare('SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d AND comment_agent LIKE %s', intval($post->ID), 'Disqus/1.0:' . $comment_id));
			}
				
			switch( $_GET['dsq_sync_action'] ) {
				case 'mark_spam':
					wp_set_comment_status($comment_id, 'spam');
					echo "<!-- dsq_sync: wp_set_comment_status($comment_id, 'spam') -->";
					break;
				case 'mark_approved':
					wp_set_comment_status($comment_id, 'approve');
					echo "<!-- dsq_sync: wp_set_comment_status($comment_id, 'approve') -->";
					break;
				case 'mark_killed':
					wp_set_comment_status($comment_id, 'hold');
					echo "<!-- dsq_sync: wp_set_comment_status($comment_id, 'hold') -->";
					break;
			}
		}
	}
}


function dsq_request_handler() {
	global $dsq_response;
	global $dsq_api;
	global $post;
	
	
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'sync_comments':
				if( !( $post_id = $_GET['post_id'] ) ) {
					header("HTTP/1.0 400 Bad Request");
					die(); 
				}
				// schedule the event for 30 seconds from now in case they
				// happen to make a quick post
				// wp_schedule_single_event(time(), 'dsq_sync_post', array($post_id));
				die('OK');
			break;
			case 'export_comments':
				if (current_user_can('manage_options') && DISQUS_CAN_EXPORT) {
// handle vars
					$post_id = intval($_GET['post_id']);
					$limit = 2;
					global $wpdb, $dsq_api;
					$posts = $wpdb->get_results($wpdb->prepare("
						SELECT * 
						FROM $wpdb->posts 
						WHERE post_type != 'revision'
						AND post_status = 'publish'
						AND comment_count > 0
						AND ID > %d
						ORDER BY ID ASC
						LIMIT $limit
					", $post_id));
					$first_post_id = $posts[0]->ID;
					$last_post_id = $posts[(count($posts) - 1)]->ID;
					$max_post_id = $wpdb->get_var($wpdb->prepare("
						SELECT MAX(ID)
						FROM $wpdb->posts 
						WHERE post_type != 'revision'
						AND post_status = 'publish'
						AND comment_count > 0
						AND ID > %d
					", $post_id));
					if ($last_post_id == $max_post_id) {
						$status = 'complete';
						$msg = 'All comments sent to Disqus!';
					}
					else {
						$status = 'partial';
						if (count($posts) == 1) {
							$msg = 'Processed comments on post #'.$first_post_id.'&hellip;';
						}
						else {
							$msg = 'Processed comments on posts #'.$first_post_id.'-'.$last_post_id.'&hellip;';
						}
					}
					$result = 'fail';
					if (count($posts)) {
						include_once(dirname(__FILE__) . '/export.php');
						$wxr = dsq_export_wp($posts);
						$import_id = $dsq_api->import_wordpress_comments($wxr);
						if ($import_id < 0) {
							$result = 'fail';
							$msg = '<p class="status dsq-export-fail">Sorry, something unexpected happened with the export. Please <a href="#" id="dsq_export_retry">try again</a></p><p>If your API key has changed, you may need to reinstall Disqus (deactivate the plugin and then reactivate it). If you are still having issues, refer to the <a href="http://disqus.com/help/wordpress" onclick="window.open(this.href); return false">WordPress help page</a>.</p>';
						}
						else {
							$result = 'success';
						}
					}
// send AJAX response
					$response = compact('result', 'status', 'last_post_id', 'msg');
					header('Content-type: text/javascript');
					echo cf_json_encode($response);
					die();
				}
			break;
		}
	}
}

add_action('init', 'dsq_request_handler');

function dsq_sync_post($post_id) {
	global $dsq_api;
	$post = get_post($post_id);

	// Call update_thread to ensure our permalink is up to date
	dsq_update_permalink($post);

	// Pull comments from API
	$dsq_response = $dsq_api->get_thread($post);
	if( $dsq_response < 0 ) {
		// header("HTTP/1.0 500 Internal Server Error");
		echo 'There was an error when attempting to sync comments';
		return;
	}
	// Sync comments with database.
	dsq_sync_comments($post, $dsq_response);
}

add_action('dsq_sync_post', 'dsq_sync_post');

function dsq_update_permalink($post) {
	global $dsq_api;

	$response = $dsq_api->api->update_thread(null, array(
		'thread_identifier'	=> dsq_identifier_for_post($post),
		'title' => dsq_title_for_post($post),
		'url' => dsq_link_for_post($post)
	));
	
	return $response;
}


/**
 *  Filters/Actions
 */

function dsq_comments_template($value) {
	global $post;
	global $comments;

	if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
		return;
	}

	if ( !dsq_can_replace() ) {
		return $value;
	}

	if ( !dsq_is_installed() ) {
		return $value;
	}

	// TODO: If a disqus-comments.php is found in the current template's
	// path, use that instead of the default bundled comments.php
	//return TEMPLATEPATH . '/disqus-comments.php';

	return dirname(__FILE__) . '/comments.php';
}

// Mark entries in index to replace comments link.
function dsq_comments_number($comment_text) {
	global $post;

	if ( dsq_can_replace() ) {
		return '<span class="dsq-postid" rel="'.htmlspecialchars(dsq_identifier_for_post($post)).'">View Comments</span>';
	} else {
		return $comment_text;
	}
}

function dsq_bloginfo_url($url) {
	if ( get_feed_link('comments_rss2') == $url ) {
		return '//' . strtolower(get_option('disqus_forum_url')) . '.' . DISQUS_DOMAIN . DISQUS_RSS_PATH;
	} else {
		return $url;
	}
}

function dsq_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if (basename($file) == $plugin_file) {
		$settings_link = '<a href="edit-comments.php?page=disqus#adv">'.__('Settings', 'disqus-comment-system').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'dsq_plugin_action_links', 10, 2);

// Always add Disqus management page to the admin menu
function dsq_add_pages() {
 	add_submenu_page(
 		'edit-comments.php',
 		'Disqus', 
 		'Disqus', 
 		'moderate_comments',
 		'disqus',
 		'dsq_manage'
 	);
}
add_action('admin_menu', 'dsq_add_pages', 10);

// a little jQuery goodness to get comments menu working as desired
function sdq_menu_admin_head() {
?>
<script type="text/javascript">
jQuery(function($) {
// fix menu
 	var mc = $('#menu-comments');
	mc.find('a.wp-has-submenu').attr('href', 'edit-comments.php?page=disqus').end().find('.wp-submenu  li:has(a[href="edit-comments.php?page=disqus"])').prependTo(mc.find('.wp-submenu ul'));
});
</script>
<?php
}
add_action('admin_head', 'sdq_menu_admin_head');

// only active on dashboard
function dsq_dash_comment_counts() {
	global $wpdb;
// taken from wp-includes/comment.php - WP 2.8.5
	$count = $wpdb->get_results("
		SELECT comment_approved, COUNT( * ) AS num_comments 
		FROM {$wpdb->comments} 
		WHERE comment_type != 'trackback'
		AND comment_type != 'pingback'
		GROUP BY comment_approved
	", ARRAY_A );
	$total = 0;
	$approved = array('0' => 'moderated', '1' => 'approved', 'spam' => 'spam');
	$known_types = array_keys( $approved );
	foreach( (array) $count as $row_num => $row ) {
		$total += $row['num_comments'];
		if ( in_array( $row['comment_approved'], $known_types ) )
			$stats[$approved[$row['comment_approved']]] = $row['num_comments'];
	}

	$stats['total_comments'] = $total;
	foreach ( $approved as $key ) {
		if ( empty($stats[$key]) )
			$stats[$key] = 0;
	}
	$stats = (object) $stats;
?>
<style type="text/css">
#dashboard_right_now .inside,
#dashboard_recent_comments div.trackback {
	display: none;
}
</style>
<script type="text/javascript">
jQuery(function($) {
	$('#dashboard_right_now').find('.b-comments a').html('<?php echo $stats->total_comments; ?>').end().find('.b_approved a').html('<?php echo $stats->approved; ?>').end().find('.b-waiting a').html('<?php echo $stats->moderated; ?>').end().find('.b-spam a').html('<?php echo $stats->spam; ?>').end().find('.inside').slideDown();
 	$('#dashboard_recent_comments div.trackback').remove();
 	$('#dashboard_right_now .inside table td.last a, #dashboard_recent_comments .inside .textright a.button').attr('href', 'edit-comments.php?page=disqus');
});
</script>
<?php
}
function dsq_wp_dashboard_setup() {
	add_action('admin_head', 'dsq_dash_comment_counts');
}
//add_action('wp_dashboard_setup', 'dsq_wp_dashboard_setup'); // VIP: killed because the counts don't work since sync is disabled.

function dsq_manage() {
	include_once(dirname(__FILE__) . '/manage.php');
}

function dsq_admin_head() {
	if (isset($_GET['page']) && $_GET['page'] == 'disqus') {
?>
<link rel='stylesheet' href='<?php echo DSQ_PLUGIN_URL; ?>/styles/manage.css' type='text/css' />
<style type="text/css">
.dsq-exporting, .dsq-exported, .dsq-export-fail {
	background: url(<?php echo admin_url('images/loading.gif'); ?>) left center no-repeat;
	line-height: 16px;
	padding-left: 20px;
}
.dsq-exported {
	background: url(<?php echo admin_url('images/yes.png'); ?>) left center no-repeat;
}
.dsq-export-fail {
	background: url(<?php echo admin_url('images/no.png'); ?>) left center no-repeat;
}
</style>
<script type="text/javascript">
jQuery(function($) {
	$('#dsq-tabs li').click(function() {
		$('#dsq-tabs li.selected').removeClass('selected');
		$(this).addClass('selected');
		$('.dsq-main, .dsq-advanced').hide();
		$('.' + $(this).attr('rel')).show();
	});
	if (location.href.indexOf('#adv') != -1) {
		$('#dsq-tab-advanced').click();
	}
	dsq_fire_export();
});
dsq_fire_export = function() {
	var $ = jQuery;
	$('#dsq_export a.button, #dsq_export_retry').unbind().click(function() {
		$('#dsq_export').html('<p class="status"></p>');
		$('#dsq_export .status').removeClass('dsq-export-fail').addClass('dsq-exporting').html('Processing...');
		dsq_export_comments();
		return false;
	});
}
dsq_export_comments = function() {
	var $ = jQuery;
	var status = $('#dsq_export .status');
	var post_id = status.attr('rel') || 0;
	$.get(
		'<?php echo admin_url('index.php'); ?>',
		{
			cf_action: 'export_comments',
			post_id: post_id
		},
		function(response) {
			switch (response.result) {
				case 'success':
					status.html(response.msg).attr('rel', response.last_post_id);
					switch (response.status) {
						case 'partial':
							dsq_export_comments();
							break;
						case 'complete':
							status.removeClass('dsq-exporting').addClass('dsq-exported');
							break;
					}
				break;
				case 'fail':
					status.parent().html(response.msg);
					dsq_fire_export();
				break;
			}
		},
		'json'
	);
}
</script>
<?php
// HACK: Our own styles for older versions of WordPress.
		global $wp_version;
		if ( version_compare($wp_version, '2.5', '<') ) {
			echo "<link rel='stylesheet' href='" . DSQ_PLUGIN_URL . "/styles/manage-pre25.css' type='text/css' />";
		}
	}
}
add_action('admin_head', 'dsq_admin_head');

function dsq_warning() {
	if ( !get_option('disqus_forum_url') && !isset($_POST['forum_url']) && isset($_POST['page']) && $_GET['page'] != 'disqus' ) {
		dsq_manage_dialog('You must <a href="edit-comments.php?page=disqus">configure the plugin</a> to enable Disqus Comments.', true);
	}

	if ( !dsq_is_installed() && isset($_POST['page']) && $_GET['page'] == 'disqus' ) {
		dsq_manage_dialog('Disqus Comments has not yet been configured. (<a href="edit-comments.php?page=disqus">Click here to configure</a>)');
	}
}

/**
 * Wrapper for built-in __() which pulls all text from
 * the disqus domain and supports variable interpolation.
 */
function dsq_i($text, $params=null) {
	if (!is_array($params))
	{
		$params = func_get_args();
		$params = array_slice($params, 1);
	}
	return vsprintf(__($text, 'disqus'), $params);
}

// catch original query
function dsq_parse_query($query) {
	add_action('the_posts', 'dsq_add_request_post_ids', 999);
}
add_action('parse_request', 'dsq_parse_query');

// track the original request post_ids, only run once
function dsq_add_request_post_ids($posts) {
	dsq_add_query_posts($posts);
	remove_action('the_posts', 'dsq_log_request_post_ids', 999);
	return $posts;
}

function dsq_maybe_add_post_ids($posts) {
	global $DSQ_QUERY_COMMENTS;
	if ($DSQ_QUERY_COMMENTS) {
		dsq_add_query_posts($posts);
	}
	return $posts;
}
add_action('the_posts', 'dsq_maybe_add_post_ids');

function dsq_add_query_posts($posts) {
	global $DSQ_QUERY_POST_IDS;
	if (count($posts)) {
		foreach ($posts as $post) {
			$post_ids[] = intval($post->ID);
		}
		$DSQ_QUERY_POST_IDS[md5(serialize($post_ids))] = $post_ids;
	}
}

// check to see if the posts in the loop match the original request or an explicit request, if so output the JS
function dsq_loop_end() {
	global $wp_query;
	
	wp_reset_query(); // let's go back to the original query
	$query = $wp_query;
	
	if ( get_option('disqus_cc_fix') == '1' || !count($query->posts) || is_single() || is_page() || is_feed() ) {
		return;
	}
	global $DSQ_QUERY_POST_IDS;
	foreach ($query->posts as $post) {
		$loop_ids[] = intval($post->ID);
	}
	$posts_key = md5(serialize($loop_ids));
	if (isset($DSQ_QUERY_POST_IDS[$posts_key])) {
		dsq_output_loop_comment_js($DSQ_QUERY_POST_IDS[$posts_key]);
	}
}
add_action('wp_footer', 'dsq_loop_end');

// if someone has a better hack, let me know
// prevents duplicate calls to count.js
$_HAS_COUNTS = false;

function dsq_output_loop_comment_js($post_ids = null) {
	global $_HAS_COUNTS;
	if ($_HAS_COUNTS) return;
	$_HAS_COUNTS = true;
	if (count($post_ids)) {
?>
	<script type="text/javascript">
	// <![CDATA[
		var disqus_shortname = <?php echo wp_json_encode( strtolower(get_option('disqus_forum_url'))); ?>;
		var disqus_domain = <?php echo wp_json_encode( DISQUS_DOMAIN ); ?>;
		(function () {
			var nodes = document.getElementsByTagName('span');
			for (var i = 0, url; i < nodes.length; i++) {
				if (nodes[i].className.indexOf('dsq-postid') != -1) {
					nodes[i].parentNode.setAttribute('data-disqus-identifier', nodes[i].getAttribute('rel'));
					url = nodes[i].parentNode.href.split('#', 1);
					if (url.length == 1) url = url[0];
					else url = url[1]
					nodes[i].parentNode.href = url + '#disqus_thread';
				}
			}
			var s = document.createElement('script'); s.async = true;
			s.type = 'text/javascript';
			s.src = '//' + disqus_domain + '/forums/' + disqus_shortname + '/count.js';
			(document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
		}());
	//]]>
	</script>
<?php
	}
}

function dsq_output_footer_comment_js() {
	if (get_option('disqus_cc_fix') == '1') {
?>
	<script type="text/javascript">
	// <![CDATA[
		var disqus_shortname = <?php echo wp_json_encode( strtolower(get_option('disqus_forum_url'))) ?>;
		var disqus_domain = <?php echo wp_json_encode( DISQUS_DOMAIN ); ?>;
		(function () {
			var nodes = document.getElementsByTagName('span');
			for (var i = 0, url; i < nodes.length; i++) {
				if (nodes[i].className.indexOf('dsq-postid') != -1) {
					nodes[i].parentNode.setAttribute('data-disqus-identifier', nodes[i].getAttribute('rel'));
					url = nodes[i].parentNode.href.split('#', 1);
					if (url.length == 1) url = url[0];
					else url = url[1]
					nodes[i].parentNode.href = url + '#disqus_thread';
				}
			}
			var s = document.createElement('script'); s.async = true;
			s.type = 'text/javascript';
			s.src = '//' + disqus_domain + '/forums/' + disqus_shortname + '/count.js';
			(document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
		}());
	//]]>
	</script>
<?php
	}
}
add_action('wp_footer', 'dsq_output_footer_comment_js');

// UPDATE DSQ when a permalink changes

$dsq_prev_permalinks = array();

function dsq_prev_permalink($post_id) {
// if post not published, return
	$post = get_post($post_id);
	if ($post->post_status != 'publish') {
		return;
	}
	global $dsq_prev_permalinks;
	$dsq_prev_permalinks['post_'.$post_id] = get_permalink($post_id);
}
add_action('pre_post_update', 'dsq_prev_permalink');

function dsq_check_permalink($post_id) {
	global $dsq_prev_permalinks;
	if (!empty($dsq_prev_permalinks['post_'.$post_id]) && $dsq_prev_permalinks['post_'.$post_id] != get_permalink($post_id)) {
		$post = get_post($post_id);
		dsq_update_permalink($post);
	}
}
add_action('edit_post', 'dsq_check_permalink');

add_action('admin_notices', 'dsq_warning');

// Only replace comments if the disqus_forum_url option is set.
add_filter('comments_template', 'dsq_comments_template');
add_filter('comments_number', 'dsq_comments_number');
add_filter('bloginfo_url', 'dsq_bloginfo_url');

/**
 * JSON ENCODE for PHP < 5.2.0
 * Checks if json_encode is not available and defines json_encode
 * to use php_json_encode in its stead
 * Works on iteratable objects as well - stdClass is iteratable, so all WP objects are gonna be iteratable
 */ 
if(!function_exists('cf_json_encode')) {
	function cf_json_encode($data) {
// json_encode is sending an application/x-javascript header on Joyent servers
// for some unknown reason.
// 		if(function_exists('json_encode')) { return json_encode($data); }
// 		else { return cfjson_encode($data); }
		return cfjson_encode($data);
	}
	
	function cfjson_encode_string($str) {
		if(is_bool($str)) { 
			return $str ? 'true' : 'false'; 
		}
	
		return str_replace(
			array(
				'"'
				, '/'
				, "\n"
				, "\r"
			)
			, array(
				'\"'
				, '\/'
				, '\n'
				, '\r'
			)
			, $str
		);
	}

	function cfjson_encode($arr) {
		$json_str = '';
		if (is_array($arr)) {
			$pure_array = true;
			$array_length = count($arr);
			for ( $i = 0; $i < $array_length ; $i++) {
				if (!isset($arr[$i])) {
					$pure_array = false;
					break;
				}
			}
			if ($pure_array) {
				$json_str = '[';
				$temp = array();
				for ($i=0; $i < $array_length; $i++) {
					$temp[] = sprintf("%s", cfjson_encode($arr[$i]));
				}
				$json_str .= implode(',', $temp);
				$json_str .="]";
			}
			else {
				$json_str = '{';
				$temp = array();
				foreach ($arr as $key => $value) {
					$temp[] = sprintf("\"%s\":%s", $key, cfjson_encode($value));
				}
				$json_str .= implode(',', $temp);
				$json_str .= '}';
			}
		}
		else if (is_object($arr)) {
			$json_str = '{';
			$temp = array();
			foreach ($arr as $k => $v) {
				$temp[] = '"'.$k.'":'.cfjson_encode($v);
			}
			$json_str .= implode(',', $temp);
			$json_str .= '}';
		}
		else if (is_string($arr)) {
			$json_str = '"'. cfjson_encode_string($arr) . '"';
		}
		else if (is_numeric($arr)) {
			$json_str = $arr;
		}
		else if (is_bool($arr)) {
			$json_str = $arr ? 'true' : 'false';
		}
		else {
			$json_str = '"'. cfjson_encode_string($arr) . '"';
		}
		return $json_str;
	}
}

// Single Sign-on Integration

function dsq_sso() {
	if (!$partner_key = get_option('disqus_partner_key')) {
		return;
	}
	global $current_user, $dsq_api;
	get_currentuserinfo();
	if ($current_user->ID) {
		$avatar_tag = get_avatar($current_user->ID);
		$avatar_data = array();
		preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
		$avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
		$user_data = array(
			'username' => $current_user->display_name,
			'id' => $current_user->ID,
			'avatar' => $avatar,
			'email' => $current_user->user_email,
		);
	}
	else {
		$user_data = array();
	}
	$user_data = base64_encode(cf_json_encode($user_data));
	$time = time();
	$hmac = dsq_hmacsha1($user_data.' '.$time, $partner_key);

	$payload = $user_data.' '.$hmac.' '.$time;
	echo '<script type="text/javascript" src="//'.$dsq_api->short_name.'.disqus.com/remote_auth.js?remote_auth_s2='.urlencode($payload).'"></script>';
}
// WPCOM: disable discus v2 SSO as it is no longer supported and causes JS errors. /z 28343
// add_action('wp_head', 'dsq_sso');

// from: http://www.php.net/manual/en/function.sha1.php#39492
//Calculate HMAC-SHA1 according to RFC2104
// http://www.ietf.org/rfc/rfc2104.txt
function dsq_hmacsha1($data, $key) {
    $blocksize=64;
    $hashfunc='sha1';
    if (strlen($key)>$blocksize)
        $key=pack('H*', $hashfunc($key));
    $key=str_pad($key,$blocksize,chr(0x00));
    $ipad=str_repeat(chr(0x36),$blocksize);
    $opad=str_repeat(chr(0x5c),$blocksize);
    $hmac = pack(
                'H*',$hashfunc(
                    ($key^$opad).pack(
                        'H*',$hashfunc(
                            ($key^$ipad).$data
                        )
                    )
                )
            );
    return bin2hex($hmac);
}

/**
 * This is the only modified part of the plugin from the original on VIP.
 * We have to fix the identifier for imported posts because the post_id is no longer
 * the same, so we have to pull in the one that was set in the guid.  There is potential
 * for more than one post having the same identifier now, however, the only reason the
 * post ID is incorrect, should be from menus, pages (which don't have comments) and
 * revisions.
 *
 * @param object $post
 * @return string
 */
function dsq_identifier_for_post($post) {
	if('post' == get_post_type($post)) {
		$guid_parts = parse_url($post->guid);
		if(isset($guid_parts['query'])) {
			parse_str($guid_parts['query'], $guid_parts_query);
			if(isset($guid_parts_query['p']) && $guid_parts_query['p'] != $post->ID) {
				//all this for that...
				return $guid_parts_query['p'] . ' ' . $post->guid;
			}
		}
	}
	return $post->ID . ' ' . $post->guid;
}

function dsq_title_for_post($post) {
	$title = get_the_title($post);
	$title = strip_tags($title, DISQUS_ALLOWED_HTML);
	$title = urlencode($title);
	return $title;
}

function dsq_link_for_post($post) {
	return get_permalink($post);
}

?>
