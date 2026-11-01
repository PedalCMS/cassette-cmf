<?php
/**
 * Backward-compatibility dump bootstrap.
 *
 * Mirrors tests/bootstrap.php (WP test library, WP_TESTS_CONFIG_FILE_PATH, WP_CORE_DIR)
 * but loads ONLY this repository's own examples, never the parent
 * cassette-cmf-example.php (which would also boot the sibling library).
 *
 * @package PedalCMS\CassetteCMF\Tests\BC
 */

// Repository root.
define( 'TESTS_PLUGIN_DIR', dirname( __DIR__, 2 ) );
define( 'BC_REPO_KIND', 'cmf' );

if ( ! getenv( 'WP_TESTS_DIR' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );
}

if ( ! defined( 'CASSETTE_CMF_TESTING' ) ) {
	define( 'CASSETTE_CMF_TESTING', true );
}

$autoloader = TESTS_PLUGIN_DIR . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	echo 'Composer autoloader not found. Run `composer install` first.' . PHP_EOL;
	exit( 1 );
}
require_once $autoloader;

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_possible_dirs = [
		TESTS_PLUGIN_DIR . '/vendor/wp-phpunit/wp-phpunit',
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

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'WordPress test library not found. Set WP_TESTS_DIR or run composer install.' . PHP_EOL;
	exit( 1 );
}

if ( ! defined( 'WP_CORE_DIR' ) ) {
	$_wp_core_dir = getenv( 'WP_CORE_DIR' );

	if ( ! $_wp_core_dir ) {
		$_possible_wp_dirs = [
			dirname( TESTS_PLUGIN_DIR, 3 ),
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

require_once $_tests_dir . '/includes/functions.php';

// Record every _doing_it_wrong() / deprecation raised from the very start of the boot.
$GLOBALS['bc_notices'] = [];

foreach ( [ 'doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run', 'deprecated_file_included' ] as $_bc_hook ) {
	tests_add_filter(
		$_bc_hook,
		static function ( $name = '', $message = '' ) use ( $_bc_hook ) {
			$GLOBALS['bc_notices'][] = [
				'hook'    => $_bc_hook,
				'name'    => is_scalar( $name ) ? (string) $name : '',
				'message' => is_scalar( $message ) ? (string) $message : '',
			];
		},
		1,
		2
	);
}

tests_add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
tests_add_filter( 'deprecated_function_trigger_error', '__return_false' );
tests_add_filter( 'deprecated_argument_trigger_error', '__return_false' );
tests_add_filter( 'deprecated_hook_trigger_error', '__return_false' );
tests_add_filter( 'deprecated_file_trigger_error', '__return_false' );

/**
 * Load only this repository's own examples.
 *
 * The hooks they add (init, plugins_loaded, ...) fire later, when the WP test
 * bootstrap continues past muplugins_loaded.
 */
function _bc_load_examples() {
	$files = glob( TESTS_PLUGIN_DIR . '/examples/*/example.php' );
	sort( $files );

	foreach ( $files as $file ) {
		require_once $file;
	}

	\PedalCMS\CassetteCMF\Core\Manager::init();
}

tests_add_filter( 'muplugins_loaded', '_bc_load_examples' );

require $_tests_dir . '/includes/bootstrap.php';
