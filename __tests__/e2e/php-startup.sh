#!/bin/bash

if [ $WP_VERSION == "nightly" ]; then
    apk add --no-cache libzip-dev zip && docker-php-ext-install zip
fi
wp core download --path="/wp" --version="$WP_VERSION" --allow-root
wp core install --allow-root --url="http://localhost" --title="e2e test site" --admin_user="e2e_tester" --admin_password="aut0matedTe\$ter" --admin_email="e2e_tester@asdflkjasfd.com"
wp theme install twentytwentyone --activate --allow-root
php-fpm