<?php
/**
 * @package nc-plugin
 * @author  Md Imranur Rahman <imranur@newscred.com>
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

/**
 *
 * NC_Settings Class
 */

class NC_Settings extends NC_Controller {

    public function init () {
        $this->_template->display( 'settings/index.php' );
    }

}


class NC_Settings_API {
    /**
     * _instance class variable
     *
     * Class instance
     *
     * @var null | object
     **/
    private static $_instance = NULL;

    /**
     * get_instance function
     *
     * Return singleton instance
     *
     * @return object
     **/
    static function get_instance () {
        if ( self::$_instance === NULL ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init(){

        /**
         *  Access Key Setting section
         **/

        add_settings_section(
            'nc_plugin_access_key_section',
            'Access Key',
            '',
            'nc_plugin_settings'
        );

        // Access Key Text  field
        add_settings_field(
            'nc_plugin_access_key',
            'Access Key',
            function (){
                echo(
                    '<input type="text" name="nc_plugin_access_key" class="regular-text" id="nc_plugin_access_key" value="'. esc_attr( get_option( 'nc_plugin_access_key' ) ) .'" />' .
                        '<p>Enter your NewsCred access key.</p>');
            },
            'nc_plugin_settings',
            'nc_plugin_access_key_section'
        );

        // Register this field with our settings group.
        register_setting( 'nc_plugin_settings_group', 'nc_plugin_access_key', array($this, 'validate_access_key' ) );



        /**
         *  Article Search Setting section
         **/

        add_settings_section(
            'nc_plugin_article_section',
            'Article Search',
            '',
            'nc_plugin_settings'
        );

        // Article Custom Post Type
        add_settings_field(
            'nc_article_custom_post_type',
            'Custom Post Type',
            function (){
                echo(
                    '<input type="text" name="nc_article_custom_post_type"
                    class="regular-text" id="nc_article_custom_post_type"
                    value="'. esc_attr( get_option( 'nc_article_custom_post_type' ) ) .'" />' .
                        '<p>If your WordPress site uses custom post types, list your post type slug(s) separated by commas (,) </p>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article Custom Post Type
        register_setting( 'nc_plugin_settings_group', 'nc_article_custom_post_type' );

        // Article Full Text
        add_settings_field(
            'nc_article_fulltext',
            'Fulltext',
            function (){
                echo( '<input type="checkbox" id="nc_article_fulltext" name="nc_article_fulltext" value="1"' . checked( 1, get_option('nc_article_fulltext'), false ) . '/>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article Custom Post Type
        register_setting( 'nc_plugin_settings_group', 'nc_article_fulltext', 'intval' );


        // Article has images
        add_settings_field(
            'nc_article_has_images',
            'Has Images',
            function (){
                echo( '<input type="checkbox" id="nc_article_has_images" name="nc_article_has_images" value="1"' . checked( 1, get_option('nc_article_has_images'), false ) . '/>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article Has Images
        register_setting( 'nc_plugin_settings_group', 'nc_article_has_images', 'intval' );

        // Article publish time
        add_settings_field(
            'nc_article_publish_time',
            'Publish Time',
            function (){
                echo( '<input type="checkbox" id="nc_article_publish_time" name="nc_article_publish_time" value="1"' . checked( 1, get_option('nc_article_publish_time'), false ) . '/>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article publish time
        register_setting( 'nc_plugin_settings_group', 'nc_article_publish_time', 'intval' );

        // Article Tags
        add_settings_field(
            'nc_article_tags',
            'Keep Tags',
            function (){
                echo( '<input type="checkbox" id="nc_article_tags" name="nc_article_tags" value="1"' . checked( 1, get_option('nc_article_tags'), false ) . '/>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article tags
        register_setting( 'nc_plugin_settings_group', 'nc_article_tags', 'intval' );

        // Article  Category
        add_settings_field(
            'nc_article_categories',
            'Keep Categories',
            function (){
                echo( '<input type="checkbox" id="nc_article_categories" name="nc_article_categories" value="1"' . checked( 1, get_option('nc_article_categories'), false ) . '/>');
            },
            'nc_plugin_settings',
            'nc_plugin_article_section'
        );

        // Register Article publish time
        register_setting( 'nc_plugin_settings_group', 'nc_article_categories', 'intval');



        /**
         *  Image Search Setting section
         **/

        add_settings_section(
            'nc_plugin_image_section',
            'Image Search',
            '',
            'nc_plugin_settings'
        );

        // Image search minimum width
        add_settings_field(
            'nc_image_minwidth',
            'Minimum width image search',
            function (){
                echo(
                    '<input type="text" name="nc_image_minwidth" class="small-text" id="nc_image_minwidth" value="'. esc_attr( get_option( 'nc_image_minwidth' ) ) .'" />' );
            },
            'nc_plugin_settings',
            'nc_plugin_image_section'
        );

        // Register this field with our settings group.
        register_setting( 'nc_plugin_settings_group', 'nc_image_minwidth', array($this, 'nc_settings_int_validation' ) );


        // Image search minimum width
        add_settings_field(
            'nc_image_minheight',
            'Minimum height image search',
            function (){
                echo(
                    '<input type="text" name="nc_image_minheight" class="small-text" id="nc_image_minheight" value="'. esc_attr( get_option( 'nc_image_minheight' ) ).'" />' );
            },
            'nc_plugin_settings',
            'nc_plugin_image_section'
        );

        // Register this field with our settings group.
        register_setting( 'nc_plugin_settings_group', 'nc_image_minheight', array($this, 'nc_settings_int_validation' ) );


        // Insert Image width
        add_settings_field(
            'nc_image_post_width',
            'Image width in post',
            function (){
                echo(
                    '<input type="text" name="nc_image_post_width" class="small-text" id="nc_image_post_width" value="'. esc_attr( get_option( 'nc_image_post_width' ) ).'" />' );
            },
            'nc_plugin_settings',
            'nc_plugin_image_section'
        );

        // Register this field with our settings group.
        register_setting( 'nc_plugin_settings_group', 'nc_image_post_width', array($this, 'nc_settings_int_validation' ) );


        // Image search minimum width
        add_settings_field(
            'nc_image_post_height',
            'Image height in post',
            function (){
                echo(
                    '<input type="text" name="nc_image_post_height" class="small-text" id="nc_image_post_height" value="'. esc_attr( get_option( 'nc_image_post_height' ) ).'" />' );
            },
            'nc_plugin_settings',
            'nc_plugin_image_section'
        );

        // Register this field with our settings group.
        register_setting( 'nc_plugin_settings_group', 'nc_image_post_height', array($this, 'nc_settings_int_validation' ) );

    }

    public function nc_settings_int_validation($input){
        return absint($input);
    }


    public function validate_access_key($value){

        if(empty($value)){
            add_settings_error(
                'nc_plugin_access_key',
                'nc_plugin_access_key',
                'Access key can\'t be empty.' ,
                'error'
            );
            return;
        }

        if(!$this->check_accesskey($value)){
            add_settings_error(
                'nc_plugin_access_key',
                'nc_plugin_access_key',
                'Invalid NewsCred access key.',
                'error'
            );
            return;
        }else
            return $value;

    }

    /***
     * check the newscred api access key
     * @static
     * @param $accesskey
     * @return bool
     */
    public static function check_accesskey ( $accesskey ) {

        $url = sprintf( "%s/api/user?access_key=%s", NC_DOMAIN, $accesskey );

        try {
            $response = wp_remote_get($url, array( 'timeout' => 10 ));

            if( wp_remote_retrieve_response_code($response) == 200)
                return true;
            else
                return false;

        } catch ( Exception $ex ) {
            return false;
        } // end try/catch

    }

}