# VIP MU Plugins [![Build Status](https://magnum.travis-ci.com/Automattic/vipv2-mu-plugins.svg?token=saKYXPvcnyNUH8ChL4di&branch=master)](https://magnum.travis-ci.com/Automattic/vipv2-mu-plugins)

This is the complete MU plugins folder for the WordPress.com VIP next generation hosting platform.

## Tests

**To run PHP linting locally** on OSX or Unix/Linux (no setup required, beyond having PHP CLI installed):

```bash
cd /path/to/mu-plugins/
make lint
```

**To set up PHPUnit locally** (requires a working WordPress development environment, specifically PHP and MySQL):

Notes: 

* You need to replace the `%placeholder%` strings below with sensible values
* You DO need an empty DB, because the contents of this DB WILL get trashed during testing

```bash
./bin/install-wp-tests.sh %empty_DB_name% %db_user% %db_name%
```

**To run PHPUnit tests locally**:

```bash
phpunit
```
