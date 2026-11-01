/**
 * @wordpress/scripts build for the React settings renderer.
 *
 * src/assets/settings-app/index.js -> build/settings-app.js (+ .asset.php,
 * .css and the -rtl.css twin). The build output is committed.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'settings-app': path.resolve(
			__dirname,
			'src/assets/settings-app/index.js'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
