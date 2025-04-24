# VIP Telemetry Library

For most use cases, you should use the `Telemetry` class, which will send events to all configured telemetry systems:

- Tracks is an event tracking tool used to understand user behavior within Automattic.
- Pendo is a product analytics tool used by WPVIP to understand user behavior on WordPress admin screens. Note that Pendo is completely disabled in some environments; see `Pendo::is_pendo_enabled_for_environment()` for details.

If you would like to configure and use the telemetry systems individually, see the section below.

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

## Using Tracks and Pendo individually

If you wish, you can configure and use `Tracks` and `Pendo` classes individually. They have the same API as the `Telemetry` class.

```php
use Automattic\VIP\Telemetry\Pendo;
use Automattic\VIP\Telemetry\Tracks;

new Pendo( 'myplugin_', [ /* global properties */ ] );
new Tracks( 'myplugin_', [ /* global properties */ ] );
```

## Pendo Page/Feature events

Pendo Page/Feature events are client-side events that are sent to Pendo when a user interacts with a feature or page in the WordPress admin dashboard. It is automatically enabled in environments that support it and have not opted-out. Configuration is largely done via the Pendo platform.

**Note:** While it is possible to send Pendo "Track events" via the client-side library, we should always send them server-side via `Telemetry::record_event()` or `Pendo::record_event()` for consistency and safety. We use a customized version of the Pendo library with a custom browser global that may not follow code examples found in their documentation.
