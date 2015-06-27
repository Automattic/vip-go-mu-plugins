<?php
/*

Plugin Name: NewsCred Content Feeds Plugin
Plugin URI: http://www.newscred.com
Description: Publish fully licensed, full text articles and images from 2,500+ of the world’s best news sources!.
Version: 1.0.3
Author: Md Imranur Rahman (NewsCred Inc) <imranur@newscred.com>
Author URI: http://www.newscred.com

*/

/**
 * @package nc-plugin
 * @author  Md Imranur Rahman <imranur@newscred.com>
 * @version 1.0.3
 *
 *
 *  Copyright 2012 NewsCred, Inc.  (email : sales@newscred.com)
 *
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */


/* Enable to display error messages on screen (not recommended for production) */


// ===============
// = Plugin Path =
// ===============
define( 'NC_PATH', dirname( __FILE__ ) );


// ===============
// = nc access key =
// ===============

define( 'NC_DOMAIN', 'http://api.newscred.com' );


// ===============
// = nc access key =
// ===============

define( 'NC_ACCESS_KEY', get_option( "nc_plugin_access_key" ) );

// ===============
// = Plugin Name =
// ===============
define( 'NC_PLUGIN_NAME', 'nc-plugin' );

// ===================
// = Plugin Basename =
// ===================
define( 'NC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ==================
// = Plugin Version =
// ==================
define( 'NC_VERSION', '2.0.0' );

// ==============
// = Plugin Url =
// ==============
define( 'NC_URL', plugins_url( '', __FILE__ ) );


// ================
// = build Path =
// ================
define( 'NC_BUILD_URL', NC_URL . '/static/build' );
// ================
// = build Path =
// ================
define( 'NC_BUILD_PATH', NC_PATH . '/static/build' );


// ================
// = includes Path =
// ================
define( 'NC_INCLUDE_PATH', NC_PATH . '/includes' );

// ================
// = CSS Path =
// ================
define( 'NC_CSS_URL', NC_URL . '/static/css' );

// ================
// = JS Path =
// ================
define( 'NC_JS_URL', NC_URL . '/static/js' );

// ===============
// = Js Dir =
// ===============
define( 'NC_JS_DIR', NC_PATH . '/static/js' );


// ================
// = Images Path =
// ================
define( 'NC_IMAGES_URL', NC_URL . '/static/images' );

// ===============
// = Controller Path =
// ===============
define( 'NC_CONTROLLER_PATH', NC_PATH . '/application/controllers' );

// ===============
// = Template Path =
// ===============
define( 'NC_VIEW_PATH', NC_PATH . '/application/views' );

// ===============
// = Model Path =
// ===============
define( 'NC_MODEL_PATH', NC_PATH . '/application/models' );


// =====================
// = Page URL =
// =====================
define( 'NC_SETTINGS_URL', admin_url( "options-general.php?page=nc-main-settings-page" ) );

define( 'NC_PLUGIN_ACTIVE_URL', admin_url( "?active_nc_plugin=true" ) );

define( 'NC_MYFEEDS_URL', admin_url( "admin.php?page=nc-myfeeds-settings-page" ) );


// ==========================================
// = The autoload library class function =
// ==========================================
function nc_autoload ( $class_name ) {

    $class_name = strtr( strtolower( $class_name ), '_', '-' );
    $paths = array( NC_INCLUDE_PATH, NC_MODEL_PATH, );
    // Search each path for the class.
    foreach ( $paths as $path ) {
        if ( file_exists( "$path/class-$class_name.php" ) )
            require_once( "$path/class-$class_name.php" );

    }
}

spl_autoload_register( 'nc_autoload' );

/**
 *  enable feature image
 */
add_theme_support( 'post-thumbnails' );

/**
 *  include the  newscred api wrapper
 */

require_once( NC_INCLUDE_PATH . "/newscred.php" );

require_once( NC_CONTROLLER_PATH . "/class-nc-settings.php");

/**
 * pre loaded objects
 * */

global $nc_utility, $nc_controller, $nc_cron;


$nc_utility     = NC_Utility::get_instance();
$nc_cron        = NC_Cron::get_instance();
$nc_controller  = NC_Controller::get_instance();


/**
 * pre loaded model objects
 * */

global $nc_source, $nc_topic, $nc_article, $nc_image, $nc_author, $nc_settings_api;

$nc_author      = NC_Author::get_instance();
$nc_source      = NC_Source::get_instance();
$nc_topic       = NC_Topic::get_instance();
$nc_article     = NC_Article::get_instance();
$nc_image       = NC_Image::get_instance();
$nc_settings_api   = NC_Settings_API::get_instance();