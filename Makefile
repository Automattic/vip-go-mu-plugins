.PHONY: lint phpunit phpdoc phpcs phpcbf

test: lint phpunit phpcs

lint:
	find . -name \*.php -not -path "./vendor/*" -print0 | xargs -0 -n 1 -P 4 php -d display_errors=stderr -l > /dev/null

phpunit:
	phpunit

phpdoc:
	phpdoc run --no-interaction

phpcs:
	test -d /tmp/phpcs || git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs/scripts/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcs/scripts/phpcs -p .

phpcbf:
	test -d /tmp/phpcs || git clone -b master --depth 1 https://github.com/squizlabs/PHP_CodeSniffer.git /tmp/phpcs
	test -d /tmp/wpcs || git clone -b master --depth 1 https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git /tmp/wpcs
	/tmp/phpcs/scripts/phpcs --config-set installed_paths /tmp/wpcs
	/tmp/phpcs/scripts/phpcbf -p .

clean:
	rm -rf /tmp/phpcs /tmp/wpcs
