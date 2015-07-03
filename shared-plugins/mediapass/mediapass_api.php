<?php

/*
    MediaPass API Library
 */

 /*
    Copyright (C) 2011 Media Pass Inc.
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
 
 
/**
 * MediaPass API Class
 * 
 * @package		MediaPass-API
 * @author		MediaPass (www.mediapass.com)
 * @license		LICENSE.txt
 * @version     1.0.0
 * 
 */

class MediaPass {
	
	private static $client_id  			= "97B9A5B07E8FCC853F1588FA6C024E36";
	private static $default_api_prefix 	= "https://api.mediapass.com/";
	private static $staging_api_prefix 	= "https://api-staging.mediapass.com/";
	private static $dev_api_prefix     	= "http://api.dev.mediapass.com/";
	
	private static $api_auth_scope = '&scope=%s/auth.html&response_type=token&redirect_uri=';
	private static $api_auth_refresh_url = 'oauth/refresh?client_id=%s&scope=%s/auth.html&grant_type=refresh_token&redirect_uri=';
	
	private static $api_default_version = "v1/";
	
	private $api_prefix     = '';
	private $login_url 		= '';
	private $register_url	= '';
	private $refresh_url	= '';
	private $deauth_url		= '';
	
	public $access_token = '';
	public $asset_id     = '';
	
	public $membership_duration_increments;
	
	public function MediaPass( $access_token, $asset_id, $env = 'default' ) {
		$this->access_token = $access_token;
		$this->asset_id     = $asset_id;
		
		$p = self::$default_api_prefix;
		
		if( $env == 'dev' ) {
			$p = self::$dev_api_prefix;
		} else if( $env == 'stage' ) {
			$p = self::$staging_api_prefix;
		}
		
		$c = self::$client_id;
		$s = sprintf( self::$api_auth_scope, $p );
		
		if( defined('WP_DEBUG') ) {
		//	echo '<!-- MP API configured with: ' . $p . '-->';
		}
		
		$this->api_prefix = $p;
		
		$this->login_url    = $p . 'Account/Auth/?client_id=' 			. $c . $s;
		$this->register_url = $p . 'Account/AuthRegister/?client_id= '	. $c . $s;
		$this->refresh_url  = $p . sprintf( self::$api_auth_refresh_url , $c, $p );
		$this->deauth_url   = $p . 'oauth/unauthorize?client_id='		. $c . $s;
		
		$this->membership_duration_increments = $this->produceMembershipIncrements();
	}
	
	// Increment: 2592000 for month, 31104000 for year, 86400 for day.
	// Type: 0 for memebership, 1 for single article
	private function produceMembershipIncrements() {
		return array(
			'1mo' => array(
				'Length' => 1,
				'Increment' => 2592000
			),
			'3mo' => array(
				'Length' => 3,
				'Increment' => 2592000
			),
			'6mo' => array(
				'Length' => 6,
				'Increment' => 2592000
			),
			'1yr' => array(
				'Length' => 1,
				'Increment' => 31104000
			)
		);
	}
	
	/**
	 * URL Construction Helpers
	 *
	 * @access public
	 */
	
	public function get_deauth_url() {
		return $this->deauth_url;
	}
	
	public function get_refresh_url() {
		return $this->refresh_url;
	}
	
	private function get_version_if($options) {
		if( isset($options['version']) ) {
			return $options['version'] . '/';
		} else {
			return self::$api_default_version;
		}
	}
	
	/**
	 * Base API method invocation.
	 * 
	 * @access private
	 * 
	 */
	public function api_call( $options=array() ) {
		$headers = array(
			'oauth_token' => $this->access_token
		);
		
		if( isset($options['content_type']) ) {
			$headers['Content-Type'] = $options['content_type'];
		}
		
		$options = array_merge(
			array(
				'method' => 'GET',
				'action' => null,
				'params' => array(),
				'body' => array()
			),
			(array) $options
		);
		
		$body = json_encode($options['body']);
		
	 	if (isset($options['body']['Url'])) {
	 	 	$body = stripslashes($body);
	 	}
		
		$hasVersion = isset($options['version']);
		$urlPrefix = $this->api_prefix . $this->get_version_if($options);
		$urlToPost = $urlPrefix . $options['action'] . '/' . implode('/', $options['params']);
		
		if( defined('WP_DEBUG') ) {
			echo '<!-- MP API CALL: ' . $urlToPost . "\r\n"; 
			echo '  options: ';
			var_dump($options);
			var_dump($body);
			echo ' --> ';
		}

		$result = wp_remote_post(
			$urlToPost,
			array(
				'method' => $options['method'],
				'headers' => $headers,
				'body' => $body
			)
		);
		
		
		$request_ok = ! is_wp_error($result);
		
		
		if ($request_ok) {
			$response = json_decode(str_replace("(","",str_replace(")", "", $result['body'])), true);
		} else {
			$response['Status'] = 'fail';
		}
		
		//if( true || defined('WP_DEBUG') ) {
		if (defined('WP_DEBUG')){
			echo '<!-- MP API CALL RESPONSE: '. $request_ok . '\r\n';
			var_dump($result);
			var_dump($response);
			echo '-->';
		}

		return $response;	
	}
	
	public function get_access_token_from_code($client_id,$redirect_uri,$scope,$code) {
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'oauth2/token',
			'params' => array(
				'client_id' => $client_id
				
			)
		));
	}
	
	/**
	 * Retrieve Account details
	 */
	public function get_account_data($id) {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'Account',
			'params' => array(
				$id
			)
		));
		
		return $data;
	}
	
	/**
	 * Retrieve Publisher details
	 */
	public function get_publisher_data( $id ) {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'publisher',
			'version' => 'v2',
			'params' => array( $id )
		));
		
		return $data;
	}
	
	/**
	 * Add a site to the network.
	 * 
	 * @param string $title The site title
	 * @param string $domain The domain corresponding to the site
	 * @param string $backlink Url to page linked to by subscription cancelation clicks
	 */
	public function create_network_site( $title, $domain, $backlink ) {
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'network/list',
			'body' => array(
				'Id' => $this->asset_id,
				'Title' => $title,
				'Domain' => $domain,
				'BackLink' => $backlink
			)
		));
		
		return $data;	
	}
	
	/**
	 * Get all sites in the network.
	 */
	public function get_network_list( $asset_id = '' ) {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'network/list',
			'params' => array(
				$asset_id == '' ? $this->asset_id : $asset_id
			)
		));
		
		return $data;
	}
	
	/**
	 * Get Network Pricing
	 */
	public function get_network_pricing( $asset_id = '' ) {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'network/price',
			'params' => array(
				$asset_id == '' ? $this->asset_id : $asset_id
			)
		));
		
		return $data;
	}
	
	/**
	 * Update site information
	 */
	public function update_network_site( $site_model ) {
		return $this->api_call(array(
			'method' => 'PUT',
			'action' => 'network/list',
			'content_type' => 'application/json',
			'body' => array(
				'Id' => $site_model->id,
				'Active' => $site_model->active,
				'DefaultFilterType' => $site_model->default_filter_type
			)
		));
	}
	
	/**
	 * Set Network Pricing
	 */
	public function set_network_pricing( $price_model ) {
		return $this->api_call(array(
			'method' => 'POST',
			'action' => 'network/price',
			'content_type' => 'application/json',
			'body' => array(
				'Id' => $this->asset_id,
				'Active' => 1,
				'PriceModel' => $price_model
			)
		));
	}
	
	/** disable all network pricing for an asset **/
	public function disable_network_pricing( ) {
		return $this->api_call(array(
			'method' => 'POST',
			'action' => 'network/price/disable',
			'params' => array(
				$this->asset_id
			)
		));
	}
	
	/* eCPM Floor Management */
	
	public function get_ecpm_floor() {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'ecpm',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	public function set_ecpm_floor( $floor ) {
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'ecpm',
			'params' => array(
				$this->asset_id,
				$floor
			)
		));
		
		return $data;
	}
	
	public function get_paywall_status(){
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'paywall/active',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	// status is either active = 1 or inactive = 0
	public function set_paywall_status($status){
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'paywall/active',
			'content_type' => 'application/json',
			'body' => array(
				'Id' => $this->asset_id,
				'Active' => $status
			)
		));
		
		return $data;
	}
	
	public function get_included_placement(){
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'placement/included',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	public function get_placement_status(){
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'placement/setting',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	// status is either "excluded" or "included"
	public function set_placement_status($status){
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'placement/setting',
			'params' => array(
				'Status' => $status,
				'Id' => $this->asset_id
			)
		));
		
		return $data;
	}
	
	
	/* Request Metering Management */
	
	public function get_request_metering_status( ) {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'metered',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	/**
	 * Set the status and request count associated with request metering.
	 * 
	 * @param string $status "On"=1 or "Off"=0
	 * @param int $count Request count threshold for access prompting
	 *
	 * If $status = "On" then $count will be updated, else it won't be
	 */
	public function set_request_metering_status( $status, $count ) {
		if ($status == "On"){
			if ($count == 0){
				$count = 10;
			}
			$arr = array(
				'method' => 'POST',
				'action' => 'metered',
				'body' => array(
					'Id' => $this->asset_id,
					'Status' => "On",
					'Count' => $count
				)
			);		
		} else {
			$arr = array(
				'method' => 'GET',
				'action' => 'metered/off',
				'version' => 'v2',
				'params' => array(
					$this->asset_id
				)
			);
		}
		$data = $this->api_call($arr);

		return $data;
	}
	
	/**
	 * Get the "benefits" text presented during subscription prompting.
	 */
	public function get_benefits_text() {
		$data = $this->api_call(array(
			'method' => 'GET',
			'action' => 'benefit',
			'params' => array(
				$this->asset_id
			)
		));
		
		return $data;
	}
	
	/**
	 * Set the "benefits" text presented during subscription prompting.
	 */
	public function set_benefits_text( $benefits ) {
		$data = $this->api_call(array(
			'method' => 'POST',
			'action' => 'benefit',
			'content_type' => 'application/json',
			'body' => array(
				'Id' => $this->asset_id,
				'Benefits' => $benefits
			)
		));
		
		return $data;
	}
	public function set_logo($user,$logo){
		return $this->api_call(array(
			'method' => 'POST',
			'action' => 'logo',
			'body' => array(
				'Id' => $user,
				'Url' => $logo
			)
		));
	}
	public function get_logo($user) {
		return $this->api_call(array(
			'method' => 'GET',
			'action' => 'logo',
			'params' => array(
				$user
			)
		));
	}
	
	public function get_reporting_summary_stats( $user, $period ) {
		$opts = array(
			'method' => 'GET',
			'action' => 'report/summary/stats',
			'params' => array(
				$user
			)
		);
		
		if( ! empty($period) ) {
			array_push($opts['params'],$period);
		}
		
		return $this->api_call($opts);
	}

	public function get_reporting_summary_earnings( $user ) {
		$opts = array(
			'method' => 'GET',
			'action' => 'report/summary/earning',
			'params' => array(
				$user
			)
		);
		
		return $this->api_call($opts);
	}
	
	/**
	 * Get Active Single Site Pricing
	 */
	public function get_active_pricing( $user ) {
		return $this->api_call(array(
			'method' => 'GET',
			'action' => 'price',
			'params' => array(
				$user
			)
		));
	}
	
	/**
	 * Set Active Single Site Pricing
	 */
	public function set_active_pricing( $user_number , $price_model ) {
		return $this->api_call(array(
			'method' => 'POST',
			'action' => 'price',
			'body' => array(
				'Id' => !empty($user_number) ? $user_number : $this->asset_id,
				'Active' => 1,
				'PriceModel' => $price_model
			)
		));
	}
	
	public function get_monthly_data () {
		return $this->api_call(array(
			'method' => 'GET',
			'action' => 'report/chart/1'
		));
	}
	public function get_yearly_data () {
		return $this->api_call(array(
			'method' => 'GET',
			'action' => 'report/chart/2'
		));
	}
} 

class MediaPass_ContentHelper {
	const PROTECTION_TAG_RE = '/\[\/*mp(inpage|overlay|video)\]/';
	const PROTECTION_INPAGE_RE = '/\[\/*mpinpage\]/';
	const PROTECTION_OVERLAY_RE = '/\[\/*mpoverlay\]/';
	const PROTECTION_VIDEO_RE = '/\[\/*mpvideo\]/';
	const PARAPGRAPH_RE = '/(<p>|<br>|<br \/>|\\r\\n|\\r|\\n)/';//'/[<p>]|[\n]/';
	
	public static function strip_all_shortcodes( $content ) {
		return preg_replace( self::PROTECTION_TAG_RE,'',$content);
	}
	
	public static function has_existing_protection( $content ) {
		return preg_match(self::PROTECTION_TAG_RE, $content) > 0;
	}
	
	public static function enable_overlay($content, $protection_type = "", $IsEditMode = false) {
		// determine the existing protection
		if ($protection_type == ""){
			$protection_type = self::get_existing_protection_type($content);
			$content_saved = $content;
			
		}
		$content = self::strip_all_shortcodes($content);
		
		//$content = '[mpoverlay]' . $content . '[/mpoverlay]';
		if ($protection_type == ""){
			//$content = '[mpinpage]' . $content . '[/mpinpage]';
			$default_placement_mode = get_option(MediaPass_Plugin::OPT_DEFAULT_PLACEMENT_MODE);
			
			if ($default_placement_mode == "inpage"){
				// This is where we decide how many paragraphs to default to
				$num_inpage_paragraphs = get_option(MediaPass_Plugin::OPT_NUM_INPAGE_PARAGRAPHS);
				if (empty($num_inpage_paragraphs)){
					$num_inpage_paragraphs = 1;
				}
				
				// parse the content and look for paragraph tags
				$arr_paragraphs = preg_split(self::PARAPGRAPH_RE, ($content));
				if (count($arr_paragraphs) > $num_inpage_paragraphs){
					// construct a RE to split the string at the right point and insert the shortcodes
					$content = "";
					$i = 0;
					$j = 0;
					while (((!$IsEditMode && $i < $num_inpage_paragraphs+1) || ($IsEditMode && $i < $num_inpage_paragraphs)) && $j < count($arr_paragraphs)){
						if ($arr_paragraphs[$j] != ""){
							if ($i != 0){
								$content = $content . "\n\n";
							}
							$content = $content . "<p>" . str_replace("</p>", "", $arr_paragraphs[$j]) . "</p>";
							$i = $i + 1;
						}
						$j = $j + 1;
					}
					$add_mpinpage = true;
					while ($j < count($arr_paragraphs)){
						if ($arr_paragraphs[$j] != ""){
							if ($i != 0){
								$content = $content . "\n\n";
							}
							if ($add_mpinpage){
								$content = $content . "[mpinpage]";
								$add_mpinpage = false;
							}
							$content = $content . "<p>" . str_replace("</p>", "", $arr_paragraphs[$j]) . "</p>";
						}
						$j = $j + 1;
					}
					if ($add_mpinpage){
						$content = $content . "[mpinpage]";
					}
					$content = $content . "[/mpinpage]";
				} else {
					$content = '[mpinpage]' . $content . '[/mpinpage]';
				}
			} else {
				$protection_type = "overlay";
			}
		}
		// Add the overlays if we need them
		if ($protection_type == "video"){ 
			$content = '[mpvideo]' . $content . '[/mpvideo]';
		} else if ($protection_type == "overlay"){ 
			$content = '[mpoverlay]' . $content . '[/mpoverlay]';
		} else if ($protection_type == "inpage"){
			$content = $content_saved; // they already probably specified their inpage shortcodes
		}
		
		return $content;
	}
	
	public static function get_existing_protection_type( $content ) {
		if (preg_match(self::PROTECTION_INPAGE_RE, $content) > 0){
			return "inpage";
		} else if (preg_match(self::PROTECTION_VIDEO_RE, $content) > 0){
			return "video";
		} else if (preg_match(self::PROTECTION_OVERLAY_RE, $content) > 0){
			return "overlay";
		}
		return "";
	}
}

?>