const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-settings': [
			path.resolve( __dirname, 'src', 'js', 'admin-settings' ),
			path.resolve( __dirname, 'src', 'css', 'admin-settings.css' ),
		],
		loader: [
			path.resolve( __dirname, 'src', 'js', 'lib', 'loader' ),
			path.resolve( __dirname, 'src', 'js', 'lib', 'uuid-profile-call' ),
		],
		'recommended-widget': [
			path.resolve( __dirname, 'src', 'js', 'widgets', 'recommended.js' ),
			path.resolve( __dirname, 'src', 'css', 'recommended-widget.css' ),
		],
	},
};
