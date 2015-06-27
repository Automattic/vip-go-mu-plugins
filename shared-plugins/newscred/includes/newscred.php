<?php

/**
 * PHP5 Wrapper for NCplugin Platform API
 *
 * This file contains classes that make RESTful web service requests to the NCplugin Platform
 * server and pull contents(topics, articles, images, videos, Twitter conversations etc.)
 *
 * @author  Rubayeet Islam <rubayeet@newscred.com>
 * @version 0.9.5
 * @package NCpluginPHP5
 */


abstract class NCplugin {
    const NEWSCRED_DOMAIN = 'http://api.newscred.com';

    public $key = '';
    public $guid = '';
    public $url = '';
    public $module;

    public function get_endpoint_name( $name ){
        return ($name !== "story") ? $name."s" : "stories";
    }
    /**
     * Get topics related to the Topic/Article/Category/Source
     * @access public
     * @param array $options
     * @return array
     */
    public function get_related_topics ( $options = array() ) {
        if ( property_exists( $this, 'has_related_topics' ) && $this->has_related_topics === FALSE )
            return;

        return $this->get_related_stuff( 'topic', $options );
    }

    /**
     * Get articles related to the Topic/Article/Category/Source
     * @param array $options
     * @return array
     */
    public function get_related_articles ( $options = array() ) {
        if ( property_exists( $this, 'has_related_articles' ) && $this->has_related_articles === FALSE )
            return;

        return $this->get_related_stuff( 'article', $options );
    }

    protected function populate () {
        $this->url = sprintf( "%s/%s/%s?access_key=%s", esc_attr( NCplugin::NEWSCRED_DOMAIN ), esc_attr( $this->module ), esc_attr( $this->guid ),
            urlencode( esc_attr( $this->key ) ) );

        try {

            $xml = NCplugin::get( $this->url );

        } catch ( NC_Plugin_Exception $e ) {

            throw new NC_Plugin_Exception( 'Class::  ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }

        $parsed_xml = NC_Plugin_Parser::parse( $this->module, $xml, $this->key );

        foreach ( get_object_vars( $this ) as $property => $value ) {

            if ( property_exists( $parsed_xml[ 0 ], $property ) )
                $this->$property = $parsed_xml[ 0 ]->$property;
        }
    }

    protected function get_related_stuff ( $name, $options = array() ) {
        $method = NCplugin::get_endpoint_name( $name );

        if ( empty( $this->key ) ) {

            throw new NC_Plugin_Exception( 'Class::  ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_NO_ACCESS_KEY );
            return;
        }

        $identifier = ( $this->module === 'category' ) ? $this->name : $this->guid;

        $this->url = sprintf( "%s/%s/%s/%s?access_key=%s", esc_attr( NCplugin::NEWSCRED_DOMAIN ), esc_attr( $this->module ), esc_attr( $identifier ),
            esc_attr( $method ), urlencode( esc_attr( $this->key ) ) );
        if ( $options )
            $this->url .= NCplugin::get_request_params( $options );

        try {

            $xml = NCplugin::get( $this->url );

        } catch ( NC_Plugin_Exception $e ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }

        if ( $name === 'story' ) {
            return NC_Plugin_Parser::parse( 'cluster', $xml, $this->key );
        }

        return NC_Plugin_Parser::parse( $name, $xml, $this->key );
    }

    protected static function _search ( $key, $name, $query, $options = array() ) {
        $method = NCplugin::get_endpoint_name( $name );
        if ( empty( $key ) ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_NO_ACCESS_KEY );
            return;
        }

        $url = sprintf( "%s/%s?access_key=%s&query=%s", esc_attr( self::NEWSCRED_DOMAIN ), esc_attr( $method ) , esc_attr( $key ),
                        urlencode( esc_attr( $query ) ));

        // url for related sources
        if ( $name == "sourceRelated" ) {

            $url = sprintf( "%s/sources/related?access_key=%s&query=%s", esc_attr( self::NEWSCRED_DOMAIN ), esc_attr( $key ),
                            urlencode( esc_attr( $query ) ) );

        }

        if ( $options )
            $url .= self::get_request_params( $options );

        try {
            $xml = self::get( $url );
        } catch ( NC_Plugin_Exception $e ) {
            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }

        if ( $name === 'story' ) {
            return NC_Plugin_Parser::parse( 'cluster', $xml, $key );
        }
        return NC_Plugin_Parser::parse( $name, $xml, $key );
    }

    protected static function _api_call ( $key, $name, $options = array() ) {
        $method = NCplugin::get_endpoint_name( $name );
        if ( empty( $key ) ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_NO_ACCESS_KEY );
            return;
        }

        $url = sprintf( "%s/%s?access_key=%s", esc_attr( self::NEWSCRED_DOMAIN ), esc_attr( $method ) , esc_attr( $key ) );
        if ( $options )
            $url .=  self::get_request_params( $options );

        return $url;
    }

    /**
     * _search_by_url
     * @static
     * @param $key
     * @param $name
     * @param $url
     * @return array
     * @throws NC_Plugin_Exception
     */
    protected static function _search_by_url ( $key, $name, $url ) {

        if ( empty( $key ) ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_NO_ACCESS_KEY );
            return;
        }

        try {
            $xml = self::get( $url );

        } catch ( NC_Plugin_Exception $e ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }

        if ( $name === 'story' ) {
            return NC_Plugin_Parser::parse( 'cluster', $xml, $key );
        }
        return NC_Plugin_Parser::parse( $name, $xml, $key );
    }

    /**
     * Request the NCplugin API $url
     * @param string $url
     * @param string $format (xml|json)
     * @return SimpleXML|stdClass
     * @access public
     * @static
     */
    public static function get ( $url, $format = 'xml' ) {
        if ( $format === 'json' )
            $url .= '&format=json';

        $response = wp_remote_get($url, array( 'timeout' => 10 ));

        if ( is_wp_error( $response ) ){
            $result = null;
        }else{
            try {
                $result = $response['body'];

            } catch ( Exception $ex ) {
                $result = null;
            } // end try/catch
        }
        return NCplugin::parse_response( $result, $url, $format );
    }

    /**
     * Parse XML/JSON response returned by NCplugin API
     * @static
     * @param $response
     * @param $url
     * @param string $format
     * @return array|mixed|SimpleXMLElement
     * @throws NC_Plugin_Exception
     */
    public static function parse_response ( $response, $url, $format = 'xml' ) {

        $parsed_response = ( $format === 'json' ) ? json_decode( $response ) : simplexml_load_string( $response );

        if ( $parsed_response === NULL ) {
            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_JSON_PARSE_ERROR . $url );
        }
        elseif ( $parsed_response === False ) {
            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_XML_PARSE_ERROR . $url );
        }

        return $parsed_response;
    }

    /**
     * Parses error info returned by the API
     * @access public
     * @param <string> $url
     * @param <string> $api_response
     * @param <string> $format
     * @static
     */
    public static function handle_api_error ( $url, $api_response, $format = 'text/xml' ) {
        $error = ( $format === 'application/json' ) ? json_decode( $api_response )->error : simplexml_load_string( $api_response );
        throw new NC_Plugin_Exception( sprintf( 'Class:: %s Line:: %s %s URL: %s, Code: %d, Message: %s', __CLASS__,
                                              __LINE__, NC_Plugin_Exception::EXCEPTION_API_ERROR, $url, $error->code,
                                              $error->message ) );
    }

    /**
     * Build the HTTP request string from the key,value pairs in $options
     * @param array $options
     * @return string
     * @access public
     * @static
     */
    public static function get_request_params ( $options ) {
        if ( !is_array( $options ) || empty( $options ) )
            return;

        $request_params = '';

        foreach ( $options as $key => $value ) {
            if ( is_array( $value ) && ( $key === 'sources' || $key === 'source_countries' ) ) {
                //sources and source_countries params are to be joined by space
                //and don't need to be parameterized
                $request_params .= '&' . $key . '=' . urlencode( join( ' ', $value ) );
            }
            elseif ( is_bool( $value ) ) {
                //PHP turns boolean true/false into 1/0 when casted to string.
                //Need to pass true/false as is.
                $request_params .= ( $value === True ) ? '&' . $key . '=' . 'true' : '&' . $key . '=' . 'false';
            }
            else {

                $request_params .= '&' . $key . '=' . urlencode( NCplugin::parameterize( $value ) );
            }
        }

        return $request_params;
    }

    /**
     * Format $param as required by the API. ('Football player' => 'football-player')
     * @access public
     * @param string|array $param
     * @return string
     * @static
     */

    public static function parameterize ( $param ) {
        $parameterize = create_function( '$string', 'return str_replace(" ", "-",' . ' strtolower($string));' );

        if ( is_array( $param ) ) {

            return join( array_map( $parameterize, $param ), ' ' );
        }

        return $parameterize( $param );
    }

    /**
     * Parses the SimpleXML object and returns an array of NCpluginModule objects
     * @access public
     * @param string $module
     * @param string $key
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    public static function create_objects ( $module, $key, $xml ) {
        $objects = array();
        $parsed_nodes = NC_Plugin_Parser::parse( $module, $xml );

        if ( !empty( $parsed_nodes ) ) {

            foreach ( $parsed_nodes as $parsed_node ) {

                switch ( $module ) {

                    case 'topic'   :
                        $object = new NC_Plugin_Topic();
                        break;
                    case 'article' :
                        $object = new NC_Plugin_Article();
                        break;
                    case 'source'  :
                        $object = new NC_Plugin_Source();
                        break;
                    case 'author'  :
                        $object = new NC_Plugin_Author();
                        break;
                    case 'image'   :
                        $object = new NC_Plugin_Image();
                        break;
                    case 'video'   :
                        $object = new NC_Plugin_Video();
                        break;
                    case 'tweet'   :
                        $object = new NC_Plugin_Twitter();
                        break;
                }

                $object->key = $key;

                foreach ( get_object_vars( $object ) as $property => $value ) {

                    if ( property_exists( $parsed_node, $property ) )
                        $object->$property = $parsed_node->$property;
                }
                array_push( $objects, $object );
            }
        }

        return $objects;
    }
}

/**
 * Class for parsing SimpleXML nodes to PHP objects
 */
class NC_Plugin_Parser {
    /**
     * Parses a SimpleXML object and returns an array of NCplugin objects
     * @access public
     * @param string $nodeType
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    public static function parse ( $node_type, $xml, $key = NULL ) {
        $objects = array();

        $nodes = $xml->xpath( '//' . $node_type );

        // get related source from query
        if ( $node_type == "sourceRelated" ) {
            $node_type = "source";
            $nodes = $xml->xpath( '' . $node_type );
        }

        if ( empty( $nodes ) )
            return;

        foreach ( $nodes as $node ) {

            if ( $node_type === 'cluster' ) {
                array_push( $objects, self::_parse_cluster_node( $node, $key ) );
            }
            else {

                $method = '_parse_' . strtolower( $node_type ) . '_node';
                array_push( $objects, self::$method( $node, $key ) );
            }
        }

        return $objects;
    }

    /**
     * Parses a <cluster> node and returns a cluster(an array of NC_Plugin_Article objects)
     * @access private
     * @param SimpleXML $cluster_node
     * @param string $key
     * @return array
     * @static
     */

    private static function _parse_cluster_node ( $cluster_node, $key ) {
        $cluster = array();

        if ( !isset( $cluster_node->article_set ) )
            return;

        //parse each cluster->article_set->article and create a NC_Plugin_Article
        //object
        foreach ( $cluster_node->article_set->article as $article_node ) {

            $article = new NC_Plugin_Article( $key );

            foreach ( get_object_vars( $article ) as $property => $value ) {

                if ( property_exists( $article_node, $property ) )
                    $article->$property = (string)$article_node->$property;
            }

            //parse the <source> node of the <article> node and create a NC_Plugin_Source object
            $source = new NC_Plugin_Source( $key );
            foreach ( get_object_vars( $source ) as $property => $value ) {

                if ( property_exists( $article_node->source, $property ) )
                    $source->$property = (string)$article_node->source->$property;
            }
            //Assign the NC_Plugin_Source object as a property of the NC_Plugin_Article object
            $article->source = $source;

            //push the article object into the cluster
            array_push( $cluster, $article );
        }

        return $cluster;
    }

    /**
     * Parses the <topic> nodes and returns a PHP object
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_topic_node ( $topic_node, $key = NULL ) {

        $topic = new NC_Plugin_Topic();
        $topic->key = ( isset( $key ) ) ? $key : $topic->key;

        foreach ( get_object_vars( $topic ) as $property => $value ) {

            if ( $property === 'classification' || $property === 'subclassification' ) {

                $child_node = 'topic_' . $property;

                if ( isset( $topic_node->$child_node->name ) )
                    $topic->$property = (string)$topic_node->$child_node->name;

                elseif ( isset( $topic_node->$child_node ) )
                    $topic->$property = (string)$topic_node->$child_node;

                else $topic->$property = '';
            }
            else {

                if ( property_exists( $topic_node, $property ) )
                    $topic->$property = (string)$topic_node->$property;
            }
        }

        return $topic;
    }

    /**
     * Parses the <article> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_article_node ( $article_node, $key = NULL ) {
        $article = new NC_Plugin_Article();
        $article->key = ( isset( $key ) ) ? $key : $article->key;

        foreach ( get_object_vars( $article ) as $property => $value ) {

            switch ( $property ) {

                case 'description':
                    $article->description = wp_kses_post( $article_node->description ) ;
                    break;

                case 'category':
                    $article->category = isset( $article_node->category ) ? (string)$article_node->category->name : '';
                    break;

                case 'categories':
                    $article->categories = array();

                    if ( isset( $article_node->categories_set->categories ) ) {

                        try {
                            foreach ( $article_node->categories_set->categories as $category ) {
                                array_push( $article->categories, self::_parse_category_node( $category, $key ) );
                            }
                        } catch ( Exception $e ) {
                            try {
                                foreach ( $article_node->category_set->category as $category ) {
                                    array_push( $article->categories, self::_parse_category_node( $category, $key ) );
                                }
                            } catch ( Exception $e ) {
                                // to do
                            }
                        }
                        break;
                    }


                case 'thumbnail':
                    $article->thumbnail = isset( $article_node->thumbnail->link ) ? (string)$article_node->thumbnail->link : '';
                    $article->thumbnail_original = isset( $article_node->thumbnail->original_image ) ? (string)$article_node->thumbnail->original_image : '';
                    break;

                case 'source':
                    if ( isset( $article_node->source ) ) {
                        $article->source = self::_parse_source_node( $article_node->source, $key );
                    }
                    break;

                case 'author':
                    if ( isset( $article_node->author_set->author->name ) ) {
                        $article->author = (string)$article_node->author_set->author->name;
                    }
                    if ( isset( $article_node->author_set->author->first_name ) || isset( $article_node->author_set->author->last_name ) ) {
                        $article->author = (string)$article_node->author_set->author->first_name . " " . (string)$article_node->author_set->author->last_name;
                    }
                    break;

                case 'image_set':
                    $article->image_set = array();
                    if ( isset( $article_node->image_set ) ) {
                        foreach ( $article_node->image_set->image as $image ) {
                            array_push( $article->image_set, self::_parse_image_node( $image, $key ) );
                        }
                    }
                    break;

                case 'published_at':

                    // add GMT offset of local machine
                    $article->published_at = self::calculate_publish_time( $article_node->published_at  );

                    break;


                default:
                    if ( property_exists( $article_node, $property ) )
                        $article->$property = (string)$article_node->$property;
            }
        }

        if ( isset( $article_node->topic_set ) ) {

            $article->topics = array();

            foreach ( $article_node->topic_set->topic as $topic ) {
                array_push( $article->topics, self::_parse_topic_node( $topic, $key ) );
            }
        }

        return $article;
    }

    /**
     * Parses the <source> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_source_node ( $source_node, $key ) {
        $source = new NC_Plugin_Source();
        $source->key = ( isset( $key ) ) ? $key : $source->key;

        foreach ( get_object_vars( $source ) as $property => $value ) {

            if ( property_exists( $source_node, $property ) )
                $source->$property = (string)$source_node->$property;
        }

        return $source;
    }

    /**
     * Parses the <image> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_image_node ( $image_node, $key ) {
        $image = new NC_Plugin_Image();
        $image->key = ( isset( $key ) ) ? $key : $image->key;

        foreach ( get_object_vars( $image ) as $property => $value ) {

            switch ( $property ) {
                case 'source':
                    if ( isset( $image_node->source ) ) {
                        $image->source = self::_parse_source_node( $image_node->source, $key );
                    }
                    break;

                case 'published_at':
                    // add GMT offset of local machine
                    $image->published_at = self::calculate_publish_time($image_node->published_at);
                    break;


                default:
                    if ( property_exists( $image_node, $property ) )
                        $image->$property = (string)$image_node->$property;
            }

        }

        if ( isset( $image_node->urls ) ) {

            $image->image_small = isset( $image_node->urls->small ) ? (string)$image_node->urls->small : null;

            $image->image_medium = isset( $image_node->urls->medium ) ? (string)$image_node->urls->medium : null;

            $image->image_large = isset( $image_node->urls->large ) ? (string)$image_node->urls->large : null;
        }

        return $image;
    }

    /**
     * Parses the <tweet> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_tweet_node ( $tweet_node, $key ) {
        $twitter = new NC_Plugin_Twitter();
        $twitter->key = ( isset( $key ) ) ? $key : $twitter->key;

        foreach ( get_object_vars( $twitter ) as $property => $value ) {

            if ( property_exists( $tweet_node, $property ) )
                $twitter->$property = (string)$tweet_node->$property;
        }

        return $twitter;
    }

    /**
     * Parses the <video> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_video_node ( $video_node, $key ) {
        $video = new NC_Plugin_Video();
        $video->key = ( isset( $key ) ) ? $key : $video->key;

        foreach ( get_object_vars( $video ) as $property => $value ) {

            switch ( $property ) {
                case 'category':
                    $video->category = ( isset( $video_node->category ) ) ? (string)$video_node->category->name : '';
                case 'source':
                    if ( isset( $video_node->source ) ) {
                        $video->source = self::_parse_source_node( $video_node->source, $key );
                    }

                default:
                    if ( property_exists( $video_node, $property ) )
                        $video->$property = (string)$video_node->$property;
            }

        }

        if ( isset( $video_node->topic_set ) ) {

            $video->topics = array();

            foreach ( $video_node->topic_set->topic as $topic ) {
                array_push( $video->topics, self::_parse_topic_node( $topic, $key ) );
            }
        }

        return $video;
    }

    /**
     * Parses the <author> nodes and returns an array of PHP objects
     * @access private
     * @param SimpleXML $xml
     * @return array
     * @static
     */
    private static function _parse_author_node ( $author_node, $key ) {
        $author = new NC_Plugin_Author();
        $author->key = ( isset( $key ) ) ? $key : $author->key;

        foreach ( get_object_vars( $author ) as $property => $value ) {

            if ( property_exists( $author_node, $property ) )
                $author->$property = (string)$author_node->$property;
        }

        return $author;
    }

    /**
     * Parses the <category> nodes and returns the name of the category
     * @access private
     * @param SimpleXML $xml
     * @return string
     * @static
     */
    private static function _parse_category_node ( $category_node, $key ) {
        if ( property_exists( $category_node, 'name' ) ) {
            return (string)$category_node->name;
        }


        return '';
    }

    /**
     * Calculate the publish time as
     * server time zone
     * @static
     * @param $publish_time
     * @return string
     */
    private static function calculate_publish_time($publish_time){

        $hours = get_option( 'gmt_offset' );
        $time_new = strtotime( $publish_time );
        $time_new = $time_new + ( 60 * 60 * $hours );
        $new_publish_time = date( "Y-m-d H:i:s", $time_new );
        return $new_publish_time;

    }
}

/**
 * Custom Exception class for NCplugin
 */
class NC_Plugin_Exception extends Exception {
    const EXCEPTION_REMOTE_URL_ACCESS_DENIED = "Failed to connect to NCplugin Platform server. Either install PHP cURL extension on your server or set 'allow_url_fopen' flag to 'On'.";
    const EXCEPTION_API_RESPONSE_GET_FAILED = 'Failed to get API response for the request: ';
    const EXCEPTION_XML_PARSE_ERROR = 'Error parsing the XML response for the request: ';
    const EXCEPTION_JSON_PARSE_ERROR = 'Error parsing the JSON response for the request: ';
    const EXCEPTION_NO_ACCESS_KEY = 'No access key provided.';
    const EXCEPTION_API_ERROR = 'NCplugin API Error.';


    const EXCEPTION_AUTHENTICATION_FAILED = 'Authentication Failed. Please check the access key.';
    const EXCEPTION_INVALID_GUID = 'Invalid GUID provided.';

    const EXCEPTION_PLATFORM_RETURNED_ERROR = 'NCplugin Platform returned Internal Server Error for this request: ';
}


/**
 * Represents a Topic in the NCplugin Platform
 */
class NC_Plugin_Topic extends NCplugin {
    public $name;
    public $link;
    public $dashed_name;
    public $image_url;
    public $classification;
    public $subclassification;
    public $description;

    /**
     * Constructor of the class
     * @access public
     * @param string $key
     * @param string $guid
     */
    public function __construct ( $key = null, $guid = null ) {
        //Initialize all properties to empty string
        foreach ( get_object_vars( $this ) as $property => $value ) {
            $this->$property = '';
        }

        $this->module = 'topic';
        $this->key = $key;
        $this->guid = $guid;

        if ( !empty( $key ) && !empty( $guid ) ) {
            $this->populate();
        }
    }


    public function __call ( $method_name, $arguments ) {
        switch ( $method_name ) {

            case 'getRelatedStories':
                $name = 'story';
                break;
            case 'getRelatedSources':
                $name = 'source';
                break;
            case 'getRelatedImages' :
                $name = 'image';
                break;
            case 'getRelatedVideos' :
                $name = 'video';
                break;
            case 'getRelatedTweets' :
                $name = 'tweet';
                break;

            /*Raise error when no matching method name found.*/

        }

        if ( is_array( $arguments ) && !empty( $arguments ) )
            $options = $arguments[ 0 ];

        else $options = NULL;

        return $this->get_related_stuff( $name, $options );
    }

    /**
     * Get metadata of a topic
     * @access public
     * @param array $options
     * @return stdClass
     */
    public function get_meta_data ( $options = array() ) {

        $this->url = sprintf( "%s/%s/%s?access_key=%s", esc_attr( NCplugin::NEWSCRED_DOMAIN ), esc_attr( $this->module ), esc_attr( $this->guid ),
                              urlencode( esc_attr( $this->key ) ) );
        if ( $options )
            $this->url .= NCplugin::get_request_params( $options );

        try {

            $json_response = NCplugin::get( $this->url, 'json' );

        } catch ( NC_Plugin_Exception $e ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }


        return ( isset( $json_response->topic->metadata ) ) ? $json_response->topic->metadata : null;

    }

    /**
     * Extract topics from the given $query
     * @access public
     * @param string $key
     * @param string $query
     * @return array
     * @static
     */
    public static function extract ( $key, $query, $options = array() ) {
        if ( empty( $key ) ) {
            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . NC_Plugin_Exception::EXCEPTION_NO_ACCESS_KEY );
            return;
        }

        $api_method_name = ( isset( $options[ 'exact' ] ) && ( $options[ 'exact' ] == True ) ) ? 'extract' : 'related';

        $url = NCplugin::NEWSCRED_DOMAIN . '/topics/' . $api_method_name . '?access_key=' . $key . '&query=' . urlencode( $query );

        if ( !empty( $options ) ) {
            $url .= NCplugin::get_request_params( $options );
        }

        try {

            $xml = NCplugin::get( $url );

        } catch ( NC_Plugin_Exception $e ) {

            throw new NC_Plugin_Exception( 'Class:: ' . __CLASS__ . ' Line:: ' . __LINE__ . ' ' . $e->getMessage() );
        }

        return NC_Plugin_Parser::parse( 'topic', $xml, $key );
    }

    /**
     * Searches topics with the given $query
     * @access public
     * @param string $key
     * @param string $query
     * @return array
     * @static
     */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'topic', $query, $options );
    }
}

/**
 * Represents an Article in the NCplugin Platform
 */
class NC_Plugin_Article extends NCplugin {
    public $title;
    public $source;
    public $source_guid;
    public $source_website;
    public $created_at;
    public $published_at;
    public $description;
    public $category;
    public $categories;
    public $link;
    public $thumbnail;
    public $thumbnail_original;
    public $topics;
    public $author;
    public $image_set;

    /**
     * Constructor of the class
     * @access public
     * @param string $key
     * @param string $guid
     */
    public function __construct ( $key = null, $guid = null ) {
        //Initialize all properties to empty string
        foreach ( get_object_vars( $this ) as $property => $value ) {
            $this->$property = '';
        }

        $this->module = 'article';
        $this->key = $key;
        $this->guid = $guid;

        if ( !empty( $key ) && !empty( $guid ) ) {

            $this->populate();
            //supporting legacy code
            $this->source_guid = $this->source->guid;
            $this->source_website = $this->source->website;
        }
    }

    /**
     * Get images related to the Article
     * @access public
     * @param array $options
     * @return arrat
     */
    public function get_related_images ( $options = array() ) {
        return $this->get_related_stuff( 'image', $options );
    }

    /**
     * Searches article with the $query
     * @access public
     * @param string $key
     * @param string $query
     * @param array $options
     * @return array
     * @static
     */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'article', $query, $options );
    }

    /**
     * return the apicall baesd on options
     * @static
     * @param $key
     * @param array $options
     * @return string
     */
    public static function api_call ( $key, $options = array() ) {
        return parent::_api_call( $key, 'article', $options );
    }

    /**
     * Searches article with url
     * @static
     * @param $key
     * @param $url
     * @return array
     */
    public static function search_by_url ( $key, $url ) {
        return parent::_search_by_url( $key, 'article', $url );
    }


    /**
     * Search stories(cluster of articles) based on the $query
     * @static
     * @param $key
     * @param $query
     * @param array $options
     * @return array
     */
    public static function search_stories ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'story', $query, $options );
    }


}

/**
 * Represents an author in the NCplugin Platform
 */
class NC_Plugin_Author extends NCplugin {

    public $last_name;
    public $first_name;

    /**
     * Constructor of the class
     * @access public
     * @param string $key
     * @param string $guid
     */
    public function __construct ( $key = null, $guid = null ) {
        //Initialize all properties to empty string
        foreach ( get_object_vars( $this ) as $property => $value ) {
            $this->$property = '';
        }

        $this->module = 'author';
        $this->key = $key;
        $this->guid = $guid;

        if ( !empty( $key ) && !empty( $guid ) ) {
            $this->populate();
        }
    }

    /**
     * Search authors with the $query
     * @access public
     * @param string $key
     * @param string $query
     * @return <type>
     * @static
     */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'author', $query, $options );
    }
}

/**
 * Represents a source in the NCplugin Platform
 */
class NC_Plugin_Category extends NCplugin {
    public $name;

    /**
     * Constructor of the class
     * @access public
     * @param string $key
     * @param string $name
     */
    public function __construct ( $key, $name ) {
        $this->module = 'category';

        $this->key = $key;
        $this->name = $name;
    }

    public function __call ( $method_name, $arguments ) {
        switch ( $method_name ) {

            case 'getRelatedStories':
                $name = 'story';
                break;
            case 'getRelatedSources':
                $name = 'source';
                break;
            case 'getRelatedImages' :
                $name = 'image';
                break;

            /*Raise error when no matching method name found.*/
            default:
                trigger_error( 'Call to undefined method: ' . __CLASS__ . '::' . $method_name . '() in ' . __FILE__ . ' on line ' . __LINE__,
                               E_USER_ERROR );
        }

        if ( is_array( $arguments ) && !empty( $arguments ) )
            $options = $arguments[ 0 ];

        else $options = array();

        return $this->get_related_stuff( $name, $options );
    }
}

/**
 * Represents a Source in NCplugin Platform
 */
class NC_Plugin_Source extends NCplugin {
    public $name;
    public $is_blog;
    public $website;
    public $media_type;
    public $frequency;
    public $country;
    public $description;
    public $circulation;
    public $thumbnail;

    /**
     * Constructor of the class
     * @access public
     * @param string $key
     * @param string $guid
     */
    public function __construct ( $key = null, $guid = null ) {
        //Initialize all properties to empty string
        foreach ( get_object_vars( $this ) as $property => $value ) {
            $this->$property = '';
        }

        $this->module = 'source';
        $this->key = $key;
        $this->guid = $guid;

        if ( !empty( $key ) && !empty( $guid ) ) {

            $this->populate();
        }
    }

    public function __to_string () {
        return $this->name;
    }

    /**
     * Searches an author with the $query
     * @param string $key
     * @param string $query
     * @param array $options
     * @return array
     */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'source', $query, $options );
    }

    /**
     * Searches article with url
     * @static
     * @param $key
     * @param $url
     * @return array
     */
    public static function search_by_url ( $key, $url ) {
        return parent::_search_by_url( $key, 'source', $url );
    }

    /**
     * Searches Related sources
     * @param string $key
     * @param string $query
     * @param array $options
     * @return array
     */

    public static function search_related ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'sourceRelated', $query, $options );
    }

}

/*
* Represents Twitter module in NCplugin API
*/
class NC_Plugin_Twitter extends NCplugin {
    public $author_link;
    public $author_name;
    public $title;
    public $link;
    public $thumbnail;
    public $created_at;

    private $has_related_topics = FALSE;
    private $has_related_articles = FALSE;

    /*
    * Searches tweets with the given $query
    * @param string $key
    * @param string $query
    * @param array $options
    * @return array
    */

    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'tweet', $query, $options );
    }
}

/*
 * Represents Image module in NCplugin API
 */
class NC_Plugin_Image extends NCplugin {
    public $guid;
    public $caption;
    public $description;
    public $height;
    public $width;
    public $attribution_link;
    public $attribution_text;
    public $license;
    public $image_medium;
    public $image_small;
    public $image_large;
    public $published_at;
    public $created_at;
    public $source;

    private $has_related_topics = FALSE;
    private $has_related_articles = FALSE;

    /*
    * Searches images with the given $query
    * @param string $key
    * @param string $query
    * @param array $options
    * @return array
    */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'image', $query, $options );
    }
}

/*
 * Represents Video module in NCplugin API
 */
class NC_Plugin_Video extends NCplugin {
    public $title;
    public $caption;
    public $guid;
    public $thumbnail;
    public $embed_code;
    public $published_at;
    public $media_file;
    public $source_name;

    private $has_related_topics = FALSE;
    private $has_related_articles = FALSE;

    /*
    * Searches videos with the given $query
    * @param string $key
    * @param string $query
    * @param array $options
    * @return array
    */
    public static function search ( $key, $query, $options = array() ) {
        return parent::_search( $key, 'video', $query, $options );
    }
}