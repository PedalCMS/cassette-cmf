<?php
/**
 * Plugin Name: Cassette-CMF React settings fixture
 * Description: Loads the React settings example (every field type) for the Playwright specs.
 *
 * @package CassetteCMFReactSettings
 */

// The library is mounted next to this plugin by .wp-env.json ("mappings").
require_once WP_PLUGIN_DIR . '/cassette-cmf-library/examples/05-react-settings/example.php';
