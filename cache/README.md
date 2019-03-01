## Why might I want to customize how content is cached?
Web development, like anything, is often about balances and compromises. When it comes to serving dynamic content within a CMS, there are two ends of the spectrum: 
1.  Generate each page each time a user request comes. This is great for ensuring the user always has the most up-to-date copy but comes with a performance penalty as the code and database calls run each time.  
2. Generate a page once and then cache the content for future requests. This option offers fantastic performance but comes with the challenge requests displaying outdated content until it is regenerated manually or automatically

Thankfully most modern platforms including VIP Go [handle](https://vip.wordpress.com/documentation/vip-go/caching-on-vip-go/) these trade-offs for you, ensuring a good mix of performance and up-to-date-content being served.  However, there are times when the caching behaviour needs to be customized for a particular use case. This is where the Cache Personalization module comes in.

There are three different approaches you can take to customize the caching depending on your use case:
1. No Cache(also known as cache-busting). This should be used sparingly due to the performance implications.
2. Cache by Segment. This approach places each user into a "group" an serves up a variation of the content specific to that group. Typical use cases are A/B testing, GDPR-specific content, Grouping users (by type, region, language), No-auth paywalls
3. Cache by authorization. This is like the above option except the group details are encrypted. This is useful if you are trying implement a paywall or authenticated content


## Segmentation Example: A new beta feature on a post
In this example we will show how to display a like button below each post for users who have opted into the beta site.


### Step 1: Register Groups
The first step is to indicate to the caching system what groups you want to vary on. You can have multiple groups and multiple values within those groups, but for this example we'll just have a beta group:
`Vary_Cache::register_group( 'beta' );`


### Step 2: Assign the user into a the beta group when they opt-in

The next step is to assign the segment within a group. This currently must be done via a user-initiated action like a POST so that it is run on an uncached resource  
```
$beta = sanitize_key( $_POST['beta'] );
if ( 'yes' === $beta )  {
    Vary_Cache::set_group_for_user( 'beta', 'yes' );
}
```


### Step 3: Show the custom beta feature 
The last step is to chck if the user is in the group and show them the custom content if so
```
if( Vary_Cache::is_user_in_group_segment( 'beta', 'yes' ) ) {
    echo '<p><small><a href="#"❤️ Like this post</small></p>';
}
```
Obviously there will be much more logic behind what happens when the user likes a post, but that's out of the scope of the caching functionality



## Complete Segmentation example
```
<?php

namespace My\Application;

require_once( WP_CONTENT_DIR . '/mu-plugins/cache/class-vary-cache.php' );

use Automattic\VIP\Cache\Vary_Cache;

Vary_Cache::register_groups( [ 'beta' ] );

add_action( 'init', function() {
	if ( isset( $_POST['beta'] ) ) {
		$beta = sanitize_key( $_POST['beta'] );
		if ( 'yes' === $beta )  {
			Vary_Cache::set_group_for_user( 'beta', 'yes' );
		}

		// Redirect back to the same page
		add_action( 'vip_vary_cache_did_send_headers', function() {
			wp_safe_redirect( add_query_arg( '' ) );
			exit;
		} );
	}
} );



add_filter( 'body_class', function( $classes ) {
	$in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if( $in_beta ) {
		return array_merge( $classes, array( 'beta' ) );
	}

	return $classes;
} );

add_filter( 'the_content', function( $content ) {
	$in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if( ! $in_beta ) {
		return $content;
	}

	$like_option = '<p><small><a href="#"❤️ Like this post</small></p>';

	return $content  . $like_option;
} );

add_filter( 'wp_footer', function () {
	$in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if( $in_beta ) {
		?>
		<style>
			body h1 {
				background-color: darkgoldenrod;
			}
		</style>
		<?php
		return;
	}
	?>
	<div style="position:fixed; bottom:0; left:0; right:0; padding:20px; background:#333; color:#e5e5e5;">
		<form method="POST">
			We have a new site you can try out
			<button name="beta" value="yes">Try it out</button>
		</form>
	</div>
	<?php
} );
```
