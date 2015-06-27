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
 * NC_Topic Class
 */

class NC_Topic {

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
     * @static
     * get_topics_suggestion
     */
    public static function get_topics_suggestion () {

        global $nc_controller;

        $query = sanitize_text_field( $_GET[ 'term' ] );

        $topics_array = array();

        if(!$nc_controller->check_nonce_capability("nc_get_topics_nonce")){
            echo json_encode( $topics_array );
            exit;
        }

        if ( !empty( $query ) ) {

            $topic_fields = array( 'topic.guid', 'topic.name', );

            $options = array( "fields" => $topic_fields, "autosuggest" => true );

            if ( isset( $_GET[ 'pagesize' ] ) )
                $options[ 'pagesize' ] = absint( $_GET[ 'pagesize' ] );
            try{
                $topics = NC_Plugin_Topic::search( NC_ACCESS_KEY, $query, $options );

            } catch( NC_Plugin_Exception $e ){
                $topics = null;
            }

            if( $topics ){
                foreach ( $topics as $topic ) {
                    $topics_array[ ] = array( "id" => (string)$topic->guid, "text" => (string)$topic->name );
                }
            }

            echo json_encode( $topics_array );

            exit;

        }
    }

    /**
     * get nc source list from cache if its not
     * expire
     * @return array
     */
    public function get_nc_topics () {


        global $nc_utility;

        $url = 'http://api.newscred.com/api/user/topic_filters?access_key=' . NC_ACCESS_KEY;

        $topics_list = array();
        try{
            $topics = json_decode( $nc_utility->get_url( $url ) );

            if ( $topics ) {
                foreach ( $topics as $topic ) {
                    $topics_list[ $topic->name ] = $topic->name;
                }
            }

            return $topics_list;
        } catch ( NC_Plugin_Exception $e ) {
            return $topics_list;
        }
    }
}