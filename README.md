# VIP Go mu-plugins

This is the development repo for mu-plugins on [VIP Go](http://vip.wordpress.com/documentation/vip-go/),

## Development



### PHPDoc

You can find selective PHPDoc documentation here: https://automattic.github.io/vip-go-mu-plugins/

These are generated via CI by the [`generate-docs.sh`]() script.

### Tests

#### PHP Lint

```bash
make lint
```

#### PHPUnit

We have a script that runs unit tests in a self-contained Docker environment.

```
usage: ./bin/phpunit-docker.sh [wp-version]
```

You can either pass a version number to test against a specific version, or leave it blank to test against the latest version.

#### CI

PHP Linting and PHPUnit tests are run by Circle CI as part of PRs and merges. See [`.circleci/config.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.circleci/config.yml) for more.

## Deployment

### Production

**For Automattic Use:** Instructions are in the FG :)

### vip-go-mu-plugins-built

This is a repo primarily meant for local non-development use.

Every commit merged into `master` is automatically pushed to the public copy at [Automattic/vip-go-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/). This is handled via CI by the [`deploy.sh` script](https://github.com/Automattic/vip-go-mu-plugins/blob/master/ci/deploy.sh) script, which builds pushes a copy of this repo and expanded submodules.

#### How this works

1. The private part of a deploy key for [Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/) is encrypted against this repository ([Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins/)), meaning it can only be decrypted by Travis running scripts related to this repo
2. This repository and it's submodules are checked out, again, to start the build
3. All VCS config and metadata is removed from the build
4. Various files are removed, including the [`.travis.yml`](https://github.com/Automattic/vip-go-mu-plugins/blob/master/.travis.yml) containing the encrypted private part of the deploy key
5. The [Automattic/vip-mu-plugins-built](https://github.com/Automattic/vip-go-mu-plugins-built/) repo is checked out
6. The `.git` directory from the `Automattic/vip-go-mu-plugins-built` repository is moved into the build directory, and a commit is created representing the changes from this build
7. The commit is pushed to the `Automattic/vip-go-mu-plugins-built` repository
