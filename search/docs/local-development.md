# Setup

In order to develop for VIP Search several dependencies need to be installed.

1. Install our Lando-based [vip-go-mu-dev environment](https://github.com/Automattic/vip-go-mu-dev). Follow the installation process there.
1. StatsD instance:
	- Clone https://github.com/statsd/statsd.
	- Edit exampleConfig.js in that directory to this:
	```
		{
		port: 8125
		, backends: [ "./backends/console" ]
		}
	```
	- Run StatsD - node stats.js exampleConfig.js
	- Add to `vip-go-dev-mu-dev/mu-plugins/000-debug/debug-mode.php`
	- Add `define( 'VIP_STATSD_HOST', 'host.docker.internal' );`
	- Add `define( 'VIP_STATSD_PORT', 8125 );`

1. Generate fake data (either via wp fixtures or FakerPress)
	1. The lando environment provides a convenience command `lando add-fake-data` which uses the fixtures configuration found in [/configs/fixtures/test_fixtures.yml](https://github.com/Automattic/vip-go-mu-dev/blob/master/configs/fixtures/test_fixtures.yml). See that YAML for exact details
1. Index the data.
	1. `lando wp vip-search index --setup`
