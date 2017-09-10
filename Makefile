.PHONY: lint phpunit phpdoc

test: lint phpunit phpcs

lint:
	find . -name \*.php -not -path "./vendor/*" -print0 | xargs -0 -n 1 -P 4 php -d display_errors=stderr -l > /dev/null

phpunit:
	phpunit

phpdoc:
	phpdoc run --no-interaction

phpcs:
	phpcs --config-set installed_paths $HOME/.composer/vendor/wp-coding-standards/wpcs
