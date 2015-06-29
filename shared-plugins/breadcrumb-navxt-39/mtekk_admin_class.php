<?php
/*  
	Copyright 2009-2011  John Havlik  (email : mtekkmonkey@gmail.com)

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
abstract class mtekk_admin
{
	protected $version;
	protected $full_name;
	protected $short_name;
	protected $plugin_basename;
	protected $access_level = 'manage_options';
	protected $identifier;
	protected $unique_prefix;
	protected $opt = array();
	protected $message;
	/**
	 * whether or not this administration page has contextual help
	 * 
	 * @var bool
	 */
	protected $_has_contextual_help = false;
	function __construct()
	{
		//We set the plugin basename here, could manually set it
		$this->plugin_basename = plugin_basename(__FILE__);
		//Admin Init Hook
		add_action('admin_init', array($this, 'init'));
		//WordPress Admin interface hook
		add_action('admin_menu', array($this, 'add_page'));
		//Installation Script hook
		//register_activation_hook($this->plugin_basename, array($this, 'install'));
		add_action('activate_' . $this->plugin_basename, array($this, 'install'));
		//Initilizes l10n domain
		$this->local();
	}
	function admin_url()
	{
		return admin_url('options-general.php?page=' . $this->identifier);
	}
	function undo_anchor($title = '')
	{
		//Assemble our url, nonce and all
		$url = wp_nonce_url($this->admin_url() . '&' . $this->unique_prefix . '_admin_undo=true', $this->unique_prefix . '_admin_undo');
		//Return a valid Undo anchor
		return ' <a title="' . $title . '" href="' . $url . '">' . __('Undo', $this->identifier) . '</a>';
	}
	function upgrade_anchor($title = '', $text = '')
	{
		//Assemble our url, nonce and all
		$url = wp_nonce_url($this->admin_url() . '&' . $this->unique_prefix . '_admin_upgrade=true', $this->unique_prefix . '_admin_upgrade');
		if($text == '')
		{
			$text = __('Migrate now.', $this->identifier);
		}
		//Return a valid Undo anchor
		return ' <a title="' . $title . '" href="' . $url . '">' . $text . '</a>';
	}
	function init()
	{
		//Admin Options update hook
		if(isset($_POST[$this->unique_prefix . '_admin_options']))
		{
			//Temporarily add update function on init if form has been submitted
			$this->opts_update();
		}
		//Admin Options reset hook
		if(isset($_POST[$this->unique_prefix . '_admin_reset']))
		{
			//Run the reset function on init if reset form has been submitted
			$this->opts_reset();
		}
		//Admin Options export hook
		else if(isset($_POST[$this->unique_prefix . '_admin_export']))
		{
			//Run the export function on init if export form has been submitted
			$this->opts_export();
		}
		//Admin Options import hook
		else if(isset($_FILES[$this->unique_prefix . '_admin_import_file']) && !empty($_FILES[$this->unique_prefix . '_admin_import_file']['name']))
		{
			//Run the import function on init if import form has been submitted
			$this->opts_import();
		}
		//Admin Options rollback hook
		else if(isset($_GET[$this->unique_prefix . '_admin_undo']))
		{
			//Run the rollback function on init if undo button has been pressed
			$this->opts_undo();
		}
		//Admin Options rollback hook
		else if(isset($_GET[$this->unique_prefix . '_admin_upgrade']))
		{
			//Run the rollback function on init if undo button has been pressed
			$this->opts_upgrade_wrapper();
		}
		//Add in the nice "settings" link to the plugins page
		add_filter('plugin_action_links', array($this, 'filter_plugin_actions'), 10, 2);
		//Register options
		register_setting($this->unique_prefix . '_options', $this->unique_prefix . '_options', '');
	}
	/**
	 * Adds the adminpage the menue and the nice little settings link
	 *
	 */
	function add_page()
	{
		//Add the submenu page to "settings" menu
		$hookname = add_submenu_page('options-general.php', __($this->full_name, $this->identifier), $this->short_name, $this->access_level, $this->identifier, array($this, 'admin_page'));
		// check capability of user to manage options (access control)
		if(current_user_can($this->access_level))
		{
			//Register admin_head-$hookname callback
			add_action('admin_head-' . $hookname, array($this, 'admin_head'));			
			//Register Help Output
			add_action('contextual_help', array($this, 'contextual_help'), 10, 2);
		}
	}
	/**
	 * Initilizes localization textdomain for translations (if applicable)
	 * 
	 * Will conditionally load the textdomain for translations. This is here for
	 * plugins that span multiple files and have localization in more than one file
	 * 
	 * @return void
	 */
	function local()
	{
		global $l10n;
		// the global and the check might become obsolete in
		// further wordpress versions
		// @see https://core.trac.wordpress.org/ticket/10527		
		if(!isset($l10n[$this->identifier]))
		{
			load_plugin_textdomain($this->identifier, false, $this->identifier . '/languages');
		}
	}
	/**
	 * Places in a link to the settings page in the plugins listing entry
	 * 
	 * @param  array  $links An array of links that are output in the listing
	 * @param  string $file The file that is currently in processing
	 * @return array  Array of links that are output in the listing.
	 */
	function filter_plugin_actions($links, $file)
	{
		//Make sure we are adding only for the current plugin
		if($file == $this->plugin_basename)
		{ 
			//Add our link to the end of the array to better integrate into the WP 2.8 plugins page
			$links[] = '<a href="' . $this->admin_url() . '">' . __('Settings') . '</a>';
		}
		return $links;
	}
	/**
	 * uninstall
	 * 
	 * This removes database settings upon deletion of the plugin from WordPress
	 */
	function uninstall()
	{
		//Remove the option array setting
		delete_option($this->unique_prefix . '_options');
		//Remove the option backup array setting
		delete_option($this->unique_prefix . '_options_bk');
		//Remove the version setting
		delete_option($this->unique_prefix . '_version');
	}
	/**
	 * Compares the supplided version with the internal version, places an upgrade warning if there is a missmatch
	 */
	function version_check($version)
	{
		//Do a quick version check
		if(version_compare($version, $this->version, '<') && is_array($this->opt))
		{
			//Throw an error since the DB version is out of date
			$this->message['error'][] = __('Your settings are out of date.', $this->identifier) . $this->upgrade_anchor(__('Migrate the settings now.', $this->identifier), __('Migrate now.', $this->identifier));
			//Output any messages that there may be
			$this->message();
			return false;
		}
		else if(!is_array($this->opt))
		{
			//Throw an error since it appears the options were never registered
			$this->message['error'][] = __('Your plugin install is incomplete.', $this->identifier) . $this->upgrade_anchor(__('Load default settings now.', $this->identifier), __('Complete now.', $this->identifier));
			//Output any messages that there may be
			$this->message();
			return false;
		}
		return true;
	}
	/**
	 * Synchronizes the backup options entry with the current options entry
	 */
	function opts_backup()
	{
		//Set the backup options in the DB to the current options
		update_option($this->unique_prefix . '_options_bk', get_option($this->unique_prefix . '_options'));
	}
	/**
	 * Function prototype to prevent errors
	 */
	function opts_update()
	{
	}
	/**
	 * Exports a XML options document
	 */
	function opts_export()
	{
		//Do a nonce check, prevent malicious link/form problems 
		check_admin_referer($this->unique_prefix . '_admin_import_export');
		//Update our internal settings
		$this->opt = get_option($this->unique_prefix . '_options');
		//Create a DOM document
		$dom = new DOMDocument('1.0', 'UTF-8');
		//Adds in newlines and tabs to the output
		$dom->formatOutput = true;
		//We're not using a DTD therefore we need to specify it as a standalone document
		$dom->xmlStandalone = true;
		//Add an element called options
		$node = $dom->createElement('options');
		$parnode = $dom->appendChild($node);
		//Add a child element named plugin
		$node = $dom->createElement('plugin');
		$plugnode = $parnode->appendChild($node);
		//Add some attributes that identify the plugin and version for the options export
		$plugnode->setAttribute('name', $this->short_name);
		$plugnode->setAttribute('version', $this->version);
		//Change our headder to text/xml for direct save
		header('Cache-Control: public');
		//The next two will cause good browsers to download instead of displaying the file
		header('Content-Description: File Transfer');
		header('Content-disposition: attachemnt; filename=' . $this->unique_prefix . '_settings.xml');
		header('Content-Type: text/xml');
		//Loop through the options array
		foreach($this->opt as $key=>$option)
		{
			//Add a option tag under the options tag, store the option value
			$node = $dom->createElement('option', htmlentities($option, ENT_COMPAT, 'UTF-8'));
			$newnode = $plugnode->appendChild($node);
			//Change the tag's name to that of the stored option
			$newnode->setAttribute('name', $key);
		}
		//Prepair the XML for output
		$output = $dom->saveXML();
		//Let the browser know how long the file is
		header('Content-Length: ' . strlen($output)); // binary length
		//Output the file
		echo $output;
		//Prevent WordPress from continuing on
		die();
	}
	/**
	 * Imports a XML options document
	 */
	function opts_import()
	{
		//Our quick and dirty error supressor
		function error($errno, $errstr, $eerfile, $errline)
		{
			return true;
		}
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer($this->unique_prefix . '_admin_import_export');
		//Set the backup options in the DB to the current options
		$this->opts_backup();
		//Create a DOM document
		$dom = new DOMDocument('1.0', 'UTF-8');
		//We want to catch errors ourselves
		set_error_handler('error');
		//Load the user uploaded file, handle failure gracefully
		if($dom->load($_FILES[$this->unique_prefix . '_admin_import_file']['tmp_name']))
		{
			$opts_temp = array();
			$version = '';
			//Have to use an xpath query otherwise we run into problems
			$xpath = new DOMXPath($dom);  
			$option_sets = $xpath->query('plugin');
			//Loop through all of the xpath query results
			foreach($option_sets as $options)
			{
				//We only want to import options for only this plugin
				if($options->getAttribute('name') === $this->short_name)
				{
					//Grab the file version
					$version = explode('.', $options->getAttribute('version'));
					//Loop around all of the options
					foreach($options->getelementsByTagName('option') as $child)
					{
						//Place the option into the option array, DOMDocument decodes html entities for us
						$opts_temp[$child->getAttribute('name')] = $child->nodeValue;
					}
				}
			}
			$this->opts_upgrade($opts_temp, $version);
			//Commit the loaded options to the database
			update_option($this->unique_prefix . '_options', $this->opt);
			//Everything was successful, let the user know
			$this->message['updated fade'][] = __('Settings successfully imported from the uploaded file.', $this->identifier) . $this->undo_anchor(__('Undo the options import.', $this->identifier));
		}
		else
		{
			//Throw an error since we could not load the file for various reasons
			$this->message['error'][] = __('Importing settings from file failed.', $this->identifier);
		}
		//Reset to the default error handler after we're done
		restore_error_handler();
		//Output any messages that there may be
		add_action('admin_notices', array($this, 'message'));
	}
	/**
	 * Resets the database settings array to the default set in opt
	 */
	function opts_reset()
	{
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer($this->unique_prefix . '_admin_import_export');
		//Set the backup options in the DB to the current options
		$this->opts_backup();
		//Load in the hard coded default option values
		update_option($this->unique_prefix . '_options', $this->opt);
		//Reset successful, let the user know
		$this->message['updated fade'][] = __('Settings successfully reset to the default values.', $this->identifier) . $this->undo_anchor(__('Undo the options reset.', $this->identifier));
		add_action('admin_notices', array($this, 'message'));
	}
	/**
	 * Undos the last settings save/reset/import
	 */
	function opts_undo()
	{
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer($this->unique_prefix . '_admin_undo');
		//Set the options array to the current options
		$opt = get_option($this->unique_prefix . '_options');
		//Set the options in the DB to the backup options
		update_option($this->unique_prefix . '_options', get_option($this->unique_prefix . '_options_bk'));
		//Set the backup options to the undid options
		update_option($this->unique_prefix . '_options_bk', $opt);
		//Send the success/undo message
		$this->message['updated fade'][] = __('Settings successfully undid the last operation.', $this->identifier) . $this->undo_anchor(__('Undo the last undo operation.', $this->identifier));
		add_action('admin_notices', array($this, 'message'));
	}
	/**
	 * Upgrades input options array, sets to $this->opt, designed to be overwritten
	 * 
	 * @param array $opts
	 * @param string $version the version of the passed in options
	 */
	function opts_upgrade($opts, $version)
	{
		//We don't support using newer versioned option files in older releases
		if(version_compare($this->version, $version, '>='))
		{
			$this->opt = $opts;
		}
	}
	/**
	 * Forces a database settings upgrade
	 */
	function opts_upgrade_wrapper()
	{
		//Do a nonce check, prevent malicious link/form problems
		check_admin_referer($this->unique_prefix . '_admin_upgrade');
		//Grab the database options
		$opts = get_option($this->unique_prefix . '_options');
		if(is_array($opts))
		{
			//Feed the just read options into the upgrade function
			$this->opts_upgrade($opts, get_option($this->unique_prefix . '_version'));
			//Always have to update the version
			update_option($this->unique_prefix . '_version', $this->version);
			//Store the options
			update_option($this->unique_prefix . '_options', $this->opt);
			//Send the success message
			$this->message['updated fade'][] = __('Settings successfully migrated.', $this->identifier);
		}
		else
		{
			//Run the install script
			$this->install();
			//Send the success message
			$this->message['updated fade'][] = __('Default settings successfully installed.', $this->identifier);
		}
		add_action('admin_notices', array($this, 'message'));
	}
	/**
	 * contextual_help action hook function
	 * 
	 * @param  string $contextual_help
	 * @param  string $screen_id
	 * @return string
	 */
	function contextual_help($contextual_help, $screen_id)
	{
		//Add contextual help on current screen
		if($screen_id == 'settings_page_' . $this->identifier)
		{
			$contextual_help = $this->_get_contextual_help();
			$this->_has_contextual_help = true;
		}
		return $contextual_help;
	}
	/**
	 * get contextual help
	 * 
	 * @return string
	 */
	protected function _get_contextual_help()
	{
		$t = $this->_get_help_text();	
		$t = sprintf('<div class="metabox-prefs">%s</div>', $t);	
		$title = __($this->full_name, $this->identifier);	
		$t = sprintf('<h5>%s</h5>%s', sprintf(__('Get help with "%s"'), $title), $t);
		return $t;
	}
	/**
	 * Prints to screen all of the messages stored in the message member variable
	 */
	function message()
	{
		//Loop through our message classes
		foreach($this->message as $key => $class)
		{
			//Loop through the messages in the current class
			foreach($class as $message)
			{
				printf('<div class="%s"><p>%s</p></div>', $key, $message);	
			}
		}
		$this->message = array();
	}
	/**
	 * Function prototype to prevent errors
	 */
	function install()
	{
		
	}
	/**
	 * Function prototype to prevent errors
	 */
	function admin_head()
	{
		
	}
	/**
	 * Function prototype to prevent errors
	 */
	function admin_page()
	{
		
	}
	/**
	 * Function prototype to prevent errors
	 */
	protected function _get_help_text()
	{
		
	}
	/**
	 * Returns a valid xHTML element ID
	 * 
	 * @param object $option
	 * @return 
	 */
	function get_valid_id($option)
	{
		if(is_numeric($option[0]))
		{
			return 'p' . $option;
		}
		else
		{
			return $option;
		}
	}
	function import_form()
	{
		printf('<div id="%s_import_export_relocate">', $this->unique_prefix);
		printf('<form action="options-general.php?page=%s" method="post" enctype="multipart/form-data" id="%s_admin_upload">', $this->identifier, $this->unique_prefix);
		wp_nonce_field($this->unique_prefix . '_admin_import_export');
		printf('<fieldset id="import_export" class="%s_options">', $this->unique_prefix);
		echo '<h3>' . __('Import/Export/Reset Settings', $this->identifier) . '</h3>';
		echo '<p>' . __('Import settings from a XML file, export the current settings to a XML file, or reset to the default settings.', $this->identifier) . '</p>';
		echo '<table class="form-table"><tr valign="top"><th scope="row">';
		printf('<label for="%s_admin_import_file">', $this->unique_prefix);
		_e('Settings File', $this->identifier);
		echo '</label></th><td>';
		printf('<input type="file" name="%s_admin_import_file" id="%s_admin_import_file" size="32" /><br /><span class="setting-description">', $this->unique_prefix, $this->unique_prefix);
		_e('Select a XML settings file to upload and import settings from.', 'breadcrumb_navxt');
		echo '</span></td></tr></table><p class="submit">';
		printf('<input type="submit" class="button" name="%s_admin_import" value="' . __('Import', $this->identifier) . '"/>', $this->unique_prefix, $this->unique_prefix);
		printf('<input type="submit" class="button" name="%s_admin_export" value="' . __('Export', $this->identifier) . '"/>', $this->unique_prefix);
		printf('<input type="submit" class="button" name="%s_admin_reset" value="' . __('Reset', $this->identifier) . '"/>', $this->unique_prefix, $this->unique_prefix);
		echo '</p></fieldset></form></div>';
	}
	/**
	 * This will output a well formed hidden option
	 * 
	 * @param string $option
	 * @return 
	 */
	function input_hidden($option)
	{
		$optid = $this->get_valid_id($option);?>
		<input type="hidden" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" id="<?php echo $optid;?>" value="<?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?>"/>
	<?php
	}
	/**
	 * This will output a well formed table row for a text input
	 * 
	 * @param string $label
	 * @param string $option
	 * @param string $width [optional]
	 * @param bool $disable [optional]
	 * @param string $description [optional]
	 * @return 
	 */
	function input_text($label, $option, $width = '32', $disable = false, $description = '')
	{
		$optid = $this->get_valid_id($option);
		if($disable)
		{?>
			<input type="hidden" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" value="<?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?>" />
		<?php } ?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo $optid;?>"><?php echo $label;?></label>
			</th>
			<td>
				<input type="text" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" id="<?php echo $optid;?>" <?php if($disable){echo 'disabled="disabled" class="disabled"';}?> value="<?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?>" size="<?php echo $width;?>" /><br />
					<?php if($description !== ''){?><span class="setting-description"><?php echo $description;?></span><?php }?>
			</td>
		</tr>
	<?php
	}
	/**
	 * This will output a well formed textbox
	 * 
	 * @param string $label
	 * @param string $option
	 * @param string $rows [optional]
	 * @param bool $disable [optional]
	 * @param string $description [optional]
	 */
	function textbox($label, $option, $height = '3', $disable = false, $description = '')
	{
		$optid = $this->get_valid_id($option);?>
		<p>
			<label for="<?php echo $optid;?>"><?php echo $label;?></label>
		</p>
		<textarea rows="<?php echo $height;?>" <?php if($disable){echo 'disabled="disabled" class="large-text code disabled"';}else{echo 'class="large-text code"';}?> id="<?php echo $optid;?>" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]"><?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?></textarea><br />
		<?php if($description !== ''){?><span class="setting-description"><?php echo $description;?></span><?php }
	}
	/**
	 * This will output a well formed tiny mce ready textbox
	 * 
	 * @param string $label
	 * @param string $option
	 * @param string $rows [optional]
	 * @param bool $disable [optional]
	 * @param string $description [optional]
	 */
	function tinymce($label, $option, $height = '3', $disable = false, $description = '')
	{
		$optid = $this->get_valid_id($option);
		if($disable)
		{?>
			<input type="hidden" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" value="<?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?>" />
		<?php } ?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo $optid;?>"><?php echo $label;?></label>
			</th>
			<td>
				<textarea rows="<?php echo $height;?>" <?php if($disable){echo 'disabled="disabled" class="mtekk_mce disabled"';}else{echo 'class="mtekk_mce"';}?> id="<?php echo $optid;?>" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]"><?php echo htmlentities($this->opt[$option], ENT_COMPAT, 'UTF-8');?></textarea><br />
				<?php if($description !== ''){?><span class="setting-description"><?php echo $description;?></span><?php }?>
			</td>
		</tr>
	<?php
	}
	/**
	 * This will output a well formed table row for a checkbox input
	 * 
	 * @param string $label
	 * @param string $option
	 * @param string $instruction
	 * @param bool $disable [optional]
	 * @param string $description [optional]
	 * @return 
	 */
	function input_check($label, $option, $instruction, $disable = false, $description = '')
	{
		$optid = $this->get_valid_id($option);?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo $optid;?>"><?php echo $label;?></label>
			</th>
			<td>	
				<label>
					<input type="checkbox" name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" id="<?php echo $optid;?>" <?php if($disable){echo 'disabled="disabled" class="disabled"';}?> value="true" <?php checked(true, $this->opt[$option]);?> />
						<?php echo $instruction; ?>				
				</label><br />
				<?php if($description !== ''){?><span class="setting-description"><?php echo $description;?></span><?php }?>
			</td>
		</tr>
	<?php
	}
	/**
	 * This will output a singular radio type form input field
	 * 
	 * @param string $label
	 * @param string $option
	 * @param string $instruction
	 * @param object $disable [optional]
	 * @return 
	 */
	function input_radio($option, $value, $instruction, $disable = false, $type = 'radio')
	{?>
		<label>
			<input name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" type="<?php echo $type;?>" <?php if($disable){echo 'disabled="disabled" class="disabled togx"';}else{echo 'class="togx"';}?> value="<?php echo $value;?>" <?php checked($value, $this->opt[$option]);?> />
			<?php echo $instruction; ?>
		</label><br/>
	<?php
	}
	/**
	 * This will output a well formed table row for a select input
	 * 
	 * @param string $label
	 * @param string $option
	 * @param array $values
	 * @param bool $disable [optional]
	 * @param string $description [optional]
	 * @return 
	 */
	function input_select($label, $option, $values, $disable = false, $description = '', $titles = false)
	{
		//If we don't have titles passed in, we'll use option names as values
		if(!$titles)
		{
			$titles = $values;
		}
		$optid = $this->get_valid_id($option);?>
		<tr valign="top">
			<th scope="row">
				<label for="<?php echo $optid;?>"><?php echo $label;?></label>
			</th>
			<td>
				<select name="<?php echo $this->unique_prefix . '_options[' . $option;?>]" id="<?php echo $optid;?>" <?php if($disable){echo 'disabled="disabled" class="disabled"';}?>>
					<?php $this->select_options($option, $titles, $values); ?>
				</select><br />
				<?php if($description !== ''){?><span class="setting-description"><?php echo $description;?></span><?php }?>
			</td>
		</tr>
	<?php
	}
	/**
	 * Displays wordpress options as <seclect>
	 *
	 * @param string $optionname name of wordpress options store
	 * @param array $options array of names of options that can be selected
	 * @param array $exclude[optional] array of names in $options array to be excluded
	 */
	function select_options($optionname, $options, $values, $exclude = array())
	{
		$value = $this->opt[$optionname];
		//Now do the rest
		foreach($options as $key => $option)
		{
			if(!in_array($option, $exclude))
			{
				printf('<option value="%s" %s>%s</option>', $values[$key], selected(true, ($value == $values[$key]), false), $option);
			}
		}
	}
}