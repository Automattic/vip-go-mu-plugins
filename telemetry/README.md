# VIP Telemetry Library

For most use cases, you should use the `Telemetry` class, which sends events to Tracks, an event tracking tool used to understand user behavior within Automattic. Events are sent after the response has been sent to the browser.

## How to use

In this simple functional example, we track changes to the status of posts. Note that `myplugin_` is passed as the first argument to the `Telemetry` constructor; it will be prepended to all event names.

```php
use Automattic\VIP\Telemetry\Telemetry;

function track_post_status( $new_status, $old_status, $post ) {
	$telemetry = new Telemetry( 'myplugin_' );

	$telemetry->record_event( 'post_status_changed', [
		'new_status' => $new_status,
		'old_status' => $old_status,
		'post_id'       => $post->ID,
	] );
}
add_action( 'transition_post_status', 'track_post_status', 10, 3 );
```

To reduce code duplication, you may wish to create a class to encapsulate tracking logic:

```php
class MyPluginTracker {
	protected Telemetry $telemetry;

	public function __construct() {
		$this->telemetry = new Telemetry( 'myplugin_' );
	}

	public function init() {
		add_action( 'transition_post_status', [ $this, 'track_post_status' ], 10, 3 );
	}

	public function track_post_status( $new_status, $old_status, $post ) {
		$this->telemetry->record_event( 'post_status_changed', [
			'new_status' => $new_status,
			'old_status' => $old_status,
			'post'       => (array) $post,
		] );
	}
}
```

If you would like to provide global properties to all events, you can pass an array of properties to the `Telemetry` constructor:

```php
new Telemetry( 'myplugin_', [ 'plugin_version' => '1.2.3' ] );
```

## wp-admin page views

A `vip_wordpress_admin_page_view` event is recorded when a user who can edit posts views a supported wp-admin screen. This only happens on VIP production environments that are not sandboxed or FedRAMP and use an integration that sets `$enable_admin_page_tracking`. See `Admin_Page_View_Tracker`.

To record page views on more screens, add their hook suffixes with the `vip_telemetry_admin_page_view_screens` filter:

```php
add_filter( 'vip_telemetry_admin_page_view_screens', function ( $screens ) {
	$screens[] = 'toplevel_page_my-plugin';
	return $screens;
} );
```
