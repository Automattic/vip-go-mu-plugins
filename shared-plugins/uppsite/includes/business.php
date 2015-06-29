<?php
/**
 * Business-logic related functionality.
 * Offers page-based sites that are "business card" to have a webapp customized to their needs, instead of a traditional
 * blog view.
 */

/** Maximum number of items to fetch in each get_* query */
define('UPPSITE_GET_MAX_ITEMS', 100);
/** Number of images per page */
define('IMAGES_PER_PAGE', 30);
/** Query used to get pages */
define('UPPSITE_PAGELIST_QUERY', 'sort_order=ASC&post_status=publish&post_type=page&sort_column=menu_order&number=' . UPPSITE_GET_MAX_ITEMS);

/** vCard creation functionality */
require_once(dirname(__FILE__) . '/vcard.php');

/**
 * A class which is responsible for mining information from the site to an options array.
 */
class UppSiteBusinessDataMiner {
    var $current_info = null;
    var $front_page = null;
    var $regexes = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->current_info = array(
            'phone' => null,
            'phone_weak' => null,
            'address' => null,
            'email' => null
        );
        // Populate the regexes from file, as they are too big for one file.
        require_once(dirname(__FILE__) . '/regexes.inc.php');
        $this->regexes = isset($regexes) ? $regexes : array();
    }

    /**
     * Performs a search of "business information" from the site (e.g. name, phone number, email...) and stores
     * it to an options array, for later use by the webapp.
     *
     * @param bool $force    Override current setting?
     */
    public function build_site_info($force = false) {
        $this->_search_contact_info();

        $bizInfo = get_option(MYSITEAPP_OPTIONS_BUSINESS, array());
        $bizInfo['title'] = get_bloginfo('name');
        $bizInfo['description'] = get_bloginfo('description');
        $bizInfo['contact_phone'] = empty($this->current_info['phone']) && !empty($this->current_info['phone_weak']) ?
                                    $this->current_info['phone_weak'] : $this->current_info['phone'];
        $bizInfo['contact_address'] = $this->current_info['address'];
        $bizInfo['contact_address_vcf'] = isset($this->current_info['address_vcf']) ? $this->current_info['address_vcf'] : '';
        $bizInfo['email'] = $this->current_info['email'] ? $this->current_info['email'] : get_bloginfo('admin_email');

        $bizInfo['featured'] = $this->get_images_from_homepage();
        $bizInfo['all_images'] = $this->get_site_images();

        $share_arr = $this->_get_share_links();
        $bizInfo['facebook'] = $share_arr['facebook'];
        $bizInfo['twitter'] = $share_arr['twitter'];

        if (!isset($bizInfo['navbar_display']) || $force) {
            $bizInfo['navbar_display'] = true;
        }
        if (!isset($bizInfo['selected_images']) || $force) {
            // Set all images as selected
            $bizInfo['selected_images'] = $bizInfo['all_images'];
        }
        if (!isset($bizInfo['menu_pages']) || $force) {
            $pages = get_pages(UPPSITE_PAGELIST_QUERY);
            $bizInfo['menu_pages'] = array_map(create_function('$page', 'return $page->ID;'), $pages);
        }
        update_option(MYSITEAPP_OPTIONS_BUSINESS, $bizInfo);
    }

    /**
     * @param $part string  Part name (key of $this->regexes)
     * @param $content string   Content
     * @return mixed Matched part from content, or null if not found
     */
    private function _get_from_content($part, $content) {
        if (!array_key_exists($part, $this->regexes)) { return null; }
        return preg_match($this->regexes[$part], $content, $matched) > 0 ? $matched[0] : null;
    }

    /**
     * Fetches the front page and stores it for continual parsing
     * @return string|null  the front page html, or null if couldn't fetch it.
     */
    private function get_front_page() {
        if (!is_null($this->front_page)) {
            return $this->front_page;
        }

        $response = wp_remote_get( add_query_arg( 'uppsite_is_miner', '1', home_url() ) );
        if ( is_wp_error( $response ) ) {
            return null;
        }
        $this->front_page = $response['body'];
        return $this->front_page;
    }

    /**
     * Using Google API to decode an address to the right format.
     * @see https://developers.google.com/maps/documentation/geocoding/ for more details about the parameters
     * @param string $address   Raw address
     * @return array|null   String components, or null if error occured/address not found.
     */
    private function _fetch_google_address($address) {
        $response = wp_remote_get( 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=' . urlencode($address) );
        if ( is_wp_error( $response ) ) {
            return null;
        }
        $results = json_decode($response['body'], true);
        $results = $results['results'];
        $parts = array(
            'street_number' => 'street_number',
            'address' => 'route',
            'city' => 'locality',
            'state' => 'administrative_area_level_1',
            'zip' => 'postal_code',
        );
        if (count($results) == 0 || count($results[0]['address_components']) == 0) { return null; }
        $components = $results[0]['address_components'];
        $ret = array();
        foreach ($parts as $need => $type) {
            foreach ($components as &$component) {
                if (in_array($type, $component['types'])) {
                    $ret[$need] = $component['short_name'];
                }
            }
            if (!array_key_exists($need, $ret)) {
                // Fill empty values
                $ret[$need] = '';
            }
        }
        return $ret;
    }

    /**
     * Formats an address array to a string
     * @param array $arr Array of address parts
     * @param bool $vcfFormat   Format as vcf expects?
     * @return string   Formatted address
     */
    private function format_address($arr, $vcfFormat = false) {
        return sprintf($vcfFormat ? "%s %s;%s;%s;%s" : "%s %s\n%s, %s %s",
            $arr['street_number'], $arr['address'], $arr['city'], $arr['state'], $arr['zip']);
    }

    /**
     * Searches the page for contact info
     * @param string $content  Page's content
     */
    private function parse_page_for_info($content) {
        if (is_null($this->current_info['phone'])) {
            $this->current_info['phone'] = $this->_get_from_content('phone', $content);
            if (is_null($this->current_info['phone_weak'])) {
                $this->current_info['phone_weak'] = $this->_get_from_content('phone_weak', $content);
            }
        }
        if (is_null($this->current_info['address'])) {
            $address = $this->_get_from_content('address', $content);
            if (!is_null($address)) {
                // Try to get better address using google maps api.
                $parsed = $this->_fetch_google_address($this->current_info['address']);
                $vcfAddress = '';
                if (!is_null($parsed)) {
                    $address = $this->format_address($parsed);
                    $vcfAddress = $this->format_address($parsed, true);
                }
                $this->current_info['address'] = $address;
                $this->current_info['address_vcf'] = $vcfAddress;
            }
        }
        if (is_null($this->current_info['email'])) {
            $this->current_info['email'] = $this->_get_from_content('email', $content);
        }
    }

    /**
     * @return array    Array containing social sites and profiles (from the front page)
     */
    private function _get_share_links() {
        $sites = array(
            'facebook' => null,
            'twitter' => null
        );
        foreach ($sites as $key => $_v) {
            $link = preg_match('/<a[^>]*href=["\']([^"\']*(' . $key . ')[^"\']*)["\']/i', $this->get_front_page(), $matches) > 0 ?
                $matches[1] : null;
            if (!is_null($link) && preg_match("/" . $key . "\.[^\/]+\/(?:([^\/]+)\/)*([^\/]+)/i", $link, $parts) > 0) {
                $link = $parts[2];
            }
            $sites[$key] = $link;
        }
        return $sites;
    }

    /**
     * Searches for pages with information
     */
    private function _search_contact_info() {
        // Front page ?
        if ($front_page_id = get_option('page_on_front', false)) {
            $home = get_post($front_page_id);
            $this->parse_page_for_info($home->post_content);
        }

        // Potential pages with contact info
        $likelyContactPages = '/.*(Contact|About|Info|Home).*/i';
        $all_pages = get_pages();
        foreach ($all_pages as $page) {
            if (preg_match($likelyContactPages, $page->post_title) > 0) {
                $this->parse_page_for_info($page->post_content);
            }
        }

        // Homepage. Different than "Front page" as the header/footer or some widget might contain information, and
        // we are trying to search the whole page HTML.
        if ($home_page = $this->get_front_page()) {
            $this->parse_page_for_info($home_page);
        }
    }

    /**
     * @param string $content  Content to search at
     * @return array    Array containing all the images found in the content (can be empty)
     */
    private function _find_images($content) {
        return preg_match_all('/<img[^>]*src=["\'](.+?)["\']/i', $content, $matches) > 0 ?
            $matches[1] : array();
    }

    /**
     * @return array    Unique photo urls from the homepage and rest of site pages.
     */
    function get_site_images() {
        $all_images = array();
        // Search the home page
        if ($frontPage = $this->get_front_page()) {
            $all_images = array_merge($all_images, $this->_find_images( $frontPage ));
        }

        // Search all pages.
        $all_pages = get_pages();
        foreach ($all_pages as $page) {
            $all_images = array_merge($all_images, $this->_find_images( $page->post_content ));
        }

        return array_unique($all_images, SORT_STRING);
    }

    /**
     * Searches for images in the homepage that are from the uploads dir, or from the theme.
     * @return array    Array of images
     */
    private function get_images_from_homepage() {
        if (!($frontPage = $this->get_front_page())) {
            return array();
        }

        // Search for images from the upload dir
        $wp_dir = wp_upload_dir();
        preg_match_all('/<img[^>]*src=["\'](' . preg_quote($wp_dir['baseurl'], '/') . '.+?)["\']/i', $frontPage, $matches);
        if (!is_array($matches[1]) || count($matches[1]) == 0) {
            // Try searching for theme images.
            preg_match_all('/<img[^>]*src=["\'](' . preg_quote(content_url('themes'), '/') . '.+?)["\']/i', $frontPage, $matches);
            if (!is_array($matches[1]) || count($matches[1]) == 0) {
                return array(); // None found
            }
        }

        return array_slice($matches[1], 0, 3); // Return the first three.
    }
}

/**
 * In order not to create the UppSiteBusinessDataMiner at every page, we will initiate it only when required.
 * @param null|float $arg   Extra arguments, if supplied from callback
 */
function uppsite_miner_run($arg = null) {
    static $uppsiteMiner = null;
    if (!is_null($uppsiteMiner)) { return; } // Don't run twice.

    $force = !is_null($arg) && is_float($arg) && $arg < 5;
    $force |= isset($_REQUEST['uppsite_miner']);

    $bizOptions = get_option(MYSITEAPP_OPTIONS_BUSINESS);
    $shouldRun = empty($bizOptions) || count($bizOptions) == 0; // Run if no business data

    $shouldntRun = isset($_REQUEST['uppsite_is_miner']);

    if (!$shouldntRun && ($force || $shouldRun)) {
        $uppsiteMiner = new UppSiteBusinessDataMiner();
        $uppsiteMiner->build_site_info($force);
    }
}

/** Hook points that the miner would run in them. */
add_action( 'after_setup_theme', 'uppsite_miner_run' ); // Might invoked via ?uppsite_miner=1
add_action( 'uppsite_is_activated', 'uppsite_miner_run' ); // Invoked after plugin activation
add_action( 'uppsite_has_upgraded', 'uppsite_miner_run', 1, 1 ); // Invoked upon plugin upgrade

/**
 * Ajax for inner-frame communication with UppSite's frame.
 * It transfers paged data regarding the images of the site, allowing UppSite's frame to show page-listed view of the photos.
 * Array values are:
 *  array(
 *      'img_url' => <URL>,
 *      'found' => array(<list of places this image is selected>)
 *  );
 */
function uppsite_get_bizimages() {
    $page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 0;

    $image_ar = array_unique(mysiteapp_get_options_value(MYSITEAPP_OPTIONS_BUSINESS, 'all_images', array()), SORT_STRING);
    $selectedImages = mysiteapp_get_options_value(MYSITEAPP_OPTIONS_BUSINESS, 'selected_images', array());
    $featuredImages = mysiteapp_get_options_value(MYSITEAPP_OPTIONS_BUSINESS, 'featured', array());

    $current_page_ar = array_slice($image_ar, $page * IMAGES_PER_PAGE, IMAGES_PER_PAGE);
    $images_list = array();
    foreach ($current_page_ar as $image) {
        $found_in = array();
        if (in_array($image, $selectedImages)) { $found_in[] = 'photos'; }
        if (in_array($image, $featuredImages)) { $found_in[] = 'homepage'; }
        $images_list[] = array(
            'img_url' => $image,
            'found' => $found_in
        );
    }

    return $images_list;
}


/**
 * @return array    List of pages that will appear in the "business" webapp
 */
function uppsite_get_biz_pages() {
    $filter = array(
        'sort_column' => 'menu_order',
        'sort_order' => 'ASC',
    );
    $include = mysiteapp_get_options_value(MYSITEAPP_OPTIONS_BUSINESS, 'menu_pages', null);
    if (!is_null($include)) {
        $filter['include'] = $include;
    }
    $pages = get_pages($filter);
    if (!is_null($include)) {
        // Order the menu items as in the list
        array_walk(
            $pages,
            create_function('&$page, $key, $include', '$page->menu_order = array_search($page->ID, $include)+1;'),
            $include
        );
    }
    return $pages;
}


/********************************************************
 *   Functions for communicating with UppSite's frame   *
 ********************************************************/

/**
 * Returns the categories list divided into "all categories" and "selected categories for display in Homepage display"
 * Category is array( id, name )
 */
function uppsite_get_categorieslist() {
    $allCats = array_map(
        create_function('$cat', 'return array($cat->term_id, $cat->name);'),
        get_categories('order=desc&orderby=count&number=' . UPPSITE_GET_MAX_ITEMS)
    );
    $selectedCats = uppsite_homepage_get_categories();
    return array(
        'all' => $allCats,
        'selected' => $selectedCats
    );
}

/**
 * Returns the list of available pages divided into "all pages" and "selected pages for display in Business webapp"
 * Page is array( id, name )
 */
function uppsite_get_pagelist() {
    $filterValues = create_function('$page', 'return array($page->ID, $page->post_title);');
    $allPages = array_map(
        $filterValues,
        get_pages(UPPSITE_PAGELIST_QUERY)
    );

    $selectedPages = uppsite_get_biz_pages();
    // Sort the selected pages according to menu_order
    usort($selectedPages, create_function('$a, $b', 'if ($a->menu_order == $b->menu_order) { return 0; }; return ($a->menu_order < $b->menu_order) ? -1 : 1;'));
    $selectedPages = array_map(
        $filterValues,
        $selectedPages
    );
    return array(
        'all' => $allPages,
        'selected' => $selectedPages
    );
}

/**
 * Returns the "business information" gathered using the plugin
 * @note Strips "large data" such as images list
 */
function uppsite_get_bizinfo() {
    $businessData = get_option(MYSITEAPP_OPTIONS_BUSINESS, array());
    if (is_array($businessData)) {
        unset($businessData['all_images']); // All images array
        unset($businessData['selected_images']); // Selected images for "Photos" tab
        unset($businessData['featured']); // Selected images for animated background
    }
    return $businessData;
}

/**
 * Returns information about the site
 */
function uppsite_get_bloginfo() {
    return array(
        'name' => get_bloginfo('name'),
        'url' => site_url(),
        'version' => get_bloginfo('version'),
        'tagline' => get_bloginfo('description')
    );
}

/**
 * Router function for remote information fetching:
 * - Blog info
 * - Business information info
 * - Categories list (for navigation)
 * - Pages list (for business navigation)
 * - Images (for "Photos" tab)
 *
 * It prints the output as JSON
 */
function uppsite_ajax_get_info() {
    $req = sanitize_text_field($_REQUEST['uppsite_request']);
    $allowedRequests = array(
        'bloginfo',
        'bizinfo',
        'categorieslist',
        'pagelist',
        'bizimages'
    );
    if (in_array($req, $allowedRequests) && function_exists('uppsite_get_' . $req)) {
        print json_encode(call_user_func('uppsite_get_' . $req));
    }
    exit;
}

/** Functions should be available inside the admin panel for inner-communication with UppSite's frame */
add_action('wp_ajax_uppsite_get_info', 'uppsite_ajax_get_info');
/** Functions should be available to remote entities such as UppSite */
add_action('wp_ajax_nopriv_uppsite_get_info', 'uppsite_ajax_get_info');