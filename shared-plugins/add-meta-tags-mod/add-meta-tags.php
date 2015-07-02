<?php
/*
Plugin Name: Add Meta Tags Mod
Description: Adds the <em>Description</em> and <em>Keywords</em> XHTML META tags to your blog's <em>front page</em> and to each one of the <em>posts</em>, <em>static pages</em> and <em>category archives</em>. This operation is automatic, but the generated META tags can be fully customized. Please read the tips and all other info provided at the <a href="options-general.php?page=amt_options">configuration panel</a>.
Version: 1.7-WPCOM
Author: George Notaras, Automattic
*/

/*
  This is significantly modified version of the add-meta-tags plugin.

  Original plugin by George Notaras (http://www.g-loaded.eu).
  Additional contributions by Thorsten Ott, Josh Betz, and others.

  Copyright 2007 George Notaras <gnot [at] g-loaded.eu>, CodeTRAX.org

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.
*/

class Add_Meta_Tags {

	/*

	INTERNAL Configuration Options

	1 - Include/Exclude the "keywords" metatag.

		The following option exists ONLY for those who do not want a "keywords"
		metatag META tag to be generated in "Single-Post-View", but still want the
		"description" META tag.
		
		Possible values: TRUE, FALSE
		Default: TRUE
	*/
	const INCLUDE_KEYWORDS_IN_SINGLE_POSTS = TRUE;

	/*
	Custom fields that hold post/page related seo content
	*/
	public $mt_seo_fields = array();

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		$this->mt_seo_fields = array(
			'mt_seo_title' => array( __( 'Title (optional) :', 'add-meta-tags' ), 'text', __( 'The text entered here will alter the &lt;title&gt; tag using the wp_title() function. Use <code>%title%</code> to include the original title or leave empty to keep original title. i.e.) altered title <code>%title%</code>', 'add-meta-tags' ) ),
			'mt_seo_description' => array( __( 'Description (optional) :', 'add-meta-tags' ), 'textarea', __( 'This text will be used as description meta information. Left empty a description is automatically generated i.e.) an other description text', 'add-meta-tags' ) ),
			'mt_seo_keywords' => array( __( 'Keywords (optional) :', 'add-meta-tags' ), 'text', __( 'Provide a comma-delimited list of keywords for your blog. Leave it empty to use the post\'s keywords for the "keywords" meta tag. When overriding the post\'s keywords, the tag <code>%cats%</code> can be used to insert the post\'s categories, add the tag <code>%tags%</code>, to include the post\'s tags i.e.) keyword1, keyword2,%tags% %cats%', 'add-meta-tags' ) ),
			'mt_seo_google_news_meta' => array( __( 'Google News Keywords (optional) :', 'add-meta-tags' ), 'text', __( 'Provide a comma-delimited list of keywords for your blog. You can add up to ten phrases for a given article, and all keywords are given equal value.', 'add-meta-tags' ) ),
			'mt_seo_meta' => array( __( 'Additional Meta tags (optional) :', 'add-meta-tags' ), 'textarea', __( 'Provide the full XHTML code of META tags you would like to be included in this post/page. i.e.) &lt;meta name="robots" content="index,follow" /&gt;', 'add-meta-tags' ) ),
		);
	}

	function init() {
		add_action( 'save_page',             array( $this, 'mt_seo_save_meta' ) );
		add_action( 'save_post',             array( $this, 'mt_seo_save_meta' ) );

		add_action( 'admin_menu',            array( $this, 'amt_add_pages' ) );
		add_action( 'add_meta_boxes',        array( $this, 'add_mt_seo_box' ) );

		add_action( 'wp_head',               array( $this, 'amt_add_meta_tags' ), 0 );
		add_action( 'admin_head',            array( $this, 'mt_seo_style' ) );
		add_filter( 'wp_title',              array( $this, 'mt_seo_rewrite_title' ), 0, 3);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts') );

		/*
		Translation Domain

		Translation files are searched in: wp-content/plugins
		*/
		load_plugin_textdomain('add-meta-tags');
	}

	function enqueue_scripts() {
		global $pagenow;
		// TODO: load on settings page; only for supportd post types
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) && in_array( get_post_type(), array( 'post', 'page' ) ) ) {
			wp_enqueue_script( 'add-meta-tags', plugins_url( 'js/add-meta-tags.js', __FILE__), array( 'jquery') );
		}
	}

	/*
	Admin Panel
	*/

	function add_mt_seo_box( $post_type ) {
		if ( $this->is_supported_post_type( $post_type ) ) {
			add_meta_box( 'mt_seo', 'SEO', array( $this, 'mt_seo_meta_box' ), $post_type, 'normal' );
		}
	}

	function amt_add_pages() {
		add_options_page( __('Meta Tags Options', 'add-meta-tags'), __('Meta Tags', 'add-meta-tags'), 'administrator', 'amt_options', array( $this, 'amt_options_page' ) );
	}

	function amt_show_info_msg($msg) {
		echo '<div id="message" class="updated fade"><p>' . $msg . '</p></div>';
	}

	function amt_clean_array( $array ) {
		$clean = array();
		foreach( $array as $key => $value ) {
			$clean[$key] = (boolean) $value;
		}
		return $clean;
	}
	function amt_options_page() {
		if (isset($_POST['info_update'])) {
			/*
			For a little bit more security and easier maintenance, a separate options array is used.
			*/

			$options = array(
				"site_description"	=>  wp_strip_all_tags( trim( stripslashes( $_POST["site_description"] ) ) ),
				"site_keywords"		=> wp_strip_all_tags( trim( stripslashes( $_POST["site_keywords"] ) ) ),
				"site_wide_meta"	=> wp_kses( trim( stripslashes( $_POST["site_wide_meta"] ) ), array(
					'meta' => array(
						'http-equiv'	=> array(),
						'name' 			=> array(),
						'property' 		=> array(),
						'content' 		=> array(),
					)
				) ),
				"post_options"      => ( is_array( $_POST["post_options"] ) ) ? $this->amt_clean_array( $_POST["post_options"] ) : array(),
				"page_options"      => ( is_array( $_POST["page_options"] ) ) ? $this->amt_clean_array( $_POST["page_options"] ) : array(),
				"custom_post_types" => ( isset($_POST["custom_post_types"]) && is_array( $_POST["custom_post_types"] ) ) ? $this->amt_clean_array( $_POST["custom_post_types"] ) : array(),
				);
			update_option("add_meta_tags_opts", $options);
			$this->amt_show_info_msg(__('Add-Meta-Tags options saved.', 'add-meta-tags'));

		} elseif (isset($_POST["info_reset"])) {

			delete_option("add_meta_tags_opts");
			$this->amt_show_info_msg(__('Add-Meta-Tags options deleted from the WordPress database.', 'add-meta-tags'));

			/*
			The following exists for deleting old add-meta-tags options (version 1.0 or older).
			The following statement have no effect if the options do not exist.
			This is 100% safe (TM).
			*/
			delete_option('amt_site_description');
			delete_option('amt_site_keywords');

		} else {

			$options = get_option("add_meta_tags_opts");

		}

		$post_options = $options['post_options'];
		$page_options = $options['page_options'];
		$custom_post_types = $options['custom_post_types'];
		
		// good defaults is the hallmark of good software
		if ( !is_array( $post_options ) )
			$post_options = array( 'mt_seo_title' => true, 'mt_seo_description' => true, 'mt_seo_keywords' => true, 'mt_seo_meta' => true, 'mt_seo_google_news_meta' => true );
		if ( !is_array( $page_options ) )
			$page_options = array( 'mt_seo_title' => true, 'mt_seo_description' => true, 'mt_seo_keywords' => true, 'mt_seo_meta' => true, 'mt_seo_google_news_meta' => true );
		if ( ! is_array( $custom_post_types ) )
			$custom_post_types = array();
		
		$registered_post_types = get_post_types( array(
			'public'   => true,
			'show_ui'  => true,
			'_builtin' => false,
		), 'objects' );

		// Because the options page is a single print() statement, and
		// registered post types are variable, we will do sanity checks here and
		// generate the html that's used in the print() statement. Store
		// checkbox HTML in an array so we can easily join them with a comma
		$post_type_checkboxes = array();
		if ( $registered_post_types ) {
			foreach ( $registered_post_types as $post_type ) {
				$post_type_checkboxes[] = '<label>' . esc_html( $post_type->labels->name ) . ' (' . $post_type->name . ') : <input type="checkbox" name="custom_post_types[' . esc_attr( $post_type->name ) . ']" value="true" ' . checked( isset($options['custom_post_types'][$post_type->name]), true, false )  . ' /></label>';
			}
			$registered_post_type_checkbox_html = implode( ' , ', $post_type_checkboxes );
		} else {
			$registered_post_type_checkbox_html = __('No public custom post types are registered.', 'add-meta-tags');
		}
		$registered_post_type_checkbox_html .= '<br /><span class="description">' . __('Not seeing a custom post type you expect?  Post types must have the following parameters: public, show_ui', 'add-meta-tags') . '</span>';

		/*
		Configuration Page
		*/
		
		print('
		<div class="wrap" id="amt-header">
			<h2>'.__('Add-Meta-Tags', 'add-meta-tags').'</h2>
			<p>'.__('This is where you can configure the Add-Meta-Tags plugin and read about how the plugin adds META tags in the WordPress pages.', 'add-meta-tags').'</p>
			<p>'.__('Modifying any of the settings in this page is completely <strong>optional</strong>, as the plugin will add META tags automatically.', 'add-meta-tags').'</p>
			<p>'.__("For more information about the plugin's default behaviour and how you could customize the metatag generation can be found in detail in the sections that follow.", "add-meta-tags").'</p>
		</div>


		<form name="formamt" method="post" action="' . esc_attr( $_SERVER['REQUEST_URI'] ) . '">

		<div class="wrap" id="amt-config-site-wide">
			<h2>'.__('Configuration', 'add-meta-tags').'</h2>

				<fieldset class="options">
					<legend>'.__('Site Description', 'add-meta-tags').'<br />
						<p>'.__('The following text will be used in the "description" meta tag on the <strong>homepage only</strong>. If this is left <strong>empty</strong>, then the blog\'s description from the <em>General Options</em> (Tagline) will be used.', 'add-meta-tags').'</p>
						<p><textarea name="site_description" id="site_description" cols="40" rows="3" style="width: 80%; font-size: 14px;" class="code">' . esc_textarea( stripslashes( $options["site_description"] ) ) . '</textarea></p>
					</legend>
				</fieldset>

				<fieldset class="options">
					<legend>'.__('Site Keywords', 'add-meta-tags').'<br />
						<p>'.__('The following keywords will be used for the "keywords" meta tag on the <strong>homepage only</strong>. Provide a comma-delimited list of keywords for your blog. If this field is left <strong>empty</strong>, then all of your blog\'s categories will be used as keywords for the "keywords" meta tag.', 'add-meta-tags').'</p>
						<p><textarea name="site_keywords" id="site_keywords" cols="40" rows="3" style="width: 80%; font-size: 14px;" class="code">' . esc_textarea( stripslashes( $options["site_keywords"] ) ) . '</textarea></p>
						<p><strong>'.__('Example', 'add-meta-tags').'</strong>: <code>'.__('keyword1, keyword2, keyword3', 'add-meta-tags').'</code></p>
					</legend>
				</fieldset>

				<fieldset class="options">
					<legend>'.__('Site-wide META tags', 'add-meta-tags').'<br />
						<p>'.__('Provide the <strong>full XHTML code</strong> of META tags you would like to be included in <strong>all</strong> of your blog pages.', 'add-meta-tags').'</p>
						<p><textarea name="site_wide_meta" id="site_wide_meta" cols="40" rows="10" style="width: 80%; font-size: 14px;" class="code">' . esc_textarea( stripslashes( $options["site_wide_meta"] ) ) . '</textarea></p>
						<p><strong>'.__('Example', 'add-meta-tags').'</strong>: <code>&lt;meta name="robots" content="index,follow" /&gt;</code></p>
					</legend>
				</fieldset>

				<p class="submit">
					<input type="submit" name="info_update" value="'.__('Update Options', 'add-meta-tags').' &raquo;" />
				</p>

		</div>

		<div class="wrap" id="amt-header-frontpage"> 
			<h2>'.__('Meta Tags on the Front Page', 'add-meta-tags').'</h2>
			<p>'.__('If a site description and/or keywords have been set in the Add-Meta-Tags options above, then those will be used in the "<em>description</em>" and "<em>keywords</em>" META tags respectively.', 'add-meta-tags').'</p>
			<p>'.__('Alternatively, if the above options are not set, then the blog\'s description from the <em>General</em> WordPress options will be used in the "<em>description</em>" META tag, while all of the blog\'s categories, except for the "Uncategorized" category, will be used in the "<em>keywords</em>" META tag.', 'add-meta-tags').'</p>
		</div>

		<div class="wrap" id="amt-config-single">
			<h2>'.__('Meta Tags on Single Posts', 'add-meta-tags').'</h2>
			<p>'.__('Although no configuration is needed in order to put meta tags on single posts, the following information will help you customize them.', 'add-meta-tags').'</p>
			<p>'.__('By default, when a single post is displayed, the post\'s excerpt and the post\'s categories and tags are used in the "description" and the "keywords" meta tags respectively.', 'add-meta-tags').'</p>
			<p>'.__('It is possible to override them by providing a custom description in a custom field named "<strong>description</strong>" and a custom comma-delimited list of keywords by providing it in a custom field named "<strong>keywords</strong>".', 'add-meta-tags').'</p>
			<p>'.__("Furthermore, when overriding the post's keywords, but you need to include the post's categories too, you don't need to type them, but the tag <code>%cats%</code> can be used. In the same manner you can also include your tags in this custom field by adding the word <code>%tags%</code>, which will be replaced by your post's tags.", "add-meta-tags").'</p>
			<p><strong>'.__('Example', 'add-meta-tags').':</strong> <code>'.__('keyword1, keyword2, %cats%, keyword3, %tags%, keyword4', 'add-meta-tags').'</code></p>

			<p><strong>' . __('Enable the following options for posts:', 'add-meta-tags') . '</strong>
			' . __( 'Title', 'add-meta-tags' ) . ' : <input type="checkbox" name="post_options[mt_seo_title]" value="true" ' . ( ( $post_options["mt_seo_title"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Description', 'add-meta-tags' ) . ' : <input type="checkbox" name="post_options[mt_seo_description]" value="true" ' . ( ( $post_options["mt_seo_description"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Keywords', 'add-meta-tags' ) . ' : <input type="checkbox" name="post_options[mt_seo_keywords]" value="true" ' . ( ( $post_options["mt_seo_keywords"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Meta', 'add-meta-tags' ) . ' : <input type="checkbox" name="post_options[mt_seo_meta]" value="true" ' . ( ( $post_options["mt_seo_meta"] ) ? 'checked="checked"' : '' ) . ' />
			' . __( 'Google News Meta', 'add-meta-tags' ) . ' : <input type="checkbox" name="post_options[mt_seo_google_news_meta]" value="true" ' . ( ( $post_options["mt_seo_google_news_meta"] ) ? 'checked="checked"' : '' ) . ' />
			</p>

			<p><strong>' . __('Enable for the following post types:', 'add-meta-tags') . '</strong>
			' . $registered_post_type_checkbox_html . '
			</p>

			<p class="submit">
				<input type="submit" name="info_update" value="'.__('Update Options', 'add-meta-tags').' &raquo;" />
			</p>

		</div>

		<div class="wrap" id="amt-config-pages">
			<h2>'.__('Meta Tags on Pages', 'add-meta-tags').'</h2>
			<p>'.__('By default, meta tags are not added automatically when viewing Pages. However, it is possible to define a description and a comma-delimited list of keywords for the Page, by using custom fields named "<strong>description</strong>" and/or "<strong>keywords</strong>" as described for single posts.', 'add-meta-tags').'</p>
			<p>'.__('<strong>WARNING</strong>: Pages do not belong to categories in WordPress. Therefore, the tag <code>%cats%</code> will not be replaced by any categories if it is included in the comma-delimited list of keywords for the Page, so <strong>do not use it for Pages</strong>.', 'add-meta-tags').'</p>

			<p><strong>' . __('Enable the following options for pages:', 'add-meta-tags') . '</strong>
			' . __( 'Title', 'add-meta-tags' ) . ' : <input type="checkbox" name="page_options[mt_seo_title]" value="true" ' . ( ( $page_options["mt_seo_title"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Description', 'add-meta-tags' ) . ' : <input type="checkbox" name="page_options[mt_seo_description]" value="true" ' . ( ( $page_options["mt_seo_description"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Keywords', 'add-meta-tags' ) . ' : <input type="checkbox" name="page_options[mt_seo_keywords]" value="true" ' . ( ( $page_options["mt_seo_keywords"] ) ? 'checked="checked"' : '' ) . ' /> , 
			' . __( 'Meta', 'add-meta-tags' ) . ' : <input type="checkbox" name="page_options[mt_seo_meta]" value="true" ' . ( ( $page_options["mt_seo_meta"] ) ? 'checked="checked"' : '' ) . ' />
			' . __( 'Google News Meta', 'add-meta-tags' ) . ' : <input type="checkbox" name="page_options[mt_seo_google_news_meta]" value="true" ' . ( ( $page_options["mt_seo_google_news_meta"] ) ? 'checked="checked"' : '' ) . ' />
			</p>
			<p class="submit">
				<input type="submit" name="info_update" value="'.__('Update Options', 'add-meta-tags').' &raquo;" />
			</p>

		</div>
		</form>
		<div class="wrap" id="amt-header-category">
			<h2>'.__('Meta Tags on Category Archives', 'add-meta-tags').'</h2>
			<p>'.__('META tags are automatically added to Category Archives, for example when viewing all posts that belong to a specific category. In this case, if you have set a description for that category, then this description is added to a "description" META tag.', 'add-meta-tags').'</p>
			<p>'.__('Furthermore, a "keywords" META tag - containing only the category\'s name - is always added to Category Archives.', 'add-meta-tags').'</p>
		</div>

		<div class="wrap" id="amt-config-reset">
			<h2>'.__('Reset Plugin', 'add-meta-tags').'</h2>
			<form name="formamtreset" method="post" action="' . esc_attr( $_SERVER['REQUEST_URI'] ) . '">
				<p>'.__('By pressing the "Reset" button, the plugin will be reset. This means that the stored options will be deleted from the WordPress database. Although it is not necessary, you should consider doing this before uninstalling the plugin, so no trace is left behind.', 'add-meta-tags').'</p>
				<p class="submit">
					<input type="submit" name="info_reset" value="'.__('Reset Options', 'add-meta-tags').'" />
				</p>
			</from>
		</div>

		');

	}

	function amt_clean_desc($desc) {
		/*
		This is a filter for the description metatag text.
		*/
		$desc = stripslashes($desc);
		$desc = strip_tags($desc);
		$desc = htmlspecialchars($desc);
		//$desc = preg_replace('/(\n+)/', ' ', $desc);
		$desc = preg_replace('/([\n \t\r]+)/', ' ', $desc); 
		$desc = preg_replace('/( +)/', ' ', $desc);
		return trim($desc);
	}


	function amt_get_the_excerpt($excerpt_max_len = 300, $desc_avg_length = 250, $desc_min_length = 150) {
		/*
		Returns the post's excerpt.
		This was written in order to get the excerpt *outside* the loop
		because the get_the_excerpt() function does not work there any more.
		This function makes the retrieval of the excerpt independent from the
		WordPress function in order not to break compatibility with older WP versions.
		
		Also, this is even better as the algorithm tries to get text of average
		length 250 characters, which is more SEO friendly. The algorithm is not
		perfect, but will do for now.
		*/
		global $posts;

		if ( empty($posts[0]->post_excerpt) ) {

			$post_content = strip_tags( strip_shortcodes( $posts[0]->post_content ) );

			/*
			Get the initial data for the excerpt
			*/
			$amt_excerpt = substr( $post_content, 0, $excerpt_max_len );

			/*
			If this was not enough, try to get some more clean data for the description (nasty hack)
			*/
			if ( strlen($amt_excerpt) < $desc_avg_length ) {
				$amt_excerpt = substr( $post_content, 0, (int) ($excerpt_max_len * 1.5) );
				if ( strlen($amt_excerpt) < $desc_avg_length ) {
					$amt_excerpt = substr( $post_content, 0, (int) ($excerpt_max_len * 2) );
				}
			}

			$end_of_excerpt = strrpos($amt_excerpt, ".");

			if ($end_of_excerpt) {
				
				/*
				if there are sentences, end the description at the end of a sentence.
				*/
				$amt_excerpt_test = substr($amt_excerpt, 0, $end_of_excerpt + 1);

				if ( strlen($amt_excerpt_test) < $desc_min_length ) {
					/*
					don't end at the end of the sentence because the description would be too small
					*/
					$amt_excerpt .= "...";
				} else {
					/*
					If after ending at the end of a sentence the description has an acceptable length, use this
					*/
					$amt_excerpt = $amt_excerpt_test;
				}
			} else {
				/*
				otherwise (no end-of-sentence in the excerpt) add this stuff at the end of the description.
				*/
				$amt_excerpt .= "...";
			}

		} else {
			/*
			When the post excerpt has been set explicitly, then it has priority.
			*/
			$amt_excerpt = $posts[0]->post_excerpt;
		}

		return apply_filters( 'amt_get_the_excerpt', $amt_excerpt, $posts[0] );
	}


	function amt_get_keywords_from_post_cats() {
		/*
		Returns a comma-delimited list of a post's categories.
		*/
		global $posts;

		$postcats = "";
		foreach((get_the_category($posts[0]->ID)) as $cat) {
			$postcats .= $cat->cat_name . ', ';
		}
		$postcats = substr($postcats, 0, -2);

		return $postcats;
	}

	function amt_get_post_tags() {
		/*
		Retrieves the post's user-defined tags.
		
		This will only work in WordPress 2.3 or newer. On older versions it will
		return an empty string.
		*/
		global $posts;
		
		if ( version_compare( get_bloginfo('version'), '2.3', '>=' ) || 'MU' == get_bloginfo('version') ) {
			$tags = get_the_tags($posts[0]->ID);
			if ( empty( $tags ) ) {
				return false;
			} else {
				$tag_list = "";
				foreach ( $tags as $tag ) {
					$tag_list .= $tag->name . ', ';
				}
				$tag_list = strtolower(rtrim($tag_list, " ,"));
				return $tag_list;
			}
		} else {
			return "";
		}
	}


	function amt_get_all_categories($no_uncategorized = TRUE) {
		/*
		Returns a comma-delimited list of some of the blog's categories.
		The built-in category "Uncategorized" is excluded.
		*/
		if ( ! $categories = wp_cache_get( 'amt_get_all_categories', 'category' ) ) {
			$categories = get_terms( 'category', array( 'fields' => 'names', 'get' => 'all', 'number' => 20, 'orderby' => 'count' ) ); // limit to 20 to avoid killer queries
			wp_cache_add( 'amt_get_all_categories', $categories, 'category' );
		}
		
		if ( empty( $categories ) )
			return '';
		
		$all_cats = "";
		foreach ( $categories as $cat ) {
			if ( $no_uncategorized && $cat != "Uncategorized" ) {
				$all_cats .= $cat . ', ';
			}
		}
		$all_cats = strtolower( rtrim( $all_cats, " ," ) );
		return $all_cats;
	}


	function amt_get_site_wide_metatags($site_wide_meta) {
		/*
		This is a filter for the site-wide meta tags.
		*/
		$site_wide_meta = stripslashes($site_wide_meta);
		$site_wide_meta = trim($site_wide_meta);
		return $site_wide_meta;
	}

	function amt_add_meta_tags() {
		/*
		This is the main function that actually writes the meta tags to the
		appropriate page.
		*/
		global $posts;

		/*
		Get the options the DB
		*/
		$options = get_option("add_meta_tags_opts");
		$site_wide_meta = $options["site_wide_meta"];

		if ( isset( $posts[0] ) && $this->is_supported_post_type( $posts[0]->post_type ) ) {
			if ( 'page' == $posts[0]->post_type )
				$cmpvalues = $options['page_options'];
			else
				$cmpvalues = $options['post_options'];
		} else {
			$cmpvalues = array();
		}

		if ( !is_array( $cmpvalues ) )
			$cmpvalues = array( 'mt_seo_title' => true, 'mt_seo_description' => true, 'mt_seo_keywords' => true, 'mt_seo_meta' => true, 'mt_seo_google_news_meta' => true );

		$cmpvalues = $this->amt_clean_array( $cmpvalues );
		$my_metatags = "";

		// nothing allowed so just return
		if ( empty( $cmpvalues ) )
			return;
		
		if ( is_singular() ) {
			/*
			Add META tags to Single Page View or Page
			*/
			foreach( (array) $this->mt_seo_fields as $field_name => $field_data )  {
				${$field_name} = (string) get_post_meta( $posts[0]->ID, $field_name, true );

				// Back-compat with Yoast SEO meta keys
				if ( '' == ${$field_name} ) {
					switch( $field_name ) {
						case 'mt_seo_title':
							$yoast_field_name = '_yoast_wpseo_title';
							break;
						case 'mt_seo_description':
							$yoast_field_name = '_yoast_wpseo_metadesc';
							break;
					}
					if ( isset( $yoast_field_name ) )
						${$field_name} = (string) get_post_meta( $posts[0]->ID, $yoast_field_name, true );
				}
			}

			/*
			Description
			Custom post field "description" overrides post's excerpt in Single Post View.
			*/
			if ( true == $cmpvalues['mt_seo_description'] ) {
				$meta_description = '';
				if ( !empty($mt_seo_description) ) {
					/*
					  If there is a custom field, use it
					*/
					$meta_description = $mt_seo_description;
				} elseif ( is_single() ) {
					/*
					  Else, use the post's excerpt. Only for Single Post View (not valid for Pages)
					*/
					 $meta_description = $this->amt_get_the_excerpt();
				}

				// WPCOM -- allow filtering of the meta description
				$meta_description = apply_filters( 'amt_meta_description', $meta_description );
				if ( ! empty( $meta_description ) ) {
					$my_metatags .= "\n<meta name=\"description\" content=\"" . esc_attr( $this->amt_clean_desc( $meta_description ) ) . "\" />";
				}
			}
			/*
			Meta
			Custom post field "mt-seo-meta" adds additional meta tags
			*/
			if ( !empty($mt_seo_meta) && true == $cmpvalues['mt_seo_meta'] ) {
				/*
				If there is a custom field, use it
				*/
				$my_metatags .= "\n" . $mt_seo_meta;
			}
			/*
			Google News Meta
			Custom post field "mt-seo-google-news-meta" adds additional meta tags
			*/
			if ( !empty($mt_seo_google_news_meta) && true == $cmpvalues['mt_seo_google_news_meta'] ) {
				/*
				If there is a custom field, use it
				*/
				$my_metatags .= '<meta name="news_keywords" content="' . esc_attr( $mt_seo_google_news_meta ) . '" />';
			}



			/*
			Title
			Rewrite the title in case a special title is given
			*/
			//if ( !empty( $mt_seo_title ) ) {
			// see function mt_seo_rewrite_tite() which is added as filter for wp_title
			//}

			
			/*
			Keywords
			Custom post field "keywords" overrides post's categories and tags (tags exist in WordPress 2.3 or newer).
			%cats% is replaced by the post's categories.
			%tags% us replaced by the post's tags.
			NOTE: if self::INCLUDE_KEYWORDS_IN_SINGLE_POSTS is FALSE, then keywords
			metatag is not added to single posts.
			*/
			if ( true == $cmpvalues['mt_seo_keywords'] ) {
				if ( ( self::INCLUDE_KEYWORDS_IN_SINGLE_POSTS && is_single()) || is_page() ) {
					if ( !empty($mt_seo_keywords) ) {
						/*
						  If there is a custom field, use it
						*/
						if ( is_single() ) {
							/*
							  For single posts, the %cat% tag is replaced by the post's categories
							*/
								$mt_seo_keywords = str_replace("%cats%", $this->amt_get_keywords_from_post_cats(), $mt_seo_keywords);
							/*
							  Also, the %tags% tag is replaced by the post's tags (WordPress 2.3 or newer)
							*/
								if ( version_compare( get_bloginfo('version'), '2.3', '>=' ) || 'MU' == get_bloginfo('version') ) {
									$mt_seo_keywords = str_replace("%tags%", $this->amt_get_post_tags(), $mt_seo_keywords);
								}
						}
						$my_metatags .= "\n<meta name=\"keywords\" content=\"" . esc_attr( strtolower($mt_seo_keywords) ) . "\" />";
					} elseif ( is_single() ) {
						/*
						  Add keywords automatically.
						  Keywords consist of the post's categories and the post's tags (tags exist in WordPress 2.3 or newer).
						  Only for Single Post View (not valid for Pages)
						*/
						$post_keywords = strtolower( $this->amt_get_keywords_from_post_cats() );
						$post_tags = strtolower( $this->amt_get_post_tags() );

						$my_metatags .= "\n<meta name=\"keywords\" content=\"" . esc_attr( $post_keywords .', '. $post_tags ) .'" />';
					}
				}
			}
		} elseif ( is_home() ) {
			/*
			Add META tags to Home Page
			*/
			
			/*
			Description and Keywords from the options override default behaviour
			*/
			$site_description = $options["site_description"];
			$site_keywords = $options["site_keywords"];

			/*
			Description
			*/
			if ( empty($site_description) ) {
				/*
				If $site_description is empty, then use the blog description from the options
				*/
				$my_metatags .= "\n<meta name=\"description\" content=\"" . esc_attr( $this->amt_clean_desc(get_bloginfo('description')) ) . "\" />";
			} else {
				/*
				If $site_description has been set, then use it in the description meta-tag
				*/
				$my_metatags .= "\n<meta name=\"description\" content=\"" . esc_attr( $this->amt_clean_desc($site_description) ) . "\" />";
			}
			/*
			Keywords
			*/
			if ( empty($site_keywords) ) {
				/*
				If $site_keywords is empty, then all the blog's categories are added as keywords
				*/
				$my_metatags .= "\n<meta name=\"keywords\" content=\"" . esc_attr( $this->amt_get_all_categories() ) . "\" />";
			} else {
				/*
				If $site_keywords has been set, then these keywords are used.
				*/
				$my_metatags .= "\n<meta name=\"keywords\" content=\"" . esc_attr( $site_keywords ) . "\" />";
			}


		} elseif ( is_tax() || is_tag() || is_category() ) {
			/*
			Writes a description META tag only if a description for the current term has been set.
			*/

			$cur_cat_desc = term_description();
			if ( $cur_cat_desc ) {
				$my_metatags .= '<meta name="description" content="' . esc_attr( $this->amt_clean_desc($cur_cat_desc) ) . '" />';
			}
			
			/*
			Write a keyword metatag if there is a term name (always)
			*/
			$cur_cat_name = single_term_title( '', false );
			if ( $cur_cat_name ) {
				$my_metatags .= '<meta name="keywords" content="' . esc_attr( strtolower($cur_cat_name) ) . '" />';
			}
		}

		if ( $site_wide_meta ) 
			$my_metatags .= $this->amt_get_site_wide_metatags($site_wide_meta) . PHP_EOL;

		// WP.com -- allow filtering of the meta tags
		$my_metatags = apply_filters( 'amt_metatags', $my_metatags );

		if ($my_metatags) {
			echo $my_metatags . PHP_EOL;
		}
	}

	/*
	SEO Write panel
	*/
	function mt_seo_meta_box( $post, $meta_box ) {
		global $pagenow;
		$this->mt_seo_fields = apply_filters('mt_seo_fields', $this->mt_seo_fields, $post, $meta_box);
		if ( $post_id = (int) $post->ID ) {
			foreach( (array) $this->mt_seo_fields as $field_name => $field_data ) {
				${$field_name} = (string) get_post_meta( $post_id, $field_name, true );

				// back-compat with Yoast SEO
				if ( '' == ${$field_name} ) {
					switch( $field_name ) {
						case 'mt_seo_title':
							$yoast_field_name = '_yoast_wpseo_title';
							break;
						case 'mt_seo_description':
							$yoast_field_name = '_yoast_wpseo_metadesc';
							break;
					}
					if ( isset( $yoast_field_name ) )
						${$field_name} = (string) get_post_meta( $post_id, $yoast_field_name, true );
				}
			}
		} else {
			foreach( (array) $this->mt_seo_fields as $field_name => $field_data ) 
				${$field_name} = '';
		}
		$tabindex = $tabindex_start = 5000;

		$options = get_option("add_meta_tags_opts");

		if ( stristr( $pagenow, 'page' ) )
			$cmpvalues = $options['page_options'];
		elseif ( stristr( $pagenow, 'post' ) )
			$cmpvalues = $options['post_options'];

		if ( !is_array( $cmpvalues ) )
			$cmpvalues = array( 'mt_seo_title' => true, 'mt_seo_description' => true, 'mt_seo_keywords' => true, 'mt_seo_meta' => true, 'mt_seo_google_news_meta' => true );

		$cmpvalues = $this->amt_clean_array( $cmpvalues );

		$title = ( '' == $mt_seo_title ) ? get_the_title() : $mt_seo_title;
		$title = str_replace( '%title%', get_the_title(), $title );
		echo '<div class="form-field mt_seo_preview form_field">';
		echo '<h4>Preview</h4>';
		echo '<div class="mt-form-field-contents">';
		echo '<div id="mt_snippet">';
		echo '<a href="#" class="title">' . substr( $title, 0, 70 ) . '</a><br>';
		echo '<a href="#" class="url">' . get_permalink() . '</a> - <a href="#" class="util">Cached</a>';
		echo '<p class="desc"><span class="date">' . date( 'd M Y', strtotime( get_the_time( 'r' ) ) ) . '</span> &ndash; <span class="content">' . substr( $mt_seo_description, 0, 140 ) . '</span></p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		
		foreach( (array) $this->mt_seo_fields as $field_name => $field_data ) {
			if ( empty( $cmpvalues[$field_name] ) )
				continue;

			if( 'textarea' == $field_data[1] ) {
				echo '<div class="form-field ' . esc_attr($field_name) . '"><h4><label for="' . $field_name . '">' . $field_data[0] . '</label></h4>';
				echo '<div class="mt-form-field-contents"><p><textarea class="wide-seo-box" rows="4" cols="40" tabindex="' . $tabindex . '" name="' . $field_name . '"';
				echo 'id="' . $field_name .'">' . esc_textarea( ${$field_name} ) . '</textarea></p>';
				echo '<p class="description">' . $field_data[2] . "</p></div></div>\n";
			} else if ( 'text' == $field_data[1] ) {
				echo '<div class="form-field ' . esc_attr($field_name) . '"><h4><label for="' . $field_name .'">' . $field_data[0] . '</label></h4>';
				echo '<div class="mt-form-field-contents"><p><input type="text" class="wide-seo-box" tabindex="' . $tabindex . '" name="' . $field_name . '" id="' . $field_name . '" value="' . esc_attr( ${$field_name} ) . '" /></p>';
				echo '<p class="description">' . $field_data[2] . "</p></div></div>\n";
			}
			$tabindex++;
		}
		if ( $tabindex == $tabindex_start )
			echo '<p>' . __( 'No SEO fields were enabled. Please enable post fields in the Meta Tags options page', 'add-meta-tags' )  . '</p>';
		
		wp_nonce_field( 'mt-seo', 'mt_seo_nonce', false );
	}

	function mt_seo_save_meta( $post_id ) {
		foreach( (array) $this->mt_seo_fields as $field_name => $field_data ) 
			$this->mt_seo_save_meta_field( $post_id, $field_name );
	}

	function mt_seo_save_meta_field( $post_id, $field_name ) {
		// Checks to see if we're POSTing
		if ( !isset( $_SERVER['REQUEST_METHOD'] ) || 'post' !== strtolower( $_SERVER['REQUEST_METHOD'] ) || !isset($_POST[$field_name]) )
			return;

		// Bail if not a valid post type
		$options = get_option("add_meta_tags_opts");

		$valid_post_types = array( 'post', 'page' );

		if ( ! empty( $options['custom_post_types'] ) ) {
			$valid_post_types = array_merge( $valid_post_types, array_keys( (array) $options['custom_post_types'] ) );
		}

		if( ! isset( $_POST['post_type'] ) || ! in_array( $_POST['post_type'], $valid_post_types ) )
			return;

		$post_type = $_POST['post_type'];

		// Checks to make sure we came from the right page
		if ( !wp_verify_nonce( $_POST['mt_seo_nonce'], 'mt-seo' ) )
			return;

		// Checks user caps
		$post_type_object = get_post_type_object($post_type);
		if ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) )
			return;

		// Already have data?
		$old_data = get_post_meta( $post_id, $field_name, true );

		$data = isset( $_POST[$field_name] ) ? $_POST[$field_name] : '';
		$data = apply_filters( 'mt_seo_save_meta_field', $data, $field_name, $old_data, $post_id );

		// Sanitize
		if( 'mt_seo_meta' == $field_name ) {
			$data = wp_kses( trim( stripslashes( $data ) ), array(
				'meta' => array(
					'http-equiv' => array(),
					'name' => array(),
					'property' => array(),
					'content' => array(),
				)
			) );
		} else {
			$data = wp_filter_post_kses( $data );
			$data = trim( stripslashes( $data ) );
		}
		// nothing new, and we're not deleting the old
		if ( !$data && !$old_data )
			return;

		// Nothing new, and we're deleting the old
		if ( !$data && $old_data ) {
			delete_post_meta( $post_id, $field_name );
			return;
		}

		// Nothing to change
		if ( $data === $old_data )
			return;

		// Save the data
		if ( $old_data ) {
			update_post_meta( $post_id, $field_name, $data );
		} else {
			if ( !add_post_meta( $post_id, $field_name, $data, true ) )
				update_post_meta( $post_id, $field_name, $data ); // Just in case it was deleted and saved as ""
		}

		// Remove old Yoast data
		delete_post_meta( $post_id, '_yoast_wpseo_metadesc' );
		delete_post_meta( $post_id, '_yoast_wpseo_title' );
	}

	function mt_seo_style() {
		?>
	<style type="text/css">
	.wide-seo-box {
		margin: 0;
		width: 98%;
	}

	#mt_snippet { width: 540px; background: white; padding: 10px; }
	#mt_snippet .title { color: #11c; font-size: 16px; line-height: 19px; }
	#mt_snippet .url { font-size: 13px; color: #282; line-height: 15px; text-decoration: none; }
	#mt_snippet .util { color: #4272DB; text-decoration: none; }
	#mt_snippet .url:hover,
	#mt_snippet .util:hover { text-decoration: underline; }
	#mt_snippet .desc { font-size: 13px; color: #000; line-height: 15px; margin: 0; }
	#mt_snippet .date { color: #666; }
	#mt_snippet .content { color: #000; }

	.mt_counter .count { font-weight: bold; }
	.mt_counter .positive { color: green; }
	.mt_counter .negative { color: red; }
	</style>
		<?php
	}

	function mt_seo_rewrite_title( $title, $sep = '' , $seplocation = '' ) {
		global $posts;

		if ( !is_single() && !is_page() )
			return $title;

		$options = get_option("add_meta_tags_opts");
		
		if ( isset( $posts[0] ) && $this->is_supported_post_type( $posts[0]->post_type ) ) {
			if ( 'page' == $posts[0]->post_type )
				$cmpvalues = $options['page_options'];
			else
				$cmpvalues = $options['post_options'];
		} else {
			$cmpvalues = array();
		}
		
		if ( !is_array( $cmpvalues ) )
			$cmpvalues = array( 'mt_seo_title' => true, 'mt_seo_description' => true, 'mt_seo_keywords' => true, 'mt_seo_meta' => true );

		$cmpvalues = $this->amt_clean_array( $cmpvalues );
		
		if ( ! isset($cmpvalues['mt_seo_title']) || true != $cmpvalues['mt_seo_title'] )
			return $title;
		
		$mt_seo_title = (string) get_post_meta( $posts[0]->ID, 'mt_seo_title', true );
		if ( empty( $mt_seo_title ) )
			return $title;
		
		$mt_seo_title = str_replace("%title%", $title, $mt_seo_title);
		$mt_seo_title = strip_tags( $mt_seo_title );
		
		if ( apply_filters( 'mt_seo_title_append_separator', true ) && ! empty( $sep ) ) {
			if ( 'right' == $seplocation ) {
				$mt_seo_title .= " $sep ";
			} else {
				$mt_seo_title = " $sep " . $mt_seo_title;
			}
		}
		return $mt_seo_title;
	}

	static function is_supported_post_type( $post_type ) {
		$options = get_option( 'add_meta_tags_opts' );

		if ( empty( $options['custom_post_types'] ) )
			$options['custom_post_types'] = array();

		$supported_post_types = array_merge( array( 'post', 'page' ), array_keys( $options['custom_post_types'] ) );
		return in_array( $post_type, $supported_post_types );
	}

	static function post_has_seo_title( $post_id = null ) {
		$_post = get_post( $post_id );
		if ( ! $_post || ! $_post->ID ) {
			return false;
		}
		$mt_seo_title = (string) get_post_meta( $_post->ID, 'mt_seo_title', true );
		return ! empty( $mt_seo_title );
	}

}

$mt_add_meta_tags = new Add_Meta_Tags();
