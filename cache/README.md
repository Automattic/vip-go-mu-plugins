# VIP Cache Personalization API

The [VIP CDN](https://vip.wordpress.com/documentation/vip-go/caching-on-vip-go/) has been designed for low-latency and high performance and achieves that by serving the same cached content to as many users as possible.

For cases where a one-size-fits-all approach doesn't work, the [VIP Cache Personalization API](https://vip.wordpress.com/?p=33575) can help customize the caching behaviour of the CDN while still leveraging the power and scale that it provides.

In this README, we go through some real-world examples on how to use the Cache Personalization API in your applications. Before reading ahead, make sure you've [read the docs](https://vip.wordpress.com/?p=33575) to get a better understanding of the API and its capabilities.

## Tutorial (Cache Segmentation): Beta Opt-in

In this example, we're going to implement a Beta Opt-in for our site.

We've built a new Like button for posts that we want to test out with a small group of users. To do so, we'll allow users to optionally enable the new Like button (and any other beta features we add in the future).

(If you'd prefer to just go straight to the code, you can find the complete example here: https://github.com/Automattic/vip-go-mu-plugins/blob/master/cache/examples/segmentation-beta/beta.php)

### Step 0: Planning

Let's get a clear idea of our requirements:

1. We'll have two segments:
 1. Users that **have** opted in to the Beta; and
 1. Users that **have not** opted in to the Beta. (By default, all users will be in this group.)
1. We'll group them in a cache group called `beta`.
1. For users not in the beta, we'll show a floating button at the bottom of the page, which allows them to opt in.
1. For users in the beta, we'll show the new Like button at the bottom of all posts.

### Step 1: Prepare the code

Let's add this to a new file in `client-mu-plugins` called `beta.php` where we'll put all our code. We'll need to include the PHP library to make it easier to integrate:

```php
<?php

// Load the VIP Vary_Cache class
require_once( WPMU_PLUGIN_DIR . '/cache/class-vary-cache.php' );

use Automattic\VIP\Cache\Vary_Cache;
```

You're not required to put the code in `client-mu-plugins` or structure it way suggested here. Feel free to follow the organizational / structural approaches that your team prefers.

### Step 2: Register Group

For this implementation, we just need one group: `beta`. The API provides a simple function to register a group via `Vary_Cache::register_group`. Let's add this to our `beta.php` file:

```php
Vary_Cache::register_group( 'beta' );
```

This sends a hint to our caching backend (via a `Vary` header) that this and future requests may need to be varied by our segmentation cookie.

### Step 2: Display the Opt-in Button

We need a implement a call-to-action for our Beta; a simple floating button at the bottom of the page will do. We'll user the `wp_footer` action to render it for users not already in the beta (we can use the `Vary_Cache::is_user_in_group_segment` helper to verify that.)

```php
add_filter( 'wp_footer', function () {
	// If the user is already in the beta group, don't show the opt-in.
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );
	if ( $is_user_in_beta ) {
		return;
	}

	// If the user isn't in the beta group, let's show the opt-in button.
	?>
	<div style="position:fixed; bottom:0; left:0; right:0; padding:20px; background:#333; color:#e5e5e5;">
		<form method="POST">
			We have some new features that we need your help testing!

			<button name="beta-optin" value="yes">Enable them!</button>
		</form>
	</div>
	<?php
} );
```

### Step 3: Handle the Opt-in

When a user clicks on our "Enable Beta" button, they submit a POST request to the same page. We need to intercept that request and assign the user into the beta group. We'll hook into `init` and use the `Vary_Cache::set_group_for_user` helper to assign the user to that group and segment. Note that `yes` is just a simple identifier for users in the beta segment.

```
add_action( 'init', function() {
	$beta = sanitize_key( $_POST['beta-optin'] );
	if ( 'yes' === $beta )  {
    	Vary_Cache::set_group_for_user( 'beta', 'yes' );
	}
} );
```

To avoid issues with unexpected behaviour on refresh, we're going to use the [POST-REDIRECT-GET pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get) to send the user back to the page they came from. We'll hook into the action `vip_vary_cache_did_send_headers` and trigger the redirect there. This action is fired after the appropriate cache-related cookies and headers have been set and it's safe to redirect. Redirecting before this hook is fired may cause the cache segmentation to not work.

```php
// Redirect back to the same page
add_action( 'vip_vary_cache_did_send_headers', function() {
	wp_safe_redirect( add_query_arg( '' ) );
	exit;
} );
```

### Step 3: Enable Beta Features

Now that users can opt-in to the beta, we need to actually enable the beta features for them. In our case, we want to display a Like button to all posts. We'll use the `the_content` filter, and the `Vary_Cache::is_user_in_group_segment` helper to verify if they are a beta user.

```php
add_filter( 'the_content', function( $content ) {
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if ( $is_user_in_beta ) {
		$like_button = '<p><a href="#" class="like-button">❤️ Like this post</a></p>';
		$content .= $like_button;
	}

	return $content;
} );
```

Note: For the purpose of this exercise, we've kept things pretty simple and the Like button isn't actually wired up. There would be more code behind the scenes if we were to actually implement it.

### The Final Result

You can see the completed, working example here: https://github.com/Automattic/vip-go-mu-plugins/blob/master/cache/examples/segmentation-beta/beta.php
