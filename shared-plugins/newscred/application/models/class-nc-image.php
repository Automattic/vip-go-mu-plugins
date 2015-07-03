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
 * NC_Image Class
 */

class NC_Image {

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
     * get_metabox_images
     * search the images for metabox
     * @return array
     */
    public static function get_metabox_images () {


        $pagesize = 36;

        $page = absint($_POST[ 'page' ]);
        $offset = ( $page - 1 ) * $pagesize;

        $query = ( isset($_POST['query']) ) ? strip_tags( $_POST['query'] ) : "";

        $image_width = get_option('nc_image_minwidth');
        $image_height = get_option('nc_image_minheight');

        $fields = array(
            'image.guid',
            'image.caption',
            'image.description',
            'image.height',
            'image.width',
            'image.published_at',
            'image.source.name',
            'image.urls.large',
            'image.attribution_text'
        );

        $options = array(
            'fields'    => $fields,
            'pagesize'  => $pagesize,
            'offset'    => $offset,
            'licensed'  => true
        );

        $sort = ( isset($_POST['sort']) ) ? trim(strip_tags($_POST['sort'])) : null;

        if ( $sort  && ( $sort == "date" || $sort == "relevance" ) ) {
            $options[ 'sort' ] = sanitize_text_field( $sort );
        }

        $sources = ( isset($_POST['sources']) ) ? $_POST['sources'] : null;

        if ( $sources ) {
            $options[ 'sources' ] =  $sources;
        }

        $topics = ( isset($_POST['topics']) ) ? $_POST['topics'] : null;

        if ( $topics ) {
            $options[ 'topics' ] =  $topics ;
        }

        if ( $image_width )
            $options[ 'minwidth' ] = absint( $image_width );

        if ( $image_height )
            $options[ 'minheight' ] = absint( $image_height );


        $images = array();

        try {

            $images = NC_Plugin_Image::search( NC_ACCESS_KEY, $query, $options );

        } catch ( NC_Plugin_Exception $e ) {
            return $images;

        }

        return $images;
    }


}