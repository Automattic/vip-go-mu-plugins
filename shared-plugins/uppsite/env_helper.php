<?php
/**
 * Helpers functions for the different environments.
 */

/**
 * Tells whether the environment is WP.com VIP
 *
 * @return bool Is WP.com VIP env
 */
function uppsite_is_wpcom_vip() {
    return function_exists( 'wpcom_vip_load_plugin' ) || function_exists( 'wpcom_is_vip' );
}

/**
 * @return int  Platform identifier for the current installation, as stated in UppSite's DB
 *              (1 - WP standalone, 3 - WP VIP)
 */
function uppsite_remote_get_platform() {
    return uppsite_is_wpcom_vip() ? 3 : 1;
}

/**
 * Helper functions for getting a relative path between two absolute
 * @param $from From relative path
 * @param $to   To relative path
 * @return string   The relative path between both
 */
function mysiteapp_get_relative_path($from, $to) {
    $from     = explode(DIRECTORY_SEPARATOR, $from);
    $to       = explode(DIRECTORY_SEPARATOR, $to);
    $relPath  = $to;

    foreach($from as $depth => $dir) {
        // find first non-matching dir
        if($dir === $to[$depth]) {
            // ignore this directory
            array_shift($relPath);
        } else {
            // get number of remaining dirs to $from
            $remaining = count($from) - $depth;
            if($remaining > 1) {
                // add traversals up to first matching dir
                $padLength = (count($relPath) + $remaining - 1) * -1;
                $relPath = array_pad($relPath, $padLength, '..');
                break;
            } else {
                $relPath[0] = '.' . DIRECTORY_SEPARATOR . $relPath[0];
            }
        }
    }
    return implode(DIRECTORY_SEPARATOR, $relPath);
}

/**
 * Return the absolute path to the plugin's template dir
 *
 * @return string Absolute path to the template dir
 */
function mysiteapp_get_template_root() {
    // WordPress doesn't care about DIRECTORY_SEPARATOR, we do relative search, so we care :\
    $fixedThemeDir = str_replace("/", DIRECTORY_SEPARATOR, get_theme_root()) . DIRECTORY_SEPARATOR;
    return mysiteapp_get_relative_path($fixedThemeDir, dirname(__FILE__) . DIRECTORY_SEPARATOR . "themes");
}

/**
 * @return string URL for the current theme directory
 */
function uppsite_get_template_directory_uri() {
    return str_replace(DIRECTORY_SEPARATOR, "/", get_template_directory_uri());
}

/**
 * Callback action from webapp activation / de-activation
 * @param $state bool Represnt the state of the webapp
 */
function uppsite_change_webapp($state) {
    $myOpts = get_option(MYSITEAPP_OPTIONS_DATA);
    if (!isset($myOpts['fixes'])) {
        $myOpts['fixes'] = array();
    }
    // Fix WPTouch
    $v = get_option('bnc_iphone_pages');
    if (isset($v)) {
        $serialize = !is_array($v);
        if ($serialize) {
            $v = unserialize($v);
        }
        if ($state == true && !array_key_exists('wptouch-enable-regular-default', $myOpts['fixes'])) {
            // Activated
            $val = isset($v['enable-regular-default']) ? $v['enable-regular-default'] : false;
            $myOpts['fixes']['wptouch-enable-regular-default'] = $val;
            $v['enable-regular-default'] = "normal";
        } elseif ($state == false && array_key_exists('wptouch-enable-regular-default', $myOpts['fixes'])) {
            // Deactivated
            if ($myOpts['fixes']['wptouch-enable-regular-default'] == false) {
                unset($v['enable-regular-default']);
            } else {
                $v['enable-regular-default'] = $myOpts['fixes']['wptouch-enable-regular-default'];
            }
            unset($myOpts['fixes']['wptouch-enable-regular-default']);
        }
        if ($serialize) {
            $v = serialize($v);
        }
        update_option('bnc_iphone_pages', $v);
    }
    // Fix cache plugins
    if (!uppsite_is_wpcom_vip()) {
        uppsite_cache_fix_wp_super_cache(MySiteAppPlugin::$_mobile_ua, $state); // Add/remove mobile
        uppsite_cache_fix_w3_total_cache(MySiteAppPlugin::$_mobile_ua, $state); // Add/remove mobile
    }

    update_option(MYSITEAPP_OPTIONS_DATA, $myOpts);
}

/**
 * Options update of 'uppsite_options' - call webapp state change
 * @param $oldValues    Old Options
 * @param $newValues    New options
 */
function uppsite_options_updated($oldValues, $newValues) {
    // Call a change in the webapp/landing state
    uppsite_change_webapp(isset($newValues['webapp_mode']) && $newValues['webapp_mode'] != 'none');
    
    $dataOpts = get_option(MYSITEAPP_OPTIONS_DATA);
    
    if (isset($newValues['uppsite_key']) && isset($newValues['uppsite_secret'])) {
	    $dataOpts['uppsite_key'] = $newValues['uppsite_key'];
	    $dataOpts['uppsite_secret'] = $newValues['uppsite_secret'];
	    update_option(MYSITEAPP_OPTIONS_DATA, $dataOpts);
	}
}

/**
 * 'uppsite_options' Added
 * @param $newValues Options array
 */
function uppsite_options_added($optionName, $newValues) {
    uppsite_options_updated(null, $newValues);
}
/** Hook the updates on the 'uppsite_options' key */
add_action('add_option_' . MYSITEAPP_OPTIONS_OPTS, 'uppsite_options_added', 10, 2);
add_action('update_option_' . MYSITEAPP_OPTIONS_OPTS, 'uppsite_options_updated', 10, 2);

/**
 * Update plugin activation mode
 * @param $act activated / deactivated
 */
function uppsite_update_status($act) {
    if (!in_array($act, array("activated", "deactivated"))) {
        // Sanitize act
        return;
    }
    wp_remote_post(MYSITEAPP_AUTOKEY_URL,
        array(
            'body' => 'status='.$act.'&pingback=' . get_bloginfo('pingback_url'),
            'timeout' => 5
        )
    );
    do_action("uppsite_is_" . $act);
}

/**
 * Deactivation hook:
 * - Unregister webapp activities
 */
function uppsite_deactivated() {
    uppsite_change_webapp(false);
    uppsite_update_status("deactivated");
}

/** Deactivation hook */
register_deactivation_hook(dirname(__FILE__) . "/uppsite.php", 'uppsite_deactivated');

/**
 * Activation hook
 */
function uppsite_activated() {
    uppsite_update_status("activated");
}
/** Activation hook */
register_activation_hook(dirname(__FILE__) . "/uppsite.php", 'uppsite_activated');

/** Cache plugins fix helpers */
/**
 * Adds/removes a list of User-Agents to the rejected User-Agents list to cache of WP-Super-Cache
 * @param $userAgents   Array of user agents
 * @param bool $add     true to add, false to remove
 */
function uppsite_cache_fix_wp_super_cache($userAgents, $add = true) {
    if (function_exists('wp_cache_edit_rejected_ua')) {
        global $valid_nonce, $cache_rejected_user_agent;
        $shouldUpdate = false;
        foreach ($userAgents as $ua) {
            if ($add) {
                // Add to list
                if (!in_array($ua, $cache_rejected_user_agent)) {
                    $cache_rejected_user_agent[] = $ua;
                    $shouldUpdate = true;
                }
            } else {
                // Remove from list
                $uakey = array_search($ua, $cache_rejected_user_agent);
                if ($uakey !== false) {
                    unset($cache_rejected_user_agent[$uakey]);
                    $shouldUpdate = true;
                }
            }
        }
        if ($shouldUpdate) {
            $valid_nonce = true;
            ob_start();
            $_POST['wp_rejected_user_agent'] = implode("\n", $cache_rejected_user_agent);
            wp_cache_edit_rejected_ua();
            ob_end_clean();
        }
    }
}

/**
 * Adds/removes a list of User-Agents to the rejected User-Agents list to cache of W3 Total Cache
 * @param $userAgents   Array of user agents
 * @param bool $add     true to add, false to remove
 */
function uppsite_cache_fix_w3_total_cache($userAgents, $add = true) {
    if (class_exists('W3_Plugin_TotalCacheAdmin') &&
        (!isset($_REQUEST['page']) || stristr($_REQUEST['page'], "w3tc_") === false)) {
        // Make changes only if the user isn't modifying W3 Total Cache settings
        $w3_config = & w3_instance('W3_Config');
        $w3_total_cache_plugins = array('PgCache', 'Minify', 'Cdn');
        $save = array();
        foreach ($w3_total_cache_plugins as $w3tc_plugin) {
            // Search for the Rejected UAs for each plugin
            $key = strtolower($w3tc_plugin) . '.reject.ua';
            $rejectArr = $w3_config->get_array($key);
            $shouldUpdate = false;
            foreach ($userAgents as $ua) {
                if ($add) {
                    // Add to list
                    if (!in_array($ua, $rejectArr)) {
                        array_push($rejectArr, $ua);
                        $shouldUpdate = true;
                    }
                } else {
                    // Remove from list
                    $uakey = array_search($ua, $rejectArr);
                    if ($uakey !== false) {
                        unset($rejectArr[$uakey]);
                        $shouldUpdate = true;
                    }
                }
            }
            if ($shouldUpdate) {
                $w3_config->set($key, $rejectArr);
                // Schedule saving for each plugin
                $save[] = $w3tc_plugin;
            }
        }
        if (count($save) > 0) {
            $w3_config->save(false);
            foreach ($save as $plugin) {
                $w3tc_admin_instance = & w3_instance('W3_Plugin_' . $plugin . 'Admin');
                if (!is_null($w3tc_admin_instance)) {
                    if (method_exists($w3tc_admin_instance, 'write_rules_core')) {
                        $w3tc_admin_instance->write_rules_core();
                    }
                    if (method_exists($w3tc_admin_instance, 'write_rules_cache')) {
                        $w3tc_admin_instance->write_rules_cache();
                    }
                }
            }
        }
    }
}

/**
 * @return bool Whether to use a cache bypass techniques where cache systems doesn't support User-Agent groups (currently -
 *              every site that isn't WP VIP)
 */
function uppsite_should_bypass_cache() {
    return !uppsite_is_wpcom_vip();
}

/**
 * Include the functions.php file of the original theme, to run extra code from it.
 *
 * @note This must run before the plugin overrides the theme name!
 */
function uppsite_include_original_functions() {
    global $msap;
    $templateDir = $msap->original_values['template_directory'];
    include_once( $templateDir . "/functions.php" );

    // Remove all actions we know that interrupt the behaviour
    remove_all_filters('after_setup_theme');
    remove_all_filters('widgets_init');
    remove_all_filters('get_the_excerpt');
    remove_all_filters('excerpt_more');
    remove_all_filters('excerpt_length');
}

if (uppsite_is_wpcom_vip()):
    // Fixes for VIP sites
    function uppsite_vip_load_original_functions() {
        add_action( 'setup_theme', 'uppsite_include_original_functions', 1 );
    }
    add_action('uppsite_is_running', 'uppsite_vip_load_original_functions', 1); // Run before any other action is running.
else:
    // Fixes for various plugins, only in standalone env.

    // SEO Plugins
    function mysiteapp_fix_seo_plugins() {
        global $msap;
        if (!$msap->is_mobile && !$msap->is_app) { return; }
        // All in One SEO Plugin
        global $aioseop_options;
        if (is_array($aioseop_options)) {
            $curPage = trim($_SERVER['REQUEST_URI'],'/');
            if (!isset($aioseop_options['aiosp_ex_pages'])) {
                $aioseop_options['aiosp_ex_pages'] = $curPage;
            } else {
                $aioseop_options['aiosp_ex_pages'] .= ",".$curPage;
            }
        }
    }
    add_action('init', 'mysiteapp_fix_seo_plugins');

    // Cache plugins
    function uppsite_fix_cache_plugins() {
        // Build list of rejected UAs
        $userAgents = array(MYSITEAPP_AGENT);
        if (mysiteapp_should_show_webapp() || mysiteapp_should_show_landing()) {
            $userAgents = array_merge($userAgents, MySiteAppPlugin::$_mobile_ua);
        }
        // WP Super Cache
        uppsite_cache_fix_wp_super_cache($userAgents);

        // W3 Total Cache
        uppsite_cache_fix_w3_total_cache($userAgents);
    }
    add_action('admin_init','uppsite_fix_cache_plugins',10);

endif; // if (uppsite_is_wpcom_vip()):

/**
 * Disables interruping plugins in all envs.
 */
function uppsite_fix_interrupting_plugins() {
    // Disable Lazy Load plugin
    // The problem is that the 1x1 trans pixel is the "src" attr of all the pictures, thus causing us to get
    // it instead of the real picture, so we will disable it.
    $falseFunc = create_function('$state', 'return false;');
    add_action('lazyload_is_enabled', $falseFunc);

    // Disable jetpack's mobile theme
    add_action('jetpack_check_mobile', $falseFunc);

    // WordPress SEO has the option to "remove ugly permalinks". Yuck.
    function uppsite_fix_wp_seo($optionsArr) {
        $pos = array_search('wpseo_permalinks', $optionsArr);
        if ($pos !== false) {
            unset($optionsArr[$pos]);
        }
        return $optionsArr;
    }
    add_action('wpseo_options', 'uppsite_fix_wp_seo');
}

add_action('uppsite_is_running', 'uppsite_fix_interrupting_plugins', 15);