<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', true);
define( 'WPCACHEHOME', 'C:\xampp\htdocs\wetest\wp-content\plugins\wp-super-cache/' );
define( 'DB_NAME', 'wepick' );

/** MySQL database username */
define( 'DB_USER', 'admin' );

/** MySQL database password */
define( 'DB_PASSWORD', 'admin' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'B:$lw2/wn^zO g}o>*:v$!57&,$,#6}.K)uqaa2zduxl4ugW2Tin!me{O#KmNu4 ' );
define( 'SECURE_AUTH_KEY',  '5/NUTVZ$cPcVx1.gz8NK-tp< t|]2{~#r:WQBQ#cPED&dfI-)j.%Qo>oa()v|+0h' );
define( 'LOGGED_IN_KEY',    'zYgJjy$_q@9a?q2%evTJAMI0JGG*)RHa1Fg:#7(3CN~dS+eOX!K`~uE9D-Cx;zo+' );
define( 'NONCE_KEY',        ',XhS6dm2dNL;-)UY9zFjSI=$* +LR*ycCYONmW&&}0bWF9(Chqh]JINoC;fMtp9T' );
define( 'AUTH_SALT',        '<[U84=^NAp3.^tzj`m|T#Z]hnJ,l,KiX]>Y)ub%`6msR~{0u9*+4n?=Z8LXRk>1R' );
define( 'SECURE_AUTH_SALT', 'I`p8ak):)!8h/n6k0X0sVb?V.&+]EVz!hP*X $J`)i?.?hZ@}[JHVt/JV0U:xR (' );
define( 'LOGGED_IN_SALT',   '$dAad4P^@Es*Lyp?N!;)QsqSyJKx^7{5!W@H4#}~BA42gb4Bh+Ta#WRPG2K_cPc2' );
define( 'NONCE_SALT',       'Jr3k/BJ0WMnEP<`Yth<D{hGgMS~_nYvZgc?A36x1D2+BM,pC%7Ju)KH Rcu>i>[Q' );

/**#@-*/

/**
 * WordPress Database Table prefix.
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
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );



define('ALLOW_UNFILTERED_UPLOADS', true);



