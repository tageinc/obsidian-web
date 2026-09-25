import { test, expect } from '@playwright/test';

const password = 'browser-test-password';

test.beforeEach(async ({ context }) => {
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
});

async function login(page, role = 'owner') {
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(`${role}@browser.example.test`);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function actionsMenu(page) {
    const button = page.getByRole('button', {
        name: 'Actions for Browser simulator',
        exact: true,
    });
    await button.click();
    const menu = page.locator(`#${await button.getAttribute('aria-controls')}`);
    await expect(menu).toBeVisible();
    return menu;
}

test('dashboard shows only the current device experience', async ({ page }) => {
    await login(page);

    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Device locations' })).toBeVisible();
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Status', exact: true })).toBeVisible();
    await expect(
        page.getByRole('columnheader', { name: 'Last updated', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('columnheader', { name: 'Hardware', exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Show all devices on map')).toHaveCount(0);

    const search = page.getByLabel('Search by device name', { exact: true });
    await search.fill('simulator');
    await page.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page).toHaveURL(/search=simulator/);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
});

test('dashboard details and edit modal use the current fields', async ({ page }) => {
    await login(page);

    const details = page.getByRole('button', { name: 'Show details for Browser simulator' });
    await details.click();
    await expect(page.getByText('BROWSER-SIMULATOR-1', { exact: true })).toBeVisible();
    await expect(page.getByText('FIXTURE', { exact: true })).toBeVisible();
    await expect(
        page.getByText('1 Fixture Street, Test City, CA, 90001, US', { exact: true }),
    ).toBeVisible();

    const menu = await actionsMenu(page);
    await menu.getByRole('link', { name: 'Edit Browser simulator', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Edit device', exact: true });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Serial number', { exact: true })).toHaveValue(
        'BROWSER-SIMULATOR-1',
    );
    await expect(dialog.getByLabel('Serial number', { exact: true })).toHaveAttribute(
        'readonly',
        '',
    );
    await expect(dialog.getByLabel('SKU', { exact: true })).toHaveValue('FIXTURE');
    await dialog.getByRole('button', { name: 'Close device', exact: true }).click();
});

test('Inactivate and Reactivate immediately change the device state', async ({ page }) => {
    await login(page);

    let menu = await actionsMenu(page);
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        menu.getByRole('button', { name: 'Inactivate Browser simulator', exact: true }).click(),
    ]);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toHaveCount(0);

    await page.getByRole('link', { name: 'Show inactive', exact: true }).click();
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
    menu = await actionsMenu(page);
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        menu.getByRole('button', { name: 'Reactivate Browser simulator', exact: true }).click(),
    ]);
    await expect(
        page.getByRole('rowheader', { name: 'Browser simulator', exact: true }),
    ).toBeVisible();
});

test('device view is a page with overview, history, and control tabs', async ({ page }) => {
    await login(page);
    await page.getByRole('link', { name: 'Browser simulator', exact: true }).click();

    await expect(page).toHaveURL(/\/devices\/1$/);
    await expect(page.locator('h1')).toHaveText('Browser simulator');
    await expect(page.getByText('BROWSER-SIMULATOR-1', { exact: true })).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Overview', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );

    await page.getByRole('tab', { name: 'History', exact: true }).click();
    await expect(page.getByRole('tab', { name: 'History', exact: true })).toHaveAttribute(
        'aria-selected',
        'true',
    );
    await page.getByRole('tab', { name: 'Control', exact: true }).click();
    await expect(page.getByRole('switch', { name: 'Remote control mode' })).toBeVisible();
    await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
});

test('Create Device remains an in-place modal without hardware selection', async ({ page }) => {
    await login(page);
    await page.getByRole('button', { name: 'Create +', exact: true }).click();

    const dialog = page.getByRole('dialog', { name: 'Create Device', exact: true });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel('Hardware', { exact: true })).toHaveCount(0);
    await expect(dialog.getByLabel('Latitude', { exact: true })).toHaveValue(/.+/);
    await expect(dialog.getByLabel('Longitude', { exact: true })).toHaveValue(/.+/);
    await dialog.getByRole('button', { name: 'Close creation', exact: true }).click();
    await expect(dialog).not.toBeVisible();
});
