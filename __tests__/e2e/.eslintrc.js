const jestOff = Object.keys( require( 'eslint-plugin-jest' ).rules ).reduce( ( acc, rule ) => {
    acc[ `jest/${ rule }` ] = 'off';
    return acc;
}, {} );

module.exports = {
    env: {
        node: true,
    },
    extends: [
        'wpvip',
        'plugin:playwright/playwright-test',
    ],
    parser: '@typescript-eslint/parser',
    plugins: [ '@typescript-eslint' ],
    ignorePatterns: [ 'bin/**/*' ],
    rules: {
        ...jestOff,
        indent: [
            'error', 4,
        ],
    },
};
