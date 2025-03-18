require( '@automattic/eslint-plugin-wpvip/init' );

module.exports = {
    parserOptions: {
        ecmaVersion: 2021,
        project: [__dirname  + "/tsconfig.json"],
    },
    extends: [
        'plugin:@automattic/wpvip/recommended',
        'plugin:playwright/playwright-test',
        'plugin:deprecation/recommended',
    ],
    ignorePatterns: [ 'bin/**/*', '*.js' ],
    rules: {
        '@typescript-eslint/no-non-null-assertion': 'off',
    },
    root: true,
};
