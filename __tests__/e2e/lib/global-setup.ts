/**
 * External dependencies
 */
import { chromium, FullConfig } from '@playwright/test';
import fs from 'fs';

/**
 * Internal dependencies
 */
import { EditorPage } from './pages/wp-editor-page';
import { LoginPage } from './pages/wp-login-page';
import { SettingsWritingPage } from './pages/settings-writing-page';
import { goToPage } from './playwright-helpers';

async function globalSetup( config: FullConfig ) {
    const timeout = 30000;
    const artifactsDir = 'test-results/setup/';
    const { baseURL, storageState } = config.projects[ 0 ].use;
    const browser = await chromium.launch( { headless: config.projects[ 0 ].use.headless } );
    const context = await browser.newContext( { recordVideo: { dir: artifactsDir } } );
    const page = await context.newPage();
    const user = process.env.E2E_USER ? process.env.E2E_USER : 'vipgo';
    const pass = process.env.E2E_PASSWORD ? process.env.E2E_PASSWORD : 'password';
    let success = true;
    page.setDefaultNavigationTimeout( timeout );
    await context.tracing.start( { name: 'global-setup', screenshots: true, snapshots: true } );

    try {
        // Log in to wp-admin
        await goToPage( page, baseURL + '/wp-login.php' );
        const loginPage = new LoginPage( page );
        await loginPage.login( user, pass );

        // Save API Nonce to Env Var
        process.env.WP_E2E_NONCE = await page.evaluate( 'wpApiSettings.nonce' );

        // Adjust Classic Editor plugin settings if is available
        await goToPage( page, baseURL + '/wp-admin/options-writing.php' );
        const settingsWritingPage = new SettingsWritingPage( page );
        if ( await settingsWritingPage.hasClassicEditor() ) {
            await settingsWritingPage.allowBothEditors();
        } else {
            process.env.E2E_CLASSIC_TESTS = 'false';
        }

        // Dismiss editor welcome
        await goToPage( page, baseURL + '/wp-admin/post-new.php' );
        const editorPage = new EditorPage( page );
        await editorPage.dismissWelcomeTour();
    } catch ( error ) {
        // eslint-disable-next-line no-console
        console.log( error );
        success = false;
    }

    // Save signed-in state
    await page.context().storageState( { path: storageState as string } );

    await context.tracing.stop( { path: artifactsDir + 'trace.zip' } );
    await context.close();

    if ( success ) {
        // Clean up files if successful
        fs.rmSync( artifactsDir, { recursive: true, force: true } );
    } else {
        // Stop test run if login fails
        throw new Error( 'Setup unsuccessful - Tests will not run' );
    }
}

export default globalSetup;
