export default {
	// you can add preact-cli plugins here
	plugins: [
	],
	/**
	 * Function that mutates the original webpack config.
	 * Supports asynchronous changes when a promise is returned (or it's an async function).
	 *
	 * @param {object} config - original webpack config.
	 * @param {object} env - options passed to the CLI.
	 * @param {WebpackConfigHelpers} helpers - object with useful helpers for working with the webpack config.
	 * @param {object} options - this is mainly relevant for plugins (will always be empty in the config), default to an empty object
	 **/
	webpack(config, env, helpers, options) {
		/**
		 * Drop the hash part to simplify the assets including logic for JS and CSS with wp_enqueue_scripts.
		 */
		config.output.filename = '[name].js';
		config.output.chunkFilename = '[name].chunk.js';

		const [ cssExtract ] = helpers.getPluginsByName( config, 'MiniCssExtractPlugin' );
		cssExtract.plugin.options.filename = '[name].css';
		cssExtract.plugin.options.chunkFilename = '[name].chunk.css';
	},
};