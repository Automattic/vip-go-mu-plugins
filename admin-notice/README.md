# Admin Notice

This plugin enables displaying of a notice in wp-admin.

## Conditions

There are few conditions implemented. These include WordPress version, user permissions and so on. See [conditions](./conditions) for details.

## Usage

Example usage:

```
add_action(
	'vip_admin_notice_init',
	function( $admin_notice_controller ) {
		$admin_notice_controller->add(
			new Admin_Notice(
				'WordPress 5.9 is scheduled to be released on Tuesday, January 25th',
				[
					new Date_Condition( '2022-01-01', '2022-02-01' ),
					new WP_Version_Condition( '5.8', '5.9' ),
				],
				'wp-5.9'
			)
		);
	}
);
```