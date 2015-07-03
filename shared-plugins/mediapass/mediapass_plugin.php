<?php

require_once(dirname(__FILE__) . "/mediapass_api.php");
require_once(dirname(__FILE__) . "/mediapass_plugin_content_list_ex.php");
require_once(dirname(__FILE__) . "/mediapass_plugin_content_filters.php");

class MediaPass_Plugin {

	const PLUGIN_NAME	=	'mediapass';
	const API_ENV		=	'prod';
	const CLIENT_ID		=	'97B9A5B07E8FCC853F1588FA6C024E36';
	
	const API_PREFIX	=	'https://api.mediapass.com/';
	const FE_PREFIX		=	'https://www.mediapass.com/';
	
	const NONCE			=	'mp-nonce';
	
	private $faq_feed_url = 'http://mymediapass.com/wordpress/2011/06/faq/feed/?withoutcomments=1';
	
	private $api_url;
	
	public static $auth_login_url;
	public static $auth_register_url;
	
	private $auth_refresh_url;
	private $auth_deauth_url;
	
	private $api_client;
	
	private $content_list_extensions;
	
	/**
	 * Options:
	 *  - mp_placement_categories - array( category_id ) specifying categories to include in subscription
	 *  - mp_user_number - ASSET ID fetched via authentication process
	 *  - mp_access_token - OAUTH token
	 *  - mp_installed_url - The site's url, cleaned up a bit
	 */
	 
	const OPT_INSTALLED_URL = 'mp_installed_url';
	const OPT_USER_ID		= 'mp_user_id'		;
	const OPT_USER_URL		= 'mp_user_url'		;
	const OPT_USER_ERROR	= 'mp_user_err'		;
	const OPT_USER_NUMBER	= 'mp_user_number'	;
	
	const OPT_PLACEMENT_CATEGORIES 	= 'mp_placement_categories';
	const OPT_PLACEMENT_AUTHORS		= 'mp_placement_authors';
	const OPT_PLACEMENT_TAGS		= 'mp_placement_tags';
	const OPT_PLACEMENT_DATES		= 'mp_placement_dates';
	const OPT_PLACEMENT_PAGES		= 'mp_placement_pages';
	
	const OPT_INCLUDED_POSTS = 'mp_included_posts'; // Posts that have been made premium even if their meta data are not premium
	const OPT_EXCLUDED_POSTS = 'mp_excluded_posts'; // Posts that have been excluded from being premium if their meta data are premium
	const OPT_DEFAULT_PLACEMENT_MODE = 'mp_paywall_style'; // The default paywall style (either inpage or page overlay)
	const OPT_NUM_INPAGE_PARAGRAPHS = 'mp_num_inpage_paragraphs'; // Number of paragraphs to show by default in InPage overlay
	
	
	//const OPT_DEFAULT_PLACEMENT_MODE	=	'overlay';
	
	const OPT_ACCESS_TOKEN			= 'mp_access_token';
	const OPT_REFRESH_TOKEN			= 'mp_refresh_token';
	
	const NONCE_METERED		=	'metered';
	const NONCE_PRICING		=	'pricing';
	const NONCE_BENEFITS	=	'benefits';
	const NONCE_NETWORK		=	'network';
	const NONCE_ACCOUNT		=	'account';
	const NONCE_ECPM_FLOOR	=	'ecpm-floor';
	
	public function __construct() {
		$this->init_api_strings();

		$this->set_site_identifier_options();
		
		if( is_admin() ) {
			add_action('admin_init', array(&$this,'init_for_admin'));
			add_action('admin_menu', array(&$this,'add_admin_panel'));
			// Add Settings Link in Plugin Panel
			add_filter( 'plugin_action_links', array(&$this,'init_plugin_actions'), 10, 2 );
		} 
		
		add_action('init', array(&$this,'init'));
		// Add the teaser
		add_filter('the_content', array(&$this, "add_content_teaser"), get_the_content());
		add_filter('content_save_pre', array(&$this, 'add_save_shortcodes') ); // save any new shortcodes that were added
	}
	
	public static function nonce_for($for) {
		wp_nonce_field(self::NONCE . $for);
	}
	
	private function check_nonce($for){
		check_admin_referer( self::NONCE . $for );
		
		return true;
	}
	private function is_vip() {
		return function_exists('wpcom_is_vip') && wpcom_is_vip();	
	}
	
	private function is_valid_http_post_action($for){
		return !empty($_POST) && $this->check_nonce($for);
	}
	
	private function init_api_strings() {
		$p = self::API_PREFIX;
		$c = self::CLIENT_ID;
		
		// not used yet, assuming always vip
		$partner = $this->is_vip() ? "wp-vip" : "wp";
		
		self::$auth_login_url 		= $p . 'account/auth/?partner='. $partner .'&client_id='. $c .'&scope='. $p . 'auth.html&response_type=token&redirect_uri=';
		self::$auth_register_url 	= $p . 'account/authregister/?partner='. $partner .'&client_id='. $c .'&scope=' . $p . 'auth.html&response_type=token&redirect_uri='; 
		$this->auth_refresh_url		= $p . 'oauth/refresh?client_id='. $c .'&scope=' . $p . 'auth.html&grant_type=refresh_token&redirect_uri=';
		$this->auth_deauth_url		= $p . 'oauth/unauthorize?client_id='. $c .'&scope=' . $p . 'auth.html&redirect_uri=';
		
		$this->api_client = new MediaPass('','',self::API_ENV);
	}
	
	public function has_premium_meta($the_post){
		$HasPremiumCategory = false;
		$HasPremiumTag = false;
		$HasPremiumAuthor = false;

		//print_r($the_post);
		// Check if the Post has a premium category
		$selected = get_option(self::OPT_PLACEMENT_CATEGORIES);
		$selected = empty($selected) ? array() : $selected;
		if(!empty($selected) ){
			$post_categories = get_the_category( $the_post->ID );
			$post_category_ids = ! empty( $post_categories ) ? wp_list_pluck( $post_categories, 'term_id' ) : array();
			$category_overlap = array_intersect($selected, $post_category_ids);
		
			if( ! empty( $category_overlap  ) ) {
				$HasPremiumCategory = true;
			}
		}
		
		// Check if tht post has a premium author
		$selected = get_option(self::OPT_PLACEMENT_AUTHORS);
		$selected = empty($selected) ? array() : $selected;
		if( in_array( $the_post->post_author, $selected ) ) {
			$HasPremiumAuthor = true;
		}	
			
		// Check if the post has a premium tag
		$selected = get_option(self::OPT_PLACEMENT_TAGS);
		$selected = empty($selected) ? array() : $selected;
		$tags = get_the_tags( $the_post->ID );
		$tag_ids = ! empty( $tags ) ? wp_list_pluck( $tags, 'term_id' ) : array();
		$tag_overlap = array_intersect($selected, $tag_ids);
		if( ! empty( $tag_overlap ) ) {
			$HasPremiumTag = true;
		}
	
		return ($HasPremiumTag || $HasPremiumAuthor || $HasPremiumCategory);
	}	
	
	private function set_site_identifier_options() {
		$mp_base_url = split("/", site_url());
    	$mp_strip_endurl = $mp_base_url[0] ."//". $mp_base_url[2];
		$mp_str_url = 'www.' . str_replace(array('http://','https://'), '', $mp_strip_endurl);

		$installed_url = get_option( self::OPT_INSTALLED_URL );
		if ( ! $installed_url || $installed_url != $mp_str_url )
			return;
		
		update_option( self::OPT_INSTALLED_URL , $mp_str_url );
	}
	
	private function register_scripts_for_admin() {
		wp_register_style(  'MPAdminStyles'   , plugins_url('/styles/admin.css', __FILE__));
		wp_register_script( 'MPAdminScripts'  , plugins_url('/js/admin.js',__FILE__));
		
		wp_register_script( 'MPAdminContentListEx', plugins_url('/js/mp-content-list-extensions.js',__FILE__));
		wp_register_script( 'MPAdminContentEditorEx', plugins_url('/js/mp-content-editor-extensions.js',__FILE__));
		
		wp_register_script( 'MPAdminCharts'	  , plugins_url('/js/charting.js',__FILE__));
		wp_register_script( 'MPAdminQuickTags', plugins_url('/js/qtags.js',__FILE__));
		
		wp_register_script( 'fieldselection'  , plugins_url('/js/fieldselection.min.js',__FILE__));
		wp_register_script( 'formfieldlimiter', plugins_url('/js/formfieldlimiter.js', __FILE__));
		
		wp_register_script( 'jqplot', plugins_url('/js/jquery.jqplot.js', __FILE__));
		wp_register_script( 'excanvas', plugins_url('/js/excanvas.js', __FILE__));
		wp_register_script( 'barrender', plugins_url('/js/plugins/jqplot.barRenderer.js', __FILE__));
		wp_register_script( 'axisrenderer', plugins_url('/js/plugins/jqplot.categoryAxisRenderer.js', __FILE__));
		wp_register_script( 'pointlabels', plugins_url('/js/plugins/jqplot.pointLabels.js', __FILE__));
		wp_register_style('chartingstyle', plugins_url('/styles/charting.css', __FILE__));
		
		
		wp_register_style('tcstyle', plugins_url('/styles/tc.css', __FILE__));
		
		wp_register_style( 'jquery-ui-style-flick', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.1/themes/flick/jquery-ui.css', true);
	}
	
	private function enqueue_scripts_for_admin() {
		$hasPage = !empty($_GET['page']);
	
		wp_enqueue_script( 'MPAdminScripts' );
		wp_enqueue_script( 'MPAdminContentListEx');
		wp_enqueue_script( 'MPAdminContentEditorEx');
		
		wp_enqueue_style(  'MPAdminStyles'  );
		
		if( ! $hasPage ) {
			return;
		}
		
		$the_page = $_GET['page'];
		
		if ($the_page == 'mediapass_benefits') {
			wp_enqueue_script('media-upload');
			wp_enqueue_script('thickbox');
			wp_enqueue_script('formfieldlimiter');
			wp_enqueue_style('thickbox');
		} else if( $the_page == 'mediapass' || $the_page == 'mediapass_reporting') {
			wp_enqueue_script('MPAdminCharts');		
		}
	}
	
	public function add_content_teaser($content){
		global $post;
		
		if (is_single() || is_page()){
			$excerpt = $post->post_excerpt;
			if ($excerpt == ""){
				$excerpt = $this->wp2_trim_excerpt($content);
			}
			if ($excerpt != ""){
				$content = "<div id=\"media-pass-tease\" style=\"display:none;\">" . $excerpt . "</div>" . $content;
			}
		}
		return $content;
	}
	private function wp2_trim_excerpt($text) { // Fakes an excerpt if needed
	  if ($text != "") {
		$text = MediaPass_ContentHelper::strip_all_shortcodes($text);
		$text = str_replace('\]\]\>', ']]&gt;', $text);
        $text = preg_replace('@<script[^>]*?>.*?</script>@si', '', $text);
        $text = strip_tags($text, '<p>');
		$text = strip_tags($text);
		$excerpt_length = 15; // number of words to show
		$words = explode(' ', $text, $excerpt_length + 1);
		if (count($words)> $excerpt_length) {
		  array_pop($words);
		  array_push($words, '...');
		  $text = implode(' ', $words);
		}
	  }
	return $text;
	}
	
	
	public function add_editor_buttons($buttons) {
   		array_push($buttons, "overlay_button", "inpage_button", "video_button");
   	
		return $buttons;
	}
	
	public function add_editor_plugins() {
		$plugin_array['overlay_button']	= plugins_url( '/js/overlay.js', __FILE__);
		$plugin_array['inpage_button'] 	= plugins_url( '/js/inpage.js' , __FILE__);
		$plugin_array['video_button'] 	= plugins_url( '/js/video.js'  , __FILE__);
	
		return $plugin_array;
	}
	
	private function init_editor_customizations() {
	   if ( current_user_can('edit_posts') &&  current_user_can('edit_pages') )
	   {
	     add_filter('mce_external_plugins'	, array(&$this, 'add_editor_plugins') );
	     add_filter('mce_buttons'			, array(&$this, 'add_editor_buttons') );
		 add_filter('content_edit_pre'		, array(&$this, 'add_edit_shortcodes') );
	   }
	}
	
	public function add_edit_shortcodes($content){
		$content = MediaPass_Plugin_ContentFilters::mp_content_placement_exemptions($content, false);
		return $content;
	}
	public function add_save_shortcodes($content){
		global $post;
		$excluded_posts = get_option(MediaPass_Plugin::OPT_EXCLUDED_POSTS);
		$included_posts = get_option(MediaPass_Plugin::OPT_INCLUDED_POSTS);

		$PremiumMeta = MediaPass_Plugin::has_premium_meta($post);
		$Protection = MediaPass_ContentHelper::has_existing_protection($content);
		
		if ($PremiumMeta && $Protection){
			if (isset($included_posts[$post->ID])){
				unset($included_posts[$post->ID]);
			}
			// Remove any exclusions
			if (isset($excluded_posts[$post->ID])){
				unset($excluded_posts[$post->ID]);
			}
		} else if (!$PremiumMeta && $Protection){
			$included_posts[$post->ID] = true;
			// Remove any exclusions
			if (isset($excluded_posts[$post->ID])){
				unset($excluded_posts[$post->ID]);
			}
		} else if ($PremiumMeta && !$Protection){
			if (isset($included_posts[$post->ID])){
				unset($included_posts[$post->ID]);
			}
			$excluded_posts[$post->ID] = true;
		} else if (!$PremiumMeta && !$Protection){
			if (isset($included_posts[$post->ID])){
				unset($included_posts[$post->ID]);
			}
			// Remove any exclusions
			if (isset($excluded_posts[$post->ID])){
				unset($excluded_posts[$post->ID]);
			}
		}
		update_option( MediaPass_Plugin::OPT_EXCLUDED_POSTS , $excluded_posts );
		update_option( MediaPass_Plugin::OPT_INCLUDED_POSTS , $included_posts );		
		
		return $content;
	}
	
	public function init_for_admin() {
		$this->register_scripts_for_admin();
		$this->init_editor_customizations();
		$this->enqueue_scripts_for_admin();
		
		$this->content_list_extensions = new MediaPass_Plugin_ContentListExtensions();
		
		add_action('add_meta_boxes', array(&$this,'add_meta_to_editor'));

	}
	
	// Function to add Settings Link on Plugin Page
	public function init_plugin_actions ( $links, $file ) {
		if( $file == 'mediapass/mediapass.php' && function_exists( "admin_url" ) ) {
			$settings_link = '<a href="' . admin_url( 'options-general.php?page=mediapass' ) . '">' . __('Settings') . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		}
		return $links;
	}

	
	public function init() {
		if( ! $this->has_publisher_data() ) {
			$this->get_publisher_data();
		}
		
		// Initialize Options that are potentially empty
		$default_placement_mode = get_option(self::OPT_DEFAULT_PLACEMENT_MODE);
		if (empty($default_placement_mode)){
			update_option( self::OPT_DEFAULT_PLACEMENT_MODE, 'overlay' );
		}
		$num_inpage_paragraphs = get_option(self::OPT_NUM_INPAGE_PARAGRAPHS);
		if (empty($num_inpage_paragraphs)){
			update_option( self::OPT_NUM_INPAGE_PARAGRAPHS, 1 );
		}
		
		$this->content_filters = new MediaPass_Plugin_ContentFilters();
	}
	
	// check if installed domain matches the user account domain
	private function check_mp_match() {
		$error_shown = false; // use this to stop multiple error messages
		if (!empty($_GET['error']) && $_GET['error'] == "access_denied"){
			// if access_token passed then don't show the error, they have access
			if (empty($_GET['access_token'])){
				add_action('admin_notices', array(&$this, 'print_access_denied_message') );
				$error_shown = true;
			}
		}
	
		if (!empty($_GET['deauthed'])) {
			add_action('admin_notices', array(&$this, 'print_deauthed_message') );
			$error_shown = true;
		}
		
		if (!$error_shown){
			if( !$this->has_publisher_data() ) {
				add_action('admin_notices', array(&$this, 'print_mismatch_error') );
			}
		}
	}

	public function print_mismatch_error() {
		echo "<div class='error'><p>The Web site you have installed the MediaPass plugin on doesn't have an associated MediaPass.com account. Please <a href=\"" . esc_url( MediaPass_Plugin::$auth_login_url . urlencode( "http" . ( is_ssl() ? "s" : null) . "://" . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ) ) . "\">connect your account here</a> or contact <a href=\"mailto:support@mediapass.com\">support@mediapass.com</a> for help.</div>";
	}

	public	function print_deauthed_message() {
		echo "<div class='error'><p>You have successfully de-authorized this plugin and unlinked your MediaPass account.</p></div>";
	}
	
	public function print_error_message($error_msg){
		echo "<div class='error'>" . $error_msg . "</div>";
	}

	public	function print_access_denied_message() {
		echo "<div class='error'><p>Incorrect Email and Password. <a href=\"" . esc_url( MediaPass_Plugin::$auth_login_url . urlencode( "http" . ( is_ssl() ? "s" : null) . "://" . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ) ) . "\">Click here</a> to try again.</p></div>";
	}

	private function has_publisher_data() {
		return get_option( self::OPT_USER_ID ) != 0;	
	}	
	
	private function get_publisher_data() {
		// Prevent repeated lookups across pageloads when a site isn't authorized
		$cached = get_transient( 'mp_get_publisher_data_lock' );
		if ( false !== $cached )
			return;

		$pub = $this->api_client->get_publisher_data( get_option(self::OPT_USER_NUMBER) );
		
		$mp_userID 		= $pub['Id'];
		$mp_userURL 	= $pub['Domain'];
		$mp_userERROR 	= $pub['Error'];
		
		$mp_str_user_URL = str_replace(array('http://','https://'), '', $mp_userURL);
		
		update_option( self::OPT_USER_ID 	, $mp_userID );
		update_option( self::OPT_USER_URL	, $mp_str_user_URL );
		update_option( self::OPT_USER_ERROR	, $mp_userERROR );
		
		set_transient( 'mp_get_publisher_data_lock', true, 600 ); // 15 minutes seems like a healthy period
	}
	
	public function add_admin_panel(){
		if (!empty($_GET['access_token']) && !empty($_GET['refresh_token']) && !empty($_GET['id'])) {
			list( $access_token , $refresh_token, $id ) = array( $_GET['access_token'], $_GET['refresh_token'], $_GET['id'] );
		
			update_option( self::OPT_ACCESS_TOKEN , $access_token 	);
			update_option( self::OPT_REFRESH_TOKEN, $refresh_token 	);
			update_option( self::OPT_USER_NUMBER  , $id 			);
			
			delete_transient( 'mp_get_publisher_data_lock' ); // delete the transient to make sure we ping the API for fresh data
			
			$this->get_publisher_data();
			
			// Activate site and set the default mode to EXCLUDE.
			$this->api_client = new MediaPass( $access_token, $id , self::API_ENV );
			
			$site_activation = new stdClass;
			$site_activation->id = $id;
			$site_activation->active = true;
			$site_activation->default_filter_type = 0;
			
			$this->api_client->update_network_site($site_activation);
			
			$this->api_client->set_placement_status("excluded");
			// Activate the paywall
			$this->api_client->set_paywall_status(1); // active
		}
		
		$mp_user_ID 		= get_option(self::OPT_USER_NUMBER  );
		$mp_access_token 	= get_option(self::OPT_ACCESS_TOKEN );
		$mp_refresh_token 	= get_option(self::OPT_REFRESH_TOKEN);
		
		$this->check_mp_match();
		
		if ( $this->has_publisher_data() ) {
				
			$this->api_client = new MediaPass( $mp_access_token, $mp_user_ID , self::API_ENV );
			
			add_menu_page('MediaPass General Information', 'MediaPass', 'read', 'mediapass',array(&$this,'menu_default'), plugins_url('images/logo-icon-16x16.png',__FILE__) );
			
			add_submenu_page('mediapass', 'MediaPass Account Information', 'Account Info', 'edit_others_posts', 'mediapass_accountinfo',array(&$this,'menu_account_info'));
			add_submenu_page('mediapass', 'MediaPass Metered Settings', 'Plugin Settings', 'edit_others_posts', 'mediapass_metered_settings',array(&$this,'menu_metered'));
			add_submenu_page('mediapass', 'MediaPass Reporting', 'Reporting', 'edit_others_posts', 'mediapass_reporting', array(&$this,'menu_reporting'));
			add_submenu_page('mediapass', 'MediaPass Placement Configuration', 'Placement', 'edit_others_posts', 'mediapass_placement', array(&$this,'menu_placement'));
		    add_submenu_page('mediapass', 'MediaPass Price Points', 'Price Points', 'manage_options', 'mediapass_pricepoints',array(&$this,'menu_price_points'));
		    add_submenu_page('mediapass', 'MediaPass Update Benefits', 'Logo and Benefits', 'edit_others_posts', 'mediapass_benefits',array(&$this,'menu_benefits'));
			add_submenu_page('mediapass', 'MediaPass Network Settings', 'Network Settings', 'manage_options', 'mediapass_network_settings',array(&$this,'menu_network'));
		    add_submenu_page('mediapass', 'MediaPass FAQs, Terms and Conditions', 'FAQs', 'edit_posts', 'mediapass_faqs_tc',array(&$this,'menu_faqs_tc'));
		    add_submenu_page('mediapass', 'De-authorize MediaPass Account', 'De-Authorize', 'manage_options', 'mediapass_deauth',array(&$this,'menu_deauth'));
			
			// Disabled for now, pending further development and refinement.
			//
			// add_submenu_page('mediapass', 'MediaPass eCPM Floor', 'eCPM Floor', 'administrator', 'mediapass_ecpm_floor',array(&$this,'menu_ecpm_floor'));
			$excluded_posts = get_option(self::OPT_EXCLUDED_POSTS);
			if (!$excluded_posts){
				update_option( self::OPT_EXCLUDED_POSTS , array());
			}
			$included_posts = get_option(self::OPT_INCLUDED_POSTS);
			if (!$included_posts){
				update_option( self::OPT_INCLUDED_POSTS , array());
			}
			add_filter('media_upload_tabs', array(&$this, 'remove_from_url_tab'));
			//echo "i: ";
			//print_r($included_posts);
			//echo "<br />e: ";
			//print_r($excluded_posts);
		} else {
			add_menu_page('MediaPass General Information', 'MediaPass', 'read', 'mediapass',array(&$this,'menu_signup'));
		}
	}
	

	public function add_meta_to_editor(){
		wp_enqueue_script('fieldselection');
		wp_enqueue_script('MPAdminQuickTags');
		
		add_meta_box(
			'mp-display-opts',
			'MediaPass Content Protection',
			array(&$this,'print_meta_section'),
			'post',
			'core',
			'high'
		);
		
		add_action('admin_print_footer_scripts', array(&$this,'print_init_quicktags'));
	}
		
	public function print_meta_section(){
		echo '<div class="misc-pub-section">Protect Full Page</div>';
		echo '<div class="misc-pub-section">Protect Partial Page Content</div>';
		echo '<div class="misc-pub-section">Protect Video</div>';
		echo '<p class="howto">TIP: For in-page or video content protection, highlight the content in the editor you wish to protect and select the appropriate protection type above.</p>';
	}
	
	public function print_init_quicktags(){
		echo '<script type="text/javascript">mp_init_quicktags();</script>';
	}
	
	private function render_or_error($data,$success) {
		if ($data['Status'] != 'fail') {
			include_once($success);
		} else {
			$error = $data['Msg'];
			include_once('includes/error.php');
		}
	}

	public function menu_reporting() {
		wp_enqueue_script('jqplot');
		wp_enqueue_script('excanvas');
		wp_enqueue_script('barrender');
		wp_enqueue_script('axisrenderer');
		wp_enqueue_script('pointlabels');
		wp_enqueue_style('chartingstyle');
		//wp_enqueue_script('jquery-ui-tabs');
//wp_enqueue_style('jquery-ui-style-flick');
		
		$data = array(
			"monthly" => json_encode($this->api_client->get_monthly_data()),
			"yearly" => json_encode($this->api_client->get_yearly_data())
		);
		
		$this->render_or_error($data, 'includes/reporting.php');
		
		//include_once('includes/reporting.php');	
	}
	
	public function menu_signup() {
		include_once('includes/signup.php');
	}
	
	
	public function menu_metered() {
		$ok = isset($_POST['Status']) && isset($_POST['Count']);
		
		if ($this->is_valid_http_post_action(self::NONCE_METERED) && $ok) {
			list( $status, $count ) = array( $_POST['Status'], $_POST['Count'] );
		
			$flag = true;
			if ($status == "" || (strtolower($status) != "on" && strtolower($status) != "off")) {
				$this->print_error_message("<p>Please select a status of On or Off</p>");
				$flag = false;
			}

			if (strval($count) != "0" && intval($count) == 0){
				$this->print_error_message("<p>Please enter the number of page views a user can access prior to paywall prompt</p>");
				$flag = false;
			} else if ($count < 0) {
				$this->print_error_message("<p>Please enter a positive number of page views a user can access prior to paywall prompt</p>");
				$flag = false;
			}
			if ($flag) {
				$data = $this->api_client->set_request_metering_status( $status, intval($count) );
			}
			
			// Update the options for inpage number of paragraphs and default paywal style
			if (strval($_POST['NumInPageParagraphs']) != "0" && intval($_POST['NumInPageParagraphs']) == 0){
				$this->print_error_message("<p>Please enter the number of paragraphs in the In Page view before the overlay</p>");
				$flag = false;
			} else {
				update_option( self::OPT_NUM_INPAGE_PARAGRAPHS, intval($_POST['NumInPageParagraphs']) );
			}
			if ($_POST['DefaultPlacementMode'] != "overlay" && $_POST['DefaultPlacementMode'] != "inpage"){
				$this->print_error_message("<p>Please enter the default paywall style</p>");
				$flag = false;
			} else {
				update_option( self::OPT_DEFAULT_PLACEMENT_MODE, $_POST['DefaultPlacementMode'] );
			}
			
		} else {
			$data = $this->api_client->get_request_metering_status(); // we want to refresh the form so they can see what they did
		}
		
		$this->render_or_error($data,'includes/metered.php');	
	}
	
	public function menu_default() {
		/* function of page changed */
		include_once('includes/summary_report.php');
	}

	public function menu_placement() {
		/* Function of page changed. */
		include_once('includes/placement.php');
	}
	
	// Remove FROM URL in media library in benefits:
	public function remove_from_url_tab($tabs) {
		unset($tabs['type_url']);
		return $tabs;
	}
	
	public function menu_benefits() {
		$user_number = get_option(self::OPT_USER_NUMBER);
		
		if ($this->is_valid_http_post_action(self::NONCE_BENEFITS)) {
			if (!empty($_POST['upload_image'])) {
				$pathinfo = pathinfo($_POST['upload_image']);
				if (in_array($pathinfo['extension'], array('jpg', 'jpeg'))) {
					$logo = $this->api_client->set_logo( $user_number, $_POST['upload_image']);
				}
			}
			
			if (!empty($_POST['benefits'])){
				$benefits = $_POST['benefits'];
				if (strlen($benefits) > 1000){
					$benefits = substr($benefits, 0, 1000);
				}
				$benefit = $this->api_client->set_benefits_text( $_POST['benefits'] );
			}
		} else {
			$benefit = $this->api_client->get_benefits_text();
		}
		
		$logo = $this->api_client->get_logo( $user_number );
			
		$data = array(
			'Status' => $benefit['Status'],
			'Msg' => array(
				'benefit' => $benefit['Msg'],
				'logo' => $logo['Msg']
			)
		);
		
		$this->render_or_error($data,'includes/benefits.php');
	}
	
	public function menu_network() {
		$isPost = $this->is_valid_http_post_action(self::NONCE_NETWORK);
		$isActiveSiteUpdate = $isPost && isset($_POST['mp-network-update-active-site-action']);
		$isPricingUpdate = $isPost && isset($_POST['mp-network-update-active-network-pricing']);
		
		if ($isActiveSiteUpdate) {
			$networkSelected = $_POST['network-selected'];
	
			update_option( self::OPT_USER_NUMBER, $networkSelected );
		} else if( $isPricingUpdate ) {
			$increment_map = $this->api_client->membership_duration_increments;
			
			$price_model = array();
			
			foreach ($_POST['prices'] as $key => $price) {
				$price_model[$key] = $increment_map[$price['pricing_period']];
				$price_model[$key]['Price'] = $price['price'];
				$price_model[$key]['Type'] = 0;
			}
			
			$this->api_client->set_network_pricing( $price_model );
			
		} else if( $isPost ) {
			$data = $this->api_client->create_network_site( $_POST['Title'],  $_POST['Domain'],  $_POST['BackLink'] );
		} 
			
		$data = $this->api_client->get_network_list();
		$data['pricing_data'] = $this->api_client->get_network_pricing();
		
		$this->render_or_error($data, 'includes/network.php');
	}
	
	public function menu_account_info() {
		$user_number = get_option(self::OPT_USER_NUMBER);
		
		if ($this->is_valid_http_post_action(self::NONCE_ACCOUNT)) {
			$data = $this->api_client->api_call(array(
				'method' => 'POST',
				'action' => 'Account',
				'body' => array_merge(array(
					'Id' => (int) $user_number,
				), (array) $_POST)
			));
		} else {
			$data = $this->api_client->get_account_data($user_number);
		}
		
		$this->render_or_error($data,'includes/account_info.php');
	}
	
	public function menu_ecpm_floor() {
		if ($this->is_valid_http_post_action(self::NONCE_ECPM_FLOOR)) {
			$data = $this->api_client->set_ecpm_floor( $_POST['ecpm_floor'] );
		} else {
			$data = $this->api_client->get_ecpm_floor();
		}
		
		$this->render_or_error($data,'includes/ecpm_floor.php');
	}
	
	public function menu_price_points() {
		// Increment: 2592000 for month, 31104000 for year, 86400 for day.
		// Type: 0 for memebership, 1 for single article
		$increment_map = $this->api_client->membership_duration_increments;
		
		$user_number = get_option(self::OPT_USER_NUMBER);
		
		if ($this->is_valid_http_post_action(self::NONCE_PRICING)) {
			
			$price_model = array();
			
			switch ($_POST['subscription_model']) {
				case 'membership':
					foreach ($_POST['prices'] as $key => $price) {
						$price_model[$key] = $increment_map[$price['pricing_period']];
						$price_model[$key]['Price'] = $price['price'];
						$price_model[$key]['Type'] = 0;
					}
					break;
				
				case 'single':
					$price_model[] = array(
						'Type' => 1,
						'Length' => 1,
						'Increment' => 31104000,
						'Price' => $_POST['price']
					);
					break;
			}
			
			$this->api_client->set_active_pricing( $user_number, $price_model );	
		} 
		
		$data = $this->api_client->get_active_pricing($user_number);
		
		if ($data['Status'] == 'success') {
			$data = array(
				'subscription_model' => (count($data['Msg']) > 1) ? 'membership' : 'single',
				'prices' => $data['Msg']
			);
			include_once('includes/price_points.php');
		} else {
			$error = $data['Msg'];
			include_once('includes/error.php');
		}
	}
	
	public function menu_faqs_tc() {
		wp_enqueue_style('tcstyle');
		if ( ! function_exists( 'fetch_feed' ) )
			include_once(ABSPATH . WPINC . '/feed.php');
			
		$faq_feed = fetch_feed($this->faq_feed_url);
		
		if (!is_wp_error($faq_feed)) {
			$faq_items = $faq_feed->get_items(0, $faq_feed->get_item_quantity(5));
		}
		
		
		include_once('includes/faq_tc.php');
	}
	
	public function menu_deauth() {
		if (!empty($_GET['deauth'])) {
			$this->delete_all_options();
			
			echo '<div class="mp-wrap">';
			echo '<h2 class="header"><img width="24" height="24" src="'. plugins_url('/images/logo-icon.png', __FILE__) .'" class="mp-icon"> De-Authorizing</h2>';
			echo '<p>Please wait while we deauthorize the plugin.</p>';
			echo '</div>';
			
			echo '<script type="text/javascript">location.href="'. $this->auth_deauth_url . menu_page_url('mediapass',false)  .'";</script>';
		} else {
			include_once('includes/deauth.php');
		}
	}
	
	/**
	 * Clear the options database of all data.
	 * Generally for use during uninstall
	 */
	public static function delete_all_options() {
		$opts = array( 
			self::OPT_INSTALLED_URL		, self::OPT_USER_ID, self::OPT_USER_URL, self::OPT_USER_ERROR, 
			self::OPT_USER_NUMBER		, self::OPT_PLACEMENT_CATEGORIES, self::OPT_PLACEMENT_AUTHORS, 
			self::OPT_PLACEMENT_DATES	, self::OPT_PLACEMENT_TAGS		, self::OPT_ACCESS_TOKEN, 
			self::OPT_REFRESH_TOKEN		, self::OPT_DEFAULT_PLACEMENT_MODE,
			self::OPT_EXCLUDED_POSTS, self::OPT_NUM_INPAGE_PARAGRAPHS, self::OPT_INCLUDED_POSTS
		);
		
		foreach($opts as $o){
			delete_option($o);
		}
	}
}

?>
