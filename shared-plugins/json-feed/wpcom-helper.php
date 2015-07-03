<?php

/**
 * Some VIP sites are still using the old method for generating
 * rewrite rules, so we need to make sure they're properly filtered on
 */
function json_feed_rewrite_rules( $rules ) {
	global $default_rewrite_rules;
	
	if( empty( $rules ) ) {
		if( empty( $default_rewrite_rules ) )
			$default_rewrite_rules = array();
		$rules = $default_rewrite_rules;
	}
	
	$add_rules = array ( 
		'feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?&feed=$matches[1]&jsonp=$matches[3]&date_format=$matches[5]&remove_uncategorized=$matches[7]',
			
		'(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?&feed=$matches[1]&jsonp=$matches[3]&date_format=$matches[5]&remove_uncategorized=$matches[7]',
		
		'comments/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?&feed=$matches[1]&withcomments=1&jsonp=$matches[3]&date_format=$matches[5]&remove_uncategorized=$matches[7]',
			
		'comments/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?&feed=$matches[1]&withcomments=1&jsonp=$matches[3]&date_format=$matches[5]&remove_uncategorized=$matches[7]',
		
		'search/(.+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?s=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'search/(.+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?s=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'category/(.+?)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?category_name=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'category/(.+?)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?category_name=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'tag/(.+?)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?tag=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'tag/(.+?)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?tag=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'author/([^/]+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?author_name=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'author/([^/]+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?author_name=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]&jsonp=$matches[6]&date_format=$matches[8]&remove_uncategorized=$matches[10]',
		
		'([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&feed=$matches[4]&jsonp=$matches[6]&date_format=$matches[8]&remove_uncategorized=$matches[10]',
		
		'([0-9]{4})/([0-9]{1,2})/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]&jsonp=$matches[5]&date_format=$matches[7]&remove_uncategorized=$matches[9]',
		
		'([0-9]{4})/([0-9]{1,2})/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&feed=$matches[3]&jsonp=$matches[5]&date_format=$matches[7]&remove_uncategorized=$matches[9]',
		
		'([0-9]{4})/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'([0-9]{4})/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$'
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[3]&date_format=$matches[5]&remove_uncategorized=$matches[7]',
		
		'[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]&jsonp=$matches[7]&date_format=$matches[9]&remove_uncategorized=$matches[11]',
		
		'([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&feed=$matches[5]&jsonp=$matches[7]&date_format=$matches[9]&remove_uncategorized=$matches[11]',
		
		'[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/([^/]+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'.+?/attachment/([^/]+)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'.+?/attachment/([^/]+)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?attachment=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'(.+?)/feed/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?pagename=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
		
		'(.+?)/(json)(/jsonp/([^/]+))?(/date_format/([^/]+))?(/remove_uncategorized/([^/]+))?/?$' 
			=> 'index.php?pagename=$matches[1]&feed=$matches[2]&jsonp=$matches[4]&date_format=$matches[6]&remove_uncategorized=$matches[8]',
	);
	return array_merge( $add_rules, (array) $rules );
}
add_filter( 'pre_transient_rewrite_rules', 'json_feed_rewrite_rules' );