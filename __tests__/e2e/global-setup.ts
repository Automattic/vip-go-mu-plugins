import { chromium, FullConfig } from '@playwright/test';
import { LoginPage } from './lib/pages/wp-login-page';

async function globalSetup( config: FullConfig ) {
  const timeout = 30000;
  const { baseURL, storageState } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout( timeout );

  // Log in to wp-admin
  await page.goto( baseURL + '/wp-login.php', { waitUntil: 'networkidle' } );
  const loginPage = new LoginPage( page );
  await loginPage.login( 'vipgo', 'password' );

  // Save signed-in state
  await page.context().storageState( { path: storageState as string } );
  await browser.close();
}

export default globalSetup;