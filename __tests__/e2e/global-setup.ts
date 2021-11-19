import { chromium, FullConfig } from '@playwright/test';
import { LoginPage } from './lib/pages/wp-login-page';

async function globalSetup( config: FullConfig ) {
  const { baseURL, storageState } = config.projects[0].use;
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Log in to wp-admin
  await page.goto( baseURL + '/wp-login.php' );
  const loginPage = new LoginPage( page );
  await page.screenshot( { path: 'screenshot.png' } );
  await loginPage.login( 'e2e_tester', 'aut0matedTe$ter' );

  // Save signed-in state
  await page.context().storageState( { path: storageState as string } );
  await browser.close();
}

export default globalSetup;