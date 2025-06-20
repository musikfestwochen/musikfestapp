import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

dotenv.config({ path: path.resolve(__dirname, '.env.local') });

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
    testDir: './e2e',
    /* Run tests in files in parallel */
    fullyParallel: true,
    /* Fail the build on CI if you accidentally left test.only in the source code. */
    forbidOnly: !!process.env.CI,
    /* Retry on CI only */
    retries: process.env.CI ? 2 : 0,
    /* Opt out of parallel tests on CI. */
    workers: process.env.CI ? 1 : undefined,
    /* Reporter to use. See https://playwright.dev/docs/test-reporters */
    reporter: 'html',
    /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
    use: {
        /* Base URL to use in actions like `await page.goto('/')`. */
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://musikfestapp.test',

        /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
        trace: 'on-first-retry',

        /* Take screenshots on failure */
        screenshot: 'only-on-failure',

        /* Record video on failure */
        video: 'retain-on-failure',
    },

    /* Run global setup before tests */
    globalSetup: './e2e/global-setup.ts',

    /* Configure projects for major browsers */
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            /* Exclude the mobile ghost test from desktop runs */
            testIgnore: 'e2e/mobile/**/*.spec.ts',
        },
        {
            name: 'mobile-ghost',
            use: { ...devices['Pixel 5'] },
            /* Only run the ghost test in mobile emulation */
            testMatch: 'e2e/mobile/**/*.spec.ts',
        },
    ],

    /* Run your local dev server before starting the tests */
    webServer: process.env.CI
        ? undefined
        : {
              command: '', // Assuming Laravel Herd is managing the server
              url: 'http://musikfestapp.test',
              reuseExistingServer: true,
          },
});
