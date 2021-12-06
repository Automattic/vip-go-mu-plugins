#!/bin/bash

if [ $WP_VERSION == "nightly" ]; then
    apk add --no-cache libzip-dev zip && docker-php-ext-install zip
fi
wp core download --path="/wp" --version="$WP_VERSION" --allow-root
wp core install --allow-root --url="http://ng-e2e" --title="e2e test site" --admin_user="vipgo" --admin_password="password" --admin_email="vip@localhost.local"
wp theme install twentytwentyone --activate --allow-root