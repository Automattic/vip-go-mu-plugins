# Setup

In order to develop for VIP Search several dependencies need to be installed.

1. Install our Lando-based [vip-go-mu-dev environment](https://github.com/Automattic/vip-go-mu-dev). Follow the installation process there.
1. StatsD instance:
    - `lando logs` or `lando logs -f` to see StatsD output.
1. Generate fake data (either via wp fixtures or FakerPress)
	- The lando environment provides a convenience command `lando add-fake-data` which uses the fixtures configuration found in [/configs/fixtures/test_fixtures.yml](https://github.com/Automattic/vip-go-mu-dev/blob/master/configs/fixtures/test_fixtures.yml). See that YAML for exact details
1. Index the data.
	- `lando wp vip-search index --setup`
