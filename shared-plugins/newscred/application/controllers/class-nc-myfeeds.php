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
 *  NC_Myfeeds
 *  controller class
 *  its handel the myFeeds CRUD opration
 *
 */

class NC_Myfeeds extends NC_Controller {

    private $message = array();
    private $data;

    /**
     *  init :
     *  its  shows the myfeeds existing list
     *  and also add new feed here
     */
    public function init () {

        // check the capability
        if ( ! current_user_can( 'edit_posts' ) )
            return;

        // check nonce

        $submit_value = "Add";

        if ( isset( $_POST[ 'submit' ] ) && $_POST[ 'submit' ] == "Add MyFeeds" && wp_verify_nonce( $_POST['myfeeds_nonce_add_submit'], "myfeeds_nonce_add" )) {
            $this->data = $_POST;
            $this->add_myfeeds();
        }

        if ( isset( $_POST[ 'submit' ] ) && $_POST[ 'submit' ] == "Update MyFeeds" && wp_verify_nonce( $_POST['myfeeds_nonce_update_submit'], "myfeeds_nonce_update" )) {
            $this->data = $_POST;
            $this->update_myfeeds();
        }

        if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == "delete" && check_admin_referer('myfeed_delete_nonce')) {
            $this->delete_myfeeds();
        }


        if ( isset( $_POST[ 'action' ] ) && wp_verify_nonce( $_POST['myfeeds_list_nonce_submit'], "myfeeds_list_nonce" ) ) {
            $this->bulk_delete_myfeeds();
        }

        if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == "edit" && check_admin_referer("myfeed_edit_nonce" )) {

            $result = get_post_meta( absint( $_GET[ 'id' ] ), '_ncmyfeed_attr', true );

            if ( $result ) {
                $result[ 'id' ] = absint($_GET[ 'id' ]);
                $result[ 'autopublish' ] = absint( get_post_meta(absint($_GET[ 'id' ]), '_ncmyfeed_autopublish', true));
                $this->data = $result;
            }
            $submit_value = "Update";
        }

        $myfeed_list = $this->myfeed_list();

        $this->_template->assign( "submit_value", $submit_value );
        $this->_template->assign( "message", $this->message );
        $this->_template->assign( "data", $this->data );
        $this->_template->assign( "myfeed_list", $myfeed_list );


        $this->_template->assign( 'access_key', get_option( "nc_plugin_access_key" ) );

        $this->_template->assign( 'categories', get_categories( "hide_empty=0" ) );
        $this->_template->display( 'myfeeds/index.php' );


    }

    /**
     * createApiCall
     * create a NewsCred API for myfeeds
     */
    function create_api_call () {

        global $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_create_apicall_nonce")){
            exit;
        }


        $pagesize = 10;
        $offset = 0;

        $fields = array(
            'article.guid',
            'article.description',
            'article.title',
            'article.published_at',
            'article.source.name',
            'article.tracking_pixel',
            'article.topic.name',
            'article.categories.dashed_name',
            'article.categories.name',
            'article.author.name',

            'article.image.guid',
            'article.image.caption',
            'article.image.description',
            'article.image.height',
            'article.image.width',
            'article.image.published_at',
            'article.image.source.name',
            'article.image.urls.large'

        );

        $options = array( 'fields' => $fields, 'get_topics' => true );

        if ( !empty( $_POST[ 'categories' ] ) )
            $options[ 'categories' ] = $_POST[ 'categories' ];

        if ( !empty( $_POST[ 'source_guids' ] ) )
            $options[ 'sources' ] = sanitize_text_field( $_POST[ 'source_guids' ] );


        if ( !empty( $_POST[ 'source_filter_name' ] ) ) {
            $options[ 'source_filter_name' ] = sanitize_text_field( $_POST[ 'source_filter_name' ] );
            $options[ 'source_filter_mode' ] = "whitelist";
        }

        if ( !empty( $_POST[ 'topic_guids' ] ) )
            $options[ 'topics' ] = sanitize_text_field( $_POST[ 'topic_guids' ] );

        if ( !empty( $_POST[ 'topic_filter_name' ] ) ) {
            $options[ 'topic_filter_name' ] = sanitize_text_field( $_POST[ 'topic_filter_name' ] );
            $options[ 'topic_filter_mode' ] = "whitelist";
        }

        if ( isset( $_POST[ 'has_images' ] ) )
            $options[ 'has_images' ] = "true";

        if ( isset( $_POST[ 'fulltext' ] ) )
            $options[ 'fulltext' ] = "true";


        echo NC_Plugin_Article::api_call( NC_ACCESS_KEY, $options );

        exit;
    }

    /**
     * bulk_delete_myfeeds
     *
     * used for myFeeds bulk delete
     *
     * @return string|void
     */
    function bulk_delete_myfeeds () {

        $deleted_myfeeds = $_POST[ 'delete_feeds' ];

        if($deleted_myfeeds){
            foreach($deleted_myfeeds as $myfeed){
                $result = wp_delete_post( absint($myfeed), true );
                if ( $result ) {
                    delete_post_meta( intval($myfeed), '_ncmyfeed_attr' );
                }
                else {
                    $this->message[ ] =array( "msg"=> "Some problems appeared. Please try again later.", "type"=> "error");
                    return ;
                }
            }

            $this->message[ ] =array( "msg"=> "MyFeeds deleted successfully.", "type" => "success");
            $this->data = "";
        }
    }

    /**
     * delete_myfeeds
     *
     * used for myFeeds delete
     *
     */
    function delete_myfeeds () {


        $result = wp_delete_post( absint($_GET[ 'id' ]) , true );

        if ( $result ) {
            delete_post_meta( absint($_GET[ 'id' ]), '_ncmyfeed_attr' );
            $this->message[ ] = array( "msg"=>"MyFeeds deleted successfully.", "type" => "success");
            $this->data = "";
        }
        else {
            $this->message[ ] =array( "msg"=> "Some problems appeared. Please try again later.", "type"=> "error");
        }

    }

    /**
     * retrive all myFeeds List
     * myfeed_list
     * @return mixed
     */
    function myfeed_list () {

        global $wpdb;

        $per_page = 20;

        /**
         * paginations
         */

        $pagenum = isset( $_GET[ 'pagenum' ] ) ? absint( $_GET[ 'pagenum' ] ) : 0;

        if ( empty( $pagenum ) )
            $pagenum = 1;


        $total_myfeed = wp_count_posts("nc_myfeeds")->draft;


        $num_pages = ceil( $total_myfeed / $per_page );

        $app_pagin = paginate_links( array(
                                            'base'      => add_query_arg( 'pagenum','%#%' ),
                                            'format'    => '', 'prev_text' => __( '&laquo;' ),
                                            'next_text' => __( '&raquo;' ),
                                            'total'     => $num_pages,
                                            'current'   => $pagenum
                                           ) );


        $this->_template->assign( "app_pagin", $app_pagin );
        $this->_template->assign( "pagenum", $pagenum );
        $this->_template->assign( "per_page", $per_page );
        $this->_template->assign( "num_rows", $total_myfeed );


        $args = array(
            'post_type'         => 'nc_myfeeds',
            'post_status'       => 'draft',
            'posts_per_page'    => $per_page,
            'paged'             => $pagenum,
        );
        $query = new WP_Query( $args );

        $posts = $query->posts;

        $myfeeds_result = array();
        if($posts){
            foreach( $posts as $myfeed ){
                    $unserialize_data =  get_post_meta($myfeed->ID, '_ncmyfeed_attr', true);
                    $autopublish = get_post_meta($myfeed->ID, '_ncmyfeed_autopublish', true);
                    $myfeeds_result[ ] = (object)array(
                        'id'                => $myfeed->ID,
                        'name'              => $unserialize_data[ 'name' ],
                        'autopublish'       => absint($autopublish),
                        'publish_interval'  => $unserialize_data[ 'publish_interval' ],
                        'publish_time'      => $unserialize_data[ 'publish_time' ]
                    );
            }
            return $myfeeds_result;
        }else
            return;

    }


    /**
     * update myFeeds
     * update_myfeeds
     */
    function update_myfeeds () {

        global $nc_utility;

        if ( empty( $_POST[ 'name' ] ) )
            $this->message[ ] = array("msg" => "Please enter MyFeed name.", "type" => "error");

        if ( empty( $_POST[ 'apicall' ] ) )
            $this->message[ ] = array( "msg"=> "Please enter api call.", "type"=> "error");


        if ( !$this->message && isset($_POST[ 'apicall' ]) )
           $apicall = $this->check_valid_nc_apicall( $_POST[ 'apicall' ] );


        if ( !$this->message ) {

            $id = absint( $_POST[ 'id' ]  );
            $name = sanitize_text_field( $_POST[ 'name' ] );
            $apicall = sanitize_text_field( $_POST[ 'apicall' ] );

            $autopublish = ( isset( $_POST[ 'autopublish' ] ) ? 1 : 0 );
            if ( $autopublish )
                $publish_status = intval($_POST[ 'publish_status' ]);
            else
                $publish_status = 0;

            update_post_meta($id, '_ncmyfeed_autopublish', $autopublish);


            $publish_interval = "";
            if ( isset( $_POST[ 'publish_interval' ] ) )
                $publish_interval = absint($_POST[ 'publish_interval' ]);


            $myfeed_category = "";
            if ( isset( $_POST[ 'myfeed_category' ] ) && $_POST[ 'myfeed_category' ] )
                $myfeed_category = serialize( $_POST[ 'myfeed_category' ] );

            $feed_tag = ( isset( $_POST[ 'feed_tag' ] ) ? 1 : 0 );
            $feature_image = ( isset( $_POST[ 'feature_image' ] ) ? 1 : 0 );

            $update_time = new DateTime();
            // add post meta
            $myfeed_meta = array();

            $myfeed_meta[ 'name' ]              = $name;
            $myfeed_meta[ 'apicall' ]           = $apicall;
            $myfeed_meta[ 'autopublish' ]       = $autopublish;
            $myfeed_meta[ 'publish_status' ]    = $publish_status;
            $myfeed_meta[ 'publish_interval' ]  = $publish_interval;
            $myfeed_meta[ 'myfeed_category' ]   = $myfeed_category;
            $myfeed_meta[ 'feed_tag' ]          = $feed_tag;
            $myfeed_meta[ 'feature_image' ]     = $feature_image;
            $myfeed_meta[ 'update_time' ]       = $update_time->format( 'Y-m-d H:i:s' );
            $myfeed_meta[ 'publish_time' ]      = sanitize_text_field($_POST[ 'publish_time' ]);

            $result = update_post_meta( $id, '_ncmyfeed_attr', $myfeed_meta );


            if ( $result ) {
                $this->message[ ] = array( "msg" => "MyFeeds update successfully.", "type" =>"success" );
                $this->data = "";

                if ( $autopublish ) {
                    wp_clear_scheduled_hook( "nc_hourly_plugin_hook" );
                    wp_clear_scheduled_hook( "nc_mins_plugin_hook" );
                }
            }
            else {
                $this->message[ ] =array( "msg"=> "Some problems appeared. Please try again later.", "type"=> "error");
            }

        }
    }

    /**
     * add myFeeds
     */
    function add_myfeeds () {

        if ( empty($_POST[ 'name' ]) )
            $this->message[ ] = array("msg" => "Please enter MyFeed name.", "type" => "error");

        if ( empty($_POST[ 'apicall' ]) )
            $this->message[ ] = array( "msg"=> "Please enter api call.", "type"=> "error");


        if ( !$this->message && isset($_POST[ 'apicall' ]) )
            $apicall = $this->check_valid_nc_apicall( $_POST[ 'apicall' ] );

        if ( !$this->message ) {

            $name = htmlspecialchars( trim( $_POST[ 'name' ] ) );



            $autopublish = ( isset( $_POST[ 'autopublish' ] ) ? 1 : 0 );

            if ( $autopublish )
                $publish_status = absint($_POST[ 'publish_status' ]);
            else
                $publish_status = 0;

            $publish_interval = "";
            if ( isset( $_POST[ 'publish_interval' ] ) )
                $publish_interval = absint($_POST[ 'publish_interval' ]);

            if ( !$autopublish )
                $publish_interval = 0;

            $myfeed_category = "";

            if ( isset( $_POST[ 'myfeed_category' ] ) && $_POST[ 'myfeed_category' ] != "" )
                $myfeed_category = serialize( $_POST[ 'myfeed_category' ] );

            $feed_tag = ( isset( $_POST[ 'feed_tag' ] ) ? 1 : 0 );
            $feature_image = ( isset( $_POST[ 'feature_image' ] ) ? 1 : 0 );

            // Create nc_myfeeds custom  post object
            $my_post = array( 'post_type' => 'nc_myfeeds');
            // Insert the post into the database
            $myfeed_id = wp_insert_post( $my_post );


            if ( $myfeed_id ) {

                // add autopublish meta
                add_post_meta( $myfeed_id, '_ncmyfeed_autopublish', $autopublish );

                $update_time = new DateTime();

                // add post meta
                $myfeed_meta = array();

                $myfeed_meta[ 'name' ]              = $name;
                $myfeed_meta[ 'apicall' ]           = $apicall;
                $myfeed_meta[ 'publish_status' ]    = $publish_status;
                $myfeed_meta[ 'publish_interval' ]  = $publish_interval;
                $myfeed_meta[ 'myfeed_category' ]   = $myfeed_category;
                $myfeed_meta[ 'feed_tag' ]          = $feed_tag;
                $myfeed_meta[ 'feature_image' ]     = $feature_image;
                $myfeed_meta[ 'update_time' ]       = $update_time->format( 'Y-m-d H:i:s' );
                $myfeed_meta[ 'publish_time' ]      = '0000-00-00 00:00:00';

                add_post_meta( $myfeed_id, '_ncmyfeed_attr', $myfeed_meta );


                $this->message[ ] = array( "msg" => "MyFeeds add successfully.", "type" =>"success" );
                $this->data = "";

                if ( $autopublish ) {
                    wp_clear_scheduled_hook( "nc_hourly_plugin_hook" );
                    wp_clear_scheduled_hook( "nc_mins_plugin_hook" );
                }
            }
            else {
                $this->message[ ] =array( "msg"=> "Some problems appeared. Please try again later.", "type"=> "error");
            }

        }
    }


    /**
     * get image sets for auto published post
     */
    public function get_image_sets () {



        global $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_get_image_set_nonce")){
            exit;
        }

        $post_id = absint($_POST[ 'post_id' ]);

        $image_set = get_post_meta( $post_id, "nc_image_set", true );

        if ( $image_set ) {
            echo json_encode( unserialize( $image_set ) );
        }

        exit;


    }

    /**
     * create wp category
     */
    public function create_wp_category () {

        global $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_add_category_nonce")){
            echo "";
            exit;
        }


        $cat = sanitize_text_field( $_POST[ 'cat' ] );

        $cat_id = get_cat_ID( $cat );

        if ( $cat_id == 0 ) {

            $slug = strtolower( $cat );
            $new_cat = array( 'cat_name' => $cat, 'category_nicename' => $slug );

            $my_cat_id = wp_insert_category( $new_cat );

            echo $my_cat_id;
        }
        else
            echo "";

        exit;
    }

    function check_valid_nc_apicall( $apicall ){

        global $nc_utility;

        $apicall = html_entity_decode( htmlspecialchars( trim( $apicall ) ) );

        $fields = array(
            'article.guid',
            'article.description',
            'article.title',
            'article.published_at',
            'article.source.name',
            'article.tracking_pixel',
            'article.topic.name',
            'article.categories.dashed_name',
            'article.categories.name',
            'article.author.name',

            'article.image.guid',
            'article.image.caption',
            'article.image.description',
            'article.image.height',
            'article.image.width',
            'article.image.published_at',
            'article.image.source.name',
            'article.image.urls.large'

        );

        $apicall_url_part = parse_url( $apicall );

        // check its a valid host or not
        if(isset($apicall_url_part['host']) && $apicall_url_part['host'] != "api.newscred.com"){
            $this->message[ ] =array( "msg" => "Please enter valid api call for articles.", "type"=>"error");
            return ;
        }

        // check its a valid end point or not
        if(isset($apicall_url_part['path']) && $apicall_url_part['path'] != "/articles"){
            $this->message[ ] = array("msg"=> "Please enter valid api call for articles.", "type"=>"error");
            return ;
        }

        parse_str( $apicall_url_part[ 'query' ], $parameter );
        if ( isset( $parameter[ 'fields' ] ) ) {
            if ( $parameter[ 'fields' ] != implode( " ", $fields ) ) {
                $parameter[ 'fields' ] = implode( " ", $fields );
                $new_query_url = http_build_query( $parameter );
                $apicall_url_part[ 'query' ] = $new_query_url;
                $apicall = $this->join_url( $apicall_url_part );
            }

        }
        else {
            $parameter[ 'fields' ] = implode( " ", $fields );
            $new_query_url = http_build_query( $parameter );
            $apicall_url_part[ 'query' ] = $new_query_url;
            $apicall = $this->join_url( $apicall_url_part );
        }

        $response = $nc_utility->get_url( esc_url( $apicall ) );

        if ( !$response ){
            $this->message[ ] =array( "msg" => "Please enter valid api call.", "type"=> "error");
            return;
        }

        return $apicall;

    }
    /**
     * @param $parts
     * @param bool $encode
     * @return string
     */
    function join_url ( $parts, $encode = TRUE ) {
        if ( $encode ) {
            if ( isset( $parts[ 'user' ] ) )
                $parts[ 'user' ] = rawurlencode( $parts[ 'user' ] );
            if ( isset( $parts[ 'pass' ] ) )
                $parts[ 'pass' ] = rawurlencode( $parts[ 'pass' ] );
            if ( isset( $parts[ 'host' ] ) && !preg_match( '!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts[ 'host' ] )
            )
                $parts[ 'host' ] = rawurlencode( $parts[ 'host' ] );
            if ( !empty( $parts[ 'path' ] ) )
                $parts[ 'path' ] = preg_replace( '!%2F!ui', '/', rawurlencode( $parts[ 'path' ] ) );
            if ( isset( $parts[ 'query' ] ) )
                $parts[ 'query' ] = rawurlencode( $parts[ 'query' ] );
            if ( isset( $parts[ 'fragment' ] ) )
                $parts[ 'fragment' ] = rawurlencode( $parts[ 'fragment' ] );
        }

        $url = '';
        if ( !empty( $parts[ 'scheme' ] ) )
            $url .= $parts[ 'scheme' ] . ':';
        if ( isset( $parts[ 'host' ] ) ) {
            $url .= '//';
            if ( isset( $parts[ 'user' ] ) ) {
                $url .= $parts[ 'user' ];
                if ( isset( $parts[ 'pass' ] ) )
                    $url .= ':' . $parts[ 'pass' ];
                $url .= '@';
            }
            if ( preg_match( '!^[\da-f]*:[\da-f.:]+$!ui', $parts[ 'host' ] ) )
                $url .= '[' . $parts[ 'host' ] . ']'; // IPv6
            else
                $url .= $parts[ 'host' ]; // IPv4 or name
            if ( isset( $parts[ 'port' ] ) )
                $url .= ':' . $parts[ 'port' ];
            if ( !empty( $parts[ 'path' ] ) && $parts[ 'path' ][ 0 ] != '/' )
                $url .= '/';
        }
        if ( !empty( $parts[ 'path' ] ) )
            $url .= $parts[ 'path' ];
        if ( isset( $parts[ 'query' ] ) )
            $url .= '?' . $parts[ 'query' ];
        if ( isset( $parts[ 'fragment' ] ) )
            $url .= '#' . $parts[ 'fragment' ];
        return urldecode( $url );
    }

    /**
     *  update the corn for any
     *  specific myFeeds
     * @param int $id
     */

    public function update_myfeed_cron ( $id = 0 ) {

        global $nc_cron, $nc_controller;

        // check nonce and capability
        if(!$nc_controller->check_nonce_capability("nc_myfeeds_update_corn_nonce"))
            exit;


        if ( !$id )
            $id = absint($_POST[ 'id' ]);

        $result = (object)get_post_meta( $id, '_ncmyfeed_attr', true );
        $result->id = $id;

        if ( $result ) {

            $current_time = date( "Y-m-d H:i:s", time() );
            $publish_time = $result->publish_time;

            $difference = $nc_cron->time_difference( $current_time, $publish_time );

            $from_date = date( 'Y-m-d H:i:s', strtotime( " -$difference minute" ) );

            $parse_url = parse_url( $result->apicall );

            parse_str( html_entity_decode( $parse_url[ 'query' ] ), $querys );

            $querys[ 'fields' ]     = "article.guid";
            $querys[ 'from_date' ]  = $from_date;

            $query_str = http_build_query( $querys );

            $url = $parse_url[ 'scheme' ] . "://" . $parse_url[ 'host' ] . $parse_url[ 'path' ] . "?" . $query_str;

            $nc_cron->insert_article_guid( $url, $result->id );
            wp_clear_scheduled_hook( "nc_mins_plugin_hook" );

            $result = (object)get_post_meta( $id, '_ncmyfeed_attr', true );

            echo $result->publish_time;

            exit;

        }

        exit;

    }

    /**
     * get_all_myfeeds
     * @return mixed
     */
    function get_all_myfeeds () {


        global $nc_controller;

        $myfeeds_array = array();

        if(!$nc_controller->check_nonce_capability("nc_get_myfeeds_nonce")){
            echo json_encode( $myfeeds_array );
            exit;
        }

        $args = array(
            'post_type'         => 'nc_myfeeds',
            'post_status'       => 'draft',
            'posts_per_page'    => 200
        );
        $query = new WP_Query( $args );

        $posts = $query->posts;


        if($posts){
            foreach( $posts as $myfeed ){
                $unserialize_data =  get_post_meta($myfeed->ID, '_ncmyfeed_attr', true);
                $myfeeds_array[ ] = array( "id" => $myfeed->ID, "text" => $unserialize_data[ 'name' ] );

            }

            echo json_encode( $myfeeds_array );

            exit;

        }

    }
}