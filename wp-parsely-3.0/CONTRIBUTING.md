# Getting Started

Thank you for your interest in contributing to the Parse.ly plugin! We hope this document helps you get set up with everything you need to contribute and we look forward to working with you!

## Reporting Bugs

Please search the [repo's issues](https://github.com/Parsely/wp-parsely/issues) to see if your issue has been reported already and if so, comment on that issue instead of opening a new one.

When creating a new issue, please add specific steps to reproduce the problem, upload any relevant screenshots, and describe what happened and what you expected would happen instead.

## Setting up your environment

### Minimum required versions

- Node.js - 14 (LTS)

  Node.js is used in the build process of the Parse.ly plugin. If it's not already installed on your system, you can [visit the Node.js website and install the latest Long Term Support (LTS) version.](https://nodejs.org/).

  If you use [nvm](https://github.com/nvm-sh/nvm) to manage node versions, you can run:

  ```
  nvm install
  ```

- npm - 7

  Node 14 ships with npm version 6, so you will need to update your version of npm. Once node is installed, update it with:

  ```
  npm i -g npm
  ```

  This is important to maintain the integrity of the `package-lock.json` file (we use [`lockfileVersion` 2](https://docs.npmjs.com/cli/v7/configuring-npm/package-lock-json#lockfileversion)).

- PHP - 7.1–8.0

  There are multiple ways to install PHP on your operating system. You can check out the [official installation instructions from the PHP project's website.](https://www.php.net/manual/en/install.php)

- Composer - 1.x (but 2.x recommended).

  The Parse.ly plugin includes several packages that require Composer, the PHP package manager. You can view the [composer.json](https://github.com/Parsely/wp-parsely/blob/develop/composer.json) file for a full list of packages. You can install Composer through Homebrew on macOS: `brew install composer`. If you don't have access to Homebrew you can view instructions for how to install Composer on the [Composer website](https://getcomposer.org/download/).

- WordPress - 5.0

### Installing Dependencies

Once you have Node.js, PHP, and Composer installed locally, you will need to run `composer install` in the main plugin directory to install the dependencies of the plugin needed to run tests and check coding standards.

## Contributing Patches and New Features

### Branches

Ongoing development will be done in the `develop` branch with merges done into `trunk` once considered stable.

To contribute an improvement to this project, fork the repo and open a pull request to the `develop` branch. Alternatively, if you have push access to this repo, create a feature branch and then open an intra-repo PR from that branch to `develop`.

### Coding Standards

The Parse.ly plugin uses the PHP_CodeSniffer tool that is installed through Composer. This plugin uses a [custom ruleset.](https://github.com/Parsely/wp-parsely/blob/develop/.phpcs.xml.dist)

The plugin aims to use strong types where possible, so be sure to declare `strict_types=1` on new files, and include type definitions for parameters and return types that are compatible with the minimum version of PHP that this plugin supports.

For JavaScript we recommend installing ESLint. This plugin includes a [.eslintrc](https://github.com/Parsely/wp-parsely/blob/develop/.eslintrc) file that defines our coding standards.

### Linting

If you haven't installed Composer, you will need to do that first.

To lint your PHP code:

```
composer lint
```

To check your code with our code standards:

```
compose cs
```

### Testing

First, you'll need to install the tests by running the install script:

1. Navigate to the main plugin directory.
1. You'll need to have a local database setup and have the database name, username, password, and host ready.
1. Run the test install script using the database information.
   ```
   ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
   ```

Then, you can use Composer to run the tests from your terminal.

To run the single-site tests:

```
composer test
```

To run the multisite tests:

```
composer test-ms
```

To run with code coverage:

```
composer coverage
```

## Building Included Assets

JavaScript files that are included in the released plugin are built with the
[wp-scripts tool](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/).

Here's how to get started making changes to them:

1. Install the dependencies: `npm i`
1. Start the build tool: `npm run start`
1. Make and test your changes -- assets are rebuilt automatically
1. Create a branch / PR with all applicable changes to:
   - Source files (in the `src` directory)
   - Build tooling (including an updated `package-lock.json` if you've altered dependencies)
   - Built files (in the `build` directory)
     - When you're ready to do this, stop your `start` script and run `npm run build` instead
