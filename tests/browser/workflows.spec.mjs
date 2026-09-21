import { test, expect } from '@playwright/test';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const axePath = require.resolve('axe-core/axe.min.js');
const password = 'browser-test-password'; // Synthetic fixture credential, never a local user credential.

test.beforeEach(async ({ context, page }) => {
    page.on('requestfailed', (request) => {
        const url = new URL(request.url());
        if (url.pathname.startsWith('/build/')) {
            console.error(
                `Asset request failed: ${url.pathname} (${request.failure()?.errorText})`,
            );
        }
    });
    page.on('response', (response) => {
        const url = new URL(response.url());
        if (url.pathname.startsWith('/build/') && response.status() >= 400) {
            console.error(`Asset response failed: ${url.pathname} (${response.status()})`);
        }
    });
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
    // Browser coverage cancels deletion; no regression may remove even a synthetic device.
    await page.route('**/delete-device/*', (route) => route.abort());
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

async function openAccountNavigation(page, { keyboard = false } = {}) {
    const navigationToggle = page.getByRole('button', { name: 'Toggle navigation' });
    if (
        (await navigationToggle.isVisible()) &&
        (await navigationToggle.getAttribute('aria-expanded')) !== 'true'
    ) {
        if (keyboard) {
            await navigationToggle.focus();
            await page.keyboard.press('Enter');
        } else {
            await navigationToggle.click();
        }
        await expect(navigationToggle).toHaveAttribute('aria-expanded', 'true');
    }
    const account = page.locator('button[aria-controls="account-navigation"]');
    if ((await account.getAttribute('aria-expanded')) !== 'true') {
        if (keyboard) {
            await account.focus();
            await page.keyboard.press('Enter');
        } else {
            await account.click();
        }
    }
    await expect(account).toHaveAttribute('aria-expanded', 'true');
    const dropdown = page.locator('#account-navigation');
    await expect(dropdown).toBeVisible();
    return dropdown;
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

test('account dropdown supports keyboard, Escape, mobile navigation and native logout', async ({
    page,
    isMobile,
}) => {
    if (!isMobile) await page.setViewportSize({ width: 1240, height: 800 });
    await login(page);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    const account = page.locator('button[aria-controls="account-navigation"]');
    await expect(account).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#account-navigation')).not.toBeVisible();
    const dropdown = await openAccountNavigation(page, { keyboard: true });
    await expect(account).toHaveAccessibleName('Owner browser fixture');
    await expect(account).toBeFocused();
    await expect(dropdown.getByRole('link', { name: 'Dashboard', exact: true })).toBeVisible();
    await expect(dropdown.getByRole('link', { name: 'Profile', exact: true })).toBeVisible();
    await expect(dropdown.getByRole('link', { name: 'Register my device' })).toBeVisible();
    await expect(dropdown.getByRole('link', { name: 'Developer Workspace' })).toHaveCount(0);
    const dropdownBounds = await dropdown.boundingBox();
    expect(dropdownBounds).not.toBeNull();
    expect(dropdownBounds.x).toBeGreaterThanOrEqual(0);
    expect(dropdownBounds.x + dropdownBounds.width).toBeLessThanOrEqual(page.viewportSize().width);
    await checkAccessibility(page);
    await page.keyboard.press('Tab');
    await expect(dropdown.getByRole('link', { name: 'Dashboard', exact: true })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dropdown).not.toBeVisible();
    await expect(account).toHaveAttribute('aria-expanded', 'false');
    await expect(account).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(dropdown).toBeVisible();
    await expect(dropdown.getByRole('link', { name: 'Dashboard', exact: true })).toBeFocused();
    await dropdown.getByRole('link', { name: 'Profile', exact: true }).click();
    await expect(page).toHaveURL(/\/profile$/);
    await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible();
    await expect(dropdown).not.toBeVisible();
    await expect(account).toHaveAttribute('aria-expanded', 'false');
    await checkAccessibility(page);
    if (isMobile) {
        await expect(page.getByRole('button', { name: 'Toggle navigation' })).toHaveAttribute(
            'aria-expanded',
            'false',
        );
    }
    await openAccountNavigation(page, { keyboard: true });
    await dropdown.getByRole('button', { name: 'Logout', exact: true }).click();
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

test('device actions stay usable and alias search keeps the table and map in sync', async ({
    page,
    isMobile,
}) => {
    const deletionRequests = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname.startsWith('/delete-device/')) {
            deletionRequests.push(request.url());
        }
    });
    await login(page);
    const details = page.getByRole('button', {
        name: /^(Show|Hide) details for Browser simulator$/,
    });
    const detailRow = page.locator(`#${await details.getAttribute('aria-controls')}`);
    const serial = page.getByText('BROWSER-SIMULATOR-1', { exact: true });
    await expect(details).toHaveAttribute('aria-expanded', 'false');
    await expect(detailRow).not.toBeVisible();
    await expect(serial).not.toBeVisible();
    if (isMobile) {
        await expect(page.getByRole('table')).toHaveCount(1);
        for (const heading of ['Hardware', 'Last updated', 'Status']) {
            await expect(
                page.getByRole('columnheader', { name: heading, exact: true }),
            ).toHaveCount(1);
        }
        const row = page.locator('.device-table-row');
        await expect(row).toHaveCSS('display', 'block');
        for (const label of ['Device', 'Hardware', 'Last updated', 'Status', 'Actions']) {
            await expect(
                row.locator('.device-field-label').filter({ hasText: new RegExp(`^${label}$`) }),
            ).toBeVisible();
        }
        const rowBounds = await row.boundingBox();
        expect(rowBounds.x).toBeGreaterThanOrEqual(0);
        expect(rowBounds.x + rowBounds.width).toBeLessThanOrEqual(page.viewportSize().width);
    }
    await details.focus();
    await page.keyboard.press('Enter');
    await expect(details).toHaveAccessibleName('Hide details for Browser simulator');
    await expect(details).toHaveAttribute('aria-expanded', 'true');
    await expect(detailRow).toBeVisible();
    await expect(serial).toBeVisible();
    await expect(page.getByText('FIXTURE', { exact: true })).toBeVisible();
    await expect(detailRow.getByText('1 Fixture Street', { exact: true })).toBeVisible();
    await checkAccessibility(page);
    await details.click();
    await expect(details).toHaveAttribute('aria-expanded', 'false');
    await expect(serial).not.toBeVisible();
    const actions = page.getByRole('button', {
        name: 'Actions for Browser simulator',
        exact: true,
    });
    await actions.scrollIntoViewIfNeeded();
    await actions.focus();
    await page.keyboard.press('Enter');
    await expect(actions).toHaveAttribute('aria-expanded', 'true');
    await expect(details).toHaveAttribute('aria-expanded', 'false');
    const menu = page.locator(`#${await actions.getAttribute('aria-controls')}`);
    await expect(menu).toBeVisible();
    await expect(
        menu.getByRole('link', { name: 'View Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        menu.getByRole('link', { name: 'Delete Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(menu).not.toBeVisible();
    await expect(page.locator('#devices-per-page')).toBeFocused();
    await actions.focus();
    await page.keyboard.press('Enter');
    await expect(
        menu.getByRole('link', { name: 'View Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(menu).not.toBeVisible();
    await expect(actions).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(menu).toBeVisible();
    const menuBounds = await menu.boundingBox();
    expect(menuBounds).not.toBeNull();
    expect(menuBounds.x).toBeGreaterThanOrEqual(0);
    expect(menuBounds.y).toBeGreaterThanOrEqual(0);
    expect(menuBounds.x + menuBounds.width).toBeLessThanOrEqual(page.viewportSize().width);
    expect(menuBounds.y + menuBounds.height).toBeLessThanOrEqual(page.viewportSize().height);
    for (const action of ['View', 'Edit', 'Delete']) {
        const link = menu.getByRole('link', { name: `${action} Browser simulator`, exact: true });
        await expect(link).toBeVisible();
        expect(
            await link.evaluate((element) => {
                const bounds = element.getBoundingClientRect();
                const hit = document.elementFromPoint(
                    bounds.x + bounds.width / 2,
                    bounds.y + bounds.height / 2,
                );
                return hit !== null && element.contains(hit);
            }),
        ).toBe(true);
    }
    await checkAccessibility(page);
    await page.keyboard.press('Escape');
    await expect(menu).not.toBeVisible();
    await expect(actions).toBeFocused();
    await actions.click();
    await page.getByRole('heading', { name: 'Device Manager', exact: true }).click();
    await expect(menu).not.toBeVisible();
    await actions.click();
    const canceled = page.waitForEvent('dialog').then(async (dialog) => {
        expect(dialog.type()).toBe('confirm');
        await dialog.dismiss();
    });
    await Promise.all([
        canceled,
        menu.getByRole('link', { name: 'Delete Browser simulator', exact: true }).click(),
    ]);
    expect(deletionRequests).toEqual([]);
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(details).toHaveAttribute('aria-expanded', 'false');
    if ((await actions.getAttribute('aria-expanded')) !== 'true') await actions.click();
    await menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }).click();
    await expect(page).toHaveURL(/\/edit-device\/1$/);
    await expect(page.getByRole('heading', { name: 'Edit device', exact: true })).toBeVisible();

    await page.goto('/dashboard?show=20&page=2');
    const search = page.getByLabel('Search by device alias', { exact: true });
    await search.fill('simulator');
    const matchingMapRequest = page.waitForRequest((request) => {
        const url = new URL(request.url());
        return (
            url.pathname === '/paginated-devices' && url.searchParams.get('search') === 'simulator'
        );
    });
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await matchingMapRequest;
    const matchingUrl = new URL(page.url());
    expect(matchingUrl.searchParams.get('search')).toBe('simulator');
    expect(matchingUrl.searchParams.get('show')).toBe('20');
    expect(Number(matchingUrl.searchParams.get('page') ?? 1)).toBe(1);
    await expect(search).toHaveValue('simulator');
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    const allMapRequest = page.waitForRequest((request) => {
        const url = new URL(request.url());
        return url.pathname === '/all-devices' && url.searchParams.get('search') === 'simulator';
    });
    await page.getByLabel('Show all matching devices on map').check();
    await allMapRequest;
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    await search.fill('no-matching-fixture');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(search).toHaveValue('no-matching-fixture');
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toHaveCount(0);
    await expect(page.getByText('No devices available for this map.')).toBeVisible();
    await expect(
        page.getByText(
            'No devices match “no-matching-fixture”. Try another alias or clear the search.',
        ),
    ).toBeVisible();
    await expect(page.getByText('No devices registered yet.', { exact: false })).toHaveCount(0);
    await page.getByRole('link', { name: 'Clear search', exact: true }).click();
    await expect(search).toHaveValue('');
    expect(new URL(page.url()).searchParams.get('search')).toBeNull();
    expect(new URL(page.url()).searchParams.get('show')).toBe('20');
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Showing 1 of 1 device locations.')).toBeVisible();
    await checkAccessibility(page);
    expect(deletionRequests).toEqual([]);
    const alias = page
        .getByRole('rowheader', { name: 'Browser simulator', exact: true })
        .getByRole('link');
    await expect(alias).toHaveAttribute('href', /\/device-info\/1$/);
    await alias.hover();
    await checkAccessibility(page);
    await alias.click();
    await expect(page).toHaveURL(/\/device-info\/1$/);
    await expect(
        page.getByRole('heading', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
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
    const actions = page.getByRole('button', {
        name: 'Actions for Browser simulator',
        exact: true,
    });
    await actions.click();
    await expect(
        page.getByRole('button', { name: 'Show details for Browser simulator', exact: true }),
    ).toHaveAttribute('aria-expanded', 'false');
    await page
        .locator(`#${await actions.getAttribute('aria-controls')}`)
        .getByRole('link', { name: 'View Browser simulator', exact: true })
        .click();
    await expect(page).toHaveURL(/\/device-info\/1$/);
    await expect(
        page.getByRole('heading', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Overview', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).not.toBeVisible();
    await expect(page.locator('#device-panel-control')).not.toBeVisible();
    await checkAccessibility(page);
    await page.getByRole('button', { name: /^Explore history/ }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#device-panel-history')).toBeFocused();
    await expect(page.getByRole('tab', { name: 'History', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    await expect(page.getByText('6 raw readings', { exact: true })).toBeVisible();
    await expect(page.locator('canvas')).toHaveCount(1);
    await expect(page.getByRole('img', { name: /Temperature.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /(?:AM|PM)/,
    );
    await page.getByRole('button', { name: '1 hour', exact: true }).click();
    await expect(page.getByText('2 raw readings', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: '12 hours', exact: true }).click();
    await expect(page.getByText('4 raw readings', { exact: true })).toBeVisible();
    await page.getByLabel('Measurement', { exact: true }).selectOption('panels');
    await expect(page.getByRole('img', { name: /Panel sensors.*Pacific Time/ })).toBeVisible();
    await checkAccessibility(page);
    await page.getByRole('tab', { name: 'Control', exact: true }).click();
    await expect(page.locator('#device-panel-control')).toBeVisible();
    await expect(page.locator('#device-panel-history')).not.toBeVisible();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).toBeVisible();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).not.toBeChecked();
    await expect(page.getByRole('button', { name: 'Up', exact: true })).toBeDisabled();
    await checkAccessibility(page);
    await page.reload();
    await expect(page.getByRole('tab', { name: 'Overview', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    expect(commands).toEqual([]);
    await page.getByRole('link', { name: '← Devices', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    expect(commands).toEqual([]);
});

test('auth and public documents retain URLs, form labels and keyboard access', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByRole('heading', { name: 'Login', exact: true })).toBeVisible();
    await expect(page.locator('button[aria-controls="account-navigation"]')).not.toBeVisible();
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

test('developer authorization and multipart server validation remain in Laravel', async ({
    page,
}) => {
    await login(page);
    const forbidden = await page.goto('/developer-workspace');
    expect(forbidden.status()).toBe(403);
    await page.context().clearCookies();
    await login(page, 'admin');
    const dropdown = await openAccountNavigation(page);
    const developerLink = dropdown.getByRole('link', { name: 'Developer Workspace', exact: true });
    await expect(developerLink).toHaveAttribute('href', /\/developer-workspace$/);
    await developerLink.click();
    await expect(dropdown).not.toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Developer Workspace', exact: true }),
    ).toBeVisible();
    await expect(page.locator('#developer-firmware-panel')).toBeVisible();
    await expect(page.locator('#firmware-upload')).not.toBeVisible();
    await expect(page.locator('#developer-config-panel')).not.toBeVisible();
    await checkAccessibility(page);
    await page.getByRole('tab', { name: /^Firmware/ }).focus();
    await page.keyboard.press('End');
    await expect(page.getByRole('tab', { name: /^Configuration/ })).toBeFocused();
    await expect(page.locator('#developer-config-panel')).toBeVisible();
    await expect(page.locator('#developer-firmware-panel')).not.toBeVisible();
    await page
        .locator('#developer-config-panel')
        .getByRole('button', { name: 'New upload' })
        .click();
    await expect(page.locator('#config')).toBeFocused();
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
