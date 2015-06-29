<?php	
	if ( defined('ABSPATH') ) {
		require_once( ABSPATH . '/wp-includes/class-snoopy.php');
	} else {
		require_once( '../../../wp-includes/class-snoopy.php');
	}
	
   $snoopy = new Snoopy;
   $snoopy->fetch('http://www.bravenewcode.com/custom/wptouch-news.php?type=wordtwit');
   $response = $snoopy->results;
   
   echo '<h3>Latest News</h3>' . $response;
?>
