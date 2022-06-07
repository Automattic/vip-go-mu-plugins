# VIP Cache Personalization API

The [VIP CDN](https://docs.wpvip.com/technical-references/caching/) has been designed for low-latency and high performance and achieves that by serving the same cached content to as many users as possible.

For cases where a one-size-fits-all approach doesn't work, the [VIP Cache Personalization API](https://docs.wpvip.com/technical-references/caching/the-vip-cache-personalization-api/) can help customize the caching behaviour of the CDN while still leveraging the power and scale that it provides.

In this README, we go through some real-world examples on how to use the Cache Personalization API in your applications. Before reading ahead, make sure you've [read the docs](https://docs.wpvip.com/technical-references/caching/the-vip-cache-personalization-api/) to get a better understanding of the API and its capabilities.

## Tutorial (Cache Segmentation): Beta Opt-in

In this example, we're going to implement a Beta Opt-in for our site.

We've built a new Like button for posts that we want to test out with a small group of users. To do so, we'll allow users to optionally enable the new Like button (and any other beta features we add in the future).

(If you'd prefer to just go straight to the code, you can find the complete example here: https://github.com/Automattic/vip-go-mu-plugins/blob/master/cache/examples/segmentation-beta/beta.php)

### Step 0: Planning

Let's get a clear idea of our requirements:

- We'll have two segments:
  - Users that **have** opted in to the Beta; and
  - Users that **have not** opted in to the Beta. (By default, all users will be in this group.)
- We'll group them in a cache group called `beta`.
- For users not in the beta, we'll show a floating button at the bottom of the page, which allows them to opt in.
- For users in the beta, we'll show the new Like button at the bottom of all posts.

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

We need to implement a call-to-action for our Beta; a simple floating button at the bottom of the page will do. We'll user the `wp_footer` action to render it for users not already in the beta (we can use the `Vary_Cache::is_user_in_group_segment` helper to verify that.)

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

### Step 4: Enable Beta Features

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

### Detailed API Documentation
For the complete list of the API methods and their functionality, see the [Vary_Cache API documentation](https://automattic.github.io/vip-go-mu-plugins/classes/Automattic.VIP.Cache.Vary_Cache.html)

---

## Tutorial (Cache Segmentation): Maintenance Mode & Automated Scans

In this example, we have an application in pre-launch stage and access is restricted using [the Maintenance Mode plugin](https://github.com/Automattic/maintenance-mode-wp). We're working with an external provider to run automated scans on our environment (e.g. Load Testing, Content Analysis, etc.). However, the service is currently blocked since the Maintenance Mode plugin only allows authenticated requests to the environment.

If the service provider can send a specific cookie header along with their requests, we can use Cache Segmentation to allow them to bypass the Maintenance Mode requirement.

**Note:** This cannot be used alongside the `Vary_Cache` helper library and may result unexpected results.

(If you'd prefer to just go straight to the code, you can find the complete example here: https://github.com/Automattic/vip-go-mu-plugins/blob/master/cache/examples/segmentation-maintenance-mode-bypass/vip-config.php)

### Step 0: Planning

Let's understand our requirements:

- We want the environment restricted using the Maintenance Mode plugin:
 - Unauthenticated users and users without the correct permissions should see the Maintenance Mode page.
 - Authenticated users with the correct permissions should be able to access the environment.
 - Requests from our service provider with the correct secret should be able to access the environment.

### Step 1: Generate a Secret

We'll need to generate a secret value to share with our service provider to allow the bypass. Note that this shouldn't be shared with anyone else.

Using the `openssl` tool, we can generate a random secret:

```
openssl rand -hex 40
```

Feel free to adjust the length and strength of the secret based on your needs.

### Step 2: Prepare the code

Note that because the Maintenance Mode constant is set super early in the WordPress boot process (usually `vip-config/vip-config.php`), we can't use the `Vary_Cache` helper class as it relies on WordPress hooks that are run much later in the lifecycle.

Instead, we'll add our code to `vip-config/vip-config.php`. Let's find the line where our constant is set and work from there:

```
define( 'VIP_MAINTENANCE_MODE', true );
```

### Step 3: Add a wrapper function

To keep things clean, we'll use a wrapper function to encapsulate variables and our logic:

```
function x_maybe_enable_maintenance_mode() {

}

x_maybe_enable_maintenance_mode();
```

We'll execute the function right away as well. Feel free to rename it to suit your naming conventions.

### Step 4: Add the secret

Let's add the the secret we generated earlier to a variable within the function:

```
// Generate using something like `openssl rand -hex 40`.
// This is a secret value shared with our service provider.
$maintenance_bypass_secret = 'this-is-a-secret-value';
```

The example above uses a placeholder value; please update to the value you generated.

### Step 5: Verify and act on incoming requests

Let's implement the remaining code.

Because we have a few conditions to check, we'll need a logic block to verify incoming requests. By default, we want Maintenance Mode to always be on:

```
// Enabled by default but disabled conditionally below.
$enable_maintenance_mode = true;
```

Now, if the cookie is set and includes the correct secret, we can skip enabling Maintenance Mode:

```
if ( isset( $_COOKIE['vip-go-seg'] )
	&& hash_equals( $maintenance_bypass_secret, $_COOKIE['vip-go-seg'] ) ) {
	// Don't enable if the request has our secret key.
	$enable_maintenance_mode = false;
}
```

Note the use of `hash_equals` to safely verify the secret and avoid [timing attacks](https://en.wikipedia.org/wiki/Timing_attack).

Now, we can set our constant based on the outcome of the tests:

```
// Enable maintenance mode if needed.
define( 'VIP_MAINTENANCE_MODE', $enable_maintenance_mode );
```

And finally, we need to send a signal to the VIP CDN that this request should be varied by segment:

```
// Make sure our reverse proxy respects the cookie.
header( 'Vary: X-VIP-Go-Segmentation', false );
```

### Step 6: Run the scans

With our code in place, we can now make requests that bypass Maintenance Mode. To do so, we can include a Cookie header with the secret:

```
curl https://example.com -H 'Cookie: vip-go-seg=this-is-a-secret-value'
```

`this-is-a-secret-value` should match the secret (and the value of `$maintenance_bypass_secret` variable).

Requests with the correct cookie will bypass Maintenance Mode and still benefit from cached requests. Requests without an incorrect secret in the cookie or unprivileged users will hit the Maintenance Mode page.

### The Final Result

You can see the completed, working example here: https://github.com/Automattic/vip-go-mu-plugins/blob/master/cache/examples/segmentation-maintenance-mode-bypass/vip-config.php
