# Library: Feature

## Description

The purpose of this library is to allow for gradual rollouts of features that may be too risky/complex to be deployed at once.

## Usage

There are currently three ways to target the feature: By percentage, by environment ID and by environment type.

Global helper to check whether a particular feature is enabled by any means:

```php
\Automattic\VIP\Feature::is_enabled( 'my-awesome-complex-feature' );
```

### Define and checking features by percentage

This is defined via `$feature_percentages` class property. It's an associative array where the key is the feature slug and the value is % as a decimal.

For multisites this also applies for network sites. E.g. to avoid the situation where a large multisite environment falls into enabled bucket and has the feature enabled for ALL of the network sites.

E.g. to roll out `my-awesome-complex-feature` to 10% of environments (and 10% of network sites in multisites)

```php
public static $feature_percentages = [
	'my-awesome-complex-feature' => 0.1,
];
```

To check specifically for percentage:

```php
\Automattic\VIP\Feature::is_enabled_by_percentage( 'my-awesome-complex-feature' );
```

### Defining and checking features by Env IDs

This is defined via `$feature_ids` class property. It's an associative array where the key is the feature slug and the value is an array of Env IDS:

```php
public static $feature_ids = [
	'my-awesome-complex-feature' => [
		// enable for Env ID 123
		123 => true,
		// disable for Env ID 234
		234 => false,
	],
];
```

To check if enabled:

```php
\Automattic\VIP\Feature::is_enabled_by_ids( 'my-awesome-complex-feature' );
```

To check if disabled:
```php
\Automattic\VIP\Feature::is_disabled_by_ids( 'my-awesome-complex-feature' );
```


### Defining and checking features by environment type

This is defined via `$feature_envs` class property. It's an associative array where the key is the feature slug and the value is an array of environments names as keys and booleans for values. Also supports `non-production`

```php
public static $feature_envs = [
	'my-awesome-complex-feature' => [
		// enable for all staging envs
		'staging' => true,
		// disable for prod envs
		'production' => false,
		// Enable for ALL non-production environments
		'non-production' => true
	],
];
```

To check:

```php
\Automattic\VIP\Feature::is_enabled_by_env( 'my-awesome-complex-feature', $default = false );
```

`$default` is default return value if environment is not on list.
