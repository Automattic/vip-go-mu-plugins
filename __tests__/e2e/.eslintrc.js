module.exports = {
    extends: [
        'plugin:@automattic/wpvip/base',
        'plugin:@automattic/wpvip/testing',
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
