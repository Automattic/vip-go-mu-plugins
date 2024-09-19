# VIP Telemetry Library

## Tracks

Tracks is an event tracking tool used to understand user behaviour within Automattic. This library provides a way for plugins to interact with the Tracks system and start recording events.

### How to use

Example:

```php
use Automattic\VIP\Telemetry\Tracks;

function track_post_status( $new_status, $old_status, $post ) {
	$tracks = new Tracks( 'myplugin_' );

	$tracks->record_event( 'post_status_changed', [
		'new_status' => $new_status,
		'old_status' => $old_status,
		'post_id'       => $post->ID,
	] );
}
add_action( 'transition_post_status', 'track_post_status', 10, 3 );
```

The example above is the most basic way to use this Tracks library. The client plugin would need a function to hook into the WordPress action they want to track and that function has to instantiate and call the `record_event` method from the `Tracks` class. This can be abstracted further to reduce code duplication by wrapping the functions in a class for example:

```php
namespace MyPlugin\Telemetry;

use Automattic\VIP\Telemetry\Tracks;

class MyPluginTracker {
	protected $tracks;

	public function __construct() {
		$this->tracks = new Tracks( 'myplugin_' );
	}

	public function register_events() {
		add_action( 'transition_post_status', [ $this, 'track_post_status' ], 10, 3 );
	}

	public function track_post_status( $new_status, $old_status, $post ) {
		$this->tracks->record_event( 'post_status_changed', [
			'new_status' => $new_status,
			'old_status' => $old_status,
			'post'       => (array) $post,
		] );
	}
}
```

With the class above, you can then initiate event tracking in the main plugin file with these lines:

```php
$tracker = new MyPluginTracker();
$tracker->register_events();
```

If necessary to provide global properties to all events, you can pass an array of properties to the `Tracks` constructor:

```php
$this->tracks = new Tracks( 'myplugin_', [
    'plugin_version' => '1.2.3',
] );
```