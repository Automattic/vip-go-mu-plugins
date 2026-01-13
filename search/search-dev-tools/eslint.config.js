const { configs } = require( '@automattic/eslint-plugin-wpvip' );
const { browser } = require( 'globals' );

const config = [
	...configs.javascript,
	...configs.react,
	{
		rules: {
		},
		languageOptions: {
			globals: {
				...browser,
			},
		},
		linterOptions: {
			reportUnusedDisableDirectives: 'warn',
		},
		settings: {
			react: {
				pragma: 'h',
			},
		},
	},
];

module.exports = config;
