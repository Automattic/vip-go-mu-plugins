/**
 * External dependencies
 */
import { PlaywrightTestConfig } from '@playwright/test';

const config: PlaywrightTestConfig = {
	retries: 1,
	globalSetup: require.resolve( './lib/global-setup' ),
	timeout: 120000,
	// `list` prints per-test durations in CI logs; `github` adds failure annotations.
	reporter: process.env.CI ? [ [ 'list' ], [ 'github' ] ] : 'line',
	reportSlowTests: null,
	// Every test creates its own content, so tests can be spread across workers individually.
	fullyParallel: true,
	workers: process.env.CI ? 2 : undefined,
	use: {
		headless: process.env.DEBUG_TESTS !== 'true',
		viewport: { width: 1280, height: 1000 },
		ignoreHTTPSErrors: true,
		contextOptions: {
			reducedMotion: 'reduce',
		},
		// Record artifacts only when a test is retried, instead of for every test.
		video: 'on-first-retry',
		trace: 'on-first-retry',
		storageState: 'e2eStorageState.json',
		baseURL: process.env.E2E_BASE_URL ? process.env.E2E_BASE_URL : 'http://e2e-test-site.vipdev.lndo.site',
	},
};

export default config;
