/**
 * Updating Gutenberg's default E2E configuration to have a lower timeout (i.e. we want tests to fail fast).
 * Notice that this jest configuration only applies to E2E tests (as per the `-e2e` suffix).
 */
const baseConfig = require( '@wordpress/scripts/config/jest-e2e.config' );

module.exports = {
	...baseConfig,
	testTimeout: 10000,
};
