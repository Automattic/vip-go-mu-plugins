# Developing on Windows

**Disclaimer:** We don't officially support or recommend development on Windows, as some of our tooling may not run well out of the box or may not support Windows in the future.

Having said that, this document describes some workarounds that (as of the time of this writing) result in a fully functional development environment under Windows.

## Making integration tests work

In order to run the integration tests included in the plugin, you must first install the WP Tests Suite.

### Prerequisites

- The prerequisites described in [CONTRIBUTING.md](CONTRIBUTING.md).
- An SVN client. Most SVN clients should do, but we've had success using [SlikSVN](https://sliksvn.com/download/). 

Please note that:

- Your SVN client directory should be added to your `Path` Environment Variable. Most installers do this automatically.
- `curl.exe` and your SVN client will need internet access to download required files.

### Assumptions

For this section, we will assume that:

- You want to download the WP Tests Suite in the directory `C:\my-custom-path\wp-tests\` (please make sure you have write access to your desired path).
- Your database user is `root` and your password is empty.
- You want to name your database table `wp_tests`.
- Note that below, "terminal" refers to any command line program you might be using, such as CMD, PowerShell, VSCode terminal, Cmder, etc.

### Setting up

1. Create the database `wp_tests` using your preferred tool.
2. In `bin\install-wp-tests.sh`, change the `TMPDIR` variable to the desired path. Note that a traditional Windows path won't work. Here's an example with our path:

	```
	TMPDIR="/C/my-custom-path/wp-tests/"
	```

	**Warning:** If you don't do this, the files will be downloaded in the Windows TEMP directory, meaning they could get deleted soon.
3. Open the plugin's directory in Git Bash (or any other environment that can run `.sh` files) and issue this command to download all the WP Test Suite files:

	```
	./bin/install-wp-tests.sh "wp_tests" "root" "" "localhost" "trunk" "true"
	```

4. Open the `C:\my-custom-path\wp-tests\wordpress-tests-lib\wp-tests-config.php` file, and update the `ABSPATH` constant (should be on line 3) so it can be understood by PHP on Windows. For our case, `C:/my-custom-path/wp-tests/wordpress/` should work.
5. Add a new Environment Variable called `WP_TESTS_DIR` with the value of the WP Tests Suite path, appending `wordpress-tests-lib\` (in our case `C:\my-custom-path\wp-tests\wordpress-tests-lib\`). Note that any existing terminal windows won't be aware of the new variable, so it is recommended to close them.
6. In a new terminal, you should be able to execute the integration tests by running:

	```
	composer testwp
	```

	**Note:** If you're issuing the command from PowerShell and it fails, please try another environment as PowerShell had some issues during our testing.
7. You might want to revert your change in `install-wp-tests.sh` so you don't commit it accidentally.

## Fixing composer issues when committing

When trying to make your first commit, you might receive errors telling you that composer (the PHP package manager) is an unknown command. The issue is that the pre-commit process (outlined in `.husky\pre-commit`) may not recognize the `composer.phar` or `composer.bat` files in your composer installation.

To make this work:

- Verify that composer is in your `Path` Environment Variable. 
- In your composer installation directory, make a copy of `composer.phar` and rename it to `composer` (remove the extension).

Now the pre-commit process should be able to pickup any `composer` commands normally.
