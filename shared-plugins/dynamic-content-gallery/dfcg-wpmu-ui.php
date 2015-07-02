<?php
/* This file is part of the DYNAMIC CONTENT GALLERY Plugin Version 2.2
**********************************************************************
Copyright 2008  Ade WALKER  (email : info@studiograsshopper.ch)

Options Page for WordPress MU
*/

dfcg_load_textdomain();

// Handle the updating of options
	if( isset($_POST['info_update']) ) {
		
		// Is the user allowed to do this?
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Sorry. You do not have permission to do this.'));
			
		// check the nonce
		check_admin_referer( 'dfcg-update' );
		
		// build the array from input
		$updated_options = $_POST['dfcg'];
		
		// trim whitespace within the array values
		foreach( $updated_options as $key => $value ) {
			$updated_options[$key] = trim($value);
		}
		
		// deal with the MOOTOOLS checkbox
		$onoff_opts = array( 'mootools' );
		foreach($onoff_opts as $key) {
			$updated_options[$key] = $updated_options[$key] ? '1' : '0';
		}
		
		// deal with the RESET checkbox
		$bool_opts = array( 'reset' );
		foreach($bool_opts as $key) {
			$updated_options[$key] = $updated_options[$key] ? 'true' : 'false';
		}
		
		// If RESET is checked, reset the options
		if ( $updated_options['reset'] == "true" ) {
			dfcg_unset_gallery_options();	// clear out the old ones 
			dfcg_set_gallery_options();		// put back the defaults
			echo '<div id="message" class="updated fade"><p><strong>' . __('Dynamic Content Gallery Settings reset to defaults.') . '</strong></p></div>';
		} else {
		// Otherwise, update the options
		update_option( 'dfcg_plugin_settings', $updated_options);
		echo '<div id="message" class="updated fade"><p><strong>' . __('Dynamic Content Gallery Settings updated and saved.') . '</strong></p></div>';
		}
	}
	// Display the updated options
	$options = get_option('dfcg_plugin_settings');
?>
<style>
.form-table {margin-bottom:-6px;}
.dfcginfo {border:1px solid #CCCCCC;margin:24px 0px 0px 0px;padding:10px 10px 10px 10px;}
.dfcginfo ul {list-style-type:disc;margin-left:30px;font-size:11px;}
.dfcgopts {background:#F1F1F1;padding:10px 10px 10px 10px;}
.dfcgcredits {border-top:1px solid #CCCCCC;margin:10px 0px 0px 0px;padding:10px 0px 0px 0px;}
</style>
<div class="wrap" id="dfcgstyle">

	<h2>Dynamic Content Gallery Configuration</h2>
	<form method="post">
		<?php
		// put the nonce in
		wp_nonce_field('dfcg-update');
		?>
		
		<div class="dfcginfo">
			<p><em>You are using Dynamic Content Gallery version <?php echo DFCG_VER; ?> for WordPress Mu.</em></p>
			<p><?php _e("This is where you set up the selection of Categories and Posts, the paths to the locations of the gallery images, and the styling options for the gallery.", DFCG_DOMAIN); ?></p>
			<p><?php _e("For further information, see the README.txt document supplied with the plugin or visit the", DFCG_DOMAIN); ?> <a href="http://www.studiograsshopper.ch/dynamic-content-gallery-configuration/">Dynamic Content Gallery configuration</a> page.</p>
			<p><strong><?php _e('IMPORTANT: You must follow the instructions in sections 1 and 2 below and configure this page before using the plugin.', DFCG_DOMAIN); ?></strong></p>
		</div>
		
		<div class="dfcginfo">
			<h3><?php _e('How to add the Dynamic Content Gallery to your Theme:', DFCG_DOMAIN); ?></h3>
			<p><?php _e('To display the Dynamic Content Gallery in your theme, add this code to your theme file wherever you want to display the gallery:', DFCG_DOMAIN); ?></p>
			<p><code>&lt;?php include (ABSPATH . 'wp-content/themes/vip/plugins/dynamic-content-gallery/dynamic-gallery.php'); ?&gt;</code></p>
		</div>
		
		<fieldset name="dynamic_content_gallery" class="options">
		
			<div class="dfcginfo">
				<h3><?php _e('1. Assigning an image and a description to each Post (REQUIRED):', DFCG_DOMAIN); ?></h3>
				<p>In order to populate the gallery with your images follow these steps:</p>
				<p>Go to Admin > Write Post as if you were going to create a new Post. There is no need to type anything in this Post - it is being used simply to access the Media Uploader. Click the Add Media icon. When the Media Uploader pop-up appears, select "Choose Files to Upload" and browse to your chosen image. Once the Media Uploader screen has uploaded your file, make a note of the URL shown in the "Link URL" box. Make sure that you click the File URL button before noting down the Link URL. Ignore the "Insert into Post" button and simply click "Save all changes" to exit the Uploader. Exit the Write Post screen without saving.</p>
				<p>To assign an uploaded image to a Post and have this displayed in the gallery, create two Custom Post fields when writing a new post (or when editing an existing one):</p> 
				<ul>
					<li>Key = <strong>dfcg_image</strong> <?php _e('with a Value =', DFCG_DOMAIN); ?> <strong><?php _e('Full path to the Image file as per the "Link URL" that you made a note of when uploading the image', DFCG_DOMAIN); ?></strong> <?php _e('eg.', DFCG_DOMAIN); ?> <em>http://myblog.blogs.com/files/2008/11/myImage.jpg</em></li>
					<li>Key = <strong>dfcg_desc</strong> with a Value = <strong>Description text</strong> eg. <em>Here's our latest news!</em></li>
				</ul>
				<p>Don't forget to Save (and/or Publish) the Post when you are finished.</p>
			</div>
					
			<div class="dfcginfo">
				<h3>2. Select the Categories and Posts (REQUIRED):</h3>
				<p>The gallery is designed to display 5 images.  For each of the 5 gallery image "slots", the plugin will display the image specified in the Custom Field <strong>dfcg_image</strong>, the description specified in the Custom Field <strong>dfcg_desc</strong>, and the Post Title, in accordance with the combination of Category ID and Post Select that you enter in the boxes below.</p>
				<p>For the Post Select: enter <strong>1</strong> for the latest post, <strong>2</strong> for the last-but-one post, <strong>3</strong> for the post before that, and so on. Possible schemes are:</p>
				<ul>
					<li>To display the latest Post from 5 Categories: Enter a different ID number in each <strong>Category ID</strong> and enter <strong>1</strong> in each <strong>Post Select</strong> box.</li>
					<li>To display the latest 5 Posts from 1 Category: Enter the same ID number in each <strong>Category ID</strong> and enter <strong>1</strong>, <strong>2</strong>, <strong>3</strong>, <strong>4</strong>, <strong>5</strong> in the <strong>Post Select</strong> boxes.</li>
					<li>To display the latest 5 Posts regardless of Category: Blank out the ID numbers in each <strong>Category ID</strong> and enter <strong>1</strong>, <strong>2</strong>, <strong>3</strong>, <strong>4</strong>, <strong>5</strong> in the <strong>Post Select</strong> boxes.</li>
					<li>Or any other combination of <strong>Category ID</strong> and <strong>Post Select</strong> as desired.</li>
				</ul>
			
			<table class="optiontable form-table">
				<tbody>
					<tr valign="top">
						<th width="120px" scope="row">Image "Slots"</th><th scope="row">Category ID</th><th scope="row">Post Select</th>
					</tr>
					<tr valign="top">
						<td width="120px">1st image</td>
						<td><input name="dfcg[cat01]" id="cat01" size="5" value="<?php echo $options['cat01']; ?>" /></td>
						<td><input name="dfcg[off01]" id="off01" size="5" value="<?php echo $options['off01']; ?>" />&nbsp;Ex. Enter <strong>1</strong> for latest post.</td>
					</tr>
					<tr valign="top">
						<td>2nd image</td>
						<td><input name="dfcg[cat02]" id="cat02" size="5" value="<?php echo $options['cat02']; ?>" /></td>
						<td><input name="dfcg[off02]" id="off02" size="5" value="<?php echo $options['off02']; ?>" /></td>
					</tr>
					<tr valign="top">
						<td>3rd image</td>
						<td><input name="dfcg[cat03]" id="cat03" size="5" value="<?php echo $options['cat03']; ?>" /></td>
						<td><input name="dfcg[off03]" id="off03" size="5" value="<?php echo $options['off03']; ?>" /></td>
					</tr>
					<tr valign="top">
						<td>4th image</td>
						<td><input name="dfcg[cat04]" id="cat04" size="5" value="<?php echo $options['cat04']; ?>" /></td>
						<td><input name="dfcg[off04]" id="off04" size="5" value="<?php echo $options['off04']; ?>" /></td>
					</tr>
					<tr valign="top">
						<td>5th image</td>
						<td><input name="dfcg[cat05]" id="cat05" size="5" value="<?php echo $options['cat05']; ?>" /></td>
						<td><input name="dfcg[off05]" id="off05" size="5" value="<?php echo $options['off05']; ?>" /></td>
					</tr>
				</tbody>
			</table>
			</div>
			
			<input name="dfcg[homeurl]" id="dfcg-imagepath" type="hidden" value="<?php $options['homeurl']; ?>" />
			<input name="dfcg[imagepath]" id="dfcg-imagepath" type="hidden" value="<?php $options['imagepath']; ?>" />
			<input name="dfcg[defimagepath]" id="dfcg-defimagepath" type="hidden" value="<?php $options['defimagepath']; ?>" />
       		        
			<div class="dfcginfo">
				<h3>3. Default image description (OPTIONAL):</h3>
				<p>By default the Dynamic Content Gallery plugin displays a description for each image displayed. The plugin looks for the image description in this sequence:</p>
				<ul>
					<li>First, it checks the Post for a Custom Field with the Key of <strong>dfcg_desc</strong>, or if this doesn't exist =></li>
					<li>Pulls in the Category Description set up in WP Admin>Manage>Categories, or if this doesn't exist =></li>
					<li>Shows the description below.</li>
				</ul>
				<p>Be aware that the gallery has relatively little space in which to display this text and therefore it is recommended to keep this description short, probably less than 20 words.</p>
			
			<table class="optiontable form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">Default Description:</th>
						<td><textarea name="dfcg[defimagedesc]" cols="75" rows="2" id="dfcg-defimagedesc"><?php echo stripslashes( $options['defimagedesc'] ); ?></textarea></td>
					</tr>
				</tbody>
			</table>
			</div>
						
			<div class="dfcginfo">
				<h3>4. Gallery size and CSS options (REQUIRED):</h3>
				<p>This is where you set up various layout and CSS options for your gallery including the size of the gallery, the height of the "Slider", gallery border, and the font sizes, colours and margins for the text displayed in the "Slider". The addition of these options in this Settings page saves you having to customise the plugin's CSS stylesheet. These are the items that users most often need to personalise to fit with their theme.</p>	
			
			<table class="optiontable form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">Gallery Width:</th>
						<td><input name="dfcg[gallery-width]" id="dfcg-gallery-width" size="5" value="<?php echo $options['gallery-width']; ?>" />&nbsp;in pixels. <em>Default is 460px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Gallery Height:</th>
						<td><input name="dfcg[gallery-height]" id="dfcg-gallery-height" size="5" value="<?php echo $options['gallery-height']; ?>" />&nbsp;in pixels. <em>Default is 250px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Slider Height:</th>
						<td><input name="dfcg[slide-height]" id="dfcg-slide-height" size="5" value="<?php echo $options['slide-height']; ?>" />&nbsp;in pixels. <em>Default is 50px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Gallery border width:</th>
						<td><input name="dfcg[gallery-border-thick]" id="dfcg-gallery-border-thick" size="3" value="<?php echo $options['gallery-border-thick']; ?>" />&nbsp;in pixels. If you don't want a border enter 0 in this box. <em>Default is 1px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Gallery border colour:</th>
						<td><input name="dfcg[gallery-border-colour]" id="dfcg-gallery-border-colour" size="8" value="<?php echo $options['gallery-border-colour']; ?>" />&nbsp;Enter color hex code like this #000000. <em>Default is #000000.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Heading font size:</th>
						<td><input name="dfcg[slide-h2-size]" id="dfcg-slide-h2-size" size="5" value="<?php echo $options['slide-h2-size']; ?>" />&nbsp;in pixels. <em>Default is 12px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Heading font colour:</th>
						<td><input name="dfcg[slide-h2-colour]" id="dfcg-slide-h2-colour" size="8" value="<?php echo $options['slide-h2-colour']; ?>" />&nbsp;Enter color hex code like this #FFFFFF. <em>Default is #FFFFFF.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Heading Margin top and bottom:</th>
						<td><input name="dfcg[slide-h2-margtb]" id="dfcg-slide-h2-margtb" size="3" value="<?php echo $options['slide-h2-margtb']; ?>" />&nbsp;in pixels. <em>Default is 2px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Heading Margin left and right:</th>
						<td><input name="dfcg[slide-h2-marglr]" id="dfcg-slide-h2-marglr" size="5" value="<?php echo $options['slide-h2-marglr']; ?>" />&nbsp;in pixels. <em>Default is 5px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Description font size:</th>
						<td><input name="dfcg[slide-p-size]" id="dfcg-slide-p-size" size="5" value="<?php echo $options['slide-p-size']; ?>" />&nbsp;in pixels. <em>Default is 11px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Description font colour:</th>
						<td><input name="dfcg[slide-p-colour]" id="dfcg-slide-p-colour" size="8" value="<?php echo $options['slide-p-colour']; ?>" />&nbsp;Enter color hex code like this #FFFFFF. <em>Default is #FFFFFF.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Description Margin top and bottom:</th>
						<td><input name="dfcg[slide-p-margtb]" id="dfcg-slide-p-margtb" size="5" value="<?php echo $options['slide-p-margtb']; ?>" />&nbsp;in pixels. <em>Default is 2px.</em></td>
					</tr>
					<tr valign="top">
						<th scope="row">Description Margin left and right:</th>
						<td><input name="dfcg[slide-p-marglr]" id="dfcg-slide-p-marglr" size="5" value="<?php echo $options['slide-p-marglr']; ?>" />&nbsp;in pixels. <em>Default is 5px.</em></td>
					</tr>
				</tbody>
			</table>
			</div>
			
			<input name="dfcg[homeurl]" id="dfcg-imagepath" type="hidden" value="<?php $options['homeurl']; ?>" />
			<input name="dfcg[imagepath]" id="dfcg-imagepath" type="hidden" value="<?php $options['imagepath']; ?>" />
			<input name="dfcg[defimagepath]" id="dfcg-defimagepath" type="hidden" value="<?php $options['defimagepath']; ?>" />
				
			<div class="dfcginfo">
			<label for="dfcg-mootools">
				<input name="dfcg[mootools]" type="checkbox" id="dfcg-mootools" value="1" <?php checked('1', $options['mootools']); ?> />
				<?php _e('Disable Mootools Javascript Library') ?></label>
			<p><?php _e('Check the box ONLY in the event that another plugin is already loading the Mootools Javascript library files in your site.'); ?> <em><?php _e('Default is UNCHECKED.', DFCG_DOMAIN); ?></em></p>
			</div>
		
			<div class="dfcginfo">
			<label for="dfcg-reset">
				<input type="checkbox" name="dfcg[reset]" id="dfcg-reset" value="<?php echo $options['reset']; ?>" />&nbsp;<strong><?php _e('Reset all options to the Default settings')?></strong></label>
			</div>
        
		</fieldset>
		<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update Options') ?>" /></p>
	</form>
	
	<div class="dfcgcredits">
		<p>For further information please read the README document included in the plugin download, or visit the <a href="http://www.studiograsshopper.ch/dynamic-content-gallery-mu-configuration/">Dynamic Content Gallery MU configuration</a> page.</p>
		<p>The Dynamic Content Gallery plugin uses the SmoothGallery script developed by <a href="http://smoothgallery.jondesign.net/">Jonathan Schemoul</a>, and is inspired by Jason Schuller's Featured Content Gallery plugin. Grateful acknowledgements to Jonathan's wonderful script and Jason's popular WordPress plugin implementation.</p> 
		<p>Dynamic Content Gallery plugin for WordPress and WordPress Mu by <a href="http://www.studiograsshopper.ch/">Ade Walker</a></p>
		<p>You are using the WordPress Mu version of the Dynamic Content Gallery <strong>Version: <?php echo DFCG_VER; ?></strong></p>      
		<p>If you have found this plugin useful, please consider making a donation to help support future development. Your support will be much appreciated. Thank you!</p>
	</div>
</div>
