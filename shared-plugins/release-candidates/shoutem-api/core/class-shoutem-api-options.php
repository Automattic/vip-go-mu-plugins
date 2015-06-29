<?php
/*
  Copyright 2011 by ShoutEm, Inc. (www.shoutem.com)

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
class ShoutemApiOptions {

	var $shoutem_options_name = "shoutem_api_options";

	var $shoutem_default_options = array (
		'encryption_key'=>'change.me',
		'cache_expiration' => 3600, //1h,
		'include_featured_image' => true,
		'enable_fb_commentable' => false,
		'enable_wp_commentable' => true,
		'lead_img_custom_field_regex' => ''
	);

	public function __construct($shoutem_api) {
		$this->shoutem_api = $shoutem_api;
		add_action('admin_menu',array($this, 'admin_menu'));
	}

	public function add_listener($listener) {
		add_action("update_option_{$this->shoutem_options_name}", $listener);
	}

	public function admin_menu() {
		add_options_page('Shoutem API Settings', 'Shoutem API', 'manage_options', 'shoutem-api', array($this, 'admin_options'));
	}

	public function get_options() {
		$shoutem_options = $this->shoutem_default_options;
		$saved_options = get_option($this->shoutem_options_name);
		if(!empty($saved_options)) {
			foreach($saved_options as $key=>$val) {
				$shoutem_options[$key] = $val;
			}
		}
		return $shoutem_options;
	}

	public function save_options($options) {
		do_action("shoutem_save_options");
		update_option($this->shoutem_options_name,$options);
	}

	public function admin_options() {
		if (!current_user_can('manage_options'))  {
	    	wp_die( __('You do not have sufficient permissions to access this page.') );
	 	}
	 	$options = $this->get_options();
	 	$encryption_key = $options['encryption_key'];
	 	if (!empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], "update-options")) {
	 		$this->update_options($options);
	 	}

	 	if (class_exists('ShoutemNGGDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-ngg-dao.php";
		}

		if (class_exists('ShoutemFlaGalleryDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-flagallery-dao.php";
		}

		if (class_exists('ShoutemEventsManagerDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-events-manager-dao.php";
		}

		if (class_exists('ShoutemEventsCalendarDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-events-calendar-dao.php";
		}

		$ngg_integration = array(
				'plugin_name' => __('NextGEN Gallery'),
				'integration_desc' => __('Allows you to import NextGEN galleries in to your ShoutEm application'),
				'integration_ok' => ShoutemNGGDao::available(),
				'plugin_link' => 'http://wordpress.org/extend/plugins/nextgen-gallery/'
				);
		$flagallery_integration = array(
				'plugin_name' => __('GRAND FlAGallery'),
				'integration_desc' => __('Allows you to import GRAND FlAGallery galleries in to your ShoutEm application'),
				'integration_ok' => ShoutemFlaGalleryDao::available(),
				'plugin_link' => 'http://wordpress.org/extend/plugins/flash-album-gallery/'
				);

		$podpress_integration = array(
				'plugin_name' => __('podPress'),
				'integration_desc' => __('Allows you to import podcasts into your ShoutEm application'),
				'integration_ok' => isset($GLOBALS['podPress']),
				'plugin_link' => 'http://wordpress.org/extend/plugins/podpress/'
				);

		$powerpress_integration = array(
				'plugin_name' => __('PowerPress'),
				'integration_desc' => __('Show podcasts in posts on your ShoutEm application'),
				'integration_ok' => function_exists('powerpress_get_enclosure'),
				'plugin_link' => 'http://wordpress.org/extend/plugins/powerpress/'
				);

		$viper_integration = array(
				'plugin_name' => __('Viper\'s Video Quicktags'),
				'integration_desc' => __('Show videos in posts on your ShoutEm application'),
				'integration_ok' => isset($GLOBALS['VipersVideoQuicktags']),
				'plugin_link' => 'http://wordpress.org/extend/plugins/vipers-video-quicktags/'
				);

		$em_integration = array(
				'plugin_name' => __('Events Manager'),
				'integration_desc' => __('Allows you to import events into your ShoutEm application'),
				'integration_ok' => ShoutemEventsManagerDao::available(),
				'plugin_link' => 'http://wordpress.org/extend/plugins/events-manager/'
				);

		$ec_integration = array(
				'plugin_name' => __('The Events Calendar'),
				'integration_desc' => __('Allows you to import events into your ShoutEm application'),
				'integration_ok' => ShoutemEventsCalendarDao::available(),
				'plugin_link' => 'http://wordpress.org/extend/plugins/the-events-calendar/'
				);

		$plugin_integration = array(
			$ngg_integration,
			$flagallery_integration,
			$podpress_integration,
			$powerpress_integration,
			$viper_integration,
			$em_integration,
			$ec_integration
		);

	 	$this->print_options_page($options,$plugin_integration);

	}

	private function get_checkbox_value($key) {
		if(array_key_exists($key,$_POST)) {
			$cb = $_POST[$key];
			if ($cb == "true") {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	private function update_options(&$options) {

		if(!empty($_POST['encryption_key'])) {
			$options['encryption_key'] = sanitize_text_field($_POST['encryption_key']);
		}

		if(array_key_exists('cache_expiration',$_POST)) {
			$expiration = sanitize_text_field($_POST['cache_expiration']);
			if (is_numeric($expiration)
			&& (int)$expiration >= 0) {
				$options['cache_expiration'] = $expiration;
			}
		}

		if(array_key_exists('lead_img_custom_field_regex',$_POST)) {
			$options['lead_img_custom_field_regex'] = sanitize_text_field($_POST['lead_img_custom_field_regex']);
		}

		if (array_key_exists('comments_provider',$_POST)) {
			$comments_provider = $_POST['comments_provider'];
			if ($comments_provider == 'wordpress') {
				$options['enable_fb_commentable'] = false;
				$options['enable_wp_commentable'] = true;
			} else if ($comments_provider = 'facebook') {
				$options['enable_fb_commentable'] = true;
				$options['enable_wp_commentable'] = false;
			} else {
				$options['enable_fb_commentable'] = true;
				$options['enable_wp_commentable'] = true;
			}

		}
		$options['include_featured_image'] = $this->get_checkbox_value('include_featured_image');

		$this->save_options($options);
	}

	private function print_options_page($options, $plugin_integrations) {
		$default_encryption_key_warrning = '';
		if($options['encryption_key'] == $this->shoutem_default_options['encryption_key']) {
			$default_encryption_key_warrning =
			'<p>*Currently, the default encryption key is set. Leaving default encryption key could lead to compromised security of site. Change encryption key!</p>';
		}
		?>
			<div class="wrap">
  				<div id="icon-options-general" class="icon32"><br /></div>
  				<h2><?php esc_html_e('Shoutem API Settings') ?></h2>
  				<script type="text/javascript">
  					//Tnx for password generator to: Xhanch Studio http://xhanch.com
  					function gen_numb(min, max){
		                return (Math.floor(Math.random() * (max - min)) + min);
		            }

		            function gen_chr(num, lwr, upr, oth, ext){
		                var num_chr = "0123456789";
		                var lwr_chr = "abcdefghijklmnopqrstuvwxyz";
		                var upr_chr = lwr_chr.toUpperCase();
		                var oth_chr = "`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ";
		                var sel_chr = ext;

		                if(num == true)
		                    sel_chr += num_chr;
		                if(lwr == true)
		                    sel_chr += lwr_chr;
		                if(upr == true)
		                    sel_chr += upr_chr;
		                if(oth == true)
		                    sel_chr += oth_chr;
		                return sel_chr.charAt(gen_numb(0, sel_chr.length));
		            }

		            function gen_pass(len, ext, bgn_num, bgn_lwr, bgn_upr, bgn_oth,
		                flw_num, flw_lwr, flw_upr, flw_oth){
		                var res = "";

		                if(len > 0){
		                    res += gen_chr(bgn_num, bgn_lwr, bgn_upr, bgn_oth, ext);
		                    for(var i=1;i<len;i++)
		                        res += gen_chr(flw_num, flw_lwr, flw_upr, flw_oth, ext);
		                    return res;
		                }
		            }
  					var generate_random_encryption_key_on_click = function() {
        					var encryption_key_element = document.getElementById('shoutem_api_encryption_key_input');
        					encryption_key_element.setAttribute('value',gen_pass(16,true,true,true,false,true,true,true,false));
        			}
  				</script>

    			<form action="options-general.php?page=shoutem-api" method="post">
    				<?php wp_nonce_field('update-options'); ?>
    				<table class="form-table">
      					<tr valign="top">
        				<th scope="row"><?php esc_html_e('Shoutem api encryption key') ?></th>
        				<td><input type="text" id="shoutem_api_encryption_key_input" name="encryption_key" value="<?php echo htmlentities($options['encryption_key']); ?>" size="15" />
        				<input class="button-primary" type="button" name="generate_random_encryption_key" onClick="generate_random_encryption_key_on_click();" value="<?php esc_attr_e('Generate') ?>" />
        				</td>
        				<tr valign="top">
        				<th scope="row"><?php esc_html_e('Cache expiration') ?>Cache expiration</th>
        				<td><input type="text" id="shoutem_api_cache_expiration_input" name="cache_expiration" value="<?php echo htmlentities($options['cache_expiration']); ?>" size="15" />
        				<?php esc_html_e('seconds (0 dissables caching)') ?>
        				</td>
        				<tr valign="top">
        				<th scope="row"><?php esc_html_e('Lead image custom field regex') ?></th>
        				<td><input type="text" name="lead_img_custom_field_regex" value="<?php echo htmlentities($options['lead_img_custom_field_regex']); ?>" size="15" />
        				</tr>
        				<tr valign="top">
        				<th scope="row"><?php esc_html_e('Include featured/thumbnail post image') ?></th>
        				<td>
        				<input type="hidden" name="include_featured_image" value="false" />
        				<input type="checkbox" name="include_featured_image" value="true" <?php echo ($options['include_featured_image'] ? "checked=\"yes\"" : "") ?> />
        				</td>
        				</tr>

        				<tr valign="top">
        				<th scope="row"><?php esc_html_e('Comments provider') ?></th>
        				<td>
        				<input type="radio" name="comments_provider" value="wordpress" <?php echo ((!$options['enable_fb_commentable'] && $options['enable_wp_commentable']) ? "checked" : "");?>> <?php esc_html_e('Wordpress Comments') ?> <br />
        				<input type="radio" name="comments_provider" value="facebook" <?php echo (($options['enable_fb_commentable'] && !$options['enable_wp_commentable']) ? "checked" : "");?>> <?php esc_html_e('Facebook Comments') ?> <br />
        				<input type="radio" name="comments_provider" value="wordpress_facebook" <?php echo (($options['enable_fb_commentable'] && $options['enable_wp_commentable']) ? "checked" : "");?>> <?php esc_html_e('Facebook and Wordpress Comments') ?> <br />
        				</td>
        				</tr>

    				</table>
    				<?php echo $default_encryption_key_warrning; ?>
    				<p class="submit">
				    	<input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
				    </p>
    			</form>
    			<div class="clear"></div>
    			<?php if ( ! function_exists('wpcom_is_vip' ) || !wpcom_is_vip() ): ?>
    			<h4><?php esc_html_e('ShoutEm automatically integrates with the following plugins:') ?></h4>
    			<table class="widefat">
    				<thead>
    					<tr>
    						<th class="manage-column"><?php esc_html_e('Plugin') ?></th>
    						<th class="manage-column"><?php esc_html_e('Integration Status') ?></th>
    						<th class="manage-column"><?php esc_html_e('Integration Description') ?></th>
    					</tr>
    				</thead>
    					<?php foreach($plugin_integrations as $plugin_integration) { ?>
    						<tr>
    							<td class="plugin-title">
    							<?php if ($plugin_integration['plugin_link']) {
	    								$link = esc_url($plugin_integration['plugin_link']);
	    								echo "<a href=\"$link\" target=\"_blank\">".htmlentities($plugin_integration['plugin_name'])."</a>";
    								} else {
    									echo htmlentities($plugin_integration['plugin_name']);
    								}
    							?>
    							</td>
    							<td><?php $plugin_integration['integration_ok'] ? esc_html_e('OK') : esc_html_e('Not Integrated, check if the plugin is installed and active');?></td>
    							<td class="desc"><?php echo htmlentities($plugin_integration['integration_desc']) ?></td>
    						</tr>
    					<?php } ?>
    			</table>
	    		<?php endif; ?>
    		</div>
		<?php
	}
}
