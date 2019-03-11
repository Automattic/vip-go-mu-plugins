## Why might I want to customize how content is cached?
Web development, like anything, is often about balances and compromises. When it comes to serving dynamic content within a CMS, there are two ends of the spectrum: 
1.  Generate each page each time a user request comes. This is great for ensuring the user always has the most up-to-date copy but comes with a performance penalty as the code and database calls run each time.  
2. Generate a page once and then cache the content for future requests. This option offers fantastic performance but comes with the challenge requests displaying outdated content until it is regenerated manually or automatically

Thankfully most modern platforms including VIP Go [handle](https://vip.wordpress.com/documentation/vip-go/caching-on-vip-go/) these trade-offs for you, ensuring a good mix of performance and up-to-date-content being served.  However, there are times when the caching behaviour needs to be customized for a particular use case. This is where the Cache Personalization module comes in.

There are three different approaches you can take to customize the caching depending on your use case:
1. No Cache(also known as cache-busting). This should be used sparingly due to the performance implications.
2. Cache by Segment. This approach places each user into a "group" an serves up a variation of the content specific to that group. Typical use cases are A/B testing, GDPR-specific content, Grouping users (by type, region, language), No-auth paywalls
3. Cache by authorization. This is like the above option except the group details are encrypted. This is useful if you are trying implement a paywall or authenticated content


## Tutorial (Cache Segmentation): Beta Opt-in

In this example, we're going to implement a Beta Opt-in for our site.

We've built a new Like button for posts that we want to test out with a small group of users. To do so, we'll allow users to optionally enable the new Like button (and any other beta features we add in the future).

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

To avoid issues with duplicate submissions, we're going to use the [POST-REDIRECT-GET pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get) to send the user back to the page they came from. We'll hook into the VIP action `vip_vary_cache_did_send_headers` and trigger the redirect there. This action is fired after the appropriate cache-related cookies and headers have been set and it's safe to redirect.

```php
// Redirect back to the same page
add_action( 'vip_vary_cache_did_send_headers', function() {
	wp_safe_redirect( add_query_arg( '' ) );
	exit;
} );
```

### Step 3: Enable Beta Features 

Now that users can opt-in to the beta, we need to actually enable the beta features for them. In our case, we want to display a Like button to all posts. We'll use the `the_content` filter, and the `Vary_Cache::is_user_in_group_segment` helper to verify if they are a beta user.

```
add_filter( 'the_content', function( $content ) {
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if ( $is_user_in_beta ) {
		$like_button = '<p><a href="#" class="like-button">❤️ Like this post</a></p>';
		$content .= $like_button;
	}

	return $content;
} );
```

Note: For the purpose of this exercise, we've kept things pretty simple and the Like button isn't actually wired up. There would be more code behind the scenes if we were actually implement it.

## Complete Segmentation example

TODO: move this to examples/beta.php

```
<?php

namespace My\Application;

// Load the VIP Vary_Cache class
require_once( WP_CONTENT_DIR . '/mu-plugins/cache/class-vary-cache.php' );

use Automattic\VIP\Cache\Vary_Cache;

// Register the `beta` group
Vary_Cache::register_group( 'beta' );

// Display the opt-in banner / button.
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

// Handle the opt-in request.
add_action( 'init', function() {
	if ( isset( $_POST['beta-optin'] ) ) {
		$beta = sanitize_key( $_POST['beta-optin'] );
		if ( 'yes' === $beta )  {
			Vary_Cache::set_group_for_user( 'beta', 'yes' );
		}

		// Redirect back to the same page (per the POST-REDIRECT-GET pattern)
		add_action( 'vip_vary_cache_did_send_headers', function() {
			wp_safe_redirect( add_query_arg( '' ) );
			exit;
		} );
	}
} );

// Display the Like button for beta users.
add_filter( 'the_content', function( $content ) {
	$is_user_in_beta = Vary_Cache::is_user_in_group_segment( 'beta', 'yes' );

	if ( $is_user_in_beta ) {
		$like_button = '<p><a href="#" class="like-button">❤️ Like this post</a></p>';
		$content .= $like_button;
	}

	return $content;
} );
```
