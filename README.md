# VIP Go MU Plugins

Every commit which is merged to the `master` branch of this repository is automattically pushed to the public copy at [Automattic/vip-mu-plugins-public](https://github.com/Automattic/vip-mu-plugins-public/). There are exceptions which are not deployed, these are controlled by the [`deploy.sh` script](https://github.com/Automattic/vip-go-mu-plugins/blob/master/ci/deploy.sh).

## PHPDoc

You can find selective PHPDoc documentation here: https://automattic.github.io/vip-go-mu-plugins/

## Tests

PHP Linting and PHP Unit tests are run by Travis, see the `script` section of [`.travis.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.travis.yml). Test results are reported into one of the WordPress.com VIP Slack channels.

For notes on running tests locally, see [README-PUBLIC.md](README-PUBLIC.md).

## PHPCS

As part of the continuous integration process, we run the WordPress-VIP PHP Codesniffer suite. PHPCS includes a tool, phpcbf, to automatically fix some types of errors. We recommend running this with `make phpcbf` or installing a plugin for your text editor which will run it automatically on save.

## Deployment

When the tests have been successfully run, the [`deploy.sh` script](https://github.com/Automattic/vip-go-mu-plugins/blob/master/ci/deploy.sh) deploys a build of this repository and it's submodules to the public repository at [Automattic/vip-mu-plugins-public](https://github.com/Automattic/vip-mu-plugins-public/).

Deployment process:

1. The private part of a deploy key for [Automattic/vip-mu-plugins-public](https://github.com/Automattic/vip-mu-plugins-public/) is encrypted against this repository ([Automattic/vip-mu-plugins-public](https://github.com/Automattic/vip-go-mu-plugins/)), meaning it can only be decrypted by Travis running scripts related to this repo
2. This repository and it's submodules are checked out, again, to start the build
3. All VCS config and metadata is removed from the build
4. Various files are removed, including the [`.travis.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.travis.yml) containing the encrypted private part of the deploy key
5. The [Automattic/vip-mu-plugins-public](https://github.com/Automattic/vip-mu-plugins-public/) repo is checked out
6. The `.git` directory from the `Automattic/vip-mu-plugins-public` repository is moved into the build directory, and a commit is created representing the changes from this build
7. The commit is pushed to the `Automattic/vip-mu-plugins-public` repository
