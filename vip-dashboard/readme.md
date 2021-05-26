# VIP Dashboard

WordPress plugin that provides a new dashboard for VIP Go clients.

The interface is built with [React.js](https://facebook.github.io/react/).

## Getting Started

### Prerequisites

Make sure you have [Node.js](https://nodejs.org/) and [NPM](https://docs.npmjs.com/getting-started/what-is-npm) installed. Here's a [handy installer](https://nodejs.org/download/) for Windows, Mac, and Linux.

The repository is a sub-module of the [mu-plugins](https://github.com/Automattic/vip-go-mu-plugins) directory.

### Gulp

[Gulp](http://gulpjs.com/) is required to work on this repository. We use Gulp to compile JSX into valid JavaScript and manage other assets such as CSS and images.

To get setup run the following command in the `vip-dashboard` directory:

```
npm install
```

Once node has completed the install you should set the URL to your local development site in `gulpfile.js`. Line 50:

```
proxylocation: 'vip.w.dev'
```

You can then run the default gulp task by running:

```
gulp
```

The default task watches for changes to files and re-compiles assets when a change is detected. Your browser window will also automatically be refreshed with each change. We also check for JS errors so keep an eye on your console and fix any reported issues.

Before deploying you may wish to run:

```
gulp compress
```

This will generate minified versions of the JavaScript ready for production.

## Testing

Run

```
make lint
```

To test your JavaScript for errors.

## Directory Structure

```
├── readme.md
├── gulpfile.js
├── package.json
├── Makefile
├── vip-dashboard.php
├── .travis.yml
├── assets
│   └── css
│   └── img
│   └── js
├── components
│   └── ... react components

```

### assets

Compiled assets, do not edit anything here.

### components

Where each react component lives with the relevent JSX and SCSS files.

## Git Workflow

* The Master branch is production code (i.e. completely deployable by the time it gets merged)
* All branches except Master and Develop get prefixed with something/
* New features get a add/ prefix
* Fixes get a fix/ prefix, and have an issue number: e.g. fix/999-fix-fatal-errors where issue 999 describes the bug being fixed
* All branches get deleted once merged
* No development takes place on Master or Develop (if Develop exists)
* Nobody should merge code they’ve written, instead create a Pull Request and ask another colleague to merge it
* Pull Requests should not be monstrous quantities of code, or they’ll be too daunting to review
