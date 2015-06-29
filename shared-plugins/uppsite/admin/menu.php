<?php
/**
 * UppSite admin menu creation
 */

define('UPPSITE_ADMIN_SETUP_SLUG', 'uppsite-setup');
define('UPPSITE_ADMIN_SETTINGS', 'uppsite-settings');

/**
 * Helper class for holding the admin menu options
 */
class UppSiteAdmin {
    static $admin_options = array(
        array(
            'name' => 'Home',
            'slug' => 'home',
            'isMain' => true
        ),
        array(
            'name' => 'General',
            'slug' => 'general'
        ),
        array(
            'name' => 'Configuration',
            'slug' => 'config'
        ),
        array(
            'name' => 'Design',
            'slug' => 'design'
        ),
        array(
            'name' => 'App Stores',
            'slug' => 'stores'
        ),
        array(
            'name' => 'Analytics',
            'slug' => 'analytics'
        ),
        array(
            'name' => 'Support',
            'slug' => 'support'
        ),
        array(
            'name' => 'About',
            'slug' => 'about'
        )
    );

    /**
     * @return string|null  Selected tab name, or null if not found (this shouldn't happen)
     */
    static function getCurrentTab() {
        if (!isset($_GET['page'])) {
            return self::$admin_options[0];
        }
        foreach (self::$admin_options as $menu) {
            $menuPage = UPPSITE_ADMIN_SETTINGS . ( array_key_exists('isMain', $menu) ? "" : "-" . $menu['slug'] );
            if ($menuPage == $_GET['page']) {
                return $menu;
            }
        }
        return null;
    }
}

/**
 * Additional JS required for UppSite's admin
 * @param string $hook  The hooked page
 */
function uppsite_admin_scripts($hook) {
    if (strpos($hook, "uppsite") === false) {
        return;
    }
    wp_register_script('uppsite-postmessage', plugins_url('js/postmessage.js', __FILE__));
    wp_enqueue_script('uppsite-postmessage');

    if (uppsite_admin_did_setup()) {
        // Dashboard js
        wp_register_script('uppsite-dashboard', plugins_url('js/dashboard.js', __FILE__));
        wp_enqueue_script('uppsite-dashboard');
    }
}

/**
 * Additional CSS required for UppSite's admin
 */
function uppsite_admin_styles() {
    wp_register_style('uppsite-css', plugins_url('css/uppsite.css', __FILE__));
    wp_enqueue_style('uppsite-css');
}

/**
 * Inits the admin menu of UppSite
 */
function mysiteapp_admin_menu() {
    if (uppsite_admin_did_setup()) {
        $mainFunc = 'uppsite_admin_general';
        add_menu_page('UppSite - Go Mobile', 'Mobile', UPPSITE_ADMIN_REQUIRED_LEVEL, UPPSITE_ADMIN_SETTINGS, $mainFunc, 'div');
        // Show full menu
        $first = true;
        foreach (UppSiteAdmin::$admin_options as $menu) {
            // Add the sub-menus
            add_submenu_page(
                UPPSITE_ADMIN_SETTINGS,
                'UppSite - ' . $menu['name'],
                $menu['name'],
                UPPSITE_ADMIN_REQUIRED_LEVEL,
                UPPSITE_ADMIN_SETTINGS . ( !$first ? "-" . $menu['slug'] : "" ),
                $mainFunc
            );
            $first = false;
        }
    } else {
        // Show setup
        add_menu_page('UppSite - Go Mobile', 'Mobile', UPPSITE_ADMIN_REQUIRED_LEVEL, UPPSITE_ADMIN_SETUP_SLUG, 'uppsite_admin_setup', 'div');
    }
}

/** Hooking the menu **/
add_action('admin_menu', 'mysiteapp_admin_menu');
/** Add additional scripts */
add_action('admin_enqueue_scripts', 'uppsite_admin_scripts');
/** Style most be loaded at all time, to provide the icon for the toplevel menu */
add_action('admin_print_styles', 'uppsite_admin_styles');