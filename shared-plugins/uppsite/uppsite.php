<?php
/*
 Plugin Name: UppSite - Go Mobile&#0153;
 Plugin URI: http://www.uppsite.com/features/
 Description: UppSite is the best way to make your site mobile. Here is how you get started: 1) Activate your plugin by clicking the "Activate" link to the left of this description, and 2) Configure your mobile apps by visiting the Mobile tab under Settings (tab will show only after plugin is activated). Go Mobile&#0153; <strong>**** DISABLING THIS PLUGIN MAY PREVENT YOUR USERS FROM ACCESSING YOUR MOBILE APPS! ****</strong>
 Author: UppSite
 Version: 5.1.3
 Author URI: https://www.uppsite.com
 */

if (!defined('MYSITEAPP_AGENT')):

/** Plugin version **/
define('MYSITEAPP_PLUGIN_VERSION', '5.1.3');

/** Theme name in cookie **/
define('MYSITEAPP_WEBAPP_PREF_THEME', 'uppsite_theme_select');
/** Theme save time in cookie **/
define('MYSITEAPP_WEBAPP_PREF_TIME', 'uppsite_theme_time');
/** Preview flag **/
define('MYSITEAPP_WEBAPP_PREVIEW', 'uppsite_preview');

/** UppSite's data option key */
define('MYSITEAPP_OPTIONS_DATA', 'uppsite_data');
/** UppSite's admin prefs */
define('MYSITEAPP_OPTIONS_OPTS', 'uppsite_options');
/** UppSite's prefs option key */
define('MYSITEAPP_OPTIONS_PREFS', 'uppsite_prefs');
/** UppSite's business option key */
define('MYSITEAPP_OPTIONS_BUSINESS', 'uppsite_biz');

/** User-Agent for mobile requests **/
define('MYSITEAPP_AGENT','MySiteApp');
/** Helper for the different enviornments (VIP / Standalone) */
require_once( dirname(__FILE__) . '/env_helper.php' );
/** Template root */
define('MYSITEAPP_TEMPLATE_ROOT', mysiteapp_get_template_root() );
/** Template for mobile requests **/
define('MYSITEAPP_TEMPLATE_APP', MYSITEAPP_TEMPLATE_ROOT . DIRECTORY_SEPARATOR . 'mysiteapp');
/** Template for web app **/
define('MYSITEAPP_TEMPLATE_WEBAPP', MYSITEAPP_TEMPLATE_ROOT . DIRECTORY_SEPARATOR . 'webapp');
/** Template for the mobile landing page **/
define('MYSITEAPP_TEMPLATE_LANDING', MYSITEAPP_TEMPLATE_ROOT . DIRECTORY_SEPARATOR . 'landing');
/** API url **/
define('MYSITEAPP_WEBSERVICES_URL', 'http://api.uppsite.com');
/** HomeSite url **/
define('UPPSITE_REMOTE_URL', defined('UPPSITE_BASE_SITE') ? constant('UPPSITE_BASE_SITE') : 'https://www.uppsite.com');
/** Push services url **/
define('MYSITEAPP_PUSHSERVICE', MYSITEAPP_WEBSERVICES_URL.'/push/notification.php');
/** URL for fetching native app link **/
define('MYSITEAPP_APP_NATIVE_URL', MYSITEAPP_WEBSERVICES_URL.'/getapplink.php?v=2');
/** URL for fetching API key & secret **/
define('MYSITEAPP_AUTOKEY_URL', MYSITEAPP_WEBSERVICES_URL.'/autokeys.php');
/** URL for fetching app preferences **/
define('MYSITEAPP_PREFERENCES_URL', MYSITEAPP_WEBSERVICES_URL . '/preferences.php?ver=' . MYSITEAPP_PLUGIN_VERSION);
/** URL for resrouces */
define('MYSITEAPP_WEBAPP_RESOURCES', 'http://static.uppsite.com/v3/webapp');
/** One day in seconds */
define('MYSITEAPP_ONE_DAY', 86400); // 60*60*24
/** Number of posts that will contain content if the display mode is "first full, rest ..." */
define('MYSITEAPP_BUFFER_POSTS_COUNT', 5);
/** Homepage Number of posts  */
define('MYSITEAPP_HOMEPAGE_POSTS', 5);
/** Homepage - number of maximum categories to show */
define('MYSITEAPP_HOMEPAGE_MAX_CATEGORIES', 10);
/** Homepage - Number of minimum posts in each category */
define('MYSITEAPP_HOMEPAGE_DEFAULT_MIN_POSTS', 2);
/** Homepage - Default cover image for fresh WordPress installation */
define('MYSITEAPP_HOMEPAGE_FRESH_COVER', 'http://static.uppsite.com/plugin/cover.png');
/** Landing page - Default background image */
define('MYSITEAPP_LANDING_DEFAULT_BG', 'http://static.uppsite.com/webapp/landing-background.jpg');
/** Upper limit for some queries */
define('UPPSITE_UPPER_LIMIT', 15);

/** Mobile type: Content (Blog) */
define('UPPSITE_TYPE_CONTENT', 1);
/** Mobile type: Business (Mostly pages) */
define('UPPSITE_TYPE_BUSINESS', 2);
/** Mobile type: Combined (Pages with posts list) */
define('UPPSITE_TYPE_BOTH', 3);
/** Nonce prefix */
define('UPPSITE_NONCE_PREFIX', 'uppsite_nonce_');

// Few constants
if (!defined('MYSITEAPP_PLUGIN_BASENAME'))
    define('MYSITEAPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
if (!defined( 'WP_CONTENT_URL' ))
    define('WP_CONTENT_URL', get_option('siteurl').'/wp-content');
if (!defined('WP_CONTENT_DIR'))
    define('WP_CONTENT_DIR', ABSPATH.'wp-content');
if (!defined( 'WP_PLUGIN_URL'))
    define( 'WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');
if (!defined('WP_PLUGIN_DIR'))
    define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins');

/**
 * Helps to preview the webapp without the need of activating it
 * @return bool Show the webapp or not (even if turned off)
 */
function mysiteapp_should_preview_webapp() {
    if ( function_exists( 'vary_cache_on_function' ) ) {
        // Help Batcache
        vary_cache_on_function(
            'return isset($_COOKIE["' . MYSITEAPP_WEBAPP_PREVIEW . '"]) && $_COOKIE["' . MYSITEAPP_WEBAPP_PREVIEW . '"];'
        );
    }
    if (isset($_COOKIE[MYSITEAPP_WEBAPP_PREVIEW]) && $_COOKIE[MYSITEAPP_WEBAPP_PREVIEW]) {
        return true;
    }
    if (isset($_REQUEST['uppPreview'])) {
        $previewRequest = $_REQUEST['uppPreview'];
        $hash = md5(get_bloginfo('pingback_url'));
        if ($previewRequest == $hash) {
            // Save a cookie for later use (as following AJAX requests for the webapp data will be without the query string)
            setcookie(MYSITEAPP_WEBAPP_PREVIEW, true, time() + 60*60*24*30, COOKIEPATH, COOKIE_DOMAIN);
            return true;
        }
    }
    return false;
}
/**
 * Tells whether the webapp should be enabled (according to activation and display mode)
 * @note    We can request for it with a special query string that contain the md5 of xmlrpc url
 * @return bool Webapp should be enabled
 */
function mysiteapp_should_show_webapp() {
    $os = MySiteAppPlugin::detect_specific_os();
    if ($os == "wp") {
        // Windows Phone support in webapp isn't completed yet
        return false;
    }
    $options = get_option(MYSITEAPP_OPTIONS_OPTS);
    return (isset($options['activated']) && $options['activated'] && isset($options['webapp_mode']) &&
        ($options['webapp_mode'] == "all" || $options['webapp_mode'] == "webapp_only")) || mysiteapp_should_preview_webapp();
}

/**
 * @param $type string  What type of information the caller wish to retrieve (can be - url / identifier / store_url)
 * @param $os string    App OS (can be null, then we will try to fetch the current OS's link)
 * @return string   Native app information (or null if no such app/info exists)
 */
function uppsite_get_native_app($type = "url", $os = null) {
    if (is_null($os)) {
        $os = MySiteAppPlugin::detect_specific_os();
    }
    if (!in_array($type, array("url", "identifier", "store_url", "banner"))) {
        // Sanitize the $type argument
        return null;
    }
    $options = get_option(MYSITEAPP_OPTIONS_DATA, array());
    if (!isset($options['native_apps']) || !is_array($options['native_apps'])) {
        return null;
    }
    $apps = $options['native_apps'];
    return isset($apps[$os]) && array_key_exists($type, $apps[$os]) ? $apps[$os][$type] : null;

}

/**
 * Tells whether the landing page ("selection page") should be enabled (according to activation and display mode)
 * @return bool Landing page should be enabled
 */
function mysiteapp_should_show_landing() {
    $options = get_option(MYSITEAPP_OPTIONS_OPTS);
    $showLanding = isset($options['activated']) && $options['activated'] && isset($options['webapp_mode']) &&
        ($options['webapp_mode'] == "all" || $options['webapp_mode'] == "landing_only");
    if ($showLanding && !mysiteapp_should_show_webapp()) {
        // "Landing only" or ("all" and "Windows Phone" client)
        $showLanding = $showLanding && !is_null(uppsite_get_native_app());
    }
    return $showLanding;
}

/**
 * Helper class which provides functions to detect mobile  
 */
class MySiteAppPlugin {
    /**
     * Is coming from mobile browser
     * @var boolean
     */
    var $is_mobile = false;
    /**
     * Is using MySiteApp's User-Agent
     * (Probably mobile app)
     * @var boolean
     */
    var $is_app = false;

    /**
     * The hooked template
     * @var string
     */
    var $new_template = null;

    /**
     * User agents of specific OSes
     * @var array
     */
    static $_mobile_ua_os = array(
        "ios" => array(
            "iPhone",
            "iPad",
            "iPod"
        ),
        "android" => array(
            "Android"
        ),
        "wp" => array(
            "Windows Phone"
        )
    );

    /**
     * @var string Current specific os (from $_mobile_ua_os), if identified
     */
    static $os = null;

    /**
     * List of mobile user agents
     * @var array
     */
    static $_mobile_ua = array(
        "WebTV",
        "AvantGo",
        "Blazer",
        "PalmOS",
        "lynx",
        "Go.Web",
        "Elaine",
        "ProxiNet",
        "ChaiFarer",
        "Digital Paths",
        "UP.Browser",
        "Mazingo",
        "Mobile",
        "T68",
        "Syncalot",
        "Danger",
        "Symbian",
        "Symbian OS",
        "SymbianOS",
        "Maemo",
        "Nokia",
        "Xiino",
        "AU-MIC",
        "EPOC",
        "Wireless",
        "Handheld",
        "Smartphone",
        "SAMSUNG",
        "J2ME",
        "MIDP",
        "MIDP-2.0",
        "320x240",
        "240x320",
        "Blackberry8700",
        "Opera Mini",
        "NetFront",
        "BlackBerry",
        "PSP"
    );

    /**
     * @var array Original values of templates and stylesheets
     */
    var $original_values = null;

    /**
     * Constructor
     */
    function MySiteAppPlugin() {
        /** Admin panel options **/
        if (is_admin()) {
            require_once( dirname(__FILE__) . '/admin/uppsite_admin.php' );
        }
        $this->detect_user_agent();
        if ($this->is_mobile || $this->is_app) {
            if (function_exists('add_theme_support')) {
                // Add functionality of post thumbnails
                add_theme_support( 'post-thumbnails' );
                // RSS Feed links
                add_theme_support( 'automatic-feed-links' );
            }

            $this->original_values = array(
                'template' => get_template(),
                'stylesheet' => get_stylesheet(),
                'template_directory' => get_template_directory(),
                'template_directory_uri' => get_template_directory_uri(),
                'stylesheet_directory' => get_stylesheet_directory(),
                'stylesheet_directory_uri' => get_stylesheet_directory_uri()
            );

            do_action('uppsite_is_running');
        }
    }

    /**
     * @note Supports Batcache
     * @return bool Tells whether this request is coming from mobile app
     */
    private function _is_agent() {
        if ( function_exists( 'vary_cache_on_function' ) ) {
            vary_cache_on_function(
                'return array_key_exists("HTTP_USER_AGENT", $_SERVER) && strpos($_SERVER["HTTP_USER_AGENT"], "' . MYSITEAPP_AGENT . '") !== false;'
            );
        }
        return array_key_exists('HTTP_USER_AGENT', $_SERVER) &&  strpos($_SERVER['HTTP_USER_AGENT'], MYSITEAPP_AGENT) !== false;
    }

    /**
     * @note Supports Batcache
     * @param $osUAs array   List of User Agents
     * @return bool Tells whether this user agent is of specific UA group
     */
    static private function is_specific_os($osUAs) {
        if ( function_exists( 'vary_cache_on_function' ) ) {
            vary_cache_on_function(
                'return array_key_exists("HTTP_USER_AGENT", $_SERVER) && (bool)preg_match("/(' . implode("|", $osUAs). ')/i", $_SERVER["HTTP_USER_AGENT"]);'
            );
        }
        return array_key_exists("HTTP_USER_AGENT", $_SERVER) && (bool)preg_match('/('.implode('|', $osUAs).')/i', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * Detects the user agent of the visitor, and marks how the plugin
     * should handle the user in the current run.
     */
    function detect_user_agent() {
        if ($this->_is_agent()) {
            // Mobile (from our applications)
            $this->is_app = true;
            $this->new_template = MYSITEAPP_TEMPLATE_APP;
        } elseif (mysiteapp_should_show_landing() || mysiteapp_should_show_webapp()) {
            if (self::is_specific_os(MySiteAppPlugin::$_mobile_ua) || mysiteapp_should_preview_webapp()) {
                // Mobile user (from some browser)
                $this->is_mobile = true;
                $this->new_template = $this->get_webapp_template();
            }
        }
    }

    /**
     * Decide which template to show when coming from mobile.
     * If no choice was previously saved, a landing page is displayed.
     * @return string The template name that should be displayed.
     */
    function get_webapp_template() {
        $ret = mysiteapp_should_show_landing() ? "landing" : ( mysiteapp_should_show_webapp() ? "webapp" : "normal" );
        if (isset($_COOKIE[MYSITEAPP_WEBAPP_PREF_THEME]) && isset($_COOKIE[MYSITEAPP_WEBAPP_PREF_TIME])) {
            $ret = $_COOKIE[MYSITEAPP_WEBAPP_PREF_THEME];
            $saveTime = $_COOKIE[MYSITEAPP_WEBAPP_PREF_TIME];
            // Renew the saving time of the cookie
            setcookie(MYSITEAPP_WEBAPP_PREF_THEME, $ret, time() + $saveTime, COOKIEPATH, COOKIE_DOMAIN);
        }
        switch ($ret) {
            case "webapp":
                if (mysiteapp_should_show_webapp()) {
                    return MYSITEAPP_TEMPLATE_WEBAPP;
                }
                break;
            case "landing":
                if (mysiteapp_should_show_landing()) {
                    return MYSITEAPP_TEMPLATE_LANDING;
                }
                break;
        }
        // Normal mode - no webapp
        $this->is_mobile = false; // Disable the webapp and landing
        return null;
    }

    /**
     * @return bool Tells whether need to use custom theme for this request (app/webapp/landing) or not
     */
    function has_custom_theme() {
        return !is_null($this->new_template);
    }

    /**
     * @return string|null  A specific os from $_mobile_ua_os
     */
    static function detect_specific_os() {
        if (is_null(self::$os)) {
            foreach (self::$_mobile_ua_os as $osName => $ua) {
                if (self::is_specific_os($ua)) {
                    self::$os = $osName;
                    break;
                }
            }
        }
        return self::$os;
    }
}

// Because PHP doesn't have static constructors, we merge all the user agents
// together here, in global context
foreach (MySiteAppPlugin::$_mobile_ua_os as $osName => $osUA) {
    MySiteAppPlugin::$_mobile_ua = array_merge(MySiteAppPlugin::$_mobile_ua, $osUA);
}

/** Include the business part here, as it requires the MySiteAppPlugin class. */
require_once( dirname(__FILE__) . '/includes/business.php' );
/** Comments helper */
require_once( dirname(__FILE__) . '/includes/comments_helper.php' );

// Create a global instance of MySiteAppPlugin
global $msap;
$msap = new MySiteAppPlugin();

/**
 * Filter template/stylesheet name, and return the right template if running from mobile / app.
 * @param $newValue string Value of 'template'/'stylesheet' from db
 * @return string App/Mobile template if required, else the template name from db.
 */
function mysiteapp_filter_template($newValue) {
    global $msap;
    return $msap->has_custom_theme() ? $msap->new_template : $newValue;
}
add_filter('option_template', 'mysiteapp_filter_template'); // Filter 'get_option(template)'
add_filter('option_stylesheet', 'mysiteapp_filter_template'); // Filter 'get_option(stylesheet)'

/**
 * Extracts the src url form an html tag.
 * @param $html string The HTML content
 * @return string|null The url if found, or null
 */
function uppsite_extract_src_url($html) {
    if (strpos($html, "http") === 0) {
        // This is a URL
        return $html;
    }
    if (preg_match("/src=[\"']([\s\S]+?)[\"']/", $html, $match)) {
        return $match[1];
    }
    return null;
}

/**
 * In some cases, the thumb image also appear in the post, and we with to remove it. Because it can appear in more than
 * one size, we will search for the other size it might appear in.
 * @note This function will be only called
 * @param &$content string  Post content
 * @param $thumb string Thumb url
 */
function uppsite_nullify_thumb(&$content, &$thumb) {
    // Try to remove the image from the post
    if (preg_match("/(.*?)(?:-)(\\d{1,4})([_x\\-]?\\d{0,4})(\\.[a-zA-Z]{1,4}.*)/", $thumb, $imgParts)) {
        // Images of type 'http://nocamels.com/wp-content/uploads/2012/02/sellAring-55x55.jpg' has size modifiers
        // that may not appear inside the post, so we will try to search for pictures with the same name
        $sizeModifier = $imgParts[2];
        if (is_numeric($sizeModifier) && intval($sizeModifier) < 50 || empty($imgParts[3])) {
            // We guess that image galleries might be of url GALLERY-0.png, GALLERY-1.png and on, such as
            // https://testrpcblog.files.wordpress.com/2012/03/assassins-creed-iii-20120326034850652-000.jpg?w=458
            // so we return the full url
            // So - do nothing
        } else {
            // Guessing we can remove the size modifiers, and give a better "thumb"
            $thumb = $imgParts[1] . $imgParts[4];
        }
    }
    $content = preg_replace("/<img[^>]*src=\"". preg_quote($thumb, "/") ."[^>]*>/ms", "", $content);
}

/**
 * Try and extract the first image in the post, to be used as thumbnail image.
 * @param &$content string Post content (By ref, to save the copy of large data)
 * @return mixed
 */
function uppsite_extract_image_from_post_content(&$content) {
    if (!preg_match("/<img[^>]*src=\"([^\"]+)\"[^>]*>/", $content, $matches)) {
        return null;
    }
    if (strpos($matches[0], "uppsite-youtube-video") !== false) {
        // Don't extract youtube video images.
        return null;
    }
    return $matches[0]; // Will be stripped later
}

/**
 * Enum of the various functionalities available via mysiteapp_extract_thumbnail
 * (Every value is a different bit)
 */
class UppSiteImageAlgo {
    // Try using 'has_post_thumbnail' function
    const NATIVE_FUNC = 0x1;
    // Try using 'The Attached Image' plugin
    const THE_ATTACHED_IMAGE = 0x2;
    // Try using 'Get The Image' plugin
    const GET_THE_IMAGE = 0x4;
    // Try getting custom post field
    const CUSTOM_FIELD = 0x8;
    // Try the first image in post
    const FIRST_IMAGE = 0x10;

    // All options!
    const ALL = 0xFFFFFFFF;
}

/**
 * @return array Sanitized "thumbnail_algo" data from the preferences
 */
function uppsite_get_image_algos() {
    $imageAlgos = mysiteapp_get_prefs_value('thumbnail_algo',
        array(
            'type' => UppSiteImageAlgo::ALL,
            'extra' => null
        )
    );
    // Sanitize
    if (!is_array($imageAlgos)) {
        $imageAlgos = json_decode($imageAlgos, true);
        if (!is_array($imageAlgos)) {
            // Failed to decode
            $imageAlgos = array();
        }
    }
    if (!array_key_exists('type', $imageAlgos)) {
        $imageAlgos['type'] = UppSiteImageAlgo::ALL;
    }
    if (!array_key_exists('extra', $imageAlgos)) {
        $imageAlgos['extra'] = null;
    }
    return $imageAlgos;
}

/**
 * @param $imageAlgos array  Array of "thumbnail_algo" from uppsite_get_image_algos()
 * @param $imageAlgoType int    The algorithm type to check
 * @return bool Is the algorithm present in the algo structure
 */
function uppsite_has_image_algo(&$imageAlgos, $imageAlgoType) {
    return ($imageAlgos['type'] & $imageAlgoType) > 0;
}

/**
 * Extracts the thumbnail url of the post by iterating
 * over popular plugins that provide the thumbnail image url
 * @note This function should be called inside the post loop.
 * @param $content string If present, search in the post content and remove the image.
 * @return string URL for the thumbnail
 */
function mysiteapp_extract_thumbnail(&$content = null) {
    $thumb_url = null;

    // Image algo is being used to decide how the thumbnail should be fetched
    $imageAlgos = uppsite_get_image_algos();

    if (uppsite_has_image_algo($imageAlgos, UppSiteImageAlgo::NATIVE_FUNC) &&
        function_exists('has_post_thumbnail') && has_post_thumbnail()) {
        // Built-in function
        $thumb_url = get_the_post_thumbnail();
    }

    if (uppsite_has_image_algo($imageAlgos, UppSiteImageAlgo::THE_ATTACHED_IMAGE) &&
        empty($thumb_url) && function_exists('the_attached_image')) {
        // The Attached Image plugin
        $temp_thumb = the_attached_image('img_size=thumb&echo=false');
        if (!empty($temp_thumb)) {
            $thumb_url = $temp_thumb;
        }
    }
    if (uppsite_has_image_algo($imageAlgos, UppSiteImageAlgo::GET_THE_IMAGE) &&
        empty($thumb_url) && function_exists('get_the_image')) {
        // Get The Image plugin
        $temp_thumb = get_the_image(array('size' => 'thumbnail', 'echo' => false, 'link_to_post' => false));
        if (!empty($temp_thumb)) {
            $thumb_url = $temp_thumb;
        }
    }

    if (uppsite_has_image_algo($imageAlgos, UppSiteImageAlgo::CUSTOM_FIELD) &&
        empty($thumb_url) && !is_null($imageAlgos['extra'])) {
        // Try getting a custom field from the post
        $thumb_url = get_post_meta(get_the_ID(), $imageAlgos['extra'], true);
    }

    if (uppsite_has_image_algo($imageAlgos, UppSiteImageAlgo::FIRST_IMAGE) &&
        empty($thumb_url)) {
        if (mysiteapp_is_fresh_wordpress_installation()) {
            // On fresh installation - put UppSite cover to make the homepage look good
            $thumb_url = MYSITEAPP_HOMEPAGE_FRESH_COVER;
        } else {
            if (is_null($content)) {
                ob_start();
                the_content();
                $content = ob_get_contents();
                ob_get_clean();
            }
            $thumb_url = uppsite_extract_image_from_post_content($content);
        }
    }
    if (!empty($thumb_url)) {
        // Found via the helper functions
        $thumb_url = uppsite_extract_src_url($thumb_url);
        if (!is_null($content)) {
            // Search for the original image in post, and remove it to remove doubles.
            uppsite_nullify_thumb($content, $thumb_url);
        }
    }
    return $thumb_url;
}

/**
 * Sign a message with the API secret
 * @param string $message    The message
 * @return string The signed message
 */
function mysiteapp_sign_message($message){
    $options = get_option(MYSITEAPP_OPTIONS_DATA);
    $str = $options['uppsite_secret'].$message;
    return md5($str);
}

/**
 * Check if needs to search for a new application links
 * @return boolean Should ask UppSite server if there is a mobile app?
 */
function mysiteapp_is_need_new_link(){
    $dataOptions = get_option(MYSITEAPP_OPTIONS_DATA);
    $lastCheck = isset($dataOptions['last_native_check']) ? $dataOptions['last_native_check'] : 0;
    // Should update once in a day
    return time() > $lastCheck + MYSITEAPP_ONE_DAY;
}

/**
 * Fetch and set the preferences from UppSite server
 * @param boolean $forceUpdate  Force fetching prefs or not.
 */
function uppsite_prefs_init($forceUpdate = false) {
    if (!uppsite_api_values_set()) {
        // Still no data options
        return;
    }

    // Other initializations
    mysiteapp_get_app_links();

    $prefsOptions = get_option(MYSITEAPP_OPTIONS_PREFS, array());
    if (count($prefsOptions) > 0 && !$forceUpdate) {
        return;
    }
    $dataOptions = get_option(MYSITEAPP_OPTIONS_DATA);
    $uppPrefs = wp_remote_post(MYSITEAPP_PREFERENCES_URL,
        array(
            'body' => 'os_id=4&json=1&key=' . $dataOptions['uppsite_key'],
            'timeout' => 5
        )
    );
    if (is_wp_error($uppPrefs)) { return; }
    $newPrefs = json_decode($uppPrefs['body'], true);
    if (is_array($newPrefs) && is_array($newPrefs['preferences'])) {
        $prefsOptions = array_merge($prefsOptions, $newPrefs['preferences']);
        $dataOptions['app_id'] = isset($prefsOptions['id']) ? $prefsOptions['id'] : 0;
        update_option(MYSITEAPP_OPTIONS_PREFS, $prefsOptions);
        $dataOptions['prefs_update'] = time();
        update_option(MYSITEAPP_OPTIONS_DATA, $dataOptions);
    }
}

/**
 * Retrives a list of application keys for the current website
 * and updates the database.
 */
function mysiteapp_get_app_links() {
    if (!mysiteapp_is_need_new_link()) {
        return false;
    }
    
    $options = get_option(MYSITEAPP_OPTIONS_DATA);

    if (empty($options['uppsite_key']))
        return false;

    $hash = mysiteapp_sign_message($options['uppsite_key']);
    $get = '&api_key='.$options['uppsite_key'].'&hash='.$hash;
    
    $response = wp_remote_get(MYSITEAPP_APP_NATIVE_URL.$get);
    if (is_wp_error($response)) {
        return false;
    }

    $data = json_decode($response['body'],true);
    if ($data) {
        $options['native_apps'] = $data;
        // Set updated in this time
        $options['last_native_check'] = time();
        update_option(MYSITEAPP_OPTIONS_DATA, $options);
    }
    return true;
}


/**
 * Returns the layout of the posts, as the mobile application
 * wishes to display it.
 *
 * @note Will work only on plugin-related requests, else "posts_list_view" isn't set.
 * @return string    Enum: full / ffull_rexcerpt / ffull_rtitle / title / excerpt / homepage
 */
function mysiteapp_get_posts_layout() {
    $posts_list_view = isset($_REQUEST['posts_list_view']) ? esc_html(stripslashes($_REQUEST['posts_list_view'])) : "";
    // Validate value
    switch ($posts_list_view) {
        case "full":
        case "ffull_rexcerpt":
        case "ffull_rtitle":
        case "title":
        case "excerpt":
        case "homepage":
            return $posts_list_view;
    }
    return "";
}

/**
 * Checks if a theme selection has made ("landing page").
 * Sets the theme in a cookie, and redirects back to the referer page
 */
function mysiteapp_set_webapp_theme(/*&$wp*/) {
    $templateType = isset($_REQUEST['msa_theme_select']) ? esc_html(stripslashes($_REQUEST['msa_theme_select'])) : "";
    $templateSaveForever = isset($_REQUEST['msa_theme_save_forever']) ? esc_html(stripslashes($_REQUEST['msa_theme_save_forever'])) : "";
    if (empty($templateType)) {
        return;
    }

    // Validate templateType
    if (!in_array($templateType, array("webapp", "normal"))) {
        return;
    }

    $cookieTime = $templateSaveForever ? 60*60*24*7 : 60*60; // "Forever" = 7 days, else = 1 hour
    setcookie(MYSITEAPP_WEBAPP_PREF_THEME, $templateType, time() + $cookieTime, COOKIEPATH, COOKIE_DOMAIN);
    // Set the cookie saving time, to renew on plugin init.
    setcookie(MYSITEAPP_WEBAPP_PREF_TIME, $cookieTime, time() + 60*60*24*30, COOKIEPATH, COOKIE_DOMAIN);

    // Refresh the page that will now load with the correct theme.
    $cleanUrl = remove_query_arg(array("msa_theme_select","msa_theme_save_forever"));
    wp_safe_redirect($cleanUrl);
    exit;
}

/**
 * Helper function to merge between wp options array and values from remote_activation
 * @param array $curOpts Current options array
 * @param array $newVals New options (json encoded)
 * @return array Merged options
 */
function uppsite_update_options($curOpts, $newVals){
    $newVals = json_decode($newVals, true);
    if ($newVals === false) {
        return $curOpts;
    }
    $curOpts = ($curOpts === false) ? array() : $curOpts;
    return array_merge($curOpts, $newVals);
}

/**
 * Helper function for giving a feedback in a format the remote requester will understand
 * @note Dies in the end.
 * @param mixed $feedback   Values for the feedback
 */
function uppsite_provide_feedback($feedback) {
    if (is_bool($feedback)) {
        $ret = $feedback;
    } else {
        $ret = array('error' => $feedback);
    }
    print json_encode($ret);
    exit;
}

/**
 * Allow to reset the plugin state to initial (except for the key & secret) if some options screwed in the process.
 * @param $dataOpts Array with data options, which contain the key and secret.
 */
function uppsite_reset_db_vals($dataOpts) {
    if (!array_key_exists('uppsite_key', $dataOpts) || !array_key_exists('uppsite_secret', $dataOpts)) {
        // Don't allow to reset.
        uppsite_provide_feedback('Options reset failed.');
    }
    update_option(MYSITEAPP_OPTIONS_DATA, array(
        'uppsite_key' => $dataOpts['uppsite_key'],
        'uppsite_secret' => $dataOpts['uppsite_secret']
    ));
    delete_option(MYSITEAPP_OPTIONS_OPTS);
    delete_option(MYSITEAPP_OPTIONS_BUSINESS);
    delete_option(MYSITEAPP_OPTIONS_PREFS);
    uppsite_provide_feedback('Options reset');
}

/**
 * Active the webapp functionality when UppSite notifies the app is "Published"
 * (this was made so if the user didn't like the app but forgot to deactivate the plugin,
 *  he will not see the webapp)
 * @note API Key & Secret already present at this stage, as they are filled upon plugin activation. Just in case there
 *       is a outgoing communication problem with this server ('fopen doesn't allow remote hosts'), we are setting
 *       the API key & secret here too.
 */
function uppsite_remote_activation() {
    $query_var = isset($_REQUEST['msa_remote_activation']) ? $_REQUEST['msa_remote_activation'] : "";
    if (empty($query_var)) {
        return;
    }
    $decoded = json_decode(base64_decode($query_var), true);

    /**
     * If API Secret is present, the message will be signed by it.
     * If not, the message is signed by the pingback_url.
     */
    $dataOpts = get_option(MYSITEAPP_OPTIONS_DATA, array());
    $signKey = 1;
    $signVal = get_bloginfo('pingback_url');
    if (array_key_exists('uppsite_secret', $dataOpts) && !empty($dataOpts['uppsite_secret'])) {
        $signKey = 2;
        $signVal = $dataOpts['uppsite_secret'];
    }
    $signVal = md5($signVal);
    if (md5($decoded['data'].$decoded['secret' . $signKey]) != $decoded['verify' . $signKey]
        || $decoded['secret' . $signKey] != $signVal) {
        uppsite_provide_feedback(array(
            'error' => 'verification failed',
            'signKey' => $signKey
        ));
        return;
    }
    $data = json_decode($decoded['data'], true);

    $prefOpts = get_option(MYSITEAPP_OPTIONS_PREFS, array());
    $opts = get_option(MYSITEAPP_OPTIONS_OPTS, array());
    $bizOpts =  get_option(MYSITEAPP_OPTIONS_BUSINESS, array());
    $refreshPrefs = false;
    $debugPrefs = false;

    foreach ($data as $key=>$val) {
        // Allow only some keys, and into specific tables.
        switch ($key) {
            case "app_id":
            case "uppsite_key":
            case "uppsite_secret":
            case "last_native_check":
                $dataOpts[$key] = $val;
                break;
            case "update_prefs":
                $refreshPrefs = true;
                break;
            case "activated":
            case "webapp_mode":
            case "site_type":
            case "push_control":
                $opts[$key] = $val;
                break;
            case "change_biz":
                $bizOpts = uppsite_update_options($bizOpts, $val);
                break;
            case "change_prefs":
                $prefOpts = uppsite_update_options($prefOpts, $val);
                break;
            case 'debug_uppsite':
                $debugPrefs = true;
                break;
            case 'reset_uppsite':
                uppsite_reset_db_vals($dataOpts);
                break;
        }
    }

    update_option(MYSITEAPP_OPTIONS_DATA ,$dataOpts);
    update_option(MYSITEAPP_OPTIONS_OPTS, $opts);
    update_option(MYSITEAPP_OPTIONS_BUSINESS, $bizOpts);
    update_option(MYSITEAPP_OPTIONS_PREFS, $prefOpts);

    if ($refreshPrefs) {
        // Initiate a callback for refreshing this site's preferences.
        uppsite_prefs_init(true);
    }
    if ($debugPrefs) {
        // Print debug information
        unset($dataOpts['uppsite_key'], $dataOpts['uppsite_secret']);
        $uppsite_options[MYSITEAPP_OPTIONS_DATA] = $dataOpts;
        $uppsite_options[MYSITEAPP_OPTIONS_OPTS] = $opts;
        $uppsite_options[MYSITEAPP_OPTIONS_PREFS] = $prefOpts;
        // No need for biz options, we can get them with ajax.
        uppsite_provide_feedback($uppsite_options);
    }
    uppsite_provide_feedback(true);
}

/**
 * @return string JSON-encoded string with ad details for the webapp
 */
function mysiteapp_get_ads() {
    $ad_active = mysiteapp_get_prefs_value('ad_display', false);
    $ret = array(
        "active" => $ad_active && $ad_active != "false",
        "html" => mysiteapp_get_prefs_value('ads', '')
    );
    if (($nexageDcn = mysiteapp_get_prefs_value('ads_nexage_dcn', false)) !== false) {
        $ret['nexage_dcn'] = $nexageDcn;
        $ret['nexage_params'] = mysiteapp_get_prefs_value('ads_nexage_params', null);
    }
    $state_arr = array(
        '0' => 'none',
        '1' => 'top',
        '2' => 'bottom'
    );
    $ad_state = mysiteapp_get_prefs_value('ad_state', 1);
    $ret['ad_state'] = array_key_exists($ad_state, $state_arr) ? $state_arr[$ad_state] : 'top';
    return json_encode($ret);
}

/**
 * Returns data from the one of UppSite's options
 * @param $options_name string  Name of the option key in db.
 * @param $key string   Key to search
 * @param $default  mixed   Default value to return if key is empty
 * @return mixed    The value for this key, or null if no array/key not found
 */
function mysiteapp_get_options_value($options_name, $key, $default = null){
    $arr = get_option($options_name);
    if ($arr === false || !is_array($arr) ||
        ( is_array($arr) && !array_key_exists($key, $arr) )) {
        return !is_null($default) ? $default : null;
    }
    if (!is_null($default) && empty($arr[$key])) {
        return $default;
    }
    return $arr[$key];
}

/**
 * Returns data from the preferences
 * @param $key string   Key to search
 * @param $default  mixed   Default value to return if key is empty
 * @return mixed    The value for this key, or null if no prefs/key not found
 */
function mysiteapp_get_prefs_value($key, $default = null) {
    return mysiteapp_get_options_value(MYSITEAPP_OPTIONS_PREFS, $key, $default);
}

/**
 * Converts a date from WP format to unix format
 * @param string $datetime Date string (e.g. 2008-02-07 12:19:32)
 * @return int Unix timestamp
 */
function mysiteapp_convert_datetime($datetime) {
    $values = explode(" ", $datetime);

    $dates = explode("-", $values[0]);
    $times = explode(":", $values[1]);

    return mktime($times[0], $times[1], $times[2], $dates[1], $dates[2], $dates[0]);
}

/**
 * @return bool Tells whether a push notification can be sent.
 */
function uppsite_api_values_set() {
    $dataOpts = get_option(MYSITEAPP_OPTIONS_DATA, array());
    return array_key_exists('uppsite_key', $dataOpts) && array_key_exists('uppsite_secret', $dataOpts) &&
        !empty($dataOpts['uppsite_key']) && !empty($dataOpts['uppsite_secret']);
}

/**
 * @return bool Whether push control is available for this site or not.
 */
function uppsite_push_control_enabled() {
    $options = get_option(MYSITEAPP_OPTIONS_OPTS, array());
    return isset($options['push_control']) && $options['push_control'] == '1';
}

/**
 * Sends notification to UppSite's server in order to send push notification to clients.
 * @param int $post_id  Post id
 * @param null $post_details (optional) Post details
 */
function mysiteapp_send_push($post_id, $post_details = NULL) {
    if (!uppsite_api_values_set() ||
        uppsite_push_control_enabled() && !isset($_POST['send_push'])) {
        // Can't send push notifications if no api key and secret are set.
        return;
    }

    if (is_null($post_details)) {
        // Fill post details
        $post_details = get_post($post_id, ARRAY_A);
    }
    $dataOpts = get_option(MYSITEAPP_OPTIONS_DATA);

    $json_str = json_encode(array(
        'title' => $post_details['post_title'],
        'post_id' => $post_details['ID'],
        'utime' => mysiteapp_convert_datetime($post_details['post_date']),
        'api_key' => $dataOpts['uppsite_key']
    ));
    $hash = mysiteapp_sign_message($json_str);

    wp_remote_post(MYSITEAPP_PUSHSERVICE, array(
        'body' => 'data='.$json_str.'&hash='.$hash,
        'timeout' => 5,
    ));
}

/**
 * Sends push notification, if post is published
 * @param $post_id int Post id
 */
function mysiteapp_new_post_push($post_id) {
    if ($_POST['post_status'] != 'publish') { return; }
    if ( (isset($_POST['original_post_status']) && $_POST['original_post_status'] != $_POST['post_status']) || // Post status changed
        (isset($_POST['_status']) && $_POST['_status'] != $_POST['post_status']) ) { // Another way of changing the post status
        mysiteapp_send_push($post_id);
    }
}

/**
 * Sends a push notification for a future post
 * @param $post_id int Post id
 */
function mysiteapp_future_post_push($post_id) {
    $post_details = get_post($post_id, ARRAY_A);
    if ($post_details['post_status'] != 'publish') { return; }

    if (!$_POST &&
        false == (isset($post_details['sticky']) && $post_details['sticky'] == 'sticky')) {
        // Send only if not a sticky post
        mysiteapp_send_push($post_id, $post_details);
    }
}

/**
 * Appends Apple's smart banner to the header only when having iOS app, and using the regular theme
 * (wp_head hook)
 */
function mysiteapp_append_native_link() {
    global $msap, $wp;
    if ($msap->has_custom_theme()) {
        // Mobile app / native / landing
        return;
    }

    $appleId = uppsite_get_native_app("identifier", "ios");
    if (!is_null($appleId) && uppsite_get_native_app("banner", "ios")) {
        $currentUrl = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
        print '<meta name="apple-itunes-app" content="app-id=' . $appleId . ', app-argument=' . esc_attr($currentUrl) . '"/>';
    }
}

/**
 * Helper function for getting the current query to parse
 * @return WP_Query  A custom query made by the plugin, or the global query if none.
 */
function mysiteapp_get_current_query() {
    global $mysiteapp_cur_query, $wp_query;
    return !is_null($mysiteapp_cur_query) ? $mysiteapp_cur_query : $wp_query;
}
/**
 * Sets a custom query to be handled in the plugin
 * @param $query array The query params to construct WP_Query
 * @return WP_Query The new WP_Query object that was just created
 */
function mysiteapp_set_current_query($query) {
    global $mysiteapp_cur_query;
    $mysiteapp_cur_query = new WP_Query($query);
    return $mysiteapp_cur_query;
}

/** Homepage functionality */

/**
 * @return array    Returns an array with homepage settings (if any)
 */
function uppsite_homepage_get_settings() {
    $hpSettings = mysiteapp_get_prefs_value('homepage_settings');
    return !is_null($hpSettings) ? json_decode($hpSettings, true) : array();
}

/**
 * @note    Enforcing max allowed posts in carousel
 * @return int Number of posts to return for the carousel
 */
function mysiteapp_homepage_carousel_posts_num() {
    $num = MYSITEAPP_HOMEPAGE_POSTS;
    $homepageSettings = uppsite_homepage_get_settings();
    if (isset($_REQUEST['homepage_post']) && is_numeric($_REQUEST['homepage_post'])) {
        $num = $_REQUEST['homepage_post'];
    } elseif (isset($homepageSettings['homepage_post']) && is_numeric($homepageSettings['homepage_post'])) {
        $num = $homepageSettings['homepage_post'];
    }
    return min( abs($num), UPPSITE_UPPER_LIMIT );
}

/**
 * @note    Enforcing max allowed posts per category
 * @return int Number of posts in each grouped category
 */
function mysiteapp_homepage_cat_posts() {
    $num = MYSITEAPP_HOMEPAGE_DEFAULT_MIN_POSTS;
    $homepageSettings = uppsite_homepage_get_settings();
    if (isset($_REQUEST['posts_num']) && is_numeric($_REQUEST['posts_num'])) {
        $num = $_REQUEST['posts_num'];
    } elseif (isset($homepageSettings['posts_num']) && is_numeric($homepageSettings['posts_num'])) {
        $num = $homepageSettings['posts_num'];
    }
    return min( abs($num), UPPSITE_UPPER_LIMIT );
}

/**
 * Adds a post that appears in the Homepage Carousel to the list of excluded posts in the following list.
 * @param $post_id   int The post id
 */
function mysiteapp_homepage_add_post($post_id){
    global $homepage_post_ids;
    if (!is_array($homepage_post_ids)) {
        $homepage_post_ids = array();
    }
    array_push($homepage_post_ids, $post_id);

}
/**
 * @return array    List of post ids to exclude from post queries.
 */
function mysiteapp_homepage_get_excluded_posts() {
    global $homepage_post_ids;
    return !is_array($homepage_post_ids) ? array() : $homepage_post_ids;
}

/**
 * Guesses whether this is a fresh installation (only 1 published posts)
 *
 * @return bool Is this a fresh installation of WordPress or not
 */
function mysiteapp_is_fresh_wordpress_installation(){
     return wp_count_posts()->publish == 1;
}

/**
 * Searches for popular categories and orders them in descending order
 *
 * @return array Category ids of popular categories
 */
function mysiteapp_homepage_get_popular_categories() {
    $pop_cat = get_categories( 'order=desc&orderby=count&number=' . MYSITEAPP_HOMEPAGE_MAX_CATEGORIES );
    return array_map(create_function('$cat', 'return $cat->term_id;'), $pop_cat);
}

/**
 * Seeks for categories to display in homepage -
 * If the requested explicitly ask for some cats, use that.
 * If not, and a list present in prefs, display by it. In the worst case, return the popular categories.
 * @return array    List of categories to display in homepage
 */
function uppsite_homepage_get_categories() {
    $cats = null;
    if (array_key_exists('cats_ar', $_REQUEST) && is_array($_REQUEST['cats_ar'])) {
        $cats = $_REQUEST['cats_ar'];
    } else {
        $settings = uppsite_homepage_get_settings();
        $cats = array_key_exists('cats_ar', $settings) ? $settings['cats_ar'] : mysiteapp_homepage_get_popular_categories();
    }
    // Sanitize
    $cats = array_splice($cats, 0, UPPSITE_UPPER_LIMIT);
    $cats = array_map( 'sanitize_text_field', $cats );
    return $cats;
}

/**
 * @return bool  Is it homepage display mode requested
 */
function mysiteapp_should_show_homepage() {
    return mysiteapp_get_posts_layout() == "homepage";
}

/**
 * Pre-set the query according to the display mode, to save unnecessary query_posts() calls.
 * @note Will work only when it is a plugin-related request, as it uses "mysiteapp_get_posts_layout()"
 * @param mixed $query WP_Query
 */
function uppsite_pre_get_posts($query = false) {
    global $wp_the_query, // is_main_query() is from 3.3
            $msap;
    if (!$msap->has_custom_theme() || !is_a($query, 'WP_Query') || ($query != $wp_the_query)) {
        // Bail if it is not the app
        return;
    }
    // Disable "Sticky"?
    if (mysiteapp_get_prefs_value('disable_sticky', false)) {
        // 'caller_get_posts' deprecated at 3.1
        $query->set(get_bloginfo('version') >= 3.1 ? 'ignore_sticky_posts' : 'caller_get_posts', 1);
    }
    if (is_home() && mysiteapp_should_show_homepage()) {
        // Set Homepage query params
        $query->set('paged', 1);
        $query->set('posts_per_page', mysiteapp_homepage_carousel_posts_num());
        $query->set('order', 'desc');
    }
}

/**
 * Filter the "show on front" setting,
 * @param $val
 * @return string If we should always show posts on the frontpage, return 'posts', else $val
 */
function uppsite_filter_show_on_front($val) {
    global $msap;
    return $msap->has_custom_theme() && mysiteapp_get_prefs_value('always_show_posts', false) ? 'posts' : $val;
}

/****************************************
 *           Security related           *
 ****************************************/

/**
 * Ajax action that verifies the nonce was created at this blog.
 */
function uppsite_ajax_verify_nonce() {
    $nonce = isset($_REQUEST['nonce_name']) ? $_REQUEST['nonce_name'] : null;
    $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : null;
    $requesterUid = isset($_REQUEST['nonce_requester']) && is_numeric($_REQUEST['nonce_requester']) ? $_REQUEST['nonce_requester'] : null;
    if (empty($nonce) || empty($token) || empty($requesterUid) ||
        strpos($nonce, UPPSITE_NONCE_PREFIX) === false) {
        return;
    }
    // 'nonce_user_logged_out' filter is only at 3.6 :(
    // So we will do a little hack
    $requesterUser = new WP_User(""); // Must not use the constructor, else it will load the user data.

    // Will issue deprecated warning on 3.3+, so we will supress that.
    // We can't use "ID", because wp_verify_nonce use "id" until 3.2 or something like this.
    add_filter('deprecated_argument_trigger_error', 'uppsite_supress_deprecated_warnings');
    $requesterUser->ID = $requesterUser->id = $requesterUid;
    
    global $current_user;
    $current_user = $requesterUser; // We are not using wp_set_current_user() because we don't wish to populate the WP_User obj

    print json_encode(array(
        'nonce' => wp_verify_nonce($token, $nonce)
    ));
    exit;
}

/**
 * Because we use WP_User->id on versions 3.3 and higher, it will generate a deprecated warning.
 * @see uppsite_ajax_verify_nonce
 * @return bool Should supress warnings
 */
function uppsite_supress_deprecated_warnings(/* $shouldDisplay*/) {
    return false;
}

/** Webapp theme selection **/
add_action('wp', 'mysiteapp_set_webapp_theme');
/** Webapp activation */
add_action('init', 'uppsite_remote_activation');
/** Push notification upon new post */
add_action('publish_post', 'mysiteapp_new_post_push', 10, 1);
add_action('publish_future_post', 'mysiteapp_future_post_push', 10, 1);
/** Append smart banners to the header */
add_action('wp_head', 'mysiteapp_append_native_link');
/** Leverage pre_get_posts to save the main query be called for nothing. */
add_action('pre_get_posts', 'uppsite_pre_get_posts', 99999); // Run at the latest priority, so no one will overide 'posts_per_page'
/** Filter 'get_option(show_on_front)' */
add_filter('option_show_on_front', 'uppsite_filter_show_on_front');
/** Nonce verification related */
add_action('wp_ajax_nopriv_uppsite_verify_nonce', 'uppsite_ajax_verify_nonce');

endif; /*if (!defined('MYSITEAPP_AGENT')):*/
