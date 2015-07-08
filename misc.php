<?php
/*
Plugin Name: VIP Hosting Miscellaneous
Description: Handles CSS and JS concatenation, and Nginx compatibility
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Cleaner permalink options
add_filter( 'got_url_rewrite', '__return_true' );

// Activate concatenation
require __DIR__ .'/http-concat/jsconcat.php';
require __DIR__ .'/http-concat/cssconcat.php';
