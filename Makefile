.PHONY: lint phpunit phpdoc

test: lint phpunit

lint:
	find . -name \*.php -not -path "./vendor/*" -print0 | xargs -0 -n 1 -P 4 php -d display_errors=stderr -l > /dev/null

phpunit:
	phpunit

phpdoc:
	phpdoc run --no-interaction
