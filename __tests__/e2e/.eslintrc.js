module.exports = {
    env: {
        node: true,
    },
    extends: [
        'plugin:@wordpress/eslint-plugin/recommended',
        'plugin:@automattic/wpvip/base',
        'plugin:@automattic/wpvip/typescript',
        'plugin:playwright/playwright-test',
    ],
    ignorePatterns: [ 'bin/**/*' ],
    rules: {
        indent: [
            'error', 4,
        ],
    },
};
