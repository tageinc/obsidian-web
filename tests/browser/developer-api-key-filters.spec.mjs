import { test, expect } from '@playwright/test';
import axe from 'axe-core';

test.beforeEach(async ({ page, context }) => {
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (request.method() !== 'GET' && /remote-control/.test(url.pathname)) return route.abort();
        return route.continue();
    });
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill('developer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeFocused();
    const navigation = page.getByRole('button', { name: 'Toggle navigation' });
    if (await navigation.isVisible()) await navigation.click();
    await page.getByRole('button', { name: 'Developer browser fixture' }).focus();
    await page.keyboard.press('ArrowDown');
    await page.getByRole('link', { name: 'Developer Workspace', exact: true }).click();
    await page.getByRole('tab', { name: 'External API Keys', exact: true }).click();
    await expect(keysPanel(page).getByRole('rowheader').first()).toBeVisible();
    await keysPanel(page).getByLabel('API keys per page', { exact: true }).selectOption('10');
    await expect(keysPanel(page).getByRole('rowheader')).toHaveCount(10);
});

function keysPanel(page) {
    return page.getByRole('tabpanel', { name: 'External API Keys', exact: true });
}

function fixtureName(index) {
    return `Fixture integration ${String(index).padStart(2, '0')}`;
}

async function search(page, value) {
    const panel = keysPanel(page);
    await panel.getByLabel('Search API keys', { exact: true }).fill(value);
    await panel.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(panel.getByLabel('Search API keys', { exact: true })).toHaveValue(value);
    await expect.poll(() => new URL(page.url()).searchParams.get('keys_search')).toBe(value);
}

async function chooseFilter(page, label, text, value) {
    const input = page.getByRole('combobox', { name: label, exact: true });
    await input.fill(text);
    const listboxId = await input.getAttribute('aria-controls');
    const option = page
        .locator(`[id="${listboxId}"]`)
        .getByRole('option', { name: text, exact: true });
    await expect(option).toBeVisible();
    await input.press('ArrowDown');
    await expect(option).toHaveAttribute('aria-selected', 'true');
    await input.press('Enter');
    await expect(input.locator('..').locator(`.item[data-value="${value}"]`)).toBeVisible();
    if ((await input.getAttribute('aria-expanded')) === 'true') await input.press('Escape');
}

async function verifyLayoutAndAccessibility(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
    await page.addScriptTag({ content: axe.source });
    const violations = await page.evaluate(async () =>
        (
            await axe.run(document.querySelector('.developer-workspace'), {
                runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] },
            })
        ).violations.map(({ id }) => id),
    );
    expect(violations).toEqual([]);
}

test('API-key search finds names and prefixes beyond the first page and survives reload', async ({
    page,
}) => {
    const panel = keysPanel(page);
    await expect(panel.getByRole('rowheader', { name: fixtureName(1), exact: true })).toHaveCount(
        0,
    );
    await search(page, fixtureName(1));
    await expect(panel.getByRole('rowheader')).toHaveText([fixtureName(1)]);
    await page.reload();
    await expect(panel.getByLabel('Search API keys', { exact: true })).toHaveValue(fixtureName(1));
    await expect(panel.getByRole('rowheader')).toHaveText([fixtureName(1)]);
    await search(page, 'fixture-key-0002');
    await expect(panel.getByRole('rowheader')).toHaveText([fixtureName(2)]);
    await search(page, 'missing-integration');
    await expect(panel.getByRole('rowheader')).toHaveCount(0);
    await expect(panel.getByText('0 of 0 keys', { exact: true })).toBeVisible();
    await panel
        .getByRole('button', { name: 'Remove Search: missing-integration', exact: true })
        .click();
    await expect(panel.getByRole('rowheader')).toHaveCount(10);
    await expect(panel.getByLabel('Search API keys', { exact: true })).toHaveValue('');
    await verifyLayoutAndAccessibility(page);
});

test('API-key Tom Select filters combine status, expiration and inclusive creation dates', async ({
    page,
}) => {
    const panel = keysPanel(page);
    await search(page, 'Fixture integration');
    await panel.getByRole('button', { name: /^Filters/ }).click();
    await chooseFilter(page, 'API key status', 'Active', 'Active');
    await chooseFilter(page, 'API key status', 'Expired', 'Expired');
    await chooseFilter(page, 'API key expiration', 'No expiration', 'never');
    await chooseFilter(page, 'API key expiration', 'Has expiration', 'dated');
    await panel.getByLabel('API key created from', { exact: true }).fill('2024-01-03');
    await panel.getByLabel('API key created to', { exact: true }).fill('2024-01-09');
    await verifyLayoutAndAccessibility(page);
    await panel.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveText([9, 7, 6, 5, 3].map(fixtureName));
    await expect(panel.getByText('Showing 1–5 of 5 keys', { exact: true })).toBeVisible();
    expect(new URL(page.url()).searchParams.getAll('keys_statuses[]').sort()).toEqual([
        'Active',
        'Expired',
    ]);
    expect(new URL(page.url()).searchParams.getAll('keys_expiration[]').sort()).toEqual([
        'dated',
        'never',
    ]);
    await page.reload();
    await expect(panel.getByRole('rowheader')).toHaveText([9, 7, 6, 5, 3].map(fixtureName));
    await panel
        .getByRole('button', { name: 'Remove Expiration: No expiration', exact: true })
        .click();
    await expect(panel.getByRole('rowheader')).toHaveText([7, 6, 3].map(fixtureName));
    await panel.getByRole('button', { name: 'Remove Status: Active', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveText([7, 3].map(fixtureName));
    await panel.getByRole('button', { name: 'Clear filters', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveCount(10);
    expect(new URL(page.url()).searchParams.get('keys_from')).toBeNull();
    expect(new URL(page.url()).searchParams.getAll('keys_statuses[]')).toEqual([]);
});

test('API-key paging and sort preserve the full filtered result count', async ({ page }) => {
    const panel = keysPanel(page);
    await search(page, 'Fixture integration');
    await panel.getByLabel('Sort API keys', { exact: true }).selectOption('oldest');
    await expect(panel.getByRole('rowheader')).toHaveText(
        Array.from({ length: 10 }, (_, index) => fixtureName(index + 1)),
    );
    await expect(panel.getByText('Showing 1–10 of 24 keys', { exact: true })).toBeVisible();
    const pagination = panel.getByRole('navigation', { name: 'API key pages', exact: true });
    await pagination.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveText(
        Array.from({ length: 10 }, (_, index) => fixtureName(index + 11)),
    );
    await expect(panel.getByText('Showing 11–20 of 24 keys', { exact: true })).toBeVisible();
    await panel.getByLabel('API keys per page', { exact: true }).selectOption('20');
    await expect(panel.getByRole('rowheader')).toHaveCount(20);
    await pagination.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveText([21, 22, 23, 24].map(fixtureName));
    await page.reload();
    await expect(panel.getByRole('rowheader')).toHaveText([21, 22, 23, 24].map(fixtureName));
    await expect(panel.getByLabel('API keys per page', { exact: true })).toHaveValue('20');
    await expect(panel.getByLabel('Sort API keys', { exact: true })).toHaveValue('oldest');
    await expect(panel.getByText('Showing 21–24 of 24 keys', { exact: true })).toBeVisible();
    await verifyLayoutAndAccessibility(page);
});

test('API-key filter drafts cancel with Escape and apply with date-field Enter', async ({
    page,
}) => {
    const panel = keysPanel(page);
    await search(page, 'Fixture integration');
    const filters = panel.getByRole('button', { name: /^Filters/ });
    await filters.click();
    await chooseFilter(page, 'API key status', 'Active', 'Active');
    await page.getByRole('combobox', { name: 'API key status', exact: true }).press('Escape');
    await expect(filters).toHaveAttribute('aria-expanded', 'false');
    await expect(filters).toBeFocused();
    expect(new URL(page.url()).searchParams.getAll('keys_statuses[]')).toEqual([]);
    await filters.click();
    const status = page.getByRole('combobox', { name: 'API key status', exact: true });
    await expect(status.locator('..').locator('.item')).toHaveCount(0);
    await status.fill('not-a-status');
    await expect(panel.getByText('No results found', { exact: true })).toBeVisible();
    await status.press('Enter');
    await expect(filters).toHaveAttribute('aria-expanded', 'true');
    await chooseFilter(page, 'API key status', 'Active', 'Active');
    await chooseFilter(page, 'API key expiration', 'No expiration', 'never');
    await panel.getByLabel('API key created from', { exact: true }).fill('2024-01-05');
    const to = panel.getByLabel('API key created to', { exact: true });
    await to.fill('2024-01-06');
    await to.press('Enter');
    await expect(panel.getByRole('rowheader')).toHaveText([fixtureName(5)]);
    await page.reload();
    await expect(panel.getByRole('rowheader')).toHaveText([fixtureName(5)]);
    await panel.getByRole('button', { name: /^Filters/ }).click();
    await expect(panel.getByLabel('API key created from', { exact: true })).toHaveValue(
        '2024-01-05',
    );
    await expect(panel.getByLabel('API key created to', { exact: true })).toHaveValue('2024-01-06');
    await panel.getByRole('button', { name: 'Reset filters', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveCount(10);
});

test('creating and revoking keys refreshes the filtered list without losing filters', async ({
    page,
}, testInfo) => {
    const panel = keysPanel(page);
    const name = `Browser filtered ${testInfo.project.name} key`;
    await search(page, name);
    await panel.getByRole('button', { name: /^Filters/ }).click();
    await chooseFilter(page, 'API key status', 'Active', 'Active');
    await panel.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveCount(0);
    await panel.getByRole('button', { name: 'Create Key', exact: true }).click();
    await page.getByLabel('Key name', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Create key', exact: true }).click();
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await expect(page.getByLabel('API key', { exact: true })).toHaveCount(0);
    await expect(panel.getByRole('rowheader')).toHaveText([name]);
    await expect(
        panel.getByRole('button', { name: 'Remove Status: Active', exact: true }),
    ).toBeVisible();
    await expect(panel.getByLabel('Search API keys', { exact: true })).toHaveValue(name);
    await page.reload();
    await expect(panel.getByRole('rowheader')).toHaveText([name]);
    await expect(page.getByLabel('API key', { exact: true })).toHaveCount(0);
    await panel.getByRole('button', { name: `Revoke ${name}`, exact: true }).click();
    await page.getByRole('button', { name: 'Revoke key', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveCount(0);
    await expect(
        panel.getByRole('button', { name: 'Remove Status: Active', exact: true }),
    ).toBeVisible();
    await expect(panel.getByLabel('Search API keys', { exact: true })).toHaveValue(name);
    await expect(panel.getByRole('button', { name: 'Create Key', exact: true })).toBeFocused();
    await page.reload();
    await expect(panel.getByRole('rowheader')).toHaveCount(0);
    await panel.getByRole('button', { name: 'Remove Status: Active', exact: true }).click();
    await expect(panel.getByRole('rowheader')).toHaveText([name]);
    await expect(
        panel.getByRole('row').filter({ has: page.getByRole('rowheader', { name, exact: true }) }),
    ).toContainText('Revoked');
});

test('API-key asynchronous search and filter actions retain keyboard focus', async ({ page }) => {
    const panel = keysPanel(page);
    const searchButton = panel.getByRole('button', { name: 'Search', exact: true });
    await search(page, 'Fixture integration');
    await expect(searchButton).toBeEnabled();
    const afterSearch = await page.evaluate(() => ({
        tag: document.activeElement.tagName,
        id: document.activeElement.id,
    }));
    await expect
        .soft(searchButton, `Focus after Search: ${JSON.stringify(afterSearch)}`)
        .toBeFocused();
    const filters = panel.getByRole('button', { name: /^Filters/ });
    await filters.click();
    await chooseFilter(page, 'API key status', 'Active', 'Active');
    await verifyLayoutAndAccessibility(page);
    await panel.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await expect(
        panel.getByRole('button', { name: 'Remove Status: Active', exact: true }),
    ).toBeVisible();
    await expect(searchButton).toBeEnabled();
    const afterApply = await page.evaluate(() => ({
        tag: document.activeElement.tagName,
        id: document.activeElement.id,
    }));
    await expect.soft(filters, `Focus after Apply: ${JSON.stringify(afterApply)}`).toBeFocused();
});
