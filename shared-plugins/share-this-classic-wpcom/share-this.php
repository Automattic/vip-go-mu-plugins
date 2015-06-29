<?php

// ShareThis
//
// Copyright (c) 2006-2007 Nextumi, Inc.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress
// http://wordpress.org/
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// *****************************************************************

/*
Plugin Name: ShareThis Classic
Plugin URI: http://alexking.org/projects/share-this
Description: Let your visitors share a post/page with others. Supports e-mail and posting to social bookmarking sites. Questions on configuration, etc.? Make sure to read the README.
Version: 1.5.2-WPCOM 
Author: ShareThis, Crowd Favorite (crowdfavorite.com) - modified by Automattic
Author URI: http://sharethis.com
*/


// WPCOM this is not the way should load WordPress. Just doing the minimum work to maintain this old script
if ( !function_exists('get_option') )
	require_once( dirname( dirname( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) ) ) . '/wp-load.php' );

// WPCOM ghetto
@define( 'AKST_PATH', 'http://s0.wp.com/wp-content/themes/vip/plugins/share-this-classic-wpcom/' );
@define( 'AKST_FILE', get_bloginfo('url') . '/wp-content/themes/vip/plugins/share-this-classic-wpcom/share-this.php' );


if ( !function_exists('akst_addtocontent') ) {
	function akst_addtocontent() {
		$addtocontent = get_option('akst_addtocontent');
		if($addtocontent == '') {
			$addtocontent = 1;
			add_option('akst_addtocontent', $addtocontent);
		}
	
		return $addtocontent;
	}
}
/*function akst_addtofooter() {
	$addtofooter = get_option('akst_addtofooter');
	if($addtofooter == '') {
		$addtofooter = 1;
		add_option('akst_addtofooter', $addtofooter);
	}
	
	return $addtofooter;
}*/

if ( !function_exists('akst_addtofeed') ) {
	function akst_addtofeed() {
		$addtofeed = get_option('akst_addtofeed');
		if($addtofeed == '') {
			$addtofeed = 1;
			add_option('akst_addtofeed', $addtofeed);
		}

		return $addtofeed;
	}
}

if ( !function_exists('akst_showicon') ) {
	function akst_showicon() {
		$showicon = get_option('akst_showicon');
		if($showicon == '') {
			$showicon = 1;
			add_option('akst_showicon', $showicon);
		}

		return $showicon;
	}
}

if ( !function_exists('akst_linktext') ) {
	function akst_linktext() {
		$linktext = get_option('akst_linktext');
		if($linktext == '') {
			$linktext = 'ShareThis';
			add_option('akst_linktext', $linktext);
		}
	
		return $linktext;
	}
}

if ( !function_exists('akst_tabs') ) {
	function akst_tabs() {
		$tabs = get_option('akst_tabs');
		if ($tabs == '') {
			$tabs = 'email,social';
			add_option('akst_tabs', $tabs);
		}
	
		return $tabs;
	}
}

if ( !function_exists('akst_socialwebs') ) {
	function akst_socialwebs() {
	/* WPCOM this wpcom feature was never fully implmented, if demand for customizing can revisit
	 * The problem is that this php file is called directly and Wordpress env is therefore not loaded
	 * causing fatal error when loading JS
	 * /wp-content/themes/vip/plugins/share-this-classic-wpcom/share-this.php?akst_action=js
		$socialwebs = get_option('akst_socialwebs');
		if ($socialwebs == '') {
			$socialwebs = 'twitter,facebook,digg,stumbleupon,delicious,reddit,blinklist,newsvine,furl,tailrank,magnolia';
			add_option('akst_socialwebs', $socialwebs);
		}
*/
		$socialwebs = 'twitter,facebook,digg,stumbleupon,delicious,reddit';
		return $socialwebs;
	}
}

if ( !function_exists('akst_disp_socialweb') ) {
	function akst_disp_socialweb($socialweb) {
		return strstr(akst_socialwebs(), $socialweb);
	}
}

// Find more URLs here: 
// http://3spots.blogspot.com/2006/02/30-social-bookmarks-add-to-footer.html

$social_sites = array(
	'twitter' => array(
		'name' => 'Twitter'
		, 'url' => 'http://twitter.com/?status={url}'
	)
	, 'facebook' => array(
		'name' => 'Facebook'
		, 'url' => 'http://www.facebook.com/share.php?u={url}'
	)
	, 'digg' => array(
		'name' => 'Digg'
		, 'url' => 'http://digg.com/submit?phase=2&url={url}&title={title}'
	)
	, 'stumbleupon' => array(
		'name' => 'StumbleUpon'
		, 'url' => 'http://www.stumbleupon.com/submit?url={url}&title={title}'
	)
	, 'delicious' => array(
		'name' => 'del.icio.us'
		, 'url' => 'http://del.icio.us/post?url={url}&title={title}'
	)
	, 'reddit' => array(
		'name' => 'reddit'
		, 'url' => 'http://reddit.com/submit?url={url}&title={title}'
	)
	, 'blinklist' => array(
		'name' => 'BlinkList'
		, 'url' => 'http://blinklist.com/index.php?Action=Blink/addblink.php&Url={url}&Title={title}'
	)
	, 'newsvine' => array(
		'name' => 'Newsvine'
		, 'url' => 'http://www.newsvine.com/_tools/seed&save?popoff=0&u={url}&h={title}'
	)
	, 'furl' => array(
		'name' => 'Furl'
		, 'url' => 'http://furl.net/storeIt.jsp?u={url}&t={title}'
	)
	, 'tailrank' => array(
		'name' => 'Tailrank'
		, 'url' => 'http://tailrank.com/share/?link_href={url}&title={title}'
	)
	, 'magnolia' => array(
		'name' => 'Ma.gnolia'
		, 'url' => 'http://ma.gnolia.com/bookmarklet/add?url={url}&title={title}'
	)
	, 'google_bmarks' => array(
		'name' => 'Google Bookmarks'
		, 'url' => 'http://www.google.com/bookmarks/mark?op=edit&bkmk={url}&title={title}'
	)
	, 'yahoo_myweb' => array(
		'name' => 'Yahoo! My Web'
		, 'url' => 'http://myweb2.search.yahoo.com/myresults/bookmarklet?u={url}&t={title}'
	)
	, 'technorati' => array(
		'name' => 'Technorati'
		, 'url' => 'http://www.technorati.com/faves?add={url}'
	)
	, 'windows_live' => array(
		'name' => 'Windows Live'
		, 'url' => 'https://favorites.live.com/quickadd.aspx?marklet=1&mkt=en-us&url={url}&title={title}&top=1'
	)
	, 'blogmarks' => array(
		'name' => 'Blogmarks'
		, 'url' => 'http://blogmarks.net/my/new.php?mini=1&url={url}&title={title}'
	)
	, 'plugim' => array(
		'name' => 'PlugIM'
		, 'url' => 'http://www.plugim.com/submit?url={url}&title={title}'
	)
	, 'yigg' => array(
		'name' => 'Y!gg'
		, 'url' => 'http://yigg.de/neu?exturl={url}&exttitle={title}'
	)
	, 'simpy' => array(
		'name' => 'Simpy'
		, 'url' => 'http://www.simpy.com/simpy/LinkAdd.do?title={title}&href={url}'
	)
	, 'favoriting' => array(
		'name' => 'Favoriting'
		, 'url' => 'http://www.favoriting.com/nuevoFavorito.asp?qs_origen=3&qs_url={url}&qs_title={title}'
	)
	, 'design_float' => array(
		'name' => 'Design Float'
		, 'url' => 'http://www.designfloat.com/submit.php?url={url}'
	)
	, 'propeller' => array(
		'name' => 'Propeller'
		, 'url' => 'http://www.propeller.com/submit/?U={url}&T={title}'
	)
	, 'bizz_buzz' => array(
		'name' => 'Bizz Buzz'
		, 'url' => 'http://www.bestwaytoinvest.com/bbsubmit?u={url}&t={title}'
	)
);

$akst_limit_mail_recipients = 5;


$akst_action = '';

if ( !function_exists('ak_check_email_address') ) {
	function ak_check_email_address($email) {
// From: http://www.ilovejackdaniels.com/php/email-address-validation/
// First, we check that there's one @ symbol, and that the lengths are right
		if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) {
			// Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
			return false;
		}
// Split it into sections to make life easier
		$email_array = explode("@", $email);
		$local_array = explode(".", $email_array[0]);
		for ($i = 0; $i < sizeof($local_array); $i++) {
			 if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) {
				return false;
			}
		}	
		if (!ereg("^\[?[0-9\.]+\]?$", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
			$domain_array = explode(".", $email_array[1]);
			if (sizeof($domain_array) < 2) {
					return false; // Not enough parts to domain
			}
			for ($i = 0; $i < sizeof($domain_array); $i++) {
				if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i])) {
					return false;
				}
			}
		}
		return true;
	}
}

if ( !function_exists('ak_decode_entities') ) {
	function ak_decode_entities($text, $quote_style = ENT_COMPAT) {
// From: http://us2.php.net/manual/en/function.html-entity-decode.php#68536
		if (function_exists('html_entity_decode')) {
			$text = html_entity_decode($text, $quote_style, 'ISO-8859-1'); // NOTE: UTF-8 does not work!
		}
		else { 
			$trans_tbl = get_html_translation_table(HTML_ENTITIES, $quote_style);
			$trans_tbl = array_flip($trans_tbl);
			$text = strtr($text, $trans_tbl);
		}
		$text = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $text); 
		$text = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $text);
		return $text;
	}
}

if ( !function_exists('ak_prototype') ) {
	function ak_prototype() {
		if (!function_exists('wp_enqueue_script')) {
			global $ak_prototype;
			if (!isset($ak_prototype) || !$ak_prototype) {
				print('
		<script type="text/javascript" src="http://s0.wp.com/wp-includes/js/prototype.js"></script>
				');
			}
			$ak_prototype = true;
		}
	}
}

if ( !function_exists('ak_uuid') ) {
	function ak_uuid() {
		return sprintf( 
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x'
			, mt_rand( 0, 0xffff )
			, mt_rand( 0, 0xffff )
			, mt_rand( 0, 0xffff )
			, mt_rand( 0, 0x0fff ) | 0x4000
			, mt_rand( 0, 0x3fff ) | 0x8000
			, mt_rand( 0, 0xffff )
			, mt_rand( 0, 0xffff )
			, mt_rand( 0, 0xffff )
		);
	}
}

if ( !function_exists('ak_can_update_options') ) {
	function ak_can_update_options() {
		if (function_exists('current_user_can')) {
			if (current_user_can('manage_options')) {
				return true;
			}
		}
		else {
			global $user_level;
			get_currentuserinfo();
			if ($user_level >= 6) {
				return true;
			}
		}
		return false;
	}
}

if (!empty($_REQUEST['akst_action'])) {

	switch ($_REQUEST['akst_action']) {
		case 'js':
			header("Content-type: text/javascript");
?>
function akst_share(id, url, title, html_id, pubid) {
	var form = $('akst_form');
	var post_id = $('akst_post_id');
	
	if (form.style.display == 'block' && post_id.value == id) {
		form.style.display = 'none';
		return;
	}
	
	var link = $('akst_link_' + html_id);
	var offset = Position.cumulativeOffset(link);

	if (document.getElementById('akst_social')) {

<?php
	foreach ($social_sites as $key => $data) {
		if (akst_disp_socialweb($key))
			print('		$("akst_'.$key.'").href = akst_share_url("'.$data['url'].'", url, title, pubid);'."\n");
	}
?>
	}

	if (document.getElementById('akst_email')) {
		post_id.value = id;
	}

	form.style.left = offset[0] + 'px';
	form.style.top = (offset[1] + link.offsetHeight + 3) + 'px';
	form.style.display = 'block';
}

function akst_share_url(base, url, title, pubid) {
	base = base.replace('{url}', url).replace('{title}', title);	
	return base;
}

function akst_share_tab(tab) {
	var tab1 = document.getElementById('akst_tab1');
	if (typeof tab1 == 'undefined') {
		tab1 = document.createElement('div');
	}
	var tab2 = document.getElementById('akst_tab2');
	if (typeof tab2 == 'undefined') {
		tab2 = document.createElement('div');
	}
	var body1 = document.getElementById('akst_social');
	if (typeof body1 == 'undefined') {
		body1 = document.createElement('div');
	}
	var body2 = document.getElementById('akst_email');
	if (typeof body1 == 'undefined') {
		body1 = document.createElement('div');
	}
	
	switch (tab) {
		case '1':
			tab2.className = '';
			tab1.className = 'selected';
			body2.style.display = 'none';
			body1.style.display = 'block';
			break;
		case '2':
			tab1.className = '';
			tab2.className = 'selected';
			body1.style.display = 'none';
			body2.style.display = 'block';
			break;
	}
}
<?php
			die();
			break;
		case 'css':
			header("Content-type: text/css");
?>
#akst_form {
	background: #999;
	border: 1px solid #ddd;
	display: none;
	position: absolute;
	width: 350px;
	z-index: 999;
}
#akst_form a.akst_close {
	color: #fff;
	float: right;
	margin: 5px;
}
#akst_form ul.tabs {
	border: 1px solid #999;
	list-style: none;
	margin: 10px 10px 0 10px;
	padding: 0;
}
#akst_form ul.tabs li {
	background: #ccc;
	border-bottom: 1px solid #999;
	cursor: pointer;
	float: left;
	margin: 0 3px 0 0;
	padding: 3px 5px 2px 5px;
}
#akst_form ul.tabs li.selected {
	background: #fff;
	border-bottom: 1px solid #fff;
	cursor: default;
	padding: 4px 5px 1px 5px;
}
#akst_form div.clear {
	clear: both;
	float: none;
}
#akst_social, #akst_email {
	background: #fff;
	border: 1px solid #fff;
	padding: 10px;
}
#akst_social ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
#akst_social ul li {
	float: left;
	margin: 0;
	padding: 0;
	width: 45%;
}
#akst_social ul li a {
	background-position: 0px 2px;
	background-repeat: no-repeat;
	display: block;
	float: left;
	height: 24px;
	padding: 4px 0 0 22px;
	vertical-align: middle;
}
<?php
foreach ($social_sites as $key => $data) {
	if (akst_disp_socialweb($key))
		print(
'#akst_'.$key.' {
	background-image: url('.AKST_PATH.$key.'.gif) !important;
}
');
}
?>
#akst_email {
	text-align: left;
}
#akst_email form, #akst_email fieldset {
	border: 0;
	margin: 0;
	padding: 0;
}
#akst_email fieldset legend {
	display: none;
}
#akst_email ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
#akst_email ul li {
	margin: 0 0 7px 0;
	padding: 0;
}
#akst_email ul li label {
	color: #555;
	display: block;
	margin-bottom: 3px;
}
#akst_email ul li input {
	padding: 3px 10px;
}
#akst_email ul li input.akst_text {
	padding: 3px;
	width: 280px;
}
#akst_credit {
	background: #fff;
	border-top: 1px solid #ddd;
	padding: 3px;
}
<?php
if (akst_showicon()) {
?>
.akst_share_link {
	background: 1px 0 url(<?php print AKST_PATH; ?>share-icon-16x16.gif) no-repeat !important;
	padding: 1px 0 3px 22px;
}
<?php
}
			die();
			break;
	}
}
if ( !function_exists( 'akst_request_handler' ) ) {
	function akst_request_handler() {
		if (!empty($_REQUEST['akst_action'])) {
			switch ($_REQUEST['akst_action']) {
				case 'share-this':
					akst_page();
					break;
				case 'send_mail':
					akst_send_mail();			
					break;
				case 'akst_update_settings':
					if (ak_can_update_options()) {
						if (empty($_POST['akst_tabs_to_show'])) {
							$_POST['akst_tabs_to_show'] = array('email', 'social');
						}
						if (empty($_POST['tab_order'])) {
							$_POST['tab_order'] = 'email';
						}

						$tabs = array();
						if (count($_POST['akst_tabs_to_show']) > 1) {
							switch ($_POST['tab_order']) {
								case 'social':
									$tabs[] = 'social';
									break;
								case 'email':
									$tabs[] = 'email';
									break;
							}
						}
						if (!in_array('social', $tabs) && in_array('social', $_POST['akst_tabs_to_show'])) {
							$tabs[] = 'social';
						}
						if (!in_array('email', $tabs) && in_array('email', $_POST['akst_tabs_to_show'])) {
							$tabs[] = 'email';
						}
						update_option('akst_tabs', implode(',', $tabs));
				
						if ( isset( $_POST['akst_addtocontent'] ) )
							update_option('akst_addtocontent', 1);
						else
							update_option('akst_addtocontent', 0);
						/*if ( isset( $_POST['akst_addtofooter'] ) )
							update_option('akst_addtofooter', 1);
						else
							update_option('akst_addtofooter', 0);
						*/
						if ( isset( $_POST['akst_addtofeed'] ) )
							update_option('akst_addtofeed', 1);
						else
							update_option('akst_addtofeed', 0);
						if ( isset( $_POST['akst_showicon'] ) )
							update_option('akst_showicon', 1);
						else
							update_option( 'akst_showicon', 0);
						if ( isset( $_POST['akst_linktext'] ) )
							update_option('akst_linktext', wp_specialchars(stripslashes($_POST['akst_linktext']), true));
						else
							update_option('akst_linktext', 'ShareThis');

						if (empty($_POST['akst_socialwebs']))
							$socialwebs = 'twitter,facebook,digg,stumbleupon,delicious,reddit,blinklist,newsvine,furl,tailrank,magnolia';
						else
							$socialwebs = implode(',', $_POST['akst_socialwebs']);
						update_option('akst_socialwebs', $socialwebs);

						header('Location: '.get_bloginfo('wpurl').'/wp-admin/options-general.php?page=share-this.php&updated=true');
						die();
					}
					break;
			}
		}
	}
}
add_action('init', 'akst_request_handler', 9999);			

if ( !function_exists('akst_init') ) {
	function akst_init() {
		if (function_exists('wp_enqueue_script')) {
			wp_enqueue_script('prototype');
		}
	}
}
add_action('init', 'akst_init');			

if ( !function_exists('akst_head') ) {
	function akst_head() {
		ak_prototype();
		print('
		<script type="text/javascript" src="'.AKST_FILE.'?akst_action=js"></script>
		<link rel="stylesheet" type="text/css" href="'.AKST_FILE.'?akst_action=css" />
		');
	}
}
add_action('wp_head', 'akst_head');

if ( !function_exists('akst_share_link') ) {
	function akst_share_link($action = 'print', $id_ext = '') {
		global $akst_action, $post;
		if (in_array($akst_action, array('page'))) {
			return '';
		}
		if (is_feed() || (function_exists('jetpack_check_mobile') && jetpack_check_mobile())) {
			$onclick = '';
		}
		else {
			$onclick = 'onclick="akst_share(\''.$post->ID.'\', \''.urlencode(get_permalink($post->ID)).'\', \''.urlencode(get_the_title()).'\', \''.$post->ID.$id_ext.'\', \''.get_option('st_pubid').'\'); return false;"';
		}
		global $post;
		ob_start();
	?>
	<a href="<?php bloginfo('siteurl'); ?>/?p=<?php print($post->ID); ?>&amp;akst_action=share-this" <?php print($onclick); ?> title="<?php _e('Email, post to del.icio.us, etc.', 'share-this'); ?>" id="akst_link_<?php print($post->ID.$id_ext); ?>" class="akst_share_link" rel="noindex nofollow"><?php _e(akst_linktext(), 'share-this'); ?></a>
	<?php
		$link = ob_get_contents();
		ob_end_clean();
		switch ($action) {
			case 'print':
				print($link);
				break;
			case 'return':
				return $link;
				break;
		}
	}
}

if (!function_exists('akst_add_share_link_to_content') ) {
	function akst_add_share_link_to_content($content) {
		$doit = false;
		if (is_feed() && akst_addtofeed()) {
			$doit = true;
		}
		else if (!is_feed() && akst_addtocontent()) {
			$doit = true;
		}
		if ($doit) {
			$content .= '<p class="akst_link snap_nopreview">'.akst_share_link('return').'</p>';
		}
		return $content;
	}
}
add_action('the_content', 'akst_add_share_link_to_content');
add_action('the_content_rss', 'akst_add_share_link_to_content');

if (!function_exists('akst_share_form') ) {
	function akst_share_form() {
		global $post, $social_sites, $current_user, $akst_limit_mail_recipients;
	
		$tabs = akst_tabs();
		$tabs = explode(',', $tabs);

		if (isset($current_user)) {
			$user = get_currentuserinfo();
			$name = $current_user->user_nicename;
			$email = $current_user->user_email;
		}
		else {
			$user = wp_get_current_commenter();
			$name = $user['comment_author'];
			$email = $user['comment_author_email'];
		}
	?>
		<!-- Share This BEGIN -->
		<div id="akst_form">
			<a href="javascript:void($('akst_form').style.display='none');" class="akst_close"><?php _e('Close', 'share-this'); ?></a>
			<ul class="tabs">
	<?php
		ob_start();
	?>
				<li id="akst_tab1" onClick="akst_share_tab('1');"><?php _e('Social Web', 'share-this'); ?></li>
	<?php
		$tab_social = ob_get_contents();
		ob_end_clean();
	
		ob_start();
	?>
				<li id="akst_tab2" onClick="akst_share_tab('2');"><?php _e('E-mail', 'share-this'); ?></li>
	<?php
		$tab_email = ob_get_contents();
		ob_end_clean();

		$i = 0;
		foreach ($tabs as $tab) {
			switch ($tab) {
				case 'social':
					if ($i == 0) {
						$tab_social = str_replace(' onclick', ' class="selected" onclick', $tab_social);
					}
					print($tab_social);
					break;
				case 'email':
					if ($i == 0) {
						$tab_email = str_replace(' onclick', ' class="selected" onclick', $tab_email);
					}
					print($tab_email);
					break;
			}
			$i++;
		}
	?>
			</ul>
			<div class="clear"></div>
	<?php
		ob_start();
	?>
			<div id="akst_social">
				<ul>
	<?php
		foreach ($social_sites as $key => $data) {
			if (akst_disp_socialweb($key))
				print('				<li><a href="#" id="akst_'.$key.'">'.$data['name'].'</a></li>'."\n");
		}
	?>
				</ul>
				<div class="clear"></div>
			</div>
	<?php
		$body_social = ob_get_contents();
		ob_end_clean();
	
		ob_start();
	?>
			<div id="akst_email">
				<form action="<?php bloginfo('wpurl'); ?>/index.php" method="post">
					<fieldset>
						<legend><?php _e('E-mail It', 'share-this'); ?></legend>
						<ul>
							<li>
								<label for="akst_to"><?php printf(__('To Addresses (up to %d):', 'share-this'), $akst_limit_mail_recipients); ?></label>
								<input type="text" id="akst_to" name="akst_to" value="" class="akst_text" />
							</li>
							<li>
								<label for="akst_name"><?php _e('Your Name:', 'share-this'); ?></label>
								<input type="text" id="akst_name" name="akst_name" value="<?php print(htmlspecialchars($name)); ?>" class="akst_text" />
							</li>
							<li>
								<label for="akst_email_field"><?php _e('Your Address:', 'share-this'); ?></label>
								<input type="text" id="akst_email_field" name="akst_email" value="<?php print(htmlspecialchars($email)); ?>" class="akst_text" />
							</li>
							<li>
								<input type="submit" name="akst_submit" value="<?php _e('Send It', 'share-this'); ?>" />
							</li>
						</ul>
						<input type="hidden" name="akst_action" value="send_mail" />
						<input type="hidden" name="akst_post_id" id="akst_post_id" value="" />
					</fieldset>
				</form>
			</div>
	<?php
		$body_email = ob_get_contents();
		ob_end_clean();

		$i = 0;
		foreach ($tabs as $tab) {
			switch ($tab) {
				case 'social':
					if ($i > 0) {
						$body_social = str_replace(' id="akst_social"', ' style="display: none;" id="akst_social"', $body_social);
					}
					print($body_social);
					break;
				case 'email':
					if ($i > 0) {
						$body_email = str_replace(' id="akst_email"', ' style="display: none;" id="akst_email"', $body_email);
					}
					print($body_email);
					break;
			}
			$i++;
		}
	
		ob_start();
	?>
			<div id="akst_credit">
				<a href="http://sharethis.com">
					<img src="<?php print AKST_PATH; ?>powered-by.gif" alt="<?php _e('Powered by ShareThis', 'share-this'); ?>" />
				</a>
			</div>
		</div>
		<!-- Share This END -->
	<?php
	}
}

//if (akst_addtofooter()) {
	add_action('wp_footer', 'akst_share_form');
//}
if ( !function_exists('akst_send_mail') ) {
	function akst_send_mail() {
		global $akst_limit_mail_recipients;
	
		$post_id = '';
		$to = '';
		$name = '';
		$email = '';
	
		if (!empty($_REQUEST['akst_to'])) {
			$to = stripslashes($_REQUEST['akst_to']);
			$to = strip_tags($to);
			$to = str_replace(
				array(
					"\n"
					,"\t"
					,"\r"
				)
				, array()
				, $to
			);
		}
	
		$to = str_replace(array(',', ';'), ' ', $to);
		if (strstr($to, ' ')) {
			$temp = explode(' ', $to);
			$to = array();
			foreach ($temp as $email) {
				if (!empty($email)) {
					$to[] = $email;
				}
			}
		}
		else {
			$to = array($to);
		}
		$to = array_unique($to);
	
		if (count($to) == 0) {
			wp_die(sprintf(__('Please make sure you have entered an e-mail address to sent this to. Click your <strong>back button</strong> and try again.', 'share-this'), $akst_limit_mail_recipients));
		}
	
		if (count($to) > $akst_limit_mail_recipients) {
			wp_die(sprintf(__('Sorry, you can only send this to %d people at once. Click your <strong>back button</strong> and try again.', 'share-this'), $akst_limit_mail_recipients));
		}
	
		if (!empty($_REQUEST['akst_name'])) {
			$name = stripslashes($_REQUEST['akst_name']);
			$name = strip_tags($name);
			$name = str_replace(
				array(
					'"'
					,"\n"
					,"\t"
					,"\r"
				)
				, array()
				, $name
			);
		}

		if (!empty($_REQUEST['akst_email'])) {
			$email = stripslashes($_REQUEST['akst_email']);
			$email = strip_tags($email);
			$email = str_replace(
				array(
					','
					,"\n"
					,"\t"
					,"\r"
				)
				, array()
				, $email
			);
		}
	
		if (!empty($_REQUEST['akst_post_id'])) {
			$post_id = intval($_REQUEST['akst_post_id']);
		}
	
		$valid_email = 0;
		foreach ($to as $address) {
			if (ak_check_email_address($address)) {
				$valid_email++;
			}
		}

		if (empty($post_id) || empty($to) || count($to) != $valid_email || empty($email) || !ak_check_email_address($email)) {
			wp_die(__('Oops, please click your <strong>back button</strong> and make sure those e-mail addresses are correct, then try again.', 'share-this'));
		}
	
		$headers = "MIME-Version: 1.0\n" .
			'From: "'.$name.'" <'.$email.'>'."\n"
			.'Reply-To: "'.$name.'" <'.$email.'>'."\n"
			.'Return-Path: "'.$name.'" <'.$email.'>'."\n"
			."Content-Type: text/plain; charset=\"" . get_option('blog_charset') ."\"\n";
	
		$subject = __('Check out this post on ', 'share-this').get_bloginfo('name');

		$post = &get_post($post_id);
	
		$excerpt = $post->post_excerpt;
		if (empty($excerpt)) {
			$excerpt = strip_tags(get_the_content());
			if (strlen($excerpt) > 250) {
				$excerpt = substr($excerpt, 0, 247).'...';
			}
		}
	
		$message = __('Greetings--', 'share-this')."\n\n"
			.$name.__(' thinks this will be of interest to you:', 'share-this')."\n\n"
			.ak_decode_entities(get_the_title($post_id))."\n\n"
			.$excerpt."\n\n"
			.get_permalink($post_id)."\n\n"
			.__('Enjoy.', 'share-this')."\n\n"
			.'--'."\n"
			.get_bloginfo('home')."\n";

		foreach ($to as $recipient) {
			@wp_mail($recipient, $subject, $message, $headers);
		}
	
		if (!empty($_SERVER['HTTP_REFERER'])) {
			$url = $_SERVER['HTTP_REFERER'];
		}
	
		wp_die(__('Thanks, we\'ve sent this article to your recipients via e-mail.', 'share-this').' <a href="'.get_permalink($post_id).'">'.__('Return to original page', 'share-this').'</a>.');
	
/*	
		header("Location: $url");
		status_header('302');
		die();
*/
	}
}

if ( !function_exists('akst_hide_pop') ) {
	function akst_hide_pop() {
		return false;
	}
}

if ( !function_exists('akst_options_form') ) {
	function akst_options_form() {
		global $social_sites;
	
		$tabs = akst_tabs();
		$tabs = explode(',', $tabs);

		$checked = ' checked="checked"';

		if (in_array('social', $tabs)) {
			$social_tab = $checked;
		}
		else {
			$social_tab = '';
		}
		if (in_array('email', $tabs)) {
			$email_tab = $checked;
		}
		else {
			$email_tab = '';
		}
		switch ($tabs[0]) {
			case 'social':
				$social_order = $checked;
				$email_order = '';
				break;
			case 'email':
				$social_order = '';
				$email_order = $checked;
				break;
		}

	?>
	<div class="wrap">
		<h2><?php _e('ShareThis Options', 'share-this') ?></h2>
		<form id="ak_sharethis" name="ak_sharethis" action="<?php echo get_bloginfo('wpurl') ?>/wp-admin/index.php" method="post">
		<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php _e('Automatically display', 'share-this')?></th>
			<td>
				<p><label>
				<input name="akst_addtocontent" id="akst_addtocontent" value="1" type="checkbox" <?php checked(1, akst_addtocontent()); ?> />
				<?php _e('ShareThis link on posts and pages. <em>If disabled, &lt;?php akst_share_link(); ?&gt; can be used in your theme templates.</em>', 'share-this') ?></p>
				</label>
				</p>
	
				<p><label>
				<input name="akst_addtofeed" id="akst_addtofeed" value="1" type="checkbox" <?php checked(1, akst_addtofeed()); ?> />
				<?php _e('ShareThis link in RSS feeds', 'share-this') ?></p>
				</label>
				</p>
			
				<p><label>
				<input name="akst_showicon" id="akst_showicon" value="1" type="checkbox" <?php checked(1, akst_showicon()); ?> />
				<?php _e('ShareThis icon', 'share-this') ?>
				</label>
				</p>
			</td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php _e('Link text', 'share-this'); ?></th>
			<td>
				<p>
				<input name="akst_linktext" type="text" id="akst_linktext" value="<?php echo akst_linktext() ?>" size="15" class="code" /><br />
				<?php _e('Text used for the ShareThis link', 'share-this') ?>
				</p>
			</td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php _e('Tabs', 'share-this'); ?></th>
			<td>
				<p><label>
				<input type="checkbox" name="akst_tabs_to_show[]" value="email" id="akst_tabs_to_show_email"<?php echo $email_tab ?> />
				<?php _e('E-mail', 'share-this') ?>
				</label>
				</p>
			
				<p><label>
				<input type="checkbox" name="akst_tabs_to_show[]" value="social" id="akst_tabs_to_show_social"<?php echo $social_tab ?> />
				<?php _e('Social Web', 'share-this') ?>
				</label>
				</p>
			</td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php _e('First tab', 'share-this'); ?></th>
			<td>
				<p><label>
				<input type="radio" name="tab_order" value="email" id="tab_order_email"<?php echo $email_order ?> />
				<?php _e('E-mail', 'share-this') ?>
				</label>
				</p>
			
				<p><label>
				<input type="radio" name="tab_order" value="social" id="tab_order_social"<?php echo $social_order ?> />
				<?php _e('Social Web', 'share-this') ?>
				</label>
				</p>
			</td>
			</tr>
	<?php	/*  WPCOM this wpcom feature was never not implmented fully, if demand for customizing can revisit
			<tr valign="top">
			<th scope="row"><?php _e('Social web sites', 'share-this'); ?></th>
			<td>

			<p>
			<?php 	foreach ($social_sites as $key => $data) { ?>
				<label><input name="akst_socialwebs[]" id="akst_socialwebs_<?php echo $key ?>" value="<?php echo $key ?>" type="checkbox" <?php if(akst_disp_socialweb($key)) echo $checked; ?> /> 
				<img alt='<?php _e($data['name'], 'share-this') ?>' src="<?php print AKST_PATH.$key; ?>.gif" height='16' width='16' /> 	
				<?php _e($data['name'], 'share-this') ?></label><br />
			<?php } ?>
			</p>
			</td>
			</tr>
		*/ ?>
		</table>
		<p class="submit">
			<input type="submit" name="submit_button" value="<?php _e('Save Changes ', 'share-this') ?>" />
		</p>
		<input type="hidden" name="akst_action" value="akst_update_settings" />
		</form>
	</div>
	<?php
	}
}
if ( !function_exists('akst_menu_items') ) {
	function akst_menu_items() {
		if (ak_can_update_options()) {
			add_options_page(
				__('ShareThis Options', 'share-this')
				, __('ShareThis', 'share-this')
				, 8
				, basename(__FILE__)
				, 'akst_options_form'
			);
		}
	}
}
add_action('admin_menu', 'akst_menu_items');

if ( !function_exists('akst_page') ) {
	function akst_page() {
		global $social_sites, $akst_action, $current_user, $post, $akst_limit_mail_recipients;
	
		$akst_action = 'page';
	
		add_action('akpc_display_popularity', 'akst_hide_pop');
	
		$id = 0;
		if (!empty($_GET['p'])) {
			$id = intval($_GET['p']);
		}
		if ($id <= 0) {
			header("Location: ".get_bloginfo('siteurl'));
			die();
		}
		if (isset($current_user)) {
			$user = get_currentuserinfo();
			$name = $current_user->user_nicename;
			$email = $current_user->user_email;
		}
		else {
			$user = wp_get_current_commenter();
			$name = $user['comment_author'];
			$email = $user['comment_author_email'];
		}
		query_posts('p='.$id);
		if (!have_posts()) {
			query_posts('page_id='.$id);
		}
		if (have_posts()) : 
			while (have_posts()) : 
				the_post();
				header('Content-Type: '.get_bloginfo('html_type').'; charset='.get_bloginfo('charset'));
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
	        "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title><?php _e('Share This : ', 'share-this'); the_title(); ?></title>
		<meta name="robots" content="noindex, noarchive" />
		<link rel="stylesheet" type="text/css" href="<?php print AKST_FILE; ?>?akst_action=css" />
		<style type="text/css">
	
		#akst_social ul li {
			width: 48%;
		}
		#akst_social ul li a {
			background-position: 0px 4px;
		}
		#akst_email {
			display: block;
		}
		#akst_email ul li {
			margin-bottom: 10px;
		}
		#akst_email ul li input.akst_text {
			width: 220px;
		}
	
		body {
			background: #fff url(<?php print AKST_PATH; ?>page_back.gif) repeat-x;
			font: 11px Verdana, sans-serif;
			padding: 20px;
			text-align: center;
		}
		#body {
			background: #fff;
			border: 1px solid #ccc;
			border-width: 5px 1px 2px 1px;
			margin: 0 auto;
			text-align: left;
			width: 700px;
		}
		#info {
			border-bottom: 1px solid #ddd;
			line-height: 150%;
			padding: 10px;
		}
		#info p {
			margin: 0;
			padding: 0;
		}
		#social {
			float: left;
			padding: 10px 0 10px 10px;
			width: 350px;
		}
		#email {
			float: left;
			padding: 10px;
			width: 300px;
		}
		#content {
			border-top: 1px solid #ddd;
			padding: 20px 50px;
		}
		#content .akst_date {
			color: #666;
			float: right;
			padding-top: 4px;
		}
		#content .akst_title {
			font: bold 18px "Lucida Sans Unicode", "Lucida Grande", "Trebuchet MS", sans-serif;
			margin: 0 150px 10px 0;
			padding: 0;
		}
		#content .akst_category {
			color: #333;
		}
		#content .akst_entry {
			font-size: 12px;
			line-height: 150%;
			margin-bottom: 20px;
		}
		#content .akst_entry p, #content .akst_entry li, #content .akst_entry dt, #content .akst_entry dd, #content .akst_entry div, #content .akst_entry blockquote {
			margin-bottom: 10px;
			padding: 0;
		}
		#content .akst_entry blockquote {
			background: #eee;
			border-left: 2px solid #ccc;
			padding: 10px;
		}
		#content .akst_entry blockquote p {
			margin: 0 0 10px 0;
		}
		#content .akst_entry p, #content .akst_entry li, #content .akst_entry dt, #content .akst_entry dd, #content .akst_entry td, #content .akst_entry blockquote, #content .akst_entry blockquote p {
			line-height: 150%;
		}
		#content .akst_return {
			font-size: 11px;
			margin: 0;
			padding: 20px;
			text-align: center;
		}
		#footer {
			background: #fff;
			border-top: 1px solid #ddd;
			padding: 10px;
		}
		#footer p {
			margin: 0;
			padding: 0;
			text-align: center;
		}
		#footer p a, #footer p a img {
			border: 0;
		}
		h2 {
			color: #333;
			font: bold 14px "Lucida Sans Unicode", "Lucida Grande", "Trebuchet MS", sans-serif;
			margin: 0 0;
			padding: 0;
		}
		div.clear {
			float: none;
			clear: both;
		}
		hr {
			border: 0;
			border-bottom: 1px solid #ccc;
		}
	
		</style>

	<?php do_action('akst_head'); ?>

	</head>
	<body>

	<div id="body">

		<div id="info">
			<p><?php printf(__('<strong>What is this?</strong> From this page you can use the <em>Social Web</em> links to save %s to a social bookmarking site, or the <em>E-mail</em> form to send a link via e-mail.', 'share-this'), '<a href="'.get_permalink($id).'">'.get_the_title().'</a>'); ?></p>
		</div>

		<div id="social">
			<h2><?php _e('Social Web', 'share-this'); ?></h2>
			<div id="akst_social">
				<ul>
	<?php
		foreach ($social_sites as $key => $data) {
			if (akst_disp_socialweb($key)) {
				$link = str_replace(
					array(
						'{url}'
						, '{title}'
					)
					, array(
						urlencode(get_permalink($id))
						, urlencode(get_the_title())
					)
					, $data['url']
				);
	
				print('				<li><a href="'.$link.'" id="akst_'.$key.'">'.$data['name'].'</a></li>'."\n");
			}
		}
	?>
				</ul>
				<div class="clear"></div>
			</div>
		</div>
	
		<div id="email">
			<h2><?php _e('E-mail', 'share-this'); ?></h2>
			<div id="akst_email">
				<form action="<?php bloginfo('wpurl'); ?>/index.php" method="post">
					<fieldset>
						<legend><?php _e('E-mail It', 'share-this'); ?></legend>
						<ul>
							<li>
								<label for="akst_to"><?php printf(__('To Addresses (up to %d):', 'share-this'), $akst_limit_mail_recipients); ?></label>
								<input type="text" id="akst_to" name="akst_to" value="" class="akst_text" />
							</li>
							<li>
								<label for="akst_name"><?php _e('Your Name:', 'share-this'); ?></label>
								<input type="text" id="akst_name" name="akst_name" value="<?php print(htmlspecialchars($name)); ?>" class="akst_text" />
							</li>
							<li>
								<label for="akst_email_field"><?php _e('Your Address:', 'share-this'); ?></label>
								<input type="text" id="akst_email_field" name="akst_email" value="<?php print(htmlspecialchars($email)); ?>" class="akst_text" />
							</li>
							<li>
								<input type="submit" name="akst_submit" value="<?php _e('Send It', 'share-this'); ?>" />
							</li>
						</ul>
						<input type="hidden" name="akst_action" value="send_mail" />
						<input type="hidden" name="akst_post_id" id="akst_post_id" value="<?php print($id); ?>" />
					</fieldset>
				</form>
			</div>
		</div>
	
		<div class="clear"></div>
	
		<div id="content">
			<span class="akst_date"><?php the_time('F d, Y'); ?></span>
			<h1 class="akst_title"><?php the_title(); ?></h1>
			<p class="akst_category"><?php _e('Posted in: ', 'share-this'); the_category(','); ?></p>
			<div class="akst_entry"><?php the_content(); ?></div>
			<hr />
			<p class="akst_return"><?php _e('Return to:', 'share-this'); ?> <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></p>
			<div class="clear"></div>
		</div>
	
		<div id="footer">
			<p>
				<a href="http://sharethis.com">
					<img src="<?php print AKST_PATH; ?>powered-by.gif" alt="<?php _e('Powered by ShareThis', 'share-this'); ?>" />
				</a>
			</p>
		</div>

	</div>

	</body>
	</html>
	<?php
			endwhile;
		endif;
		die();
	}
}

if ( !function_exists('akst_install') ) {
	function akst_install() {
		if (get_option('st_pubid') == '') {
			add_option('st_pubid', ak_uuid());
		}
	}
}
if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
	akst_install();
}

?>
