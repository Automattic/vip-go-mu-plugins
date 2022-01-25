/**
 * External dependencies
 */
import { chromium, FullConfig } from '@playwright/test';

/**
 * Internal dependencies
 */
import { EditorPage } from './pages/wp-editor-page';
import { LoginPage } from './pages/wp-login-page';
import { SettingsWritingPage } from './pages/settings-writing-page';

async function globalSetup( config: FullConfig ) {
    const timeout = 30000;
    const { baseURL, storageState } = config.projects[ 0 ].use;
    const browser = await chromium.launch();
    const page = await browser.newPage();
    const user = process.env.E2E_USER ? process.env.E2E_USER : 'vipgo';
    const pass = process.env.E2E_PASSWORD ? process.env.E2E_PASSWORD : 'password';
    page.setDefaultNavigationTimeout( timeout );

    // Log in to wp-admin
    await page.goto( baseURL + '/wp-login.php', { waitUntil: 'networkidle' } );
    const loginPage = new LoginPage( page );
    await loginPage.login( user, pass );

    // Save API Nonce to Env Var
    process.env.WP_E2E_NONCE = await page.evaluate( 'wpApiSettings.nonce' );

    // Adjust Classic Editor plugin settings if is available
    await page.goto( baseURL + '/wp-admin/options-writing.php', { waitUntil: 'networkidle' } );

    const settingsWritingPage = new SettingsWritingPage( page );
    if ( await settingsWritingPage.hasClassicEditor() ) {
        await settingsWritingPage.allowBothEditors();
    } else {
        process.env.E2E_CLASSIC_TESTS = 'false';
    }

    // Dismiss editor welcome
    await page.goto( baseURL + '/wp-admin/post-new.php', { waitUntil: 'networkidle' } );
    const editorPage = new EditorPage( page );
    await editorPage.dismissWelcomeTour();

    // Save signed-in state
    await page.context().storageState( { path: storageState as string } );

    await browser.close();
}

export default globalSetup;
