#!/bin/bash

# called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

NAP_LENGTH=1
SELENIUM_PORT=4444

# Wait for a specific port to respond to connections.
wait_for_port() {
    local PORT=$1
    while echo | telnet localhost $PORT 2>&1 | grep -qe 'Connection refused'; do
        echo "Connection refused on port $PORT. Waiting $NAP_LENGTH seconds..."
        sleep $NAP_LENGTH
    done
}

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"

composer self-update

echo 'date.timezone = "Europe/London"' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

mkdir -p $WORDPRESS_FAKE_MAIL_DIR

# Set up the database for WordPress
sudo service mysql restart
mysql -e 'CREATE DATABASE wordpress;' -uroot
mysql -e 'GRANT ALL PRIVILEGES ON wordpress.* TO "wordpress"@"localhost" IDENTIFIED BY "password"' -uroot

# http://docs.travis-ci.com/user/languages/php/#Apache-%2B-PHP

sudo apt-get install apache2 libapache2-mod-fastcgi

# enable php-fpm
# Get the WORDPRESS_FAKE_MAIL_DIR into PHP as an environment variable
echo "env[WORDPRESS_FAKE_MAIL_DIR] = ${WORDPRESS_FAKE_MAIL_DIR}" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default
sudo cp ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf.default ~/.phpenv/versions/$(phpenv version-name)/etc/php-fpm.conf
sudo a2enmod rewrite actions fastcgi alias
echo "cgi.fix_pathinfo = 1" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
~/.phpenv/versions/$(phpenv version-name)/sbin/php-fpm

# configure apache virtual hosts
# @TODO Allow HTTPS connections (need a solution which doesn't mind self-signed certs)
sudo cp -f $TRAVIS_BUILD_DIR/ci/wordpress-apache.conf /etc/apache2/sites-available/default
sudo sed -e "s?%WORDPRESS_SITE_DIR%?${WORDPRESS_SITE_DIR}?g" --in-place /etc/apache2/sites-available/default
sudo service apache2 restart

# @TODO Allow a user to add their GitHub token, encrypted, so they can authenticate with GitHub and bypass API limits applied to Travis as a whole
# https://getcomposer.org/doc/articles/troubleshooting.md#api-rate-limit-and-oauth-tokens
# http://awestruct.org/auto-deploy-to-github-pages/ and scroll to "gem install travis"
composer update --no-interaction --prefer-dist

# install WordPress
mkdir -p $WORDPRESS_SITE_DIR
cd $WORDPRESS_SITE_DIR
# @TODO Figure out how to deal with installing "trunk", SVN checkout?
$WP_CLI core download
# @TODO Set WP_DEBUG and test for notices, etc
$WP_CLI core config --dbname=wordpress --dbuser=wordpress --dbpass=password <<PHP
define( 'WORDPRESS_FAKE_MAIL_DIR', '${WORDPRESS_FAKE_MAIL_DIR}' );
PHP
$WP_CLI core install --url=local.wordpress.dev --title="WordPress Testing" --admin_user=admin --admin_password=password --admin_email=testing@example.invalid

# Make MU plugins
mkdir -p $WORDPRESS_SITE_DIR/wp-content/mu-plugins/

# Copy the plugin into MU plugins
cp -pr $TRAVIS_BUILD_DIR $WORDPRESS_SITE_DIR/wp-content/mu-plugins/
ls -al $WORDPRESS_SITE_DIR/wp-content/mu-plugins/

# Copy the No Mail MU plugin into place
cp -pr $TRAVIS_BUILD_DIR/features/bootstrap/fake-mail.php $WORDPRESS_SITE_DIR/wp-content/mu-plugins/

cat <<EOT >> $WORDPRESS_SITE_DIR/wp-content/mu-plugins/vip-support-bootstrap.php
<?php
/**
 * Plugin Name:  WordPress.com VIP Support (MU)
 * Plugin URI:   https://vip.wordpress.com/documentation/contacting-vip-hosting/
 * Description:  Manages the WordPress.com Support Users on your site
 * Version:      1.0
 * Author:       <a href="http://automattic.com">Automattic</a>
 * License:      GPLv2 or later
 */

require_once( dirname( __FILE__ ) . '/${WORDPRESS_TEST_SUBJECT}/vip-support.php' );

EOT

# Create virtual display
export DISPLAY=:99.0
sh -e /etc/init.d/xvfb start

# Wait for virtual display to initialize (i.e. xdpyinfo returns 0)
XDPYINFO_EXIT_CODE=1
# Temporarily stop exiting the whole script if one command fails
set +e
while [ $XDPYINFO_EXIT_CODE -ne 0 ] ; do
        xdpyinfo -display :99.0 &> /dev/null
        XDPYINFO_EXIT_CODE=$?
        echo "Waiting on xvfb, xdpyinfo just returned $XDPYINFO_EXIT_CODE"
        sleep $NAP_LENGTH
done
# Resume exiting the whole script if one command fails
set -e

# Run selenium 2.45.
wget http://selenium-release.storage.googleapis.com/2.45/selenium-server-standalone-2.45.0.jar
java -jar selenium-server-standalone-2.45.0.jar -p $SELENIUM_PORT > ~/selenium.log 2>&1 &

# Wait for Selenium, if necessary
wait_for_port $SELENIUM_PORT

# Wait for Apache, if necessary
wait_for_port 80

