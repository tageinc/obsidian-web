import { defineConfig, devices } from '@playwright/test';

// Deliberately fixed to an isolated server; never accept a developer/production base URL.
const baseURL = 'http://127.0.0.1:8127';

export default defineConfig({
    testDir: './tests/browser',
    testMatch: '**/*.spec.mjs',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 45000,
    expect: { timeout: 12000 },
    reporter: 'list',
    use: { baseURL, screenshot: 'off', video: 'off', trace: 'off' },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['iPhone 13'], defaultBrowserType: 'chromium' } },
    ],
    webServer: {
        command: 'node tests/browser/start-server.mjs',
        url: `${baseURL}/login`,
        timeout: 120000,
        reuseExistingServer: false,
        gracefulShutdown: { signal: 'SIGTERM', timeout: 5000 },
    },
});
