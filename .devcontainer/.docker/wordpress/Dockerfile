FROM wordpress:php8.3

RUN \
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    install -m 0755 wp-cli.phar /usr/local/bin/wp && \
    rm wp-cli.phar

RUN \
    apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-client && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data wp-config-docker.php /usr/src/wordpress/wp-config-docker.php
