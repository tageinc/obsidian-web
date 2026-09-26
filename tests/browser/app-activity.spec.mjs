import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

test.beforeEach(async ({ context }) => {
    await context.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.hostname.endsWith('.tile.openstreetmap.org')) {
            return route.fulfill({
                contentType: 'image/svg+xml',
                body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#eee"/></svg>',
            });
        }
        if (
            url.pathname.includes('/remote-control') ||
            url.pathname.includes('/update-solar-tracker')
        ) {
            return route.abort();
        }
        if (url.hostname === 'cdn.jsdelivr.net' && url.pathname.includes('bootstrap')) {
            const asset = url.pathname.endsWith('.css')
                ? 'node_modules/bootstrap/dist/css/bootstrap.min.css'
                : 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js';
            return route.fulfill({
                contentType: asset.endsWith('.css') ? 'text/css' : 'application/javascript',
                body: readFileSync(asset),
            });
        }
        if (url.hostname === 'cdn.jsdelivr.net' && url.pathname.includes('chart.js')) {
            return route.fulfill({
                contentType: 'application/javascript',
                body: readFileSync('node_modules/chart.js/dist/chart.umd.js'),
            });
        }
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        return route.continue();
    });
});

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill('owner@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

async function api(page, path, method = 'GET', data) {
    return page.evaluate(
        async ({ path, method, data }) => {
            const response = await fetch(path, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: data === undefined ? undefined : JSON.stringify(data),
            });
            if (!response.ok)
                throw new Error(`Fixture request failed: ${method} ${path} (${response.status})`);
            return response.json();
        },
        { path, method, data },
    );
}

async function reset(page) {
    await api(page, '/api/app-activity', 'DELETE');
    await api(page, '/api/user-settings', 'PATCH', { receive_app_activity_emails: true });
}

test('settings persist and activity remains available after opting out of email', async ({
    page,
}) => {
    await login(page);
    await reset(page);
    try {
        const gear = page.getByRole('button', { name: 'User Settings', exact: true });
        await gear.click();
        const dialog = page.getByRole('dialog', { name: 'User Settings', exact: true });
        await expect(dialog).toBeVisible();
        const checkbox = dialog.getByLabel('Receive app activity emails', { exact: true });
        await expect(checkbox).toBeChecked();
        await checkbox.uncheck();
        await dialog.getByRole('button', { name: 'Save settings', exact: true }).click();
        await expect(dialog).toContainText('Settings saved.');
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await page.reload();
        await gear.click();
        await expect(checkbox).not.toBeChecked();
        await checkbox.check();
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        await gear.click();
        await expect(checkbox).not.toBeChecked();
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await expect(gear).toBeFocused();

        await api(page, '/__browser-fixtures/app-activity', 'POST', { count: 12 });
        const bell = page.getByRole('button', { name: 'App activity', exact: true });
        await bell.click();
        const feed = page.getByRole('region', { name: 'App activity', exact: true });
        await expect(feed).toBeVisible();
        await expect(feed.locator('.activity-feed__item')).toHaveCount(12);
        await expect(bell).toContainText('9+');
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
        await page.keyboard.press('Escape');
        await expect(feed).not.toBeVisible();
        await expect(bell).toBeFocused();

        await bell.click();
        await feed.locator('.activity-feed__open').first().click();
        await expect(page).toHaveURL(/\/devices\/1$/);
        await expect(page.locator('h1')).toHaveText('Browser simulator');
        let persisted = await api(page, '/api/app-activity');
        expect(persisted.unread_count).toBe(11);
        expect(persisted.data.filter((entry) => entry.read_at !== null)).toHaveLength(1);
        await page.reload();
        await expect(page.locator('h1')).toBeFocused();
        await bell.click();
        await feed
            .getByRole('button', { name: 'Dismiss activity: Device status changed', exact: true })
            .first()
            .click();
        await expect(feed.locator('.activity-feed__item')).toHaveCount(11);
        persisted = await api(page, '/api/app-activity');
        expect(persisted.data).toHaveLength(11);
        await feed.getByRole('button', { name: 'Clear all', exact: true }).click();
        await expect(feed).toContainText('You’re all caught up.');
        await page.reload();
        await expect(page.locator('h1')).toBeFocused();
        await bell.click();
        await expect(feed).toContainText('You’re all caught up.');
        expect((await api(page, '/api/app-activity')).unread_count).toBe(0);
        expect((await api(page, '/api/user-settings')).data.receive_app_activity_emails).toBe(
            false,
        );
    } finally {
        await reset(page);
    }
});

test('legacy navigation offers the same settings and activity controls', async ({ page }) => {
    await login(page);
    await reset(page);
    try {
        await api(page, '/__browser-fixtures/app-activity', 'POST', { count: 1 });
        await page.goto('/__browser-fixtures/devices/1/legacy');
        const gear = page.getByRole('button', { name: 'User Settings', exact: true });
        await gear.click();
        const dialog = page.getByRole('dialog', { name: 'User Settings', exact: true });
        await expect(
            dialog.getByLabel('Receive app activity emails', { exact: true }),
        ).toBeChecked();
        await dialog.getByLabel('Receive app activity emails', { exact: true }).uncheck();
        await dialog.getByRole('button', { name: 'Save settings', exact: true }).click();
        await expect(dialog).toContainText('Settings saved.');
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible();
        await page.reload();
        await gear.click();
        await expect(
            dialog.getByLabel('Receive app activity emails', { exact: true }),
        ).not.toBeChecked();
        await page.keyboard.press('Escape');
        await expect(gear).toBeFocused();
        await page.getByRole('button', { name: 'App activity', exact: true }).click();
        const feed = page.getByRole('region', { name: 'App activity', exact: true });
        await expect(feed.locator('.activity-feed__item')).toHaveCount(1);
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
        await feed.getByRole('button', { name: 'Clear all', exact: true }).click();
        await expect(feed).toContainText('You’re all caught up.');
    } finally {
        await reset(page);
    }
});
