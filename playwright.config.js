/**
 * Extends @wordpress/scripts' default Playwright config (browser, storage
 * state, wp-env webServer) and points testDir at tests/e2e/specs.
 */
const path = require( 'path' );
const { defineConfig } = require( '@playwright/test' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = defineConfig( {
	...baseConfig,
	testDir: path.resolve( __dirname, 'tests/e2e/specs' ),
} );
