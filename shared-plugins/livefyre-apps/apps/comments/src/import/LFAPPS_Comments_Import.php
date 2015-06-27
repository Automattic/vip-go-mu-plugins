<?php
interface LFAPPS_Comments_Import {

    /**
     *
     */
    static function skip_trackback_filter($c);
    
    /**
     *
     */
    function admin_import_notice();

    /**
     *
     */
    function begin();

    /**
     *
     */
    function check_activity_map_import();

    /**
     *
     */
    function check_import();

    /**
     *
     */
    function check_utf_conversion();

    /**
     *
     */
    function comment_data_filter( $comment, $test=false );

    /**
     *
     */
    function extract_xml( $siteId, $offset=0 );

    /**
     *
     */
    function filter_unicode_longs( $long );

    /**
     *
     */
    function report_error( $message );

    /**
     *
     */
    function unicode_code_to_utf8( $unicode_list );

    /**
     *
     */
    function utf8_to_unicode_code( $utf8_string );

    /**
     *
     */
    function wrap_xml( &$articles );

}
