module.exports = {
	plugins: [
		require( 'autoprefixer' )( {
			overrideBrowserslist: [ '> 0.25%', 'IE >= 9' ],
		} ),
	],
};
