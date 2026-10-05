const { configs } = require( '@automattic/eslint-plugin-wpvip' );
const eslintPluginPlaywright = require( 'eslint-plugin-playwright' );

const config = [
	{
		ignores: [ 'bin/**' ],
	},
	...configs.recommended,
	...configs.typescript,
	eslintPluginPlaywright.configs[ 'flat/recommended' ],
	{
		files: [ '**/*.ts' ],
		rules: {
			'@typescript-eslint/no-deprecated': 'error',
			'@typescript-eslint/no-non-null-assertion': 'off',
		},
		linterOptions: {
			reportUnusedDisableDirectives: 'warn',
		},
	},
];

module.exports = config;
