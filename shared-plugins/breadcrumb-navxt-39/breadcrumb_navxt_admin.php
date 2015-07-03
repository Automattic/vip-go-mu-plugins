<?php
/*
Plugin Name: Breadcrumb NavXT
Plugin URI: http://mtekk.us/code/breadcrumb-navxt/
Description: Adds a breadcrumb navigation showing the visitor&#39;s path to their current location. For details on how to use this plugin visit <a href="http://mtekk.us/code/breadcrumb-navxt/">Breadcrumb NavXT</a>. 
Version: 3.9.0
Author: John Havlik
Author URI: http://mtekk.us/
*/
/*  Copyright 2007-2011  John Havlik  (email : mtekkmonkey@gmail.com)

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
//Do a PHP version check, require 5.2 or newer
if(version_compare(phpversion(), '5.2.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function bcn_phpold()
	{
		printf('<div class="error"><p>' . __('Your PHP version is too old, please upgrade to a newer version. Your version is %s, Breadcrumb NavXT requires %s', 'breadcrumb_navxt') . '</p></div>', phpversion(), '5.2.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'bcn_phpold');
	}
	return;
}
//Include the breadcrumb class
require_once(dirname(__FILE__) . '/breadcrumb_navxt_class.php');
//Include the WP 2.8+ widget class
require_once(dirname(__FILE__) . '/breadcrumb_navxt_widget.php');
//Include admin base class
if(!class_exists('mtekk_admin'))
{
	require_once(dirname(__FILE__) . '/mtekk_admin_class.php');
}
/**
 * The administrative interface class 
 * 
 */
class bcn_admin extends mtekk_admin
{
	/**
	 * local store for breadcrumb version
	 * 
	 * @var   string
	 */
	protected $version = '3.9.0';
	protected $full_name = 'Breadcrumb NavXT Settings';
	protected $short_name = 'Breadcrumb NavXT';
	protected $access_level = 'manage_options';
	protected $identifier = 'breadcrumb_navxt';
	protected $unique_prefix = 'bcn';
	protected $plugin_basename = 'breadcrumb-navxt/breadcrumb_navxt_admin.php';
	/**
	 * local store for the breadcrumb object
	 * 
	 * @see   bcn_admin()
	 * @var   bcn_breadcrumb
	 */
	public $breadcrumb_trail;
	/**
	 * Administrative interface class default constructor
	 */
	function bcn_admin()
	{
		//We'll let it fail fataly if the class isn't there as we depend on it
		$this->breadcrumb_trail = new bcn_breadcrumb_trail;
		//Grab defaults from the breadcrumb_trail object
		$this->opt = $this->breadcrumb_trail->opt;
		//We set the plugin basename here, could manually set it, but this is for demonstration purposes
		//$this->plugin_basename = plugin_basename(__FILE__);
		//Register the WordPress 2.8 Widget
		add_action('widgets_init', create_function('', 'return register_widget("'. $this->unique_prefix . '_widget");'));
		//We're going to make sure we load the parent's constructor
		parent::__construct();
	}
	/**
	 * admin initialization callback function
	 * 
	 * is bound to wpordpress action 'admin_init' on instantiation
	 * 
	 * @since  3.2.0
	 * @return void
	 */
	function init()
	{
		//We're going to make sure we run the parent's version of this function as well
		parent::init();	
		//Grab the current settings from the DB
		$this->opt = $this->get_option('bcn_options');
		//Add javascript enqeueing callback
		add_action('wp_print_scripts', array($this, 'javascript'));
	}
	/**
	 * Makes sure the current user can manage options to proceed
	 */
	function security()
	{
		//If the user can not manage options we will die on them
		if(!current_user_can($this->access_level))
		{
			wp_die(__('Insufficient privileges to proceed.', 'breadcrumb_navxt'));
		}
	}
	/** 
	 * This sets up and upgrades the database settings, runs on every activation
	 */
	function install()
	{
		//Call our little security function
		$this->security();
		//Try retrieving the options from the database
		$opts = $this->get_option('bcn_options');
		//If there are no settings, copy over the default settings
		if(!is_array($opts))
		{
			//Grab defaults from the breadcrumb_trail object
			$opts = $this->breadcrumb_trail->opt;
			//Add custom post types
			$this->find_posttypes($opts);
			//Add custom taxonomy types
			$this->find_taxonomies($opts);
			//Add the options
			$this->add_option('bcn_options', $opts);
			$this->add_option('bcn_options_bk', $opts, false);
			//Add the version, no need to autoload the db version
			$this->add_option('bcn_version', $this->version, false);
		}
		else
		{
			//Retrieve the database version
			$db_version = $this->get_option('bcn_version');
			if($this->version !== $db_version)
			{
				//Add custom post types
				$this->find_posttypes($opts);
				//Add custom taxonomy types
				$this->find_taxonomies($opts);
				//Run the settings update script
				$this->opts_upgrade($opts, $db_version);
				//Always have to update the version
				$this->update_option('bcn_version', $this->version);
				//Store the options
				$this->update_option('bcn_options', $this->opt);
			}
		}
	}
	/**
	 * Upgrades input options array, sets to $this->opt
	 * 
	 * @param array $opts
	 * @param string $version the version of the passed in options
	 */
	function opts_upgrade($opts, $version)
	{
		global $wp_post_types;
		//If our version is not the same as in the db, time to update
		if($version !== $this->version)
		{
			//Upgrading to 3.4
			if(version_compare($version, '3.4.0', '<'))
			{
				//Inline upgrade of the tag setting
				if($opts['post_taxonomy_type'] === 'tag')
				{
					$opts['post_taxonomy_type'] = 'post_tag';
				}
				//Fix our tag settings
				$opts['archive_post_tag_prefix'] = $this->breadcrumb_trail->opt['archive_tag_prefix'];
				$opts['archive_post_tag_suffix'] = $this->breadcrumb_trail->opt['archive_tag_suffix'];
				$opts['post_tag_prefix'] = $this->breadcrumb_trail->opt['tag_prefix'];
				$opts['post_tag_suffix'] = $this->breadcrumb_trail->opt['tag_suffix'];
				$opts['post_tag_anchor'] = $this->breadcrumb_trail->opt['tag_anchor'];
			}
			//Upgrading to 3.6
			if(version_compare($version, '3.6.0', '<'))
			{
				//Added post_ prefix to avoid conflicts with custom taxonomies
				$opts['post_page_prefix'] = $opts['page_prefix'];
				unset($opts['page_prefix']);
				$opts['post_page_suffix'] = $opts['page_suffix'];
				unset($opts['page_suffix']);
				$opts['post_page_anchor'] = $opts['page_anchor'];
				unset($opts['page_anchor']);
				$opts['post_post_prefix'] = $opts['post_prefix'];
				unset($opts['post_prefix']);
				$opts['post_post_suffix'] = $opts['post_suffix'];
				unset($opts['post_suffix']);
				$opts['post_post_anchor'] = $opts['post_anchor'];
				unset($opts['post_anchor']);
				$opts['post_post_taxonomy_display'] = $opts['post_taxonomy_display'];
				unset($opts['post_taxonomy_display']);
				$opts['post_post_taxonomy_type'] = $opts['post_taxonomy_type'];
				unset($opts['post_taxonomy_type']);
				//Update to non-autoload db version
				$this->delete_option('bcn_version');
				$this->add_option('bcn_version', $this->version, false);
				$this->add_option('bcn_options_bk', $opts, false);
			}
			//Upgrading to 3.7
			if(version_compare($version, '3.7.0', '<'))
			{
				//Add the new options for multisite
				//Should the mainsite be shown
				$opts['mainsite_display'] = true;
				//Title displayed when for the main site
				$opts['mainsite_title'] = __('Home', 'breadcrumb_navxt');
				//The anchor template for the main site, this is global, two keywords are available %link% and %title%
				$opts['mainsite_anchor'] = __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt');
				//The prefix for mainsite breadcrumbs, placed inside of current_item prefix
				$opts['mainsite_prefix'] = '';
				//The prefix for mainsite breadcrumbs, placed inside of current_item prefix
				$opts['mainsite_suffix'] = '';
				//Now add post_root for all of the custom types
				foreach($wp_post_types as $post_type)
				{
					//We only want custom post types
					if($post_type->name != 'post' && $post_type->name != 'page' && $post_type->name != 'attachment' && $post_type->name != 'revision' && $post_type->name != 'nav_menu_item')
					{
						//If the post type does not have blog_display setting, add it
						if(!array_key_exists('post_' . $post_type->name . '_root', $opts))
						{
							//Default for blog_display type dependent
							if($post_type->hierarchical)
							{
								//Set post_root for hierarchical types
								$opts['post_' . $post_type->name . '_root'] = get_option('page_on_front');
							}
							else
							{
								//Set post_root for flat types
								$opts['post_' . $post_type->name . '_root'] = get_option('page_for_posts');
							}
						}
					}
				}
			}
			//Upgrading to 3.8.1
			if(version_compare($version, '3.8.1', '<'))
			{
				$opts['post_page_root'] = get_option('page_on_front');
				$opts['post_post_root'] = get_option('page_for_posts');
			}
			//Save the passed in opts to the object's option array
			$this->opt = $opts;
		}
	}
	/**
	 * Updates the database settings from the webform
	 */
	function opts_update()
	{
		//Do some security related thigns as we are not using the normal WP settings API
		$this->security();
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer('bcn_options-options');
		//Update local options from database, going to always do a safe get
		$this->opt = wp_parse_args($this->get_option('bcn_options'), $this->breadcrumb_trail->opt);
		//If we did not get an array, might as well just quit here
		if(!is_array($this->opt))
		{
			return;
		}
		//Add custom post types
		$this->find_posttypes($this->opt);
		//Add custom taxonomy types
		$this->find_taxonomies($this->opt);
		//Update our backup options
		$this->update_option('bcn_options_bk', $this->opt);
		//Grab our incomming array (the data is dirty)
		$input = $_POST['bcn_options'];
		//We have two "permi" variables
		$input['post_page_root'] = get_option('page_on_front');
		$input['post_post_root'] = get_option('page_for_posts');
		//Loop through all of the existing options (avoids random setting injection)
		foreach($this->opt as $option => $value)
		{
			//Handle all of our boolean options first
			if(strpos($option, 'display') > 0 || $option == 'current_item_linked')
			{
				$this->opt[$option] = isset($input[$option]);
			}
			//Now handle anything that can't be blank
			else if(strpos($option, 'anchor') > 0)
			{
				//Only save a new anchor if not blank
				if(isset($input[$option]))
				{
					//Do excess slash removal sanitation
					$this->opt[$option] = stripslashes($input[$option]);
				}
			}
			//Now everything else
			else
			{
				$this->opt[$option] = stripslashes($input[$option]);
			}
		}
		//Commit the option changes
		$this->update_option('bcn_options', $this->opt);
		//Check if known settings match attempted save
		if(count(array_diff_key($input, $this->opt)) == 0)
		{
			//Let the user know everything went ok
			$this->message['updated fade'][] = __('Settings successfully saved.', $this->identifier) . $this->undo_anchor(__('Undo the options save.', $this->identifier));
		}
		else
		{
			//Let the user know the following were not saved
			$this->message['updated fade'][] = __('Some settings were not saved.', $this->identifier) . $this->undo_anchor(__('Undo the options save.', $this->identifier));
			$temp = __('The following settings were not saved:', $this->identifier);
			foreach(array_diff_key($input, $this->opt) as $setting => $value)
			{
				$temp .= '<br />' . $setting;
			}
			$this->message['updated fade'][] = $temp . '<br />' . sprintf(__('Please include this message in your %sbug report%s.', $this->identifier),'<a title="' . __('Go to the Breadcrumb NavXT support post for your version.', $this->identifier) . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/breadcrumb-navxt-' . $this->version . '/#respond">', '</a>');
		}
		add_action('admin_notices', array($this, 'message'));
	}
	/**
	 * Enqueues JS dependencies (jquery) for the tabs
	 * 
	 * @see admin_init()
	 * @return void
	 */
	function javascript()
	{
		//Enqueue ui-tabs
		wp_enqueue_script('jquery-ui-tabs');
	}
	/**
	 * get help text
	 * 
	 * @return string
	 */
	protected function _get_help_text()
	{
		return '<p>' . sprintf(__('Tips for the settings are located below select options. Please refer to the %sdocumentation%s for more information.', 'breadcrumb_navxt'), 
			'<a title="' . __('Go to the Breadcrumb NavXT online documentation', 'breadcrumb_navxt') . '" href="http://mtekk.us/code/breadcrumb-navxt/breadcrumb-navxt-doc/">', '</a>') . ' ' .
			sprintf(__('If you think you have found a bug, please include your WordPress version and details on how to reproduce the bug when you %sreport the issue%s.', $this->identifier),'<a title="' . __('Go to the Breadcrumb NavXT support post for your version.', 'breadcrumb_navxt') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/breadcrumb-navxt-' . $this->version . '/#respond">', '</a>') . '</p><h5>' .
		__('Quick Start Information', 'breadcrumb_navxt') . '</h5><p>' . __('For the settings on this page to take effect, you must either use the included Breadcrumb NavXT widget, or place either of the code sections below into your theme.', 'breadcrumb_navxt') .
		'</p><h5>' . __('Breadcrumb trail with separators', 'breadcrumb_navxt').'</h5><code>&lt;div class="breadcrumbs"&gt;'."&lt;?php if(function_exists('bcn_display')){ bcn_display();}?&gt;&lt;/div&gt;</code>" .
		'<h5>' . __('Breadcrumb trail in list form', 'breadcrumb_navxt').'</h5><code>&lt;ol class="breadcrumbs"&gt;'."&lt;?php if(function_exists('bcn_display_list')){ bcn_display_list();}?&gt;&lt;/ol&gt;</code>";
	}
	/**
	 * Adds in the JavaScript and CSS for the tabs in the adminsitrative 
	 * interface
	 * 
	 */
	function admin_head()
	{	
		// print style and script element (should go into head element) 
		?>
<style type="text/css">
	/**
	 * Tabbed Admin Page (CSS)
	 * 
	 * @see Breadcrumb NavXT (Wordpress Plugin)
	 * @author Tom Klingenberg 
	 * @colordef #c6d9e9 light-blue (older tabs border color, obsolete)
	 * @colordef #dfdfdf light-grey (tabs border color)
	 * @colordef #f9f9f9 very-light-grey (admin standard background color)
	 * @colordef #fff    white (active tab background color)
	 */
#hasadmintabs ul.ui-tabs-nav {border-bottom:1px solid #dfdfdf; font-size:12px; height:29px; list-style-image:none; list-style-position:outside; list-style-type:none; margin:13px 0 0; overflow:visible; padding:0 0 0 8px;}
#hasadmintabs ul.ui-tabs-nav li {display:block; float:left; line-height:200%; list-style-image:none; list-style-position:outside; list-style-type:none; margin:0; padding:0; position:relative; text-align:center; white-space:nowrap; width:auto;}
#hasadmintabs ul.ui-tabs-nav li a {background:transparent none no-repeat scroll 0 50%; border-bottom:1px solid #dfdfdf; display:block; float:left; line-height:28px; padding:1px 13px 0; position:relative; text-decoration:none;}
#hasadmintabs ul.ui-tabs-nav li.ui-tabs-selected a{-moz-border-radius-topleft:4px; -moz-border-radius-topright:4px;border:1px solid #dfdfdf; border-bottom-color:#f9f9f9; color:#333333; font-weight:normal; padding:0 12px;}
#hasadmintabs ul.ui-tabs-nav a:focus, a:active {outline-color:-moz-use-text-color; outline-style:none; outline-width:medium;}
#screen-options-wrap p.submit {margin:0; padding:0;}
</style>
<script type="text/javascript">
/* <![CDATA[ */
	/**
	 * Breadcrumb NavXT Admin Page (javascript/jQuery)
	 *
	 * unobtrusive approach to add tabbed forms into
	 * the wordpress admin panel and various other 
	 * stuff that needs javascript with the Admin Panel.
	 *
	 * @see Breadcrumb NavXT (Wordpress Plugin)
	 * @author Tom Klingenberg
	 * @author John Havlik
	 * @uses jQuery
	 * @uses jQuery.ui.tabs
	 */		
	jQuery(function()
	{
		bcn_context_init();
		bcn_tabulator_init();		
	 });
	/**
	 * Tabulator Bootup
	 */
	function bcn_tabulator_init(){
		if (!jQuery("#hasadmintabs").length) return;		
		/* init markup for tabs */
		jQuery('#hasadmintabs').prepend("<ul><\/ul>");
		jQuery('#hasadmintabs > fieldset').each(function(i){
		    id      = jQuery(this).attr('id');
		    caption = jQuery(this).find('h3').text();
		    jQuery('#hasadmintabs > ul').append('<li><a href="#'+id+'"><span>'+caption+"<\/span><\/a><\/li>");
		    jQuery(this).find('h3').hide();					    
	    });	
		/* init the tabs plugin */
		jQuery("#hasadmintabs").tabs();
		/* handler for opening the last tab after submit (compability version) */
		jQuery('#hasadmintabs ul a').click(function(i){
			var form   = jQuery('#bcn_admin-options');
			var action = form.attr("action").split('#', 1) + jQuery(this).attr('href');					
			form.get(0).setAttribute("action", action);
		});
	}
	/**
	 * context screen options for import/export
	 */
	 function bcn_context_init(){
		if (!jQuery("#bcn_import_export_relocate").length) return;
		jQuery('#screen-meta').prepend(
				'<div id="screen-options-wrap" class="hidden"></div>'
		);
		jQuery('#screen-meta-links').append(
				'<div id="screen-options-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
				'<a class="show-settings" id="show-settings-link" href="#screen-options"><?php printf('%s/%s/%s', __('Import', 'breadcrumb_navxt'), __('Export', 'breadcrumb_navxt'), __('Reset', 'breadcrumb_navxt')); ?></a>' + 
				'</div>'
		);
		var code = jQuery('#bcn_import_export_relocate').html();
		jQuery('#bcn_import_export_relocate').html('');
		code = code.replace(/h3>/gi, 'h5>');		
		jQuery('#screen-options-wrap').prepend(code);		
	 }
/* ]]> */
</script>
<?php
	}
	/**
	 * The administrative page for Breadcrumb NavXT
	 * 
	 */
	function admin_page()
	{
		global $wp_taxonomies, $wp_post_types;
		$this->security();?>
		<div class="wrap"><h2><?php _e('Breadcrumb NavXT Settings', 'breadcrumb_navxt'); ?></h2>		
		<div<?php if($this->_has_contextual_help): ?> class="hide-if-js"<?php endif; ?>><?php 
			print $this->_get_help_text();
		?></div>
		<?php
		//We exit after the version check if there is an action the user needs to take before saving settings
		if(!$this->version_check($this->get_option($this->unique_prefix . '_version')))
		{
			return;
		}
		?>
		<form action="options-general.php?page=breadcrumb_navxt" method="post" id="bcn_admin-options">
			<?php settings_fields('bcn_options');?>
			<div id="hasadmintabs">
			<fieldset id="general" class="bcn_options">
				<h3><?php _e('General', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Breadcrumb Separator', 'breadcrumb_navxt'), 'separator', '32', false, __('Placed in between each breadcrumb.', 'breadcrumb_navxt'));
						$this->input_text(__('Breadcrumb Max Title Length', 'breadcrumb_navxt'), 'max_title_length', '10');
					?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Home Breadcrumb', 'breadcrumb_navxt'); ?>						
						</th>
						<td>
							<label>
								<input name="bcn_options[home_display]" type="checkbox" id="home_display" value="true" <?php checked(true, $this->opt['home_display']); ?> />
								<?php _e('Place the home breadcrumb in the trail.', 'breadcrumb_navxt'); ?>				
							</label><br />
							<ul>
								<li>
									<label for="home_title">
										<?php _e('Home Title: ','breadcrumb_navxt');?>
										<input type="text" name="bcn_options[home_title]" id="home_title" value="<?php echo htmlentities($this->opt['home_title'], ENT_COMPAT, 'UTF-8'); ?>" size="20" />
									</label>
								</li>
							</ul>							
						</td>
					</tr>
					<?php
						$this->input_text(__('Home Prefix', 'breadcrumb_navxt'), 'home_prefix', '32');
						$this->input_text(__('Home Suffix', 'breadcrumb_navxt'), 'home_suffix', '32');
						$this->input_text(__('Home Anchor', 'breadcrumb_navxt'), 'home_anchor', '64', false, __('The anchor template for the home breadcrumb.', 'breadcrumb_navxt'));
						$this->input_check(__('Blog Breadcrumb', 'breadcrumb_navxt'), 'blog_display', __('Place the blog breadcrumb in the trail.', 'breadcrumb_navxt'), ($this->get_option('show_on_front') !== "page"));
						$this->input_text(__('Blog Anchor', 'breadcrumb_navxt'), 'blog_anchor', '64', ($this->get_option('show_on_front') !== "page"), __('The anchor template for the blog breadcrumb, used only in static front page environments.', 'breadcrumb_navxt'));
					?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Main Site Breadcrumb', 'breadcrumb_navxt'); ?>						
						</th>
						<td>
							<label>
								<input name="bcn_options[mainsite_display]" type="checkbox" id="mainsite_display" <?php if(!is_multisite()){echo 'disabled="disabled" class="disabled"';}?> value="true" <?php checked(true, $this->opt['mainsite_display']); ?> />
								<?php _e('Place the main site home breadcrumb in the trail in an multisite setup.', 'breadcrumb_navxt'); ?>				
							</label><br />
							<ul>
								<li>
									<label for="mainsite_title">
										<?php _e('Main Site Home Title: ','breadcrumb_navxt');?>
										<input type="text" name="bcn_options[mainsite_title]" id="mainsite_title" <?php if(!is_multisite()){echo 'disabled="disabled" class="disabled"';}?> value="<?php echo htmlentities($this->opt['mainsite_title'], ENT_COMPAT, 'UTF-8'); ?>" size="20" />
										<?php if(!is_multisite()){?><input type="hidden" name="bcn_options[mainsite_title]" value="<?php echo htmlentities($this->opt['mainsite_title'], ENT_COMPAT, 'UTF-8');?>" /><?php } ?>
									</label>
								</li>
							</ul>							
						</td>
					</tr>
					<?php
						$this->input_text(__('Main Site Home Prefix', 'breadcrumb_navxt'), 'mainsite_prefix', '32', !is_multisite(), __('Used for the main site home breadcrumb in an multisite setup', 'breadcrumb_navxt'));
						$this->input_text(__('Main Site Home Suffix', 'breadcrumb_navxt'), 'mainsite_suffix', '32', !is_multisite(), __('Used for the main site home breadcrumb in an multisite setup', 'breadcrumb_navxt'));
						$this->input_text(__('Main Site Home Anchor', 'breadcrumb_navxt'), 'mainsite_anchor', '64', !is_multisite(), __('The anchor template for the main site home breadcrumb, used only in multisite environments.', 'breadcrumb_navxt'));
						?>
				</table>
			</fieldset>
			<fieldset id="current" class="bcn_options">
				<h3><?php _e('Current Item', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_check(__('Link Current Item', 'breadcrumb_navxt'), 'current_item_linked', __('Yes'));
						$this->input_text(__('Current Item Prefix', 'breadcrumb_navxt'), 'current_item_prefix', '32', false, __('This is always placed in front of the last breadcrumb in the trail, before any other prefixes for that breadcrumb.', 'breadcrumb_navxt'));
						$this->input_text(__('Current Item Suffix', 'breadcrumb_navxt'), 'current_item_suffix', '32', false, __('This is always placed after the last breadcrumb in the trail, and after any other prefixes for that breadcrumb.', 'breadcrumb_navxt'));
						$this->input_text(__('Current Item Anchor', 'breadcrumb_navxt'), 'current_item_anchor', '64', false, __('The anchor template for current item breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_check(__('Paged Breadcrumb', 'breadcrumb_navxt'), 'paged_display', __('Include the paged breadcrumb in the breadcrumb trail.', 'breadcrumb_navxt'), false, __('Indicates that the user is on a page other than the first on paginated posts/pages.', 'breadcrumb_navxt'));
						$this->input_text(__('Paged Prefix', 'breadcrumb_navxt'), 'paged_prefix', '32');
						$this->input_text(__('Paged Suffix', 'breadcrumb_navxt'), 'paged_suffix', '32');
					?>
				</table>
			</fieldset>
			<fieldset id="single" class="bcn_options">
				<h3><?php _e('Posts &amp; Pages', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Post Prefix', 'breadcrumb_navxt'), 'post_post_prefix', '32');
						$this->input_text(__('Post Suffix', 'breadcrumb_navxt'), 'post_post_suffix', '32');
						$this->input_text(__('Post Anchor', 'breadcrumb_navxt'), 'post_post_anchor', '64', false, __('The anchor template for post breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_check(__('Post Taxonomy Display', 'breadcrumb_navxt'), 'post_post_taxonomy_display', __('Show the taxonomy leading to a post in the breadcrumb trail.', 'breadcrumb_navxt'));
					?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Post Taxonomy', 'breadcrumb_navxt'); ?>
						</th>
						<td>
							<?php
								$this->input_radio('post_post_taxonomy_type', 'category', __('Categories'));
								$this->input_radio('post_post_taxonomy_type', 'date', __('Dates'));
								$this->input_radio('post_post_taxonomy_type', 'post_tag', __('Tags'));
								$this->input_radio('post_post_taxonomy_type', 'page', __('Pages'));
								//Loop through all of the taxonomies in the array
								foreach($wp_taxonomies as $taxonomy)
								{
									//We only want custom taxonomies
									if(($taxonomy->object_type == 'post' || is_array($taxonomy->object_type) && in_array('post', $taxonomy->object_type)) && !$taxonomy->_builtin)
									{
										$this->input_radio('post_post_taxonomy_type', $taxonomy->name, mb_convert_case(__($taxonomy->label), MB_CASE_TITLE, 'UTF-8'));
									}
								}
							?>
							<span class="setting-description"><?php _e('The taxonomy which the breadcrumb trail will show.', 'breadcrumb_navxt'); ?></span>
						</td>
					</tr>
					<?php
						$this->input_text(__('Page Prefix', 'breadcrumb_navxt'), 'post_page_prefix', '32');
						$this->input_text(__('Page Suffix', 'breadcrumb_navxt'), 'post_page_suffix', '32');
						$this->input_text(__('Page Anchor', 'breadcrumb_navxt'), 'post_page_anchor', '64', false, __('The anchor template for page breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Attachment Prefix', 'breadcrumb_navxt'), 'attachment_prefix', '32');
						$this->input_text(__('Attachment Suffix', 'breadcrumb_navxt'), 'attachment_suffix', '32');
					?>
				</table>
			</fieldset>
			<?php
			//Loop through all of the post types in the array
			foreach($wp_post_types as $post_type)
			{
				//We only want custom post types
				if(!$post_type->_builtin)
				{
					//If the post type does not have settings in the options array yet, we need to load some defaults
					if(!array_key_exists('post_' . $post_type->name . '_anchor', $this->opt) || !$post_type->hierarchical && !array_key_exists('post_' . $post_type->name . '_taxonomy_type', $this->opt))
					{
						//Add the necessary option array members
						$this->opt['post_' . $post_type->name . '_prefix'] = '';
						$this->opt['post_' . $post_type->name . '_suffix'] = '';
						$this->opt['post_' . $post_type->name . '_anchor'] = __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt');
						//Do type dependent tasks
						if($post_type->hierarchical)
						{
							//Set post_root for hierarchical types
							$this->opt['post_' . $post_type->name . '_root'] = get_option('page_on_front');
						}
						//If it is flat, we need a taxonomy selection
						else
						{
							//Set post_root for flat types
							$this->opt['post_' . $post_type->name . '_root'] = get_option('page_for_posts');
							//Default to not displaying a taxonomy
							$this->opt['post_' . $post_type->name . '_taxonomy_display'] = false;
							//Loop through all of the possible taxonomies
							foreach($wp_taxonomies as $taxonomy)
							{
								//Activate the first taxonomy valid for this post type and exit the loop
								if($taxonomy->object_type == $post_type->name || in_array($post_type->name, $taxonomy->object_type))
								{
									$this->opt['post_' . $post_type->name . '_taxonomy_display'] = true;
									$this->opt['post_' . $post_type->name . '_taxonomy_type'] = $taxonomy->name;
									break;
								}
							}
							//If there are no valid taxonomies for this type, we default to not displaying taxonomies for this post type
							if(!isset($this->opt['post_' . $post_type->name . '_taxonomy_type']))
							{
								$this->opt['post_' . $post_type->name . '_taxonomy_type'] = 'date';
							}
						}
					}?>
			<fieldset id="post_<?php echo $post_type->name ?>" class="bcn_options">
				<h3><?php echo $post_type->labels->singular_name; ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(sprintf(__('%s Prefix', 'breadcrumb_navxt'), $post_type->labels->singular_name), 'post_' . $post_type->name . '_prefix', '32');
						$this->input_text(sprintf(__('%s Suffix', 'breadcrumb_navxt'), $post_type->labels->singular_name), 'post_' . $post_type->name . '_suffix', '32');
						$this->input_text(sprintf(__('%s Anchor', 'breadcrumb_navxt'), $post_type->labels->singular_name), 'post_' . $post_type->name . '_anchor', '64', false, sprintf(__('The anchor template for %s breadcrumbs.', 'breadcrumb_navxt'), strtolower(__($post_type->labels->singular_name))));
						$optid = $this->get_valid_id('post_' . $post_type->name . '_root');
					?>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $optid;?>"><?php printf(__('%s Root Page', 'breadcrumb_navxt'), $post_type->labels->singular_name);?></label>
						</th>
						<td>
							<?php wp_dropdown_pages(array('name' => $this->unique_prefix . '_options[post_' . $post_type->name . '_root]', 'id' => $optid, 'echo' => 1, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => $this->opt['post_' . $post_type->name . '_root']));?>
						</td>
					</tr>
					<?php
						//If it is flat, we need a taxonomy selection
						if(!$post_type->hierarchical)
						{
							$this->input_check(sprintf(__('%s Taxonomy Display', 'breadcrumb_navxt'), $post_type->labels->singular_name), 'post_' . $post_type->name . '_taxonomy_display', sprintf(__('Show the taxonomy leading to a %s in the breadcrumb trail.', 'breadcrumb_navxt'), strtolower(__($post_type->labels->singular_name))));
					?>
					<tr valign="top">
						<th scope="row">
							<?php printf(__('%s Taxonomy', 'breadcrumb_navxt'), $post_type->labels->singular_name); ?>
						</th>
						<td>
							<?php
								$this->input_radio('post_' . $post_type->name . '_taxonomy_type', 'date', __('Dates'));
								$this->input_radio('post_' . $post_type->name . '_taxonomy_type', 'page', __('Pages'));
								//Loop through all of the taxonomies in the array
								foreach($wp_taxonomies as $taxonomy)
								{
									//We only want custom taxonomies
									if($taxonomy->object_type == $post_type->name || in_array($post_type->name, $taxonomy->object_type))
									{
										$this->input_radio('post_' . $post_type->name . '_taxonomy_type', $taxonomy->name, $taxonomy->labels->singular_name);
									}
								}
							?>
							<span class="setting-description"><?php _e('The taxonomy which the breadcrumb trail will show.', 'breadcrumb_navxt'); ?></span>
						</td>
					</tr>
					<?php } ?>
				</table>
			</fieldset>
					<?php
				}
			}?>
			<fieldset id="category" class="bcn_options">
				<h3><?php _e('Categories', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Category Prefix', 'breadcrumb_navxt'), 'category_prefix', '32', false, __('Applied before the anchor on all category breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Category Suffix', 'breadcrumb_navxt'), 'category_suffix', '32', false, __('Applied after the anchor on all category breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Category Anchor', 'breadcrumb_navxt'), 'category_anchor', '64', false, __('The anchor template for category breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Category Prefix', 'breadcrumb_navxt'), 'archive_category_prefix', '32', false, __('Applied before the title of the current item breadcrumb on an archive by cateogry page.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Category Suffix', 'breadcrumb_navxt'), 'archive_category_suffix', '32', false, __('Applied after the title of the current item breadcrumb on an archive by cateogry page.', 'breadcrumb_navxt'));
					?>
				</table>
			</fieldset>
			<fieldset id="post_tag" class="bcn_options">
				<h3><?php _e('Tags', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Tag Prefix', 'breadcrumb_navxt'), 'post_tag_prefix', '32', false, __('Applied before the anchor on all tag breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Tag Suffix', 'breadcrumb_navxt'), 'post_tag_suffix', '32', false, __('Applied after the anchor on all tag breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Tag Anchor', 'breadcrumb_navxt'), 'post_tag_anchor', '64', false, __('The anchor template for tag breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Tag Prefix', 'breadcrumb_navxt'), 'archive_post_tag_prefix', '32', false, __('Applied before the title of the current item breadcrumb on an archive by tag page.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Tag Suffix', 'breadcrumb_navxt'), 'archive_post_tag_suffix', '32', false, __('Applied after the title of the current item breadcrumb on an archive by tag page.', 'breadcrumb_navxt'));
					?>
				</table>
			</fieldset>
			<?php
			//Loop through all of the taxonomies in the array
			foreach($wp_taxonomies as $taxonomy)
			{
				//We only want custom taxonomies
				if(!$taxonomy->_builtin)
				{
					//If the taxonomy does not have settings in the options array yet, we need to load some defaults
					if(!array_key_exists($taxonomy->name . '_anchor', $this->opt))
					{
						//Add the necessary option array members
						$this->opt[$taxonomy->name . '_prefix'] = '';
						$this->opt[$taxonomy->name . '_suffix'] = '';
						$this->opt[$taxonomy->name . '_anchor'] = __(sprintf('<a title="Go to the %%title%% %s archives." href="%%link%%">', $taxonomy->labels->singular_name), 'breadcrumb_navxt');
						$this->opt['archive_' . $taxonomy->name . '_prefix'] = '';
						$this->opt['archive_' . $taxonomy->name . '_suffix'] = '';
					}
				?>
			<fieldset id="<?php echo $taxonomy->name; ?>" class="bcn_options">
				<h3><?php echo mb_convert_case(__($taxonomy->label), MB_CASE_TITLE, 'UTF-8'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(sprintf(__('%s Prefix', 'breadcrumb_navxt'), $taxonomy->labels->singular_name), $taxonomy->name . '_prefix', '32', false, sprintf(__('Applied before the anchor on all %s breadcrumbs.', 'breadcrumb_navxt'), strtolower(__($taxonomy->label))));
						$this->input_text(sprintf(__('%s Suffix', 'breadcrumb_navxt'), $taxonomy->labels->singular_name), $taxonomy->name . '_suffix', '32', false, sprintf(__('Applied after the anchor on all %s breadcrumbs.', 'breadcrumb_navxt'), strtolower(__($taxonomy->label))));
						$this->input_text(sprintf(__('%s Anchor', 'breadcrumb_navxt'), $taxonomy->labels->singular_name), $taxonomy->name . '_anchor', '64', false, sprintf(__('The anchor template for %s breadcrumbs.', 'breadcrumb_navxt'), strtolower(__($taxonomy->label))));
						$this->input_text(sprintf(__('Archive by %s Prefix', 'breadcrumb_navxt'), $taxonomy->labels->singular_name), 'archive_' . $taxonomy->name . '_prefix', '32', false, sprintf(__('Applied before the title of the current item breadcrumb on an archive by %s page.', 'breadcrumb_navxt'), strtolower(__($taxonomy->label))));
						$this->input_text(sprintf(__('Archive by %s Suffix', 'breadcrumb_navxt'), $taxonomy->labels->singular_name), 'archive_' . $taxonomy->name . '_suffix', '32', false, sprintf(__('Applied after the title of the current item breadcrumb on an archive by %s page.', 'breadcrumb_navxt'), strtolower(__($taxonomy->label))));
					?>
				</table>
			</fieldset>
				<?php
				}
			}
			?>
			<fieldset id="date" class="bcn_options">
				<h3><?php _e('Date Archives', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Date Anchor', 'breadcrumb_navxt'), 'date_anchor', '64', false, __('The anchor template for date breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Date Prefix', 'breadcrumb_navxt'), 'archive_date_prefix', '32', false, __('Applied before the anchor on all date breadcrumbs.', 'breadcrumb_navxt'));
						$this->input_text(__('Archive by Date Suffix', 'breadcrumb_navxt'), 'archive_date_suffix', '32', false, __('Applied after the anchor on all date breadcrumbs.', 'breadcrumb_navxt'));
					?>
				</table>
			</fieldset>
			<fieldset id="miscellaneous" class="bcn_options">
				<h3><?php _e('Miscellaneous', 'breadcrumb_navxt'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Author Prefix', 'breadcrumb_navxt'), 'author_prefix', '32');
						$this->input_text(__('Author Suffix', 'breadcrumb_navxt'), 'author_suffix', '32');
						$this->input_select(__('Author Display Format', 'breadcrumb_navxt'), 'author_name', array("display_name", "nickname", "first_name", "last_name"), false, __('display_name uses the name specified in "Display name publicly as" under the user profile the others correspond to options in the user profile.', 'breadcrumb_navxt'));
						$this->input_text(__('Search Prefix', 'breadcrumb_navxt'), 'search_prefix', '32');
						$this->input_text(__('Search Suffix', 'breadcrumb_navxt'), 'search_suffix', '32');
						$this->input_text(__('Search Anchor', 'breadcrumb_navxt'), 'search_anchor', '64', false, __('The anchor template for search breadcrumbs, used only when the search results span several pages.', 'breadcrumb_navxt'));
						$this->input_text(__('404 Title', 'breadcrumb_navxt'), '404_title', '32');
						$this->input_text(__('404 Prefix', 'breadcrumb_navxt'), '404_prefix', '32');
						$this->input_text(__('404 Suffix', 'breadcrumb_navxt'), '404_suffix', '32');
					?>
				</table>
			</fieldset>
			</div>
			<p class="submit"><input type="submit" class="button-primary" name="bcn_admin_options" value="<?php esc_attr_e('Save Changes') ?>" /></p>
		</form>
		<?php $this->import_form(); ?>
		</div>
		<?php
	}
	/**
	 * Places settings into $opts array, if missing, for the registered post types
	 * 
	 * @param $opts
	 */
	function find_posttypes(&$opts)
	{
		global $wp_post_types, $wp_taxonomies;
		//Loop through all of the post types in the array
		foreach($wp_post_types as $post_type)
		{
			//We only want custom post types
			if(!$post_type->_builtin)
			{
				//If the post type does not have settings in the options array yet, we need to load some defaults
				if(!array_key_exists('post_' . $post_type->name . '_anchor', $opts) || !$post_type->hierarchical && !array_key_exists('post_' . $post_type->name . '_taxonomy_type', $opts))
				{
					//Add the necessary option array members
					$opts['post_' . $post_type->name . '_prefix'] = '';
					$opts['post_' . $post_type->name . '_suffix'] = '';
					$opts['post_' . $post_type->name . '_anchor'] = __('<a title="Go to %title%." href="%link%">', 'breadcrumb_navxt');
					//Do type dependent tasks
					if($post_type->hierarchical)
					{
						//Set post_root for hierarchical types
						$opts['post_' . $post_type->name . '_root'] = get_option('page_on_front');
					}
					//If it is flat, we need a taxonomy selection
					else
					{
						//Set post_root for flat types
						$opts['post_' . $post_type->name . '_root'] = get_option('page_for_posts');
						//Be safe and disable taxonomy display by default
						$opts['post_' . $post_type->name . '_taxonomy_display'] = false;
						//Loop through all of the possible taxonomies
						foreach($wp_taxonomies as $taxonomy)
						{
							//Activate the first taxonomy valid for this post type and exit the loop
							if($taxonomy->object_type == $post_type->name || in_array($post_type->name, $taxonomy->object_type))
							{
								$opts['post_' . $post_type->name . '_taxonomy_display'] = true;
								$opts['post_' . $post_type->name . '_taxonomy_type'] = $taxonomy->name;
								break;
							}
						}
						//If there are no valid taxonomies for this type, we default to not displaying taxonomies for this post type
						if(!isset($opts['post_' . $post_type->name . '_taxonomy_type']))
						{
							$opts['post_' . $post_type->name . '_taxonomy_type'] = 'date';
						}
					}
				}
			}
		}
	}
	/**
	 * Places settings into $opts array, if missing, for the registered taxonomies
	 * 
	 * @param $opts
	 */
	function find_taxonomies(&$opts)
	{
		global $wp_taxonomies;
		//We'll add our custom taxonomy stuff at this time
		foreach($wp_taxonomies as $taxonomy)
		{
			//We only want custom taxonomies
			if(!$taxonomy->_builtin)
			{
				//If the taxonomy does not have settings in the options array yet, we need to load some defaults
				if(!array_key_exists($taxonomy->name . '_anchor', $opts))
				{
					$opts[$taxonomy->name . '_prefix'] = '';
					$opts[$taxonomy->name . '_suffix'] = '';
					$opts[$taxonomy->name . '_anchor'] = __(sprintf('<a title="Go to the %%title%% %s archives." href="%%link%%">',  mb_convert_case(__($taxonomy->label), MB_CASE_TITLE, 'UTF-8')), 'breadcrumb_navxt');
					$opts['archive_' . $taxonomy->name . '_prefix'] = '';
					$opts['archive_' . $taxonomy->name . '_suffix'] = '';
				}
			}
		}
	}
	/**
	 * This inserts the value into the option name, works around WP's stupid string bool
	 *
	 * @param (string) key name where to save the value in $value
	 * @param (mixed) value to insert into the options db
	 * @param (bool) should WordPress autoload this option
	 * @return (bool)
	 */
	function add_option($key, $value, $autoload = true)
	{
		if($autoload)
		{
			return add_option($key, $value);
		}
		else
		{
			return add_option($key, $value, '', 'no');
		}
	}
	/**
	 * This removes the option name, WPMU safe
	 *
	 * @param (string) key name of the option to remove
	 * @return (bool)
	 */
	function delete_option($key)
	{
		return delete_option($key);
	}
	/**
	 * This updates the value into the option name, WPMU safe
	 *
	 * @param (string) key name where to save the value in $value
	 * @param (mixed) value to insert into the options db
	 * @return (bool)
	 */
	function update_option($key, $value)
	{
		return update_option($key, $value);
	}
	/**
	 * This grabs the the data from the db it is WPMU safe and can place the data 
	 * in a HTML form safe manner.
	 *
	 * @param  (string) key name of the wordpress option to get
	 * @return (mixed)  value of option
	 */
	function get_option($key)
	{
		return get_option($key);
	}
	/**
	 * Outputs the breadcrumb trail
	 * 
	 * @param  (bool)   $return Whether to return or echo the trail.
	 * @param  (bool)   $linked Whether to allow hyperlinks in the trail or not.
	 * @param  (bool)	$reverse Whether to reverse the output or not.
	 */
	function display($return = false, $linked = true, $reverse = false)
	{
		//First make sure our defaults are safe
		$this->find_posttypes($this->breadcrumb_trail->opt);
		$this->find_taxonomies($this->breadcrumb_trail->opt);
		//Grab the current settings from the db
		$this->breadcrumb_trail->opt = wp_parse_args($this->get_option('bcn_options'), $this->breadcrumb_trail->opt);
		//Generate the breadcrumb trail
		$this->breadcrumb_trail->fill();
		return $this->breadcrumb_trail->display($return, $linked, $reverse);
	}
	/**
	 * Outputs the breadcrumb trail
	 * 
	 * @since  3.2.0
	 * @param  (bool)   $return Whether to return or echo the trail.
	 * @param  (bool)   $linked Whether to allow hyperlinks in the trail or not.
	 * @param  (bool)	$reverse Whether to reverse the output or not.
	 */
	function display_list($return = false, $linked = true, $reverse = false)
	{
		//First make sure our defaults are safe
		$this->find_posttypes($this->breadcrumb_trail->opt);
		$this->find_taxonomies($this->breadcrumb_trail->opt);
		//Grab the current settings from the db
		$this->breadcrumb_trail->opt = wp_parse_args($this->get_option('bcn_options'), $this->breadcrumb_trail->opt);
		//Generate the breadcrumb trail
		$this->breadcrumb_trail->fill();
		return $this->breadcrumb_trail->display_list($return, $linked, $reverse);
	}
	/**
	 * Outputs the breadcrumb trail
	 * 
	 * @since  3.8.0
	 * @param bool $return Whether to return data or to echo it.
	 * @param bool $linked[optional] Whether to allow hyperlinks in the trail or not.
	 * @param string $tag[optional] The tag to use for the nesting
	 * @param string $mode[optional] Whether to follow the rdfa or Microdata format
	 */
	function display_nested($return = false, $linked = true, $tag = 'span', $mode = 'rdfa')
	{
		//First make sure our defaults are safe
		$this->find_posttypes($this->breadcrumb_trail->opt);
		$this->find_taxonomies($this->breadcrumb_trail->opt);
		//Grab the current settings from the db
		$this->breadcrumb_trail->opt = wp_parse_args($this->get_option('bcn_options'), $this->breadcrumb_trail->opt);
		//Generate the breadcrumb trail
		$this->breadcrumb_trail->fill();
		return $this->breadcrumb_trail->display_nested($return, $linked, $tag, $mode);
	}
}
//Let's make an instance of our object takes care of everything
# WPCOM: Wait until "init" instead of doing it now
add_action( 'init', 'bcn_init', 1 );
function bcn_init() {
	global $bcn_admin;
	$bcn_admin = new bcn_admin;
}

/**
 * A wrapper for the internal function in the class
 * 
 * @param bool $return Whether to return or echo the trail. (optional)
 * @param bool $linked Whether to allow hyperlinks in the trail or not. (optional)
 * @param bool $reverse Whether to reverse the output or not. (optional)
 */
function bcn_display($return = false, $linked = true, $reverse = false)
{
	global $bcn_admin;
	if($bcn_admin !== null)
	{
		return $bcn_admin->display($return, $linked, $reverse);
	}
}
/**
 * A wrapper for the internal function in the class
 * 
 * @param  bool $return  Whether to return or echo the trail. (optional)
 * @param  bool $linked  Whether to allow hyperlinks in the trail or not. (optional)
 * @param  bool $reverse Whether to reverse the output or not. (optional)
 */
function bcn_display_list($return = false, $linked = true, $reverse = false)
{
	global $bcn_admin;
	if($bcn_admin !== null)
	{
		return $bcn_admin->display_list($return, $linked, $reverse);
	}
}
/**
 * A wrapper for the internal function in the class
 * 
 * @param bool $return Whether to return data or to echo it.
 * @param bool $linked[optional] Whether to allow hyperlinks in the trail or not.
 * @param string $tag[optional] The tag to use for the nesting
 * @param string $mode[optional] Whether to follow the rdfa or Microdata format
 */
function bcn_display_nested($return = false, $linked = true, $tag = 'span', $mode = 'rdfa')
{
	global $bcn_admin;
	if($bcn_admin !== null)
	{
		return $bcn_admin->display_nested($return, $linked, $tag, $mode);
	}
}