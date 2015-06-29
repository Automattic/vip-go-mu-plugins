<?php
/*
Plugin Name: WP-Paginate
Plugin URI: http://www.ericmmartin.com/projects/wp-paginate/
Description: A simple and flexible pagination plugin for WordPress posts and comments.
Author: Eric Martin
Version: 1.2.2-WPCOM
Author URI: http://www.ericmmartin.com
Revision: $Id: wp-paginate.php 315138 2010-11-24 04:55:42Z emartin24 $
*/

/*  Copyright 2010 Eric Martin (eric@ericmmartin.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/* WordPress.com VIP Modifcations are marked with 'WPCOM VIP MOD' */

/**
 * Set the wp-content and plugin urls/paths
 */
if (!defined( 'WP_CONTENT_URL' ))
	define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if (!defined( 'WP_CONTENT_DIR' ))
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if (!defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
if (!defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

if (!class_exists( 'WPPaginate' )) {
	class WPPaginate {
		/**
		 * @var string The plugin version
		 */
		var $version = '1.2.2';

		/**
		 * @var string The options string name for this plugin
		 */
		var $optionsName = 'wp_paginate_options';

		/**
		 * @var string $localizationDomain Domain used for localization
		 */
		var $localizationDomain = 'wp-paginate';

		/**
		 * @var string $pluginurl The url to this plugin
		 */
		var $pluginurl = '';
		/**
		 * @var string $pluginpath The path to this plugin
		 */
		var $pluginpath = '';

		/**
		 * @var array $options Stores the options for this plugin
		 */
		var $options = array();

		var $type = 'posts';

		/**
		 * PHP 4 Compatible Constructor
		 */
		function WPPaginate() {$this->__construct();}

		/**
		 * PHP 5 Constructor
		 */
		function __construct() {
			/* WPCOM VIP MOD 
				- Replaced plugin_basename() with  basename( dirname( ) )
				- VIP Path for pluginurl and pluginpath
			*/
			
			$name = basename( dirname( __FILE__ ) );

			//Language Setup
			load_plugin_textdomain($this->localizationDomain, false, "$name/I18n/");

			$this->pluginurl = WP_CONTENT_URL . "/themes/vip/plugins/$name/";
			$this->pluginpath = WP_CONTENT_DIR . "/themes/vip/plugins/$name/";

			//Initialize the options
			$this->get_options();

			//Actions
			add_action( 'admin_menu', array(&$this, 'admin_menu_link' ));

			if ($this->options['css'])
				add_action( 'wp_print_styles', array(&$this, 'wp_paginate_css' ));
		}

		/**
		 * Pagination based on options/args
		 */
		function paginate($args = false) {
			if ($this->type === 'comments' && !get_option( 'page_comments' ))
				return;

			$r = wp_parse_args($args, $this->options);
			extract($r, EXTR_SKIP);

			if (!isset($page) && !isset($pages)) {
				global $wp_query;

				if ($this->type === 'posts' ) {
					$page = get_query_var( 'paged' );
					$posts_per_page = intval(get_query_var( 'posts_per_page' ));
					$pages = intval(ceil($wp_query->found_posts / $posts_per_page));
				}
				else {
					$page = get_query_var( 'cpage' );
					$comments_per_page = get_option( 'comments_per_page' );
					$pages = get_comment_pages_count();
				}
				$page = !empty($page) ? intval($page) : 1;
			}

			$prevlink = ($this->type === 'posts' )
				? esc_url(get_pagenum_link($page - 1))
				: esc_url( get_comments_pagenum_link($page - 1) );
			$nextlink = ($this->type === 'posts' )
				? esc_url(get_pagenum_link($page + 1))
				: esc_url( get_comments_pagenum_link($page + 1) );

			$output = stripslashes($before);
			if ($pages > 1) {
				$output .= sprintf( '<ol class="wp-paginate%s">', ($this->type === 'posts' ) ? '' : ' wp-paginate-comments' );
				$output .= sprintf( '<li><span class="title">%s</span></li>', stripslashes($title));
				$ellipsis = "<li><span class='gap'>...</span></li>";

				if ($page > 1 && !empty($previouspage)) {
					$cleanedPrevPage = stripslashes($previouspage);
					$prevLinkTag = apply_filters('wp_paginate_prev_link_tag', sprintf('<a href="%s" class="prev">%s</a>', $prevlink, $cleanedPrevPage), $prevlink, $cleanedPrevPage);
					$output .= '<li>'.$prevLinkTag.'</li>';
				}

				$min_links = $range * 2 + 1;
				$block_min = min($page - $range, $pages - $min_links);
				$block_high = max($page + $range, $min_links);
				$left_gap = (($block_min - $anchor - $gap) > 0) ? true : false;
				$right_gap = (($block_high + $anchor + $gap) < $pages) ? true : false;

				if ($left_gap && !$right_gap) {
					$output .= sprintf( '%s%s%s',
						$this->paginate_loop(1, $anchor),
						$ellipsis,
						$this->paginate_loop($block_min, $pages, $page)
					);
				}
				else if ($left_gap && $right_gap) {
					$output .= sprintf( '%s%s%s%s%s',
						$this->paginate_loop(1, $anchor),
						$ellipsis,
						$this->paginate_loop($block_min, $block_high, $page),
						$ellipsis,
						$this->paginate_loop(($pages - $anchor + 1), $pages)
					);
				}
				else if ($right_gap && !$left_gap) {
					$output .= sprintf( '%s%s%s',
						$this->paginate_loop(1, $block_high, $page),
						$ellipsis,
						$this->paginate_loop(($pages - $anchor + 1), $pages)
					);
				}
				else {
					$output .= $this->paginate_loop(1, $pages, $page);
				}

				if ($page < $pages && !empty($nextpage)) {
					$cleanedNextPage = stripslashes($nextpage);
					$nextLinkTag = apply_filters('wp_paginate_next_link_tag', sprintf('<a href="%s" class="next">%s</a>', $nextlink, $cleanedNextPage), $nextlink, $cleanednextPage);
					$output .= '<li>'.$nextLinkTag.'</li>';
				}
				$output .= "</ol>";
			}
			$output .= stripslashes($after);

			if ($pages > 1 || $empty) {
				echo $output;
			}
		}

		/**
		 * Helper function for pagination which builds the page links.
		 */
		function paginate_loop($start, $max, $page = 0) {
			$output = "";
			for ($i = $start; $i <= $max; $i++) {
				$p = ($this->type === 'posts' ) ? esc_url(get_pagenum_link($i)) : esc_url( get_comments_pagenum_link($i) );
				$output .= ($page == intval($i))
					? "<li><span class='page current'>$i</span></li>"
					: "<li><a href='$p' title='$i' class='page'>$i</a></li>";
			}
			return $output;
		}

		function wp_paginate_css() {
			/* WPCOM VIP MOD
				- use Stylesheet path instead of Template Path
				- Remove error supression from file_exists
			*/
			$name = "wp-paginate.css";
			if (false !== file_exists(STYLESHEETPATH . "/$name")) {
				$css = get_stylesheet_directory_uri() . "/$name";
			}
			else {
				$css = $this->pluginurl . $name;
			}
			wp_enqueue_style( 'wp-paginate', $css, false, $this->version, 'screen' );

			if (function_exists( 'is_rtl' ) && is_rtl()) {
				$name = "wp-paginate-rtl.css";
				if (false !== file_exists(STYLESHEETPATH . "/$name")) {
					$css = get_stylesheet_directory_uri() . "/$name";
				}
				else {
					$css = $this->pluginurl . $name;
				}
				wp_enqueue_style( 'wp-paginate-rtl', $css, false, $this->version, 'screen' );
			}
		}

		/**
		 * Retrieves the plugin options from the database.
		 * @return array
		 */
		function get_options() {
			if (!$options = get_option($this->optionsName)) {
				$options = array(
					'title' => 'Pages:',
					'nextpage' => '&raquo;',
					'previouspage' => '&laquo;',
					'css' => true,
					'before' => '<div class="navigation">',
					'after' => '</div>',
					'empty' => true,
					'range' => 3,
					'anchor' => 1,
					'gap' => 3
				);
				update_option($this->optionsName, $options);
			}
			$this->options = $options;
		}
		/**
		 * Saves the admin options to the database.
		 */
		function save_admin_options(){
			return update_option($this->optionsName, $this->options);
		}

		/**
		 * @desc Adds the options subpanel
		 */
		function admin_menu_link() {
			/* WPCOM VIP MOD
				- Replaced plugin_basename() with  basename( dirname( ) )
			*/
			add_options_page( 'WP-Paginate', 'WP-Paginate', 'manage_options', basename(__FILE__), array(&$this, 'admin_options_page' ));
			add_filter( 'plugin_action_links_' . basename( dirname( __FILE__ ) ) , array(&$this, 'filter_plugin_actions' ), 10, 2 );
		}

		/**
		 * @desc Adds the Settings link to the plugin activate/deactivate page
		 */
		function filter_plugin_actions($links, $file) {
			$settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __( 'Settings', $this->localizationDomain) . '</a>';
			array_unshift($links, $settings_link); // before other links

			return $links;
		}

		/**
		 * Adds settings/options page
		 */
		function admin_options_page() {
			/* WPCOM VIP MOD 
				- wp_filter_kses when saving html bits 
				- replaced htmlspecialchars with esc_ functions on form display 
			*/
			if (isset($_POST['wp_paginate_save'])) {
				if (wp_verify_nonce($_POST['_wpnonce'], 'wp-paginate-update-options' )) {
					$this->options['title'] = esc_attr( $_POST['title'] );
					$this->options['previouspage'] = wp_filter_kses( $_POST['previouspage'] );
					$this->options['nextpage'] = wp_filter_kses( $_POST['nextpage'] ) ;
					$this->options['before'] = wp_filter_kses( $_POST['before'] );
					$this->options['after'] = wp_filter_kses( $_POST['after'] );
					$this->options['empty'] = (isset($_POST['empty']) && $_POST['empty'] === 'on' ) ? true : false;
					$this->options['css'] = (isset($_POST['css']) && $_POST['css'] === 'on' ) ? true : false;
					$this->options['range'] = intval($_POST['range']);
					$this->options['anchor'] = intval($_POST['anchor']);
					$this->options['gap'] = intval($_POST['gap']);

					$this->save_admin_options();

					echo '<div class="updated"><p>' . __( 'Success! Your changes were successfully saved!', $this->localizationDomain) . '</p></div>';
				}
				else {
					echo '<div class="error"><p>' . __( 'Whoops! There was a problem with the data you posted. Please try again.', $this->localizationDomain) . '</p></div>';
				}
			}
?>

<div class="wrap">
<div class="icon32" id="icon-options-general"><br/></div>
<h2>WP-Paginate</h2>
<form method="post" id="wp_paginate_options">
<?php wp_nonce_field( 'wp-paginate-update-options' ); ?>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Pagination Label:', $this->localizationDomain ); ?></th>
			<td><input name="title" type="text" id="title" size="40" value="<?php echo stripslashes(esc_attr($this->options['title'])); ?>"/>
			<span class="description"><?php _e( 'The text/HTML to display before the list of pages.', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Previous Page:', $this->localizationDomain ); ?></th>
			<td><input name="previouspage" type="text" id="previouspage" size="40" value="<?php echo stripslashes(esc_html($this->options['previouspage'])); ?>"/>
			<span class="description"><?php _e( 'The text/HTML to display for the previous page link.', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Next Page:', $this->localizationDomain ); ?></th>
			<td><input name="nextpage" type="text" id="nextpage" size="40" value="<?php echo stripslashes(esc_html($this->options['nextpage'])); ?>"/>
			<span class="description"><?php _e( 'The text/HTML to display for the next page link.', $this->localizationDomain ); ?></span></td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<h3><?php _e( 'Advanced Settings', $this->localizationDomain ); ?></h3>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Before Markup:', $this->localizationDomain ); ?></th>
			<td><input name="before" type="text" id="before" size="40" value="<?php echo stripslashes(esc_html($this->options['before'])); ?>"/>
			<span class="description"><?php _e( 'The HTML markup to display before the pagination code.', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'After Markup:', $this->localizationDomain ); ?></th>
			<td><input name="after" type="text" id="after" size="40" value="<?php echo stripslashes(esc_html($this->options['after'])); ?>"/>
			<span class="description"><?php _e( 'The HTML markup to display after the pagination code.', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Markup Display:', $this->localizationDomain ); ?></th>
			<td><label for="empty">
				<input type="checkbox" id="empty" name="empty" <?php echo ($this->options['empty'] === true) ? "checked='checked'" : ""; ?>/> <?php _e( 'Show Before Markup and After Markup, even if the page list is empty?', $this->localizationDomain ); ?></label></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'WP-Paginate CSS File:', $this->localizationDomain ); ?></th>
			<td><label for="css">
				<input type="checkbox" id="css" name="css" <?php echo ($this->options['css'] === true) ? "checked='checked'" : ""; ?>/> <?php printf(__( 'Include the default stylesheet wp-paginate.css? WP-Paginate will first look for <code>wp-paginate.css</code> in your theme directory (<code>themes/%s</code>).', $this->localizationDomain), get_template()); ?></label></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Page Range:', $this->localizationDomain ); ?></th>
			<td>
				<select name="range" id="range">
				<?php for ($i=1; $i<=10; $i++) : ?>
					<option value="<?php echo $i; ?>" <?php echo ($i == $this->options['range']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
				</select>
				<span class="description"><?php _e( 'The number of page links to show before and after the current page. Recommended value: 3', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Page Anchors:', $this->localizationDomain ); ?></th>
			<td>
				<select name="anchor" id="anchor">
				<?php for ($i=1; $i<=10; $i++) : ?>
					<option value="<?php echo $i; ?>" <?php echo ($i == $this->options['anchor']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
				</select>
				<span class="description"><?php _e( 'The number of links to always show at beginning and end of pagination. Recommended value: 1', $this->localizationDomain ); ?></span></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'Page Gap:', $this->localizationDomain ); ?></th>
			<td>
				<select name="gap" id="gap">
				<?php for ($i=1; $i<=10; $i++) : ?>
					<option value="<?php echo $i; ?>" <?php echo ($i == $this->options['gap']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>
				<?php endfor; ?>
				</select>
				<span class="description"><?php _e( 'The minimum number of pages in a gap before an ellipsis (...) is added. Recommended value: 3', $this->localizationDomain ); ?></span></td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" value="Save Changes" name="wp_paginate_save" class="button-primary" />
	</p>
</form>
<h2><?php _e( 'Need Support?', $this->localizationDomain ); ?></h2>
<p><?php printf(__( 'For questions, issues or feature requests, please post them in the %s and make sure to tag the post with wp-paginate.', $this->localizationDomain), '<a href="http://wordpress.org/tags/wp-paginate?forum_id=10#postform">WordPress Forum</a>' ); ?></p>
<h2><?php _e( 'Like To Contribute?', $this->localizationDomain ); ?></h2>
<p><?php _e( 'If you would like to contribute, the following is a list of ways you can help:', $this->localizationDomain ); ?></p>
<ul>
	<li>&raquo; <?php _e( 'Translate WP-Paginate into your language', $this->localizationDomain ); ?></li>
	<li>&raquo; <?php _e( 'Blog about or link to WP-Paginate so others can find out about it', $this->localizationDomain ); ?></li>
	<li>&raquo; <?php _e( 'Report issues, provide feedback, request features, etc.', $this->localizationDomain ); ?></li>
	<li>&raquo; <a href="http://wordpress.org/extend/plugins/wp-paginate/"><?php _e( 'Rate WP-Paginate on the WordPress Plugins Page', $this->localizationDomain ); ?></a></li>
	<li>&raquo; <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=KUL9VQ6U5VYCE&lc=US&item_name=Eric%20Martin%20%28ericmmartin%2ecom%29&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted"><?php _e( 'Make a donation', $this->localizationDomain ); ?></a></li>
</ul>
<h2><?php _e( 'Other Links', $this->localizationDomain ); ?></h2>
<ul>
	<li>&raquo; <a href="http://twitter.com/ericmmartin">@ericmmartin</a> on Twitter</li>
	<li>&raquo; <a href="http://www.ericmmartin.com">EricMMartin.com</a></li>
	<li>&raquo; <a href="http://www.ericmmartin.com/projects/smcf/">SimpleModal Contact Form (SMCF) - WordPress Plugin</a></li>
	<li>&raquo; <a href="http://www.ericmmartin.com/projects/simplemodal-login/">SimpleModal Login - WordPress Plugin</a></li>
</ul>
</div>

<?php
		}
	}
}

//instantiate the class
if (class_exists( 'WPPaginate' )) {
	$wp_paginate = new WPPaginate();
}

/**
 * Pagination function to use for posts
 */
function wp_paginate($args = false) {
	global $wp_paginate;
	return $wp_paginate->paginate($args);
}

/**
 * Pagination function to use for post comments
 */
function wp_paginate_comments($args = false) {
	global $wp_paginate;
	$wp_paginate->type = 'comments';
	return $wp_paginate->paginate($args);
}

/*
 * The format of this plugin is based on the following plugin template:
 * http://pressography.com/plugins/wordpress-plugin-template/
 */
?>
