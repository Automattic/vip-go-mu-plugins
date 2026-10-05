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
			// wpvip 2.x requires a space around `!`; `ts-non-null` keeps `foo!` assertions unspaced.
			'@stylistic/space-unary-ops': [ 'error', { overrides: { '!': true, 'ts-non-null': false, yield: true } } ],
		},
		linterOptions: {
			reportUnusedDisableDirectives: 'warn',
		},
	},
];

module.exports = config;
