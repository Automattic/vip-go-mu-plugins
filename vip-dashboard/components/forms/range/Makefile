REPORTER ?= spec
MOCHA ?= ../../../node_modules/.bin/mocha

test:
	@NODE_ENV=test NODE_PATH=test:../../ $(MOCHA) --compilers jsx:jsx-require-extension --reporter $(REPORTER)

.PHONY: test
