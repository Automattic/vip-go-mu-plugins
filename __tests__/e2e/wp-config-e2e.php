<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') );

/** MySQL database username */
define( 'DB_USER', getenv('WORDPRESS_DB_USER') );

/** MySQL database password */
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') );

/** MySQL hostname */
define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'PrJvYG<ar?(Ru(U-OiygX!:k]d;(CH#l>M(J12irRY4AK=S<z7&AVMk$3mlRDI?+' );
define( 'SECURE_AUTH_KEY',  'D*%,6y@j_oW$D!Y8H,<3/eDdb}S#[][@r{n`6^drh.|d}Uw>n2PhC 6@JW/JCZ]]' );
define( 'LOGGED_IN_KEY',    'npJlpOd>YtEpN30UolnCs;j2G}nmzMoe*~vk&~7p3jCXCe0YkbUKwVxcdy2f&>]A' );
define( 'NONCE_KEY',        'FAPj[>At0zS5J=$149L*X|Qy9G@U%S>7VZ;3.YQ(@9YY(2AeG8C9L3{900?}ZG+$' );
define( 'AUTH_SALT',        '|oba RtzR_JD<gp33w/ =V57?H#3(@-_r}QUy9!rRJ{%HV<5<n,#dSm&!_w$.1rz' );
define( 'SECURE_AUTH_SALT', 'Is=-s?V@qf$H(5:wz[.T=+U*Ri<cMy4Iip8:an$?#U,odpy$6Ztttm=KO]r)>@%O' );
define( 'LOGGED_IN_SALT',   ' !Y.zu9h9^+Hc>yDs^g@h9YjtaX@GD?db=&4G=D%eYWSc/ BS^-5K^@[W,JYigR>' );
define( 'NONCE_SALT',       '=8X,mE2(sY^Z%k+_H@M26GB0QYyCp){8(RTO]FFVa-L6&J<`*M`.)Ug2e<1&bt6d' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 * 
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */
if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	define( 'VIP_GO_APP_ENVIRONMENT', 'local' );
}

/**
 * VIP Config
 */
if ( file_exists( ABSPATH . '/wp-content/vip-config/vip-config.php' ) ) {
	require_once( ABSPATH . '/wp-content/vip-config/vip-config.php' );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';