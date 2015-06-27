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
 * NC_Source Class
 */

class NC_Source {

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
     * get autosugested  source list
     * @static
     *
     */
    public static function get_sources_suggestion () {
        global $nc_controller;

        $query = sanitize_text_field( $_GET[ 'term' ] );

        $sources_array = array();

        if(!$nc_controller->check_nonce_capability("nc_get_sources_nonce")){
            echo json_encode( $sources_array );
            exit;
        }

        if ( !empty( $query ) ) {

            $source_fields = array( 'source.guid', 'source.name', );

            $options = array( "fields" => $source_fields, "autosuggest" => true, 'fulltext' => true );
            if ( isset( $_GET[ 'pagesize' ] ) )
                $options[ 'pagesize' ] = absint( $_GET[ 'pagesize' ] );
            try{
                $sources = NC_Plugin_Source::search( NC_ACCESS_KEY, $query, $options );

            } catch ( NC_Plugin_Exception $e ) {
                $source = null;

            }

            if($sources){
                foreach ( $sources as $source ) {
                    $sources_array[ ] = array( "id" => (string)$source->guid, "text" => (string)$source->name );
                }
            }

            echo json_encode( $sources_array );

            exit;

        }
    }

    /**
     *  get nc source list from cache
     *  if its not expire
     * @return array
     */
    public function get_nc_sources () {


        global $nc_utility;
        $url = 'http://api.newscred.com/api/user/source_filters?access_key=' . NC_ACCESS_KEY;

        $sources_list = array();

        try {
            $sources = json_decode( $nc_utility->get_url( $url ) );

            if ( $sources ) {
                foreach ( $sources as $source ) {
                    $sources_list[ $source->name ] = $source->name;
                }
            }

        } catch ( NC_Plugin_Exception $e ) {
            return $sources_list;

        }


        return $sources_list;
    }

}