<?php
/**
 * Cassette-CMF Test Bootstrap
 *
 * Bootstrap file for WordPress unit tests following WordPress testing standards.
 *
 * @package Pedalcms\CassetteCmf\Tests
 */

// Define plugin testing directory.
define( 'TESTS_PLUGIN_DIR', dirname( __DIR__ ) );
define( 'UNIT_TESTS_DATA_PLUGIN_DIR', TESTS_PLUGIN_DIR . '/tests/Data/' );

// Define path to wp-tests-config.php for wp-phpunit package.
// Only set this if WP_TESTS_DIR is not set (i.e., using Composer wp-phpunit package).
// In CI, WP_TESTS_DIR is set and has its own config file.
if ( ! getenv( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

// Define test mode flag.
if ( ! defined( 'CASSETTE_CMF_TESTING' ) ) {
	define( 'CASSETTE_CMF_TESTING', true );
}

// Load Composer autoloader.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	echo 'Composer autoloader not found. Run `composer install` first.' . PHP_EOL;
	exit( 1 );
}
require_once $autoloader;

// Find the WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// Try common locations.
	$_possible_dirs = [
		// Composer wp-phpunit package (preferred for Composer-based projects).
		dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit',
		// Standard temp locations.
		rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib',
		'/tmp/wordpress-tests-lib',
	];

	foreach ( $_possible_dirs as $_dir ) {
		if ( file_exists( $_dir . '/includes/functions.php' ) ) {
			$_tests_dir = $_dir;
			break;
		}
	}
}

if ( ! $_tests_dir ) {
	echo 'WordPress test library not found.' . PHP_EOL;
	echo 'Option 1: Run bash bin/install-wp-tests.sh wordpress_test root root localhost latest' . PHP_EOL;
	echo 'Option 2: Set WP_TESTS_DIR environment variable.' . PHP_EOL;
	exit( 1 );
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only.
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh?" . PHP_EOL;
	exit( 1 );
}

// Define WP_CORE_DIR - WordPress installation path.
if ( ! defined( 'WP_CORE_DIR' ) ) {
	// Check for the actual WordPress installation path.
	$_wp_core_dir = getenv( 'WP_CORE_DIR' );

	if ( ! $_wp_core_dir ) {
		// Try to find WordPress in common locations.
		$_possible_wp_dirs = [
			// Parent of plugin directory (typical wp-content/plugins/plugin structure).
			dirname( dirname( dirname( TESTS_PLUGIN_DIR ) ) ),
			// Temp directory.
			rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress',
			'/tmp/wordpress',
		];

		foreach ( $_possible_wp_dirs as $_dir ) {
			if ( file_exists( $_dir . '/wp-includes/version.php' ) ) {
				$_wp_core_dir = $_dir;
				break;
			}
		}
	}

	if ( $_wp_core_dir ) {
		define( 'WP_CORE_DIR', $_wp_core_dir . '/' );
	}
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the main plugin file if it exists.
	$plugin_file = dirname( TESTS_PLUGIN_DIR ) . '/cassette-cmf-example.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	}

	// Initialize the Cassette-CMF manager.
	\Pedalcms\CassetteCmf\Core\Manager::init();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only.
echo 'WordPress test environment loaded from: ' . $_tests_dir . PHP_EOL;
