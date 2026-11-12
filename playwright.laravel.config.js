import { defineConfig, devices } from '@playwright/test';

// Laravel-only browser suite. Does not start Wrangler, Cloudflare, or a Node backend.
// Set GOTRY_EXTERNAL_SERVER=1 to use an already-running local Laravel instance.
export default defineConfig({
  testDir: './e2e',
  testMatch: 'laravel-soccer-detail.spec.js',
  timeout: 30_000,
  retries: 0,
  use: {
    baseURL: 'http://127.0.0.1:8787',
    trace: 'retain-on-failure',
    launchOptions: process.env.GOTRY_CHROMIUM_PATH
      ? { executablePath: process.env.GOTRY_CHROMIUM_PATH }
      : {},
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['iPhone 13'], browserName: 'chromium' } },
  ],
  webServer: process.env.GOTRY_EXTERNAL_SERVER
    ? undefined
    : {
        command: 'php artisan serve --host=127.0.0.1 --port=8787',
        url: 'http://127.0.0.1:8787/activities',
        reuseExistingServer: false,
        timeout: 120_000,
      },
});
