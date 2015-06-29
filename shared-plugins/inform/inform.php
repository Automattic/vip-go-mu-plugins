<?php
/***************************************************************************
 Plugin Name: Inform Tag
 Plugin URI: http://wordpressext.inform.com/extract/wpplugin/
 Version: 0.2.3
 Author: <a href="http://www.inform.com" target="_blank">Inform Technologies, Inc.</a>
 Description: Use <a href="http://www.inform.com" target="_blank">Inform</a>'s powerful categorization engine to tag your Wordpress content.
 ***************************************************************************/

if (!class_exists('inform_plugin')) {
	
	class inform_plugin {
		
		private $s_css = 'css/inform.css';
		private $s_delim_iab = 'IAB_TOPICS';
		private $s_delim_inform = 'INFORM_TAGS';
		private $s_delim_tag = '~';
		private $s_delim_tag_pair = '::';
		private $s_js = 'js/inform.js';
		private $s_search_prefix = 'www.inform.com/topic/';
		private $s_taxonomy = 'inform';
		
		// ******************************************************************************
		// INIT
		
		/**
		 * __construct(): add action hook callbacks
		 *
		 */
		
		public function __construct() {
			add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('save_post', array($this, 'save_post'));
			add_action('wp_ajax_inform_proxy', array($this, 'ajax_proxy'));
		}
		
		// ******************************************************************************
		// CALLBACKS
		
		/**
		 * admin_enqueue_scripts(): add required CSS and JS
		 *
		 * @param string name of current admin page
		 *
		 */
		
		public function admin_enqueue_scripts($s_page) {
			// only needed on edit post page
			if (in_array($s_page, array('post.php', 'post-new.php'))) {
				wp_enqueue_style($this -> s_taxonomy.'-css', plugins_url($this -> s_css, __FILE__));
				wp_enqueue_script($this -> s_taxonomy.'-js', plugins_url($this -> s_js, __FILE__));
			}
		}
		
		/**
		 * admin_init(): register settings
		 *
		 */
		
		public function admin_init() {
			
			// register setting
			register_setting($this -> s_taxonomy, $this -> s_taxonomy.'_options', array($this, 'admin_options_validate'));
			
			// settings fields
			add_settings_section($this -> s_taxonomy.'_main', NULL, '__return_false', $this -> s_taxonomy);
			add_settings_field('auto_tags', __('Auto-select Inform tags'), array($this, 'admin_render_auto_tags'), $this -> s_taxonomy, $this -> s_taxonomy.'_main');
			add_settings_field('auto_iabs', __('Auto-select IAB tags'), array($this, 'admin_render_auto_iabs'), $this -> s_taxonomy, $this -> s_taxonomy.'_main');
			add_settings_field('required', __('Required'), array($this, 'admin_render_required'), $this -> s_taxonomy, $this -> s_taxonomy.'_main');
			
			// set defaults
			$a_options = (array) $this -> options();
			$b_defaults_set = TRUE;
			if (!isset($a_options['auto_iabs'])) {
				$a_options['auto_iabs'] = 1;
				$b_defaults_set = FALSE;
			}
			if (!isset($a_options['required'])) {
				$a_options['required'] = 1;
				$b_defaults_set = FALSE;
			}
			if (!$b_defaults_set) {
				// back-compat start - update from v0.2.2
				if ($s_dep = get_option('inform_iab_tag_option')) {
					$a_options['auto_iabs'] = !($s_dep !== 'on');
					delete_option('inform_iab_tag_option');
				}
				if ($s_dep = get_option('inform_tag_option')) {
					$a_options['auto_tags'] = $s_dep === 'on';
					delete_option('inform_tag_option');
				} // back-compat end
				update_option($this -> s_taxonomy.'_options', $a_options);
			}
		}
		
		/**
		 * admin_menu(): add admin page components, settings menu option
		 *
		 */
		
		public function admin_menu() {
			
			// add edit post page components
			add_meta_box($this -> s_taxonomy.'_metabox', '<strong class="inform-logo">Inform</strong> Article Curation Tool', array($this, 'metabox_render_inform'), 'post', 'normal', 'core');
			add_meta_box($this -> s_taxonomy.'_iab', 'IAB tags', array($this, 'metabox_render_iab'), 'post', 'side', 'default');
			
			// add options page
			add_options_page(__('Inform Tagging Options'), __('Inform'), 'manage_options', $this -> s_taxonomy.'_options', array($this, 'admin_render_options_page'));
		}
		
		/**
		 * save_post(): save Inform and IAB tags
		 *
		 * @param number $i_post_id id of saved post
		 *
		 */
		
		public function save_post($i_post_id) {
			
			global $post;
			
			// check nonce, capabilities
			if (!isset($_POST[$this -> s_taxonomy.'_nonce']) ||
				!wp_verify_nonce($_POST[$this -> s_taxonomy.'_nonce'], 'save_tags') ||
				!current_user_can('edit_post', $i_post_id) ||
				$post -> post_type == 'revision') {
				return FALSE;
			}
			
			// iab
			$this -> tags_save($i_post_id, 'iab', $_POST[$this -> s_taxonomy.'-iabs']);
			
			// inform
			$this -> tags_save($i_post_id, 'inform', $_POST[$this -> s_taxonomy.'-tags']);
			
			// set processed flag
			update_post_meta($i_post_id, '_'.$this -> s_taxonomy.'_processed', TRUE);
		}
		
		/**
		 * ajax_proxy(): call proxy from admin AJAX
		 *
		 */
		
		public function ajax_proxy() {
			require dirname(__FILE__).'/proxy.php';
		}
		
		// ******************************************************************************
		// ADMIN
		
		/**
		 * admin_options_validate(): validate settings
		 *
		 * @param array $a_input updated settings
		 *
		 * @return array validated settings
		 */
		
		public function admin_options_validate($a_input) {
			
			$a_options = $this -> options();
			
			// autoselect IAB tags
			$a_options['auto_iabs'] = isset($a_input['auto_iabs']) && $a_input['auto_iabs'];
			
			// autoselect Inform tags
			$a_options['auto_tags'] = isset($a_input['auto_tags']) && $a_input['auto_tags'];
			
			// required
			$a_options['required'] = isset($a_input['required']) && $a_input['required'];
			
			return $a_options;
		}
		
		/**
		 * admin_render_options_page(): render Inform settings page
		 *
		 */
		
		public function admin_render_options_page() {
			
			// restrict
			if (!current_user_can('manage_options')) {
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}
			
			?><div class="wrap"><?php
			
			screen_icon();
			
				?><h2><?php _e('Inform Tagging Options'); ?></h2>
				<form action="options.php" method="post"><?php
			
			settings_fields($this -> s_taxonomy);
			do_settings_sections($this -> s_taxonomy);
			
					?><p class="submit"><input class="button-primary" type="submit" name="submit" value="<?php _e('Save Changes'); ?>" /></p>
				</form>
			</div><?php
		}
		
		// ******************************************************************************
		// ADMIN FIELDS
		
		public function admin_render_auto_iabs() {
			
			$a_options = $this -> options();
			
			?><label for="<?php echo $this -> s_taxonomy; ?>_options_auto_iabs"><input type="checkbox" id="<?php echo $this -> s_taxonomy; ?>_options_auto_iabs" name="<?php echo $this -> s_taxonomy; ?>_options[auto_iabs]" value="1"<?php isset($a_options['auto_iabs']) && checked($a_options['auto_iabs'], 1); ?> /> <em style="color:gray"><?php esc_html_e('Any returned IAB tags will be added to the post\'s IAB tags'); ?></em></label><?php
		}
		
		public function admin_render_auto_tags() {
			
			$a_options = $this -> options();
			
			?><label for="<?php echo $this -> s_taxonomy; ?>_options_auto_tags"><input type="checkbox" id="<?php echo $this -> s_taxonomy; ?>_options_auto_tags" name="<?php echo $this -> s_taxonomy; ?>_options[auto_tags]" value="1"<?php isset($a_options['auto_tags']) && checked($a_options['auto_tags'], 1); ?> /> <em style="color:gray"><?php esc_html_e('Any returned Inform tags will be added to the post\'s tags'); ?></em></label><?php
		}
		
		public function admin_render_required() {
			
			$a_options = $this -> options();
			
			?><label for="<?php echo $this -> s_taxonomy; ?>_options_required"><input type="checkbox" id="<?php echo $this -> s_taxonomy; ?>_options_required" name="<?php echo $this -> s_taxonomy; ?>_options[required]" value="1"<?php isset($a_options['required']) && checked($a_options['required'], 1); ?> /> <em style="color:gray"><?php esc_html_e('User will be required to process the post before saving.'); ?></em></label><?php
		}
		
		/**
		 * metabox_render_iab(): render iab tag meta box
		 *
		 */
		
		public function metabox_render_iab() {
			
			// get saved tags
			$a_iabs = isset($_GET['post']) ? $this -> tags($_GET['post'], 'iab', 0, TRUE) : array();
			
			?><div class="tagchecklist"><?php
				
				// show tags
				foreach ($a_iabs as $i => $a_tag) {
					?><span><a class="ntdelbutton">X</a>&nbsp;<?php echo esc_html($a_tag['label']); ?></span><?php
				}
				
			?></div><?php
			?><div id="inform-iabs" class="panel tags"><?php
				?><h4 class="title"><span class="inform-logo">Inform</span> suggested <a href="http://www.iab.net/" target="_blank">IAB</a> tags</h4><?php
				?><p class="empty">Process post to see suggestions</p><?php
			?></div><?php
		}
		
		/**
		 * metabox_render_inform(): render Inform ACT metabox
		 *
		 * contains nonce and hidden textarea inputs to store selected Inform/IAB tags
		 *
		 */
		
		public function metabox_render_inform() {
			
			$a_options = $this -> options();
			
			?><div class="hide-if-js"><?php
			?><label style="vertical-align:top">Inform tags:</label> <?php
			?><textarea name="<?php echo $this -> s_taxonomy; ?>-tags"><?php
			
			// inform tags
			$a_tags = isset($_GET['post']) ? $this -> tags($_GET['post'], 'inform', 0, TRUE) : array();
			foreach ($a_tags as $i => $a_tag) {
				$a_tags[$i] = $a_tag['label'].$this -> s_delim_tag_pair.$a_tag['rel'];
			}
			echo esc_html(implode($this -> s_delim_tag, $a_tags));
			
			?></textarea><?php
			?>&nbsp;<?php
			?><label style="vertical-align:top">IAB tags:</label> <?php
			?><textarea name="<?php echo $this -> s_taxonomy; ?>-iabs"><?php
			
			// IAB tags
			$a_tags = isset($_GET['post']) ? $this -> tags($_GET['post'], 'iab', 0, TRUE) : array();
			foreach ($a_tags as $i => $a_tag) {
				$a_tags[$i] = $a_tag['label'].$this -> s_delim_tag_pair.$a_tag['rel'];
			}
			echo esc_html(implode($this -> s_delim_tag, $a_tags));
			
			?></textarea><?php
			?></div><?php
			
			wp_nonce_field('save_tags', $this -> s_taxonomy.'_nonce');
			
			// processed flag
			if (isset($_GET['post']) && get_post_meta((int) $_GET['post'], '_'.$this -> s_taxonomy.'_processed')) {
				?><input type="hidden" name="inform_processed" value="1" /><?php
			}
			
			?><p><input type="button" value="Get tags" class="button"/></p><?php
			?><script type="text/javascript">
				jQuery(document).ready(function () {
					'use strict';
					
					var informTagger = new InformTagger();
					
					// Inform settings
					informTagger.articles(10);
					informTagger.blogs(5);
					informTagger.iabDelim('<?php echo $this -> s_delim_iab; ?>');
					informTagger.iabTagsOn(<?php echo isset($a_options['auto_iabs']) && $a_options['auto_iabs'] ? 'true' : 'false'; ?>);
					informTagger.informDelim('<?php echo $this -> s_delim_inform; ?>');
					informTagger.informTagsOn(<?php echo isset($a_options['auto_tags']) && $a_options['auto_tags'] ? 'true' : 'false'; ?>);
					informTagger.pairDelim('<?php echo $this -> s_delim_tag_pair; ?>');
					informTagger.required(<?php echo isset($a_options['required']) && $a_options['required'] ? 'true' : 'false'; ?>);
					informTagger.searchPrefix('<?php echo $this -> s_search_prefix; ?>');
					informTagger.tagDelim('<?php echo $this -> s_delim_tag; ?>');
					informTagger.videos(5);
					
					// WP settings
					informTagger.wpAjaxProxy('inform_proxy');
					informTagger.wpMetaboxSelector('.postbox');
					informTagger.wpMetaboxContentsSelector('.inside');
					informTagger.wpTagChecklistSelector('.tagchecklist');
					informTagger.wpTagChecklistItemSelector('span');
					informTagger.wpUpdateTags(function () {
						tagBox.flushTags('.tagsdiv');
					});
					
					// DOM refs
					informTagger.btnProcess(jQuery('#inform_metabox :input[type = "button"]'));
					informTagger.inputTagsIab(jQuery('#inform_metabox :input[name $= "iabs"]'));
					informTagger.inputTagsInform(jQuery('#inform_metabox :input[name $= "tags"]'));
					informTagger.inputTagsWp(jQuery('#tax-input-post_tag'));
					informTagger.metaboxIab(jQuery('#inform_iab .inside'));
					informTagger.metaboxTags(jQuery('#tagsdiv-post_tag .inside'));
					informTagger.tagsIab(jQuery('#inform_iab #inform-iabs'));
					
					// go
					informTagger.init();
				});
			</script><?php
		}
		
		// ******************************************************************************
		// CORE METHODS
		
		/**
		 * options(): get saved options
		 *
		 * @return array
		 */
		
		private function options() {
			return get_option($this -> s_taxonomy.'_options');
		}
		
		/**
		 * tags(): get tags
		 *
		 * @param number $i_post_id             id of post
		 * @param string $s_type                type of tag (iab|inform)
		 * @param number $i_relevance_threshold minimum relevance score
		 * @param bool   $b_include_scores      include relevance scores in result array
		 *
		 * @return array tags
		 *
		 */
		
		function tags($i_post_id = NULL, $s_type = 'inform', $i_relevance_threshold = 0, $b_include_scores = FALSE) {
			
			global $post;
			
			// attempt to determine id
			if (!$i_post_id) {
				$i_post_id = get_the_ID();
				if (!$i_post_id && isset($post -> ID)) {
					$i_post_id = $post -> ID;
				}
				if (!$i_post_id) {
					return FALSE;
				}
			}
			
			// get tags
			$a_tags_saved = get_post_meta($i_post_id, '_'.$this -> s_taxonomy.'_'.$s_type, TRUE);
			
			// filter by relevance threshold
			$a_tags = array();
			if (is_array($a_tags_saved)) {
				foreach ($a_tags_saved as $a_tag) {
					if ($a_tag['rel'] >= $i_relevance_threshold) {
						$a_tags[] = $b_include_scores ? $a_tag : $a_tag['label'];
					}
				}
			}
			
			return $a_tags;
		}
		
		/**
		 * tags_save(): save tags
		 *
		 * @param number $i_post_id id of post being edited
		 * @param string $s_type    type of tag to save (iab|inform)
		 * @param string $s_input   string of delimited tags
		 *
		 */
		
		public function tags_save($i_post_id, $s_type, $s_input) {
			
			$a_tags = array();
			$s_tags_in = trim(stripslashes($s_input));
			
			if (!empty($s_tags_in)) {
				$a_tags_in = explode($this -> s_delim_tag, $s_tags_in);
				foreach ($a_tags_in as $s_pair) {
					$a_pair = explode($this -> s_delim_tag_pair, $s_pair);
					$a_tags[] = array('label' => $a_pair[0],
										'rel' => (int) $a_pair[1]);
				}
			}
			
			update_post_meta($i_post_id, '_'.$this -> s_taxonomy.'_'.$s_type, $a_tags);
		}
	}
	
	// instantiate plug-in
	$inform_plugin = new inform_plugin();
	
	// API (localized)
	
	function inform_tags($i_post_id = NULL, $i_relevance_threshold = 0, $b_include_scores = FALSE) {
		global $inform_plugin;
		return $inform_plugin -> tags($i_post_id, 'inform', $i_relevance_threshold, $b_include_scores);
	}
	
	function inform_iabs($i_post_id = NULL, $i_relevance_threshold = 0, $b_include_scores = FALSE) {
		global $inform_plugin;
		return $inform_plugin -> tags($i_post_id, 'iab', $i_relevance_threshold, $b_include_scores);
	}
}

?>
