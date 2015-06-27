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
 * NC_Cron Class
 * its a local file cache for newscred source and topics
 *
 */


class NC_Cron {

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
     * Constructs the controller and assigns protected variables to be
     * used by extenders of the abstract class.
     */
    public function __construct () {

        // add new schedule interval
        add_action( "cron_schedules", array( &$this, "add_nc_scheduled_interval" ) );

        /**
         *  check the schedule
         */

        if ( !wp_next_scheduled( 'nc_hourly_plugin_hook' ) ) {
            wp_schedule_event( time(), 'nc_minutes_60', 'nc_hourly_plugin_hook' );

        }

        if ( !wp_next_scheduled( 'nc_mins_plugin_hook' ) ) {
            wp_schedule_event( time(), 'nc_minutes_15', 'nc_mins_plugin_hook' );

        }

        /**
         *  cron actions
         */

        // add my hourly schedule hook
        add_action( 'nc_hourly_plugin_hook', array( &$this, "nc_plugin_hourly_cron_action" ) );

        // add my 15 mins schedule hook
        // post articles for myFeeds
        add_action( 'nc_mins_plugin_hook', array( &$this, "nc_plugin_mins_cron_action" ) );

        // add feature image for myFeeds
        add_action( 'nc_mins_plugin_hook', array( &$this, "nc_plugin_mins_myfeeds_feature_image_action" ) );

    }

    /**
     * add new schedule time for nc plugin
     * @param $schedules
     * @return array
     */
    public function add_nc_scheduled_interval ( $schedules ) {

        $schedules[ 'nc_minutes_60' ] = array( 'interval' => 3600, 'display' => 'Once 60 minutes' );
        $schedules[ 'nc_minutes_15' ] = array( 'interval' => 900, 'display' => 'Once 15 minutes' );

        return $schedules;

    }

    /**
     * nc_plugin_hourly_cron_action
     * in every hour it check
     * the time interval of myFeeds
     * if the time interval valid then it will insert
     * article guids in the nc_myfeed_publish post type
     */

    public function nc_plugin_hourly_cron_action () {

        $args = array(
            'post_type'         => 'nc_myfeeds',
            'post_status'       => 'draft',
            'posts_per_page'    => 100,
            'meta_query' => array(
                array(
                    'key'       => '_ncmyfeed_autopublish',
                    'value'     => 1,
                    'compare'   => '=',
                )
            )
        );

        $query = new WP_Query( $args );

        $myfeeds = $query->posts;

        if ( $myfeeds ) {
            $myfeeds_result = array();
            foreach ( $myfeeds as $myfeed ) {
                $unserialize_data =  get_post_meta($myfeed->ID, '_ncmyfeed_attr', true);

                $myfeeds_result[ ] = (object)array(
                    'id'                => $myfeed->ID,
                    'apicall'           => $unserialize_data[ 'apicall' ],
                    'publish_interval'  => $unserialize_data[ 'publish_interval' ],
                    'publish_time'      => $unserialize_data[ 'publish_time' ]
                );
            }


            foreach ( $myfeeds_result as $myfeed ) {

                /**
                 *  update myfeeds for first time
                 */
                if ( $myfeed->publish_time == "0000-00-00 00:00:00" ) {

                    if ( $myfeed->apicall && filter_var( $myfeed->apicall, FILTER_VALIDATE_URL ) ) {

                        $parse_url = parse_url( $myfeed->apicall );

                        parse_str( html_entity_decode( $parse_url[ 'query' ] ), $querys );

                        $querys[ 'fields' ] = "article.guid";

                        $query_str = http_build_query( $querys );

                        $url = $parse_url[ 'scheme' ] . "://" . $parse_url[ 'host' ] . $parse_url[ 'path' ] . "?" . $query_str;

                        self::insert_article_guid( $url, $myfeed->id );

                    }

                }
                else {

                    $current_time = date( "Y-m-d H:i:s", time() );
                    $publish_time = $myfeed->publish_time;

                    $difference = self::time_difference( $current_time, $publish_time );

                    if ( $difference > $myfeed->publish_interval * 60 ) {

                        $from_date = date( 'Y-m-d H:i:s', strtotime( " -$difference minute" ) );

                        $parse_url = parse_url( $myfeed->apicall );

                        parse_str( html_entity_decode( $parse_url[ 'query' ] ), $querys );

                        $querys[ 'fields' ]     = "article.guid";
                        $querys[ 'from_date' ]  = $from_date;

                        $query_str = http_build_query( $querys );

                        $url = $parse_url[ 'scheme' ] . "://" . $parse_url[ 'host' ] . $parse_url[ 'path' ] . "?" . $query_str;

                        self::insert_article_guid( $url, $myfeed->id );

                    }
                }
            }


        }

    }

    /**
     * nc_plugin_mins_cron_action
     * published articles in every 15 mins interval
     * if nc_myfeeds_publish  has article guids
     */
    public function nc_plugin_mins_cron_action () {

        $args = array(
            'post_type'         => 'nc_myfeeds_publish',
            'post_status'       => 'draft',
            'posts_per_page'    => 100
        );

        $query = new WP_Query( $args );

        $results = $query->posts;

        if ( $results ) {
            foreach ( $results as $result ) {
                $unserialize_data =  get_post_meta(absint($result->post_parent), '_ncmyfeed_attr', true);

                $row = (object)array(
                    'id'                => $result->ID,
                    'publish_status'    => $unserialize_data[ 'publish_status' ],
                    'myfeed_category'   => $unserialize_data[ 'myfeed_category' ],
                    'feature_image'     => $unserialize_data[ 'feature_image' ],
                    'feed_tag'          => $unserialize_data[ 'feed_tag' ],
                    'guid'              => $result->post_content
                );


                // publish status
                $publish_status = "draft";
                if ( $row->publish_status == 1 )
                    $publish_status = "publish";

                // get the article from API
                $url = "http://api.newscred.com/article/$row->guid?access_key=" . NC_ACCESS_KEY;

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

                $url .= "&fields=" . implode( "%20", $fields );


                $article = NC_Plugin_Article::search_by_url( NC_ACCESS_KEY, $url );
                $article = $article[ 0 ];

                // add category

                $categories = unserialize( $row->myfeed_category );


                // add tags

                $tags = "";
                if ( $row->feed_tag == 1 ) {
                    if ( $article->topics ) {
                        foreach ( $article->topics as $topic )
                            $tags .= $topic->name . ",";
                        $tags = rtrim( $tags, "," );
                    }
                }

                // set publish time as  system time zone

                $hours = get_option( 'gmt_offset' );
                $time_old = $article->published_at;
                $time_new = strtotime( $time_old );
                $time_new = $time_new + ( 60 * 60 * $hours );
                $publish_time = date( "Y-m-d H:i:s", $time_new );

                // published the article
                $post = array(
                    'post_author'       => 1,
                    'post_category'     => $categories, //post_category no longer exists, try wp_set_post_terms() for setting a post's categories
                    'post_content'      => $article->description, //The full text of the post.
                    'post_date'         => $publish_time, //The time post was made.
                    'post_date_gmt'     => $publish_time, //The time post was made, in GMT.
                    'post_status'       => $publish_status, //Set the status of the new post.
                    'post_title'        => $article->title, //The title of your post.
                    'post_type'         => 'post', //You may want to insert a regular post, page, link, a menu item or some custom post type
                    'tags_input'        => $tags //For tags.

                );
                $post_id = wp_insert_post( $post );
                if ( $post_id ) {

                    // add author

                    if(empty( $article->author )){
                        $author = $article->source->name;
                    }
                    else
                        $author = $article->author . " for " . $article->source->name;
                    add_post_meta($post_id, '_nc_post_author', $author);


                    // add image set post meta
                    if ( $article->image_set ) {
                        $article_image_set = array();
                        foreach ( $article->image_set as $image ) {

                            $article_image_set[ ] = (object)
                            array(
                                "guid"          =>  esc_attr( $image->guid ),
                                "caption"       =>  esc_html( $image->caption ),
                                "description"   =>  esc_html( $image->description ),
                                "image_large"   =>  esc_url( $image->image_large ),
                                "published_at"  =>  esc_attr( $image->published_at ),
                                "height"        =>  absint( $image->height ),
                                "width"         =>  absint( $image->width ),
                                "source"        =>  (object) array("name" => esc_html( $image->source->name ) )
                            );

                        }

                        add_post_meta( $post_id, "nc_image_set", serialize( $article_image_set ) );

                        // enable feature image for auto publish myFeeds
                        $myfeeds_feature_image = $row->feature_image;

                        if ( $myfeeds_feature_image && $publish_status == "publish" )
                            add_post_meta( $post_id, "nc_feature_image_publish", 0 );
                    }

                    // delete article guid from nc_myfeeds_publish post_type
                    wp_delete_post( $row->id , true);
                }

            }

        }

    }

    /**
     *  add feature image for myFeeds
     *  auto publish post in 5 mins
     */
    public function nc_plugin_mins_myfeeds_feature_image_action () {

        $myfeeds_posts = get_posts(
            array(
                'posts_per_page' => 100,
                'meta_query' => array(
                    array( 'key' => 'nc_feature_image_publish', 'value' => 0, 'compare' => '=', )
                ),
                'suppress_filters' => false
            )
        );

        if ( $myfeeds_posts ) {
            foreach ( $myfeeds_posts as $myfeeds_post ) {
                $nc_image_set_meta = get_post_meta( $myfeeds_post->ID, "nc_image_set" );

                if ( $nc_image_set_meta ) {
                    $nc_image_set = unserialize( $nc_image_set_meta[ 0 ] );
                    if ( $nc_image_set ) {
                        $result = $this->add_feature_image( $myfeeds_post->ID, $nc_image_set[ 0 ]->image_large,
                                                            $nc_image_set[ 0 ]->caption );
                        if ( $result )
                            update_post_meta( $myfeeds_post->ID, "nc_feature_image_publish", 1 );

                    }
                }
            }
        }


    }

    /**
     * add feature for myFeeds auto publish
     * @param $post_id
     * @param $image_thumb_url
     * @param $image_caption
     * @return int
     */
    public function add_feature_image ( $post_id, $image_thumb_url, $image_caption ) {
        $post_id = absint($post_id);

        $image_thumb_url .= "?width=" . absint( get_option('nc_image_post_width') ) . "&amp;height=" . absint( get_option('nc_image_post_height') );
        global $nc_utility;

        try {
            $attach_image_id = $nc_utility->nc_upload_image( esc_url( $image_thumb_url ), $post_id, 'image_thumbnail' );

        } catch ( Exception $e ) {
            return 0;
        }


        if ($attach_image_id){

            // set image as the post thumbnail
            set_post_thumbnail( $post_id, absint( $attach_image_id ) );

            $attach_data = array();
            $attach_data[ 'ID' ]            = $attach_image_id;
            $attach_data[ 'post_title' ]    = sanitize_text_field($image_caption);
            $attach_data[ 'post_excerpt' ]  = sanitize_text_field($image_caption);

            // Update the post into the database
            wp_update_post( $attach_data );

            return 1;

        }

    }

    /**
     * insert_article_guid
     *
     * insert articel guid
     * into nc_myfeeds_publish post type
     *
     * @param $url
     * @param $id
     */
    public function insert_article_guid ( $url, $id ) {

        $myfeeds_guids = NC_Plugin_Article::search_by_url( NC_ACCESS_KEY, $url );

        if ( $myfeeds_guids ) {

            foreach ( $myfeeds_guids as $guid ) {

                // Create nc_myfeeds_publish custom  post object
                $my_post = array(
                    'post_type'     => 'nc_myfeeds_publish',
                    'post_parent'   => $id, // nc_myfeeds id
                    'post_content'  => $guid->guid );
                // Insert the post into the database
                $myfeed_publish_id = wp_insert_post( $my_post );

            }

            $publish_time = date( "Y-m-d H:i:s", time() );
            $myfeed_data = get_post_meta( $id, "_ncmyfeed_attr", true );

            $myfeed_data[ 'publish_time' ]  = $publish_time;
            $myfeed_data[ 'update_time' ]   = $publish_time;

            update_post_meta( $id, '_ncmyfeed_attr', $myfeed_data );

        }
    }

    // get time difference in hour
    public function time_difference ( $first_time, $last_time ) {

        $current_time = strtotime( $first_time );
        $publish_time = strtotime( $last_time );

        return floor( round( abs( $current_time - $publish_time ) / ( 60 ), 2 ) );
    }
}