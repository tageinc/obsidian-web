import { test, expect } from '@playwright/test';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const axePath = require.resolve('axe-core/axe.min.js');
const password = 'browser-test-password'; // Synthetic fixture credential, never a local user credential.

test.beforeEach(async ({ context, page }) => {
    await context.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.hostname.endsWith('.tile.openstreetmap.org')) {
            return route.fulfill({
                contentType: 'image/svg+xml',
                body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#eee"/></svg>',
            });
        }
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        return route.continue();
    });
    // Fail closed even if a regression accidentally dispatches a motor command on mount.
    await page.route('**/update-solar-tracker', (route) => route.abort());
});

async function login(page, role = 'owner') {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Login', exact: true })).toBeVisible();
    await page.getByLabel('Email address', { exact: true }).fill(`${role}@browser.example.test`);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
}

async function checkAccessibility(page) {
    await page.addScriptTag({ path: axePath });
    const violations = await page.evaluate(async () => {
        const result = await window.axe.run(document, {
            runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] },
        });
        return result.violations.map(({ id, impact, nodes }) => ({
            id,
            impact,
            targets: nodes.map((node) => node.target),
        }));
    });
    expect(violations).toEqual([]);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBe(true);
}

test('native login redirects and dashboard navigation remain accessible at both viewport sizes', async ({
    page,
    isMobile,
}) => {
    await login(page);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    await page.getByRole('link', { name: 'Edit profile', exact: true }).click();
    await expect(page).toHaveURL(/\/profile$/);
    await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible();
    await checkAccessibility(page);
    if (isMobile) {
        const toggle = page.getByRole('button', { name: 'Toggle navigation' });
        await toggle.focus();
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    }
    await page.getByRole('button', { name: 'Logout', exact: true }).click();
    await expect(page).toHaveURL(/\/login$/);
});

test('dashboard reports loading, failed map reads, retry and a genuinely empty account', async ({
    page,
}) => {
    let release;
    const pending = new Promise((resolve) => {
        release = resolve;
    });
    await page.route('**/paginated-devices?*', async (route) => {
        await pending;
        await route.fulfill({
            status: 503,
            contentType: 'application/json',
            body: '{"message":"Unavailable"}',
        });
    });
    await login(page);
    await expect(page.getByText('Loading device locations…')).toBeVisible();
    release();
    await expect(page.getByRole('button', { name: 'Retry map' })).toBeVisible();
    await page.unroute('**/paginated-devices?*');
    await page.getByRole('button', { name: 'Retry map' }).click();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    await page.getByLabel('Show all devices on map').check();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    // New browser context per test avoids changing a real account or any hardware state.
    await page.context().clearCookies();
    await login(page, 'empty');
    await expect(page.getByText('No devices registered yet.', { exact: false })).toBeVisible();
    await expect(page.getByText('No devices available for this map.')).toBeVisible();
});

test('profile and device forms retain one save action, server errors and entered nonsecret values', async ({
    page,
}) => {
    await login(page);
    await page.goto('/profile');
    await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible();
    await expect(page.locator('main button[type="submit"]')).toHaveCount(1);
    await page.getByLabel('Email address', { exact: true }).fill('admin@browser.example.test');
    await page.getByLabel('City', { exact: true }).fill('Retained city');
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(page.locator('#email')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#city')).toHaveValue('Retained city');
    await expect(page.locator('#password')).toHaveValue('');
    await expect(page.getByRole('alert')).toBeFocused();

    await page.goto('/device-register');
    await expect(page.getByRole('heading', { name: 'Register device', exact: true })).toBeVisible();
    await expect(page.locator('main button[type="submit"]')).toHaveCount(1);
    await expect(
        page.locator('[name="status_notification"], [name="sms_notification"]'),
    ).toHaveCount(0);
    await page.getByLabel('Serial number', { exact: true }).fill('BROWSER-SIMULATOR-1');
    await page.getByLabel('SKU', { exact: true }).fill('TEST');
    await page.getByLabel('Order number', { exact: true }).fill('TEST-ORDER');
    await page.getByLabel('Device name', { exact: true }).fill('Invalid duplicate fixture');
    await page.getByLabel('Latitude', { exact: true }).fill('0');
    await page.getByLabel('Longitude', { exact: true }).fill('0');
    await page.getByRole('button', { name: 'Register device', exact: true }).click();
    await expect(page.locator('#serial_no')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#alias')).toHaveValue('Invalid duplicate fixture');
    await checkAccessibility(page);

    await page.goto('/edit-device/1');
    await expect(page.getByRole('heading', { name: 'Edit device', exact: true })).toBeVisible();
    await expect(page.locator('[name="serial_no"]')).toHaveCount(0);
    await expect(page.getByText('BROWSER-SIMULATOR-1', { exact: true })).toBeVisible();
    await expect(page.locator('main button[type="submit"]')).toHaveCount(1);
    await page.getByLabel('Latitude', { exact: true }).fill('91');
    await page.getByRole('button', { name: 'Update device' }).click();
    expect(
        await page.locator('#latitude').evaluate((element) => element.validity.rangeOverflow),
    ).toBe(true);
    await expect(page.getByRole('button', { name: 'Update device' })).toBeEnabled();
    await expect(page).toHaveURL(/\/edit-device\/1$/);
});

test('raw chart ranges render timestamps and navigation never sends a remote command', async ({
    page,
}) => {
    const commands = [];
    page.on('request', (request) => {
        if (request.url().endsWith('/update-solar-tracker')) commands.push(request.method());
    });
    await login(page);
    await page.getByRole('link', { name: 'View Browser simulator', exact: true }).click();
    await expect(page).toHaveURL(/\/device-info\/1$/);
    await expect(
        page.getByRole('heading', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('6 raw readings.', { exact: false })).toBeVisible();
    await expect(page.locator('canvas')).toHaveCount(3);
    await expect(page.getByRole('img', { name: /Temperature.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /(?:AM|PM)/,
    );
    await page.getByRole('button', { name: '1 hour', exact: true }).click();
    await expect(page.getByText('2 raw readings.', { exact: false })).toBeVisible();
    await page.getByRole('button', { name: '12 hours', exact: true }).click();
    await expect(page.getByText('4 raw readings.', { exact: false })).toBeVisible();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).not.toBeChecked();
    await expect(page.getByRole('button', { name: 'Up', exact: true })).toBeDisabled();
    await page.reload();
    await expect(page.getByText('6 raw readings.', { exact: false })).toBeVisible();
    expect(commands).toEqual([]);
    await page.getByRole('link', { name: 'Back', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    expect(commands).toEqual([]);
});

test('auth and public documents retain URLs, form labels and keyboard access', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Login', exact: true })).toBeVisible();
    await checkAccessibility(page);
    await page.goto('/register');
    await expect(page.getByRole('heading', { name: 'Register', exact: true })).toBeVisible();
    await expect(page.getByLabel('Email address', { exact: true })).toBeVisible();
    await expect(page.locator('input[type="password"]')).toHaveCount(2);
    await page.goto('/password/reset');
    await expect(page.getByRole('button', { name: 'Send Password Reset Link' })).toBeVisible();
    await page.goto('/contact-us');
    await expect(page.getByRole('heading', { name: 'Contact Us', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'info@tezca.net', exact: true })).toHaveAttribute(
        'href',
        'mailto:info@tezca.net',
    );
    await checkAccessibility(page);
});

test('admin authorization and multipart server validation remain in Laravel', async ({ page }) => {
    await login(page);
    const forbidden = await page.goto('/admin-control-center');
    expect(forbidden.status()).toBe(403);
    await page.context().clearCookies();
    await login(page, 'admin');
    await page.goto('/admin-control-center');
    await expect(
        page.getByRole('heading', { name: 'Admin Control Center', exact: true }),
    ).toBeVisible();
    await expect(page.locator('form[enctype="multipart/form-data"]')).toHaveCount(2);
    await page.locator('#config').setInputFiles({
        name: 'invalid.json',
        mimeType: 'text/plain',
        buffer: Buffer.from('not a json document'),
    });
    await page.locator('#config-description').fill('Synthetic rejected upload');
    await page.locator('#config-prefix').fill('BROWSER');
    await page.getByRole('button', { name: 'Upload JSON Config' }).click();
    await expect(page.locator('#config')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#config-description')).toHaveValue('Synthetic rejected upload');
    await expect(page.locator('#config')).toHaveValue('');
    await checkAccessibility(page);
});
