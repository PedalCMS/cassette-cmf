<?php
/**
 * WordPress Test Configuration
 *
 * This file configures the WordPress test environment.
 * Customize the database credentials for your local environment.
 *
 * @package Pedalcms\CassetteCmf\Tests
 */

// Path to WordPress installation - dynamically calculated from plugin location.
// Plugin is at: wp-content/plugins/cassette-cmf-example/cassette-cmf/tests/
// WordPress root is 5 levels up.
$abspath = dirname( __DIR__, 5 ) . '/';

// Allow override via environment variable.
if ( getenv( 'WP_CORE_DIR' ) ) {
	$abspath = rtrim( getenv( 'WP_CORE_DIR' ), '/\\' ) . '/';
}

define( 'ABSPATH', $abspath );

// Test database settings.
// IMPORTANT: Use a SEPARATE database from your development database!
// All tables will be dropped and recreated during testing.
define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ? getenv( 'WP_TESTS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ? getenv( 'WP_TESTS_DB_PASSWORD' ) : 'root' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ? getenv( 'WP_TESTS_DB_HOST' ) : 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Test site settings.
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Site' );
define( 'WP_PHP_BINARY', 'php' );

// Locale.
define( 'WPLANG', '' );

// Table prefix for test database.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for WordPress test suite.
$table_prefix = 'wptests_';

// Debugging.
define( 'WP_DEBUG', true );
