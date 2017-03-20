# VIP Go CLI

## Usage

VIP Go WP-CLI commands are in a common `vip` namespace to make them easy to find and avoid cluttering the global namespace. On the command line, `wp vip` will list all the available commands.

## Writing CLI Commands

WP-CLI commands on VIP Go are just like normal WP-CLI commands with two main differences:

1. Instead of extending `WP_CLI_Command`, we extend `WPCOM_VIP_CLI_Command`, which has some [helper functions](../vip-helpers/vip-wp-cli.php) useful on VIP Go.
2. Namespace new commands under `vip`, like the example below.

The [WP-CLI Command Cookbook](https://make.wordpress.org/cli/handbook/commands-cookbook/) is a good resource for writing WP-CLI commands.

```php
<?php

class VIP_Go_Custom_Command extends WPCOM_VIP_CLI_Command {
}

WP_CLI::add_command( 'vip command', 'VIP_Go_Custom_Command' );
```
