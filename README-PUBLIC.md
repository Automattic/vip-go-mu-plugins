# VIP MU Plugins

This is the complete MU plugins folder for the WordPress.com VIP next generation hosting platform, assembled without submodules for convenience. 

**If you wish to issue a pull request for code here, please do so on [Automattic/vip-go-mu-plugins](https://github.com/Automattic/vip-go-mu-plugins/).**

## PHPDoc

You can find selective PHPDoc documentation here: https://automattic.github.io/vip-go-mu-plugins/

## Tests

**To run PHP linting locally** on OSX or Unix/Linux (no setup required, beyond having PHP CLI installed):

```bash
cd /path/to/mu-plugins/
make lint
```

**To set up PHPUnit locally** (requires a working WordPress development environment, specifically PHP and MySQL):

Using [VVV](https://varyingvagrantvagrants.org/):

```bash
vagrant ssh
```

Navigate to your `wp-content` folder and clone this repo into `mu-plugins`

```bash
vagrant@vvv:/wp-content$ git clone https://github.com/Automattic/vip-go-mu-plugins.git mu-plugins
```

Setup the WordPress tests
```bash
vagrant@vvv:/wp-content$ mu-plugins/bin/install-wp-tests.sh %empty_DB_name% %db_user% %db_name%
```

Notes:

* You need to replace the `%placeholder%` strings above with sensible values
* You DO need an empty DB, because the contents of this DB WILL get trashed during testing

**To run PHPUnit tests locally**:

```bash
phpunit
```
