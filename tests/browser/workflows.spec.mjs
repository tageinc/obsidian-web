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
    // Browser coverage cancels retirement; no regression may change even a synthetic device.
    await page.route('**/retire-device/*', (route) => route.abort());
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

async function checkModalKeyboardAndViewport(page, dialog) {
    await expect(dialog).toBeVisible();
    expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
    const bounds = await dialog.boundingBox();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(page.viewportSize().width);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(page.viewportSize().height);
    const controls = dialog.locator(
        'button:not([disabled]):visible, a[href]:visible, input:not([type="hidden"]):not([disabled]):visible, select:not([disabled]):visible, textarea:not([disabled]):visible',
    );
    await controls.last().focus();
    await page.keyboard.press('Tab');
    await expect(controls.first()).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(controls.last()).toBeFocused();
    await checkAccessibility(page);
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
    await expect(dropdown.getByRole('link', { name: 'Create Device' })).toHaveCount(0);
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

test('device actions stay usable and name search keeps the table and map in sync', async ({
    page,
    isMobile,
}) => {
    const retirementRequests = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname.startsWith('/retire-device/')) {
            retirementRequests.push(request.url());
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
    await expect(menu.getByRole('link')).toHaveCount(1);
    await expect(menu.getByRole('button')).toHaveCount(1);
    await expect(menu.getByRole('separator')).toHaveCount(0);
    await expect(
        menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        menu.getByRole('button', { name: 'Retire Browser simulator', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(menu).not.toBeVisible();
    await expect(page.locator('#devices-per-page')).toBeFocused();
    await actions.focus();
    await page.keyboard.press('Enter');
    await expect(
        menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }),
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
    for (const action of [
        menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }),
        menu.getByRole('button', { name: 'Retire Browser simulator', exact: true }),
    ]) {
        await expect(action).toBeVisible();
        expect(
            await action.evaluate((element) => {
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
    await menu.getByRole('button', { name: 'Retire Browser simulator', exact: true }).click();
    await expect.poll(() => retirementRequests.length).toBe(1);
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(details).toHaveAttribute('aria-expanded', 'false');
    if ((await actions.getAttribute('aria-expanded')) !== 'true') await actions.click();
    await menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    const editDialog = page.getByRole('dialog', { name: 'Edit device', exact: true });
    await expect(editDialog).toBeVisible();
    await expect(editDialog.getByLabel('Device name', { exact: true })).toHaveValue(
        'Browser simulator',
    );
    await editDialog.getByRole('button', { name: 'Close device', exact: true }).click();
    await expect(editDialog).not.toBeVisible();
    await expect(actions).toBeFocused();

    await page.goto('/dashboard?show=20&page=2');
    const search = page.getByLabel('Search by device name', { exact: true });
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
            'No devices match “no-matching-fixture”. Try another name or clear the search.',
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
    expect(retirementRequests).toHaveLength(1);
    const name = page
        .getByRole('rowheader', { name: 'Browser simulator', exact: true })
        .getByRole('link');
    await expect(name).toHaveAttribute('href', /\/devices\/1$/);
    await name.hover();
    await checkAccessibility(page);
    await name.click();
    await expect(page).toHaveURL(/\/dashboard(?:\?|$)/);
    const viewDialog = page.getByRole('dialog', { name: 'View device', exact: true });
    await expect(viewDialog).toBeVisible();
    await expect(viewDialog.getByRole('tab', { name: 'Overview', exact: true })).toBeVisible();
    await expect(viewDialog.getByText('BROWSER-SIMULATOR-1', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(viewDialog).not.toBeVisible();
    await expect(name).toBeFocused();
});

test('device view modal includes history and control without issuing commands and switches directly to edit', async ({
    page,
}) => {
    const commands = [];
    const modalReads = [];
    page.on('request', (request) => {
        const url = new URL(request.url());
        if (url.pathname === '/update-solar-tracker') commands.push(request.method());
        if (request.method() === 'GET' && /^\/(?:view-device|edit-device)\/1$/.test(url.pathname)) {
            modalReads.push({
                path: url.pathname,
                modal: request.headers()['x-obsidian-modal'],
            });
        }
    });
    await login(page);
    await page.goto('/dashboard?show=20&search=simulator');
    const dashboardUrl = page.url();
    const name = page
        .getByRole('rowheader', { name: 'Browser simulator', exact: true })
        .getByRole('link');
    await name.click();
    const view = page.getByRole('dialog', { name: 'View device', exact: true });
    await expect(view.getByRole('tab', { name: 'Overview', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    await expect(page).toHaveURL(dashboardUrl);
    await expect(page.locator('dialog[open]')).toHaveCount(1);
    await checkModalKeyboardAndViewport(page, view);
    await view.getByRole('button', { name: /^Explore history/ }).click();
    await expect(view.getByRole('tab', { name: 'History', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    await expect(view.getByText('6 raw readings', { exact: true })).toBeVisible();
    await expect(view.getByRole('img', { name: /Temperature.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /(?:AM|PM)/,
    );
    await view.getByRole('button', { name: '1 hour', exact: true }).click();
    await expect(view.getByText('2 raw readings', { exact: true })).toBeVisible();
    await view.getByLabel('Measurement', { exact: true }).selectOption('panels');
    await expect(view.getByRole('img', { name: /Panel sensors.*Pacific Time/ })).toBeVisible();
    await view.getByRole('tab', { name: 'Control', exact: true }).click();
    await expect(view.getByRole('switch', { name: 'Remote control mode' })).not.toBeChecked();
    await expect(view.getByRole('slider', { name: 'Motor speed', exact: true })).toBeDisabled();
    await checkAccessibility(page);
    expect(commands).toEqual([]);
    await view.getByRole('button', { name: 'Edit device', exact: true }).click();
    const edit = page.getByRole('dialog', { name: 'Edit device', exact: true });
    await expect(edit.getByLabel('Device name', { exact: true })).toHaveValue('Browser simulator');
    await expect(view).not.toBeVisible();
    await expect(page.locator('dialog[open]')).toHaveCount(1);
    await expect(page).toHaveURL(dashboardUrl);
    await expect(
        edit.locator('[name="serial_no"], [name="hardware_id"], [name="status_notification"]'),
    ).toHaveCount(0);
    await checkModalKeyboardAndViewport(page, edit);
    await page.keyboard.press('Escape');
    await expect(edit).not.toBeVisible();
    await expect(name).toBeFocused();
    expect(modalReads).toEqual([
        { path: '/devices/1', modal: '1' },
        { path: '/edit-device/1', modal: '1' },
    ]);
    expect(commands).toEqual([]);
});

test('device modal shows loading and recoverable errors without navigating away', async ({
    page,
}) => {
    await login(page);
    const reads = [];
    let release;
    const waiting = new Promise((resolve) => {
        release = resolve;
    });
    await page.route('**/devices/1', async (route) => {
        reads.push(route.request().headers()['x-obsidian-modal']);
        if (reads.length === 1) {
            await waiting;
            await route.fulfill({
                status: 503,
                contentType: 'application/json',
                body: '{"message":"Synthetic temporary failure"}',
            });
        } else {
            await route.continue();
        }
    });
    const name = page
        .getByRole('rowheader', { name: 'Browser simulator', exact: true })
        .getByRole('link');
    await name.click();
    const dialog = page.getByRole('dialog', { name: 'View device', exact: true });
    await expect(dialog.getByRole('status')).toHaveText('Loading device…');
    await expect(page).toHaveURL(/\/dashboard$/);
    release();
    await expect(dialog.getByRole('alert')).toBeFocused();
    await expect(dialog.getByRole('button', { name: 'Try again', exact: true })).toBeVisible();
    await checkModalKeyboardAndViewport(page, dialog);
    await dialog.getByRole('button', { name: 'Try again', exact: true }).click();
    await expect(dialog.getByRole('tab', { name: 'Overview', exact: true })).toBeVisible();
    await expect(dialog.getByRole('alert')).toHaveCount(0);
    expect(reads).toEqual(['1', '1']);
    await dialog.getByRole('button', { name: 'Close device', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(name).toBeFocused();
});

test('device edit modal retains server validation, guards pending and reloads the same dashboard after saving', async ({
    page,
    isMobile,
}) => {
    await login(page);
    await page.goto('/dashboard?show=20&search=simulator');
    const dashboardUrl = page.url();
    const actions = page.getByRole('button', {
        name: 'Actions for Browser simulator',
        exact: true,
    });
    await actions.click();
    const menu = page.locator('#' + (await actions.getAttribute('aria-controls')));
    await menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }).click();
    const edit = page.getByRole('dialog', { name: 'Edit device', exact: true });
    await expect(edit.getByLabel('Device name', { exact: true })).toHaveValue('Browser simulator');
    await expect(page).toHaveURL(dashboardUrl);
    await checkModalKeyboardAndViewport(page, edit);
    const originalCity = await edit.getByLabel('City', { exact: true }).inputValue();
    const originalAddress2 = await edit
        .getByLabel('Address line 2 (optional)', { exact: true })
        .inputValue();
    const changedAddress2 = 'Synthetic modal save ' + (isMobile ? 'mobile' : 'desktop');
    await edit.getByLabel('Address line 2 (optional)', { exact: true }).fill(changedAddress2);
    await edit.getByLabel('City', { exact: true }).fill('');
    // Exercise the real Laravel 422 response rather than stopping at native required validation.
    await edit.locator('form').evaluate((form) => {
        form.noValidate = true;
    });
    let release;
    let held = false;
    const hold = new Promise((resolve) => {
        release = resolve;
    });
    await page.route('**/edit-device/1', async (route) => {
        if (route.request().method() === 'PUT' && !held) {
            held = true;
            await hold;
        }
        await route.continue();
    });
    const rejected = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/edit-device/1' &&
            response.request().method() === 'PUT',
    );
    const submitted = page.waitForRequest(
        (request) =>
            new URL(request.url()).pathname === '/edit-device/1' && request.method() === 'PUT',
    );
    await edit.getByRole('button', { name: 'Update device', exact: true }).click();
    const request = await submitted;
    expect(request.headers()['content-type']).toContain('application/json');
    expect(request.headers()['x-csrf-token']).toBeTruthy();
    expect(request.headers()['x-obsidian-modal']).toBe('1');
    expect(request.postDataJSON()).toMatchObject({ city: '', address_2: changedAddress2 });
    await expect(edit.locator('button[type="submit"]')).toBeDisabled();
    await expect(edit.getByRole('button', { name: 'Close device', exact: true })).toBeDisabled();
    await expect(edit.getByRole('button', { name: 'Cancel', exact: true })).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(edit).toBeVisible();
    release();
    expect((await rejected).status()).toBe(422);
    await expect(edit.getByLabel('City', { exact: true })).toHaveAttribute('aria-invalid', 'true');
    await expect(edit.getByLabel('Address line 2 (optional)', { exact: true })).toHaveValue(
        changedAddress2,
    );
    await expect(edit.getByRole('alert')).toBeFocused();
    await expect(edit.getByRole('button', { name: 'Update device', exact: true })).toBeEnabled();
    await expect(page).toHaveURL(dashboardUrl);
    await checkAccessibility(page);
    await edit.getByLabel('City', { exact: true }).fill(originalCity);
    const saved = page.waitForResponse(
        (response) =>
            new URL(response.url()).pathname === '/edit-device/1' &&
            response.request().method() === 'PUT',
    );
    const reloaded = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
    await edit.getByRole('button', { name: 'Update device', exact: true }).click();
    expect((await saved).status()).toBe(200);
    await reloaded;
    await expect(page).toHaveURL(dashboardUrl);
    await expect(edit).not.toBeVisible();
    await expect(page.getByText('Device updated successfully.', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Search by device name', { exact: true })).toHaveValue(
        'simulator',
    );
    await actions.click();
    await menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }).click();
    await expect(edit.getByLabel('Address line 2 (optional)', { exact: true })).toHaveValue(
        changedAddress2,
    );
    // Restore the isolated fixture so later desktop/mobile cases see the same baseline.
    await edit.getByLabel('Address line 2 (optional)', { exact: true }).fill(originalAddress2);
    const restored = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
    await edit.getByRole('button', { name: 'Update device', exact: true }).click();
    await restored;
    await expect(page).toHaveURL(dashboardUrl);
    await expect(edit).not.toBeVisible();
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
});

test('creation modal stays in the dashboard and preserves native validation and keyboard focus', async ({
    page,
}) => {
    await login(page);
    const create = page.getByRole('button', { name: 'Create +', exact: true });
    await expect(create).toBeVisible();
    const mapBounds = await page.locator('#map').boundingBox();
    const createBounds = await create.boundingBox();
    const managerBounds = await page
        .getByRole('heading', { name: 'Device Manager', exact: true })
        .boundingBox();
    expect(createBounds.y).toBeGreaterThanOrEqual(mapBounds.y + mapBounds.height);
    expect(createBounds.y + createBounds.height).toBeLessThanOrEqual(managerBounds.y);

    const dialog = page.getByRole('dialog', { name: 'Create Device', exact: true });
    await expect(dialog).not.toBeVisible();
    await create.focus();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeVisible();
    await expect(page).toHaveURL(/\/dashboard$/);
    await checkModalKeyboardAndViewport(page, dialog);
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
    await expect(create).toBeFocused();

    await create.click();
    await dialog.getByRole('button', { name: 'Close creation', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(create).toBeFocused();
    await create.click();
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(create).toBeFocused();

    await create.click();
    await expect(dialog.locator('form')).toHaveAttribute('method', /post/i);
    await expect(dialog.locator('form')).toHaveAttribute('action', /\/createDevice$/);
    await expect(dialog.locator('[name="_creation_modal"]')).toHaveValue('1');
    await expect(dialog.locator('button[type="submit"]')).toHaveCount(1);
    await dialog.getByLabel('Hardware', { exact: true }).selectOption('1');
    await dialog.getByLabel('Serial number', { exact: true }).fill('BROWSER-SIMULATOR-1');
    await dialog.getByLabel('SKU', { exact: true }).fill('TEST');
    await dialog.getByLabel('Order number', { exact: true }).fill('TEST-ORDER');
    await dialog.getByLabel('Device name', { exact: true }).fill('Modal duplicate fixture');
    await dialog.getByLabel('City', { exact: true }).fill('Modal city');
    await dialog.getByLabel('Latitude', { exact: true }).fill('0');
    await dialog.getByLabel('Longitude', { exact: true }).fill('0');
    await dialog.getByRole('button', { name: 'Create Device', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Serial number', { exact: true })).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await expect(dialog.getByLabel('Device name', { exact: true })).toHaveValue(
        'Modal duplicate fixture',
    );
    await expect(dialog.getByLabel('City', { exact: true })).toHaveValue('Modal city');
    await expect(dialog.getByLabel('Latitude', { exact: true })).toHaveValue('0');
    await expect(dialog.getByRole('alert')).toBeFocused();
    await expect(
        dialog.getByRole('button', { name: 'Create Device', exact: true }),
    ).toBeEnabled();
    await checkAccessibility(page);
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(create).toBeFocused();
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
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

    await page.goto('/create-device');
    await expect(page.getByRole('heading', { name: 'Create Device', exact: true })).toBeVisible();
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
    await page.getByRole('button', { name: 'Create Device', exact: true }).click();
    await expect(page.locator('#serial_no')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#name')).toHaveValue('Invalid duplicate fixture');
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
    await page.goto('/devices/1');
    await expect(page).toHaveURL(/\/devices\/1$/);
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
    await expect(page.locator('#device-panel-history .history-plot')).toHaveAttribute(
        'aria-busy',
        'false',
    );
    await expect(page.getByRole('img', { name: /Temperature.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /^Temperature, scatter plot with linear trend lines, 6 raw readings\./,
    );
    await expect(
        page.getByText(
            'Points show raw readings. Dashed lines fit all valid readings in the selected range; a trend needs at least two distinct timestamps.',
            { exact: true },
        ),
    ).toBeVisible();
    await expect(page.getByRole('img', { name: /Temperature.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /(?:AM|PM)/,
    );
    await page.getByRole('button', { name: '1 hour', exact: true }).click();
    await expect(page.getByText('2 raw readings', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: '1 hour', exact: true })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await page.getByRole('button', { name: '12 hours', exact: true }).click();
    await expect(page.getByText('4 raw readings', { exact: true })).toBeVisible();
    await page.getByLabel('Measurement', { exact: true }).selectOption('panels');
    await expect(page.getByRole('img', { name: /Panel sensors.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /^Panel sensors, scatter plot with linear trend lines, 4 raw readings\./,
    );
    await expect(page.getByRole('button', { name: '12 hours', exact: true })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await page.getByLabel('Measurement', { exact: true }).selectOption('motor');
    await expect(page.getByRole('img', { name: /Motor speed.*Pacific Time/ })).toHaveAttribute(
        'aria-label',
        /^Motor speed, scatter plot with linear trend lines, 4 raw readings\./,
    );
    await page.getByRole('button', { name: '24 hours', exact: true }).click();
    await expect(page.getByText('5 raw readings', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'All', exact: true }).click();
    await expect(page.getByText('6 raw readings', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'All', exact: true })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await expect(page.locator('#device-panel-history .history-plot')).toHaveAttribute(
        'aria-busy',
        'false',
    );
    await expect(page.locator('#device-panel-history').getByRole('alert')).toHaveCount(0);
    await checkAccessibility(page);
    await page.getByRole('tab', { name: 'Control', exact: true }).click();
    await expect(page.locator('#device-panel-control')).toBeVisible();
    await expect(page.locator('#device-panel-history')).not.toBeVisible();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).toBeVisible();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).not.toBeChecked();
    await expect(page.getByRole('slider', { name: 'Motor speed', exact: true })).toBeDisabled();
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

test('remote motor speed previews then saves the chosen range value and restores confirmed state after failure', async ({
    page,
}) => {
    // Every motor POST is fulfilled here; the fixture's automatic/zero state is never changed.
    const commands = [];
    let simulated = { mode: 0, motor_speed: 0 };
    let failNext = false;
    let pendingResponse = null;
    await page.route('**/update-solar-tracker', async (route) => {
        const request = route.request();
        expect(request.method()).toBe('POST');
        const payload = request.postDataJSON();
        expect(payload.serial_no).toBe('BROWSER-SIMULATOR-1');
        expect(payload.motor_speed).toBeGreaterThanOrEqual(-100);
        expect(payload.motor_speed).toBeLessThanOrEqual(100);
        expect(Math.abs(payload.motor_speed % 10)).toBe(0);
        commands.push(payload);
        if (pendingResponse) {
            const wait = pendingResponse;
            pendingResponse = null;
            await wait;
        }
        if (failNext) {
            failNext = false;
            return route.fulfill({
                status: 503,
                contentType: 'application/json',
                body: '{"message":"Synthetic command failure"}',
            });
        }
        simulated = {
            mode: payload.mode,
            motor_speed: payload.mode === 1 ? payload.motor_speed : 0,
        };
        return route.fulfill({ json: { success: true, ...simulated } });
    });
    // Replay only the mocked confirmed state when reopening the read-only device modal.
    await page.route('**/devices/1', async (route) => {
        if (route.request().headers()['x-obsidian-modal'] !== '1') return route.continue();
        const response = await route.fetch();
        const payload = await response.json();
        payload.props.remote = { ...payload.props.remote, ...simulated };
        return route.fulfill({ response, json: payload });
    });
    await login(page);
    const name = page
        .getByRole('rowheader', { name: 'Browser simulator', exact: true })
        .getByRole('link');
    await name.click();
    const dialog = page.getByRole('dialog', { name: 'View device', exact: true });
    await dialog.getByRole('tab', { name: 'Control', exact: true }).click();
    const remote = dialog.locator('section[aria-labelledby="remote-title"]');
    const mode = remote.getByRole('switch', { name: 'Remote control mode', exact: true });
    const slider = remote.getByRole('slider', { name: 'Motor speed', exact: true });
    await expect(slider).toHaveAttribute('min', '-100');
    await expect(slider).toHaveAttribute('max', '100');
    await expect(slider).toHaveAttribute('step', '10');
    await expect(slider).toBeDisabled();
    await expect(slider).toHaveValue('0');
    await expect(remote.locator('#remote-speed-value')).toHaveText('Stop (0)');
    await expect(remote.getByRole('button', { name: /update|up|stop|down/i })).toHaveCount(0);
    expect(commands).toEqual([]);

    await mode.check();
    await expect(slider).toBeEnabled();
    expect(commands).toHaveLength(1);
    expect(commands[0]).toMatchObject({ mode: 1, motor_speed: 0 });
    async function preview(value) {
        await slider.evaluate((element, speed) => {
            element.value = String(speed);
            element.dispatchEvent(new Event('input', { bubbles: true }));
        }, value);
    }
    await preview(-100);
    await expect(slider).toHaveValue('-100');
    await expect(remote.locator('#remote-speed-value')).toHaveText('Down (-100)');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Stop (0)');
    expect(commands).toHaveLength(1);
    let release;
    pendingResponse = new Promise((resolve) => {
        release = resolve;
    });
    await slider.dispatchEvent('change');
    await expect(slider).toBeDisabled();
    await expect(mode).toBeDisabled();
    await expect(remote.locator('[role="status"]')).toContainText('Saving');
    await expect.poll(() => commands.length).toBe(2);
    release();
    await expect(slider).toBeEnabled();
    await expect(remote.locator('#remote-saved-speed')).toHaveText(
        'Saved motor speed: Down (-100)',
    );
    await expect(remote.locator('[role="status"]')).toHaveText('Motor speed saved: Down (-100).');
    await slider.focus();
    await page.keyboard.press('End');
    await expect(slider).toHaveValue('100');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Up (100)');
    expect(commands).toHaveLength(3);
    expect(commands[2]).toMatchObject({ mode: 1, motor_speed: 100 });
    await expect(slider).toBeFocused();
    await page.keyboard.press('ArrowLeft');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Up (90)');
    await expect(slider).toHaveValue('90');
    await expect(slider).toBeFocused();
    expect(commands).toHaveLength(4);
    expect(commands[3]).toMatchObject({ mode: 1, motor_speed: 90 });
    await page.keyboard.press('ArrowRight');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Up (100)');
    await expect(slider).toHaveValue('100');
    await expect(slider).toBeFocused();
    expect(commands).toHaveLength(5);
    expect(commands[4]).toMatchObject({ mode: 1, motor_speed: 100 });

    failNext = true;
    await preview(-40);
    await expect(remote.locator('#remote-speed-value')).toHaveText('Down (-40)');
    pendingResponse = new Promise((resolve) => {
        release = resolve;
    });
    await slider.dispatchEvent('change');
    await expect(slider).toBeDisabled();
    const editDevice = dialog.getByRole('button', { name: 'Edit device', exact: true });
    await editDevice.focus();
    await expect.poll(() => commands.length).toBe(6);
    release();
    await expect(remote.getByRole('alert')).toBeVisible();
    await expect(slider).toBeEnabled();
    await expect(slider).toHaveValue('100');
    await expect(remote.locator('#remote-speed-value')).toHaveText('Up (100)');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Up (100)');
    expect(commands).toHaveLength(6);
    await expect(editDevice).toBeFocused();
    await dialog.getByRole('button', { name: 'Close device', exact: true }).click();
    await expect(name).toBeFocused();
    await name.click();
    await dialog.getByRole('tab', { name: 'Control', exact: true }).click();
    await expect(mode).toBeChecked();
    await expect(slider).toHaveValue('100');
    await expect(remote.locator('#remote-saved-speed')).toHaveText('Saved motor speed: Up (100)');
    expect(commands).toHaveLength(6);
    await checkAccessibility(page);

    await mode.uncheck();
    await expect(slider).toBeDisabled();
    await expect(mode).toBeEnabled();
    await expect(slider).toHaveValue('0');
    expect(commands[6]).toMatchObject({ mode: 0, motor_speed: 0 });
    await mode.check();
    await expect(slider).toBeEnabled();
    await expect(slider).toHaveValue('0');
    expect(commands[7]).toMatchObject({ mode: 1, motor_speed: 0 });
    expect(commands).toHaveLength(8);
    const unchanged = await page.request.get('/api/remote-control/BROWSER-SIMULATOR-1');
    expect(unchanged.ok()).toBe(true);
    expect(await unchanged.json()).toMatchObject({ mode: 0, motor_speed: 0 });
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
    await expect(page.locator('form[enctype="multipart/form-data"]')).toHaveCount(0);
    await checkAccessibility(page);
    const firmwareTrigger = page
        .locator('#developer-firmware-panel')
        .getByRole('button', { name: 'New upload', exact: true });
    const firmwareDialog = page.getByRole('dialog', { name: 'Upload firmware', exact: true });
    await firmwareTrigger.focus();
    await page.keyboard.press('Enter');
    await checkModalKeyboardAndViewport(page, firmwareDialog);
    await expect(firmwareDialog.locator('form')).toHaveAttribute('action', /\/upload-firmware$/);
    await expect(firmwareDialog.locator('form')).toHaveAttribute('enctype', 'multipart/form-data');
    await page.keyboard.press('Escape');
    await expect(firmwareDialog).not.toBeVisible();
    await expect(firmwareTrigger).toBeFocused();
    await firmwareTrigger.click();
    await firmwareDialog
        .getByRole('button', { name: 'Close firmware upload', exact: true })
        .click();
    await expect(firmwareDialog).not.toBeVisible();
    await expect(firmwareTrigger).toBeFocused();
    await firmwareTrigger.click();
    await firmwareDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(firmwareDialog).not.toBeVisible();
    await expect(firmwareTrigger).toBeFocused();
    await expect(page.locator('form[enctype="multipart/form-data"]')).toHaveCount(0);
    const configPageSize = await page.locator('#config-per-page').inputValue();
    await page.locator('#firmware-per-page').selectOption('20');
    await expect(page).toHaveURL(/firmware_show=20/);
    expect(new URL(page.url()).searchParams.get('config_show')).toBe(configPageSize);
    expect(new URL(page.url()).searchParams.get('section')).toBe('firmware');
    await expect(page.locator('#firmware-per-page')).toHaveValue('20');
    await page.getByRole('tab', { name: /^Firmware/ }).focus();
    await page.keyboard.press('End');
    await expect(page.getByRole('tab', { name: /^Configuration/ })).toBeFocused();
    await expect(page.locator('#developer-config-panel')).toBeVisible();
    await expect(page.locator('#developer-firmware-panel')).not.toBeVisible();
    const configTrigger = page
        .locator('#developer-config-panel')
        .getByRole('button', { name: 'New upload', exact: true });
    const configDialog = page.getByRole('dialog', { name: 'Upload configuration', exact: true });
    await configTrigger.click();
    await checkModalKeyboardAndViewport(page, configDialog);
    await configDialog
        .getByRole('button', { name: 'Close configuration upload', exact: true })
        .click();
    await expect(configDialog).not.toBeVisible();
    await expect(configTrigger).toBeFocused();
    await configTrigger.click();
    await configDialog.locator('#config').setInputFiles({
        name: 'invalid.json',
        mimeType: 'text/plain',
        buffer: Buffer.from('not a json document'),
    });
    await configDialog.locator('#config-description').fill('Synthetic rejected upload');
    await configDialog.locator('#config-prefix').fill('BROWSER');
    await configDialog.getByRole('button', { name: 'Upload JSON Config' }).click();
    await expect(configDialog).toBeVisible();
    await expect(firmwareDialog).not.toBeVisible();
    await expect(configDialog.locator('#config')).toHaveAttribute('aria-invalid', 'true');
    await expect(configDialog.locator('#config-description')).toHaveValue(
        'Synthetic rejected upload',
    );
    await expect(configDialog.locator('#config-prefix')).toHaveValue('BROWSER');
    await expect(configDialog.locator('#config')).toHaveValue('');
    await expect(configDialog.getByRole('alert')).toBeFocused();
    await checkAccessibility(page);
    await configDialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(configDialog).not.toBeVisible();
    await expect(configTrigger).toBeFocused();
    await expect(page.locator('#developer-config-panel')).toBeVisible();
    await expect(page.locator('#config-per-page')).toBeEnabled();
    await expect(page.locator('form[enctype="multipart/form-data"]')).toHaveCount(0);
});
