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
 *  NC_Metabox Class
 *  this controller help to genarate and controle the
 *  metabox module
 *  Feature list of meta box :
 *  - user can search articles, images from the wp post meta box
 *  - user can insert image in the post and can set image as feature image
 *
 */

class NC_Metabox extends NC_Controller {


    private $index;

    /**
     *  display the metabox in the admin post edit sidebar
     */

    public function init () {
        $this->_template->assign( 'access_key', get_option( "nc_plugin_access_key" ) );
        $this->_template->display( 'metabox/index.php' );
    }

    /**
     * search articles/images/myFeeds
     * from newscred api
     * by meta box search query
     */
    public function search () {

        global $nc_article, $nc_image, $nc_controller;

        $search_type = ( isset($_POST['type']) ) ? trim(strip_tags($_POST['type'])) : null;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_search_nonce")){
            echo "null";
            exit;
        }

        // article search
        if ( $search_type == "article" ) {
            $articles = $nc_article->get_metabox_articles();
            echo json_encode( $articles );
            exit;
        }

        // image search
        if ( $search_type == "image" ) {

            $images = $nc_image->get_metabox_images();

            echo json_encode( $images );
            exit;

        }
        // article search for myFeeds
        if ( $search_type == "myFeeds" ) {

            $myfeeds = $this->myfeeds();

            echo json_encode( $myfeeds );
            exit;
        }
        exit;
    }


    /**
     * @return array
     */
    function myfeeds () {

        $myfeed_id = intval($_POST[ 'myfeed_id' ]);
        if ( !$myfeed_id ) {
            echo "null";
            exit;
        }


        $pagesize = 10;

        $page = absint($_POST[ 'page' ]);
        $offset = ( $page - 1 ) * $pagesize ;

        $query = ( isset($_POST['query']) ) ? strip_tags( $_POST['query'] ) : "";



        $sources   = ( isset($_POST['sources']) ) ? trim(strip_tags($_POST['sources'])) : null;
        $source_str = "";
        if ( $sources )
            $source_str = "&sources=" . implode( " ", $sources );


        $topics   = ( isset($_POST['topics']) ) ? trim(strip_tags($_POST['topics'])) : null;
        $topics_str = "";
        if ( $topics )
            $topics_str = "&topics=" . implode( " ", $topics );

        $sort_str = "";
        $sort = ( isset($_POST['sort']) ) ? trim(strip_tags($_POST['sort'])) : null;

        if ( $sort  && ( $sort == "date" || $sort == "relevance" ) ) {
            $sort_str = '&sort=' . $sort;
        }


        $result = (object)get_post_meta( $myfeed_id, '_ncmyfeed_attr', true );

        $apicall = $result->apicall;
        if ( $apicall ) {
            if ( $query )
                $apicall .= "&query=" . $query . $source_str . $topics_str;

            $apicall .= $source_str . $topics_str . $sort_str . "&pagesize=" . $pagesize . "&offset=" . $offset;



            $myfeed_results = array();

            try {
                $myfeed_results = NC_Plugin_Article::search_by_url( NC_ACCESS_KEY, $apicall );
                if ( $myfeed_results )
                    return $myfeed_results;

            } catch ( NC_Plugin_Exception $e ) {
                return $myfeed_results;

            }

        }


        return;

    }

    /**
     * add feature image from
     * search image list
     */

    public function add_feature_image () {

        global $nc_utility, $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_add_feature_image_nonce")){
            exit;
        }


        $post_id = absint($_POST[ 'p_id' ]);
        $image_thumb_url = esc_url( sanitize_text_field($_POST[ 'url' ]) );

        //Display the image in the browser

        // load the image

        try {
            $attach_image_id = $nc_utility->nc_upload_image( $image_thumb_url, $post_id, 'image_thumbnail' );
        } catch ( Exception $e ) {
            $attach_image_id = 0;
        }

        if ($attach_image_id){

            // set image as the post thumbnail
            set_post_thumbnail( $post_id, $attach_image_id );

            $attach_data = array();
            $attach_data[ 'ID' ]            = $attach_image_id;
            $attach_data[ 'post_title' ]    = sanitize_text_field($_POST[ 'caption' ]);
            $attach_data[ 'post_excerpt' ]  = sanitize_text_field($_POST[ 'caption' ]);

            // Update the post into the database
            wp_update_post( $attach_data );


            $thumb = wp_get_attachment_image_src( $attach_image_id );
            $image_url = $thumb[ '0' ];

            $this->_template->assign( 'post_id', $post_id );
            $this->_template->assign( 'image_url', $image_url );
            $this->_template->display( 'metabox/addimage.php' );

        }
        exit;
    }

    /**
     * remove feature image
     */
    public function remove_feature_image () {

        global $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_remove_feature_image_nonce")){
            exit;
        }

        $p_id = intval($_POST[ 'p_id' ]);
        delete_post_thumbnail( $p_id );
        $image_url = admin_url( 'media-upload.php?post_id=' . $p_id );
        $this->_template->assign( 'image_url', $image_url );
        $this->_template->display( 'metabox/remove-image.php' );
        exit;
    }


    /**
     * NOTE: not using since v 1.0.2
     *  get suggested topics and
     * source from keyword
     *
     * get_suggested_topics_source
     */
    public function get_suggested_topics_source () {


        global $nc_controller;
        $result_array = array();


        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_get_source_topic_nonce")){
            echo json_encode( $result_array );
            exit;
        }

        $query =  sanitize_text_field( $_GET[ 'query' ] );

        $source_fields = array( 'source.guid', 'source.name', );

        $options = array(
            "fields"        => $source_fields,
            "autosuggest"   => true,
            "pagesize"      => 5,
            'fulltext'      => true
        );
        // get the sources

        $sources = NC_Plugin_Source::search( NC_ACCESS_KEY, $query, $options );


        if ( $sources ) {
            foreach ( $sources as $source ) {
                $result_array[ ] = array( "guid" => (string)$source->guid, "name" => (string)$source->name, "category" => "Sources" );
            }
        }

        $topics_fields = array( 'topic.guid', 'topic.name', );

        $topics_options = array( 'fields' => $topics_fields, "autosuggest" => true, "pagesize" => 5 );

        $topics = NC_Plugin_Topic::search( NC_ACCESS_KEY, $query, $topics_options );

        if ( $topics ) {
            foreach ( $topics as $topic )
                $result_array[ ] = array( "guid" => (string)$topic->guid, "name" => (string)$topic->name, "category" => "Topics" );
        }

        echo json_encode( $result_array );

        exit;
    }
}