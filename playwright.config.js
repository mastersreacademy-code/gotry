import { defineConfig, devices } from '@playwright/test';
export default defineConfig({
 testDir:'./e2e',timeout:30000,retries:1,
 use:{baseURL:'http://127.0.0.1:8787',trace:'retain-on-failure'},
 projects:[{name:'desktop',use:{...devices['Desktop Chrome']}},{name:'mobile',use:{...devices['iPhone 13'],browserName:'chromium'}}],
 webServer:{command:'npx wrangler dev --config wrangler.ci.jsonc --local --ip 127.0.0.1 --port 8787',url:'http://127.0.0.1:8787/health',reuseExistingServer:!process.env.CI,timeout:120000}
});