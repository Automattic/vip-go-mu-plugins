<?php

// Enable Bylines for select themes
$wpcom_vip_byline_themes = array(
    'premium/bromley',
);

if ( in_array( get_stylesheet(), $wpcom_vip_byline_themes ) )
    add_filter( 'byline_auto_filter_author', '__return_true' );
