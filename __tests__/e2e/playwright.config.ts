import { PlaywrightTestConfig } from '@playwright/test';

const config: PlaywrightTestConfig = {
    retries: 1,
    globalSetup: require.resolve('./global-setup'),
    timeout: 60000,
    use: {
      headless: true,
      viewport: { width: 1280, height: 1000 },
      ignoreHTTPSErrors: true,
      video: 'retain-on-failure',
      trace: 'retain-on-failure',
      storageState: 'e2eStorageState.json',
      baseURL: 'http://ng-e2e',
    },
};

export default config;