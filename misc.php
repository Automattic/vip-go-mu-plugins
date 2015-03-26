<?php

// Cleaner permalink options
add_filter( 'got_url_rewrite', '__return_true' );

// Activate concatenation
require __DIR__ .'/http-concat/jsconcat.php';
require __DIR__ .'/http-concat/cssconcat.php';
