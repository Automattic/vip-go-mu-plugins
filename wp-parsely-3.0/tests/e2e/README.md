# End-to-end (e2e) Tests for the wp-parsely Plugin

This suite is meant to simulate actual user actions as they interact with this plugin. Tests are run against a "real" WordPress instance and activities are performed in a "real" browser. The idea is that we can provide confidence that changes going forward have the intended effect on the DOM and rendered content that plugin users and site visitors will see under various specific conditions.

## How it Works

In order for the tests to do their job, they need a back end that simulates a WordPress instance. To that end, we spin up a bare-bones containerized site and configure it to the default values for the WordPress `e2e-tests` helper.

Once there's a functioning back end, we leverage the `@wordpress/scripts` utility's [built-in functionality](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#test-e2e) to launch a browser via [Puppeteer](https://pptr.dev/).

The tests use the [Jest framework](https://jestjs.io/) to drive a user flow and assert on expected outcomes. In addition to the [Puppeteer API](https://github.com/puppeteer/puppeteer/blob/main/docs/api.md), there are a number of helpers to accomplish frequently performed tasks in the [`@wordpress/e2e-test-utils` package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils/).

See this post for more information: https://make.wordpress.org/core/2019/06/27/introducing-the-wordpress-e2e-tests/

## How to Run

- Provision the back end

  - Make sure [`docker` is installed](https://docs.docker.com/engine/install/).
  - From the `wp-content/plugins/wp-parsely` directory, run:

    `docker compose -f tests/e2e/docker-compose.yml up`

    Tip: if you `cd` into this directory, you can simply run `docker compose` and omit the `-f tests/e2e/docker-compose.yml` from these commands.

  - Once you see a line that says:

    `Success: WordPress installed successfully.`

    ...and a tailing HTTP server log, you may proceed.

- Run the tests:

  - Once your environment is ready, you can launch the e2e tests suite by running:

    `npm run test:e2e`

    ...in a different terminal window. This will run the test suite using a headless browser.

  - For debugging purpose, you might want to follow the test visually. You can do so by running the tests in an interactive mode:

    `npm run test:e2e -- --puppeteer-interactive`

  - You can also run a given test file separately:

    `npm run test:e2e tests/e2e/specs/activation-flow.spec.js`

- Repeat

  - The tests currently expect a "pristine" WordPress environment, so if you want to run them multiple times, you'll need to recreate the WordPress environment like so:

    `docker compose -f tests/e2e/docker-compose.yml run cli /var/www/html/wp-content/plugins/wp-parsely/tests/e2e/init-e2e.sh reset`

    In the future, this will likely be built into the test suite set up to enable easier test development.

- Finish Up

When you're finished testing, the back end containers and storage can be dispatched with like so:

`docker-compose down -v`

Please note: the **mysql database storage is not persisted** to a docker volume, so its contents will be lost even if you omit the `-v` flag.

### E2E test utilities

Some utilities for the end-to-end tests are available in the [utils.js](utils.js) file. The purpose of that file is to implement some common functionalities used in the tests. 

We currently have:

- `waitForWpAdmin`. Halts the execution of the test until wp-admin is fully loaded.

### CI / Automated Testing

These tests are hooked in to a Github workflow called [End-to-end (e2e) Tests](../../.github/workflows/e2e-tests.yml). It uses the same The [docker-compose configuration](./docker-compose.yml) mentioned above to spin up a WordPress environment to test against.
