/**
 * Extends @wordpress/scripts' jest-unit config: the tests live in tests/js and
 * the shared fixtures in tests/fixtures.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	roots: [ '<rootDir>/tests/js' ],
	testMatch: [ '**/*.test.js' ],
	setupFilesAfterEnv: [
		...( defaultConfig.setupFilesAfterEnv || [] ),
		'<rootDir>/tests/js/setup.js',
	],
};
