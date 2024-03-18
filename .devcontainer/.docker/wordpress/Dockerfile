FROM wordpress:php8.3

RUN \
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    install -m 0755 wp-cli.phar /usr/local/bin/wp && \
    rm wp-cli.phar

RUN \
    apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-client libmemcached-dev libssl-dev zlib1g-dev iputils-ping telnet && \
    pecl install memcached-3.2.0 && \
    docker-php-ext-enable memcached && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data wp-config-docker.php db-config.php /usr/src/wordpress/
COPY --chown=www-data:www-data db.php hyperdb.php object-cache.php sunrise.php /usr/src/wordpress/wp-content/
