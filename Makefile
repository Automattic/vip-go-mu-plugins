.PHONY: lint phpunit phpdoc initphpcs phpcs phpcbf clean

test: lint phpunit phpcs

sniff: phpcs

lint:
	find . -name \*.php -not -path "./vendor/*" -print0 | xargs -0 -n 1 -P 4 php -d display_errors=stderr -l > /dev/null

phpunit:
	vendor/bin/phpunit

phpdoc:
	phpdoc run --no-interaction

initphpcs:
	test -f /tmp/phpcs || curl -L https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar -o /tmp/phpcs && chmod +x /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	test -d /tmp/vipcs || git clone -b master --depth 1 https://github.com/Automattic/VIP-Coding-Standards.git /tmp/vipcs
	/tmp/phpcs --config-set installed_paths /tmp/wpcs,/tmp/vipcs

phpcs: initphpcs
	/tmp/phpcs -p . --severity=6 --standard=phpcs.xml --extensions=php --runtime-set ignore_warnings_on_exit true

phpcbf: initphpcs
	/tmp/phpcbf -p . --standard=phpcs.xml --extensions=php

clean:
	rm -rf /tmp/phpcs
	rm -rf /tmp/phpcbf
	rm -rf /tmp/wpcs
	rm -rf /tmp/vipcs
