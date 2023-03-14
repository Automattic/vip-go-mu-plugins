# Cron Control #
**Contributors:** [automattic](https://profiles.wordpress.org/automattic/), [ethitter](https://profiles.wordpress.org/ethitter/)  
**Tags:** cron, cron control, concurrency, parallel, async  
**Requires at least:** 5.1  
**Tested up to:** 5.8  
**Requires PHP:** 7.4  
**Stable tag:** 3.1  
**License:** GPLv2 or later  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

Execute WordPress cron events in parallel, with custom event storage for high-volume cron.

## Description ##

This plugin sets up a custom cron table for better events storage. Using WP hooks, it then intercepts cron registration/retrieval/deletions. There are two additional interaction layers exposed by the plugin - WP CLI and the REST API.

By default the plugin disables default WP cron processing. It is recommended to use the cron control runner to process cron: https://github.com/Automattic/cron-control-runner. This is how we are able to process cron events in parallel, allowing for high-volume and reliable cron.

## Installation ##

1. Define `WP_CRON_CONTROL_SECRET` in `wp-config.php`, set to `false` to disable the REST API interface.
1. Upload the `cron-control` directory to the `/wp-content/mu-plugins/` directory
1. Create a file at `/wp-content/mu-plugins/cron-control.php` to load `/wp-content/mu-plugins/cron-control/cron-control.php`
1. (optional) Set up the the cron control runner for event processing.

## Frequently Asked Questions ##

### Deviations from WordPress Core ###

* Cron jobs are stored in a custom table and not in the `cron` option in wp_options. As long relevent code uses WP core functions for retrieving events and not direct SQL, all will stay compatible.
* Duplicate recurring events with the same action/args/schedule are prevented. If multiple of the same action is needed on the same schedule, can add an arbitrary number to the args array.
* When the cron control runner is running events, it does so via WP CLI. So the environment can be slightly different than that of a normal web request.
* The cron control runner can process multiple events in parallel, whereas core cron only did 1 at a time. By default, events with the same action will not run in parallel unless specifically granted permission to do so.

### Adding Internal Events ###

**This should be done sparingly as "Internal Events" bypass certain locks and limits built into the plugin.** Overuse will lead to unexpected resource usage, and likely resource exhaustion.

In `wp-config.php` or a similarly-early and appropriate place, define `CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS` as an array of arrays like:

```
define( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS', array(
array(
	'schedule' => 'hourly',
	'action'   => 'do_a_thing',
	'callback' => '__return_true',
),
) );
```

Due to the early loading (to limit additions), the `action` and `callback` generally can't directly reference any Core, plugin, or theme code. Since WordPress uses actions to trigger cron, class methods can be referenced, so long as the class name is not dynamically referenced. For example:

```
define( 'CRON_CONTROL_ADDITIONAL_INTERNAL_EVENTS', array(
array(
	'schedule' => 'hourly',
	'action'   => 'do_a_thing',
	'callback' => array( 'Some_Class', 'some_method' ),
),
) );
```

Take care to reference the full namespace when appropriate.

### Increasing Event Concurrency ###

In some circumstances, multiple events with the same action can safely run in parallel. This is usually not the case, largely due to Core's alloptions, but sometimes an event is written in a way that we can support concurrent executions.

To allow concurrency for your event, and to specify the level of concurrency, please hook the `a8c_cron_control_concurrent_event_whitelist` filter as in the following example:

```
add_filter( 'a8c_cron_control_concurrent_event_whitelist', function( $wh ) {
$wh['my_custom_event'] = 2;

return $wh;
} );
```

## Development & Testing ##

### Quick and easy testing ###

If you have docker installed, can just run `./__tests__/bin/test.sh`.

### Manual testing setup ###

First, you'll need svn and composer. Example of installing them on a docker container if needed:

```
apk add subversion
wget -q https://getcomposer.org/installer -O - | php -- --install-dir=/usr/bin/ --filename=composer
```

Next change directories to the plugin and set up the test environment:

```
cd wp-content/mu-plugins/cron-control

composer install

# Note that the values below can be different, it is: <db-name> <db-user> <db-pass> [db-host] [wp-version]
./__tests__/bin/install-wp-tests.sh test wordpress wordpress database latest
```

Lastly, kick things off with one command: `phpunit`

### Readme & language file updates ###

Will need `npm`. Example of installing on a docker container: `apk add --update npm`

Run `npm install` then `npm run build` to create/update language files and to convert `readme.txt` to `readme.md` if needed.

## Changelog ##

### 3.1 ###
* Update installation process, always ensuring the custom table is installed.
* Swap out deprecated `wpmu_new_blog` hook.
* Ignore archived/deleted/spam subsites during the runner's `list sites` cli command.
* Migrate legacy events from the `cron` option to the new table before deleting the option.
* Delete duplicate recurring events. Runs daily.

### 3.0 ###
* Implement WP cron filters that were added in WP 5.1.
* Cleanup the event's store & introduce new Event() object.
* Switch to a more effecient caching strategy.

### 2.0 ###
* Support additional Internal Events
* Break large cron queues into several caches
* Introduce Golang runner to execute cron
* Support concurrency for whitelisted events

### 1.5 ###
* Convert from custom post type to custom table with proper indices

### 1.0 ###
* Initial release
