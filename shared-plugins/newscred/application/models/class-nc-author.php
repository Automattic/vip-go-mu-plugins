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
 * NC_Author model Class
 */

class NC_Author {

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


    /**
     *  Constructs the controller and assigns protected variables to be
     * @param array $params
     */
    public function __construct ( array $params = array() ) {

    }

    /**
     * add post author from newscred API
     * @static
     * @param $post_id
     * @return mixed
     */
    public static function nc_add_post_author ( $post_id ) {

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;


        // OK, we're authenticated: we need to find and save the data
        if ( !isset($_POST['nc_metabox_check_auth']) || !wp_verify_nonce($_POST['nc_metabox_check_auth'],'nc_metabox_nonce') )
            return;

        if ( !wp_is_post_revision( $post_id ) && $post_id && isset( $_POST[ 'nc-add-post' ] ) && current_user_can( 'edit_posts' ) ) {

            remove_action( 'save_post', array( &$this, 'nc_add_post_author' ) );

            // save author
            if ( isset( $_POST[ 'nc-post-author' ] ) && !empty( $_POST[ 'nc-post-author' ] ) ) {
                $author = sanitize_text_field( $_POST[ 'nc-post-author' ] );
                add_post_meta($post_id, '_nc_post_author', $author);
            }

            // save category for adhoc article search
            if ( isset( $_POST[ 'nc-cat-list' ] ) && get_option('nc_article_categories') ) {

                $category = $_POST[ 'nc-cat-list' ];

                if ( $category ) {

                    $category_list = $category;

                    $category_array = array();

                    // add existing category

                    $exist_cat_list = wp_get_object_terms( $post_id, 'category' );

                    if ( $exist_cat_list ) {
                        foreach ( $exist_cat_list as $cat ) {
                            $category_array[ ] = (int)$cat->term_id;
                        }
                        if ( count( $category_array ) == 1 && $category_array[ 0 ] == 1 ) {
                            unset( $category_array );
                        }
                    }


                    foreach ( $category_list as $cat ) {

                        $cat_id = get_cat_ID( $cat );

                        if ( $cat_id == 0 && !is_int( $cat ) ) {
                            $slug = strtolower( $cat );
                            $new_cat = array( 'cat_name' => $cat, 'category_nicename' => $slug );

                            $my_cat_id = wp_insert_category( $new_cat );

                            $category_array[ ] = (int)$my_cat_id;
                        }

                        $category_array[ ] = (int)$cat_id;

                    }
                    if ( $category_array )
                        wp_set_object_terms( $post_id, $category_array, 'category' );


                }
            }

            // re-hook this function
            add_action( 'save_post', array( &$this, 'nc_add_post_author' ) );


        }


    }
}