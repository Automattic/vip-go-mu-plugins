# End to End (e2e) Tests for VIP Go MU Plugins

This is a set of tests to run against VIP test sites. They are written in Typescript and use [Playwright](https://playwright.dev/) as the test framework and runner

## Setup Env

These tests require that NodeJS and NPM be installed

If using the e2e test environment, you will also need to install the [VIP CLI](https://docs.wpvip.com/technical-references/vip-cli/installing-vip-cli/)

To start the e2e test environment, from the `vip-go-mu-plugins` root run:
`npm run setup-e2e-env`

This will start up a test environment running locally to run the tests against.

### Optional Environment Configuration

To start e2e environment with a specific version of WordPress:
`npm run setup-e2e-env -- -v 5.9-beta1`

To start e2e environment with custom client code:
`npm run setup-e2e-env -- -c pathToClientCode`

To start e2e environment with a custom set of MU plugins:
`npm run setup-e2e-env -- -p pathToMUPlugins`

If using this test environment, you only need to start it once and then you can keep running tests against it until you're done.

When you're done, you can tear down the environment using:
`npm run destroy-e2e-env`



### Run tests

To run the tests against the default environment:
`npm run test-e2e`

To run against different environments, you'll need to set these environment variables before starting the tests

| E2E_BASE_URL | Url to the environment to run tests against |
| E2E_USER | User name to run tests with |
| E2E_PASSWORD | Password to run tests with |

Example:
`E2E_BASE_URL=https://mytestsite.local E2E_USER=myuser E2E_PASSWORD=mypassword npm run test-e2e`
