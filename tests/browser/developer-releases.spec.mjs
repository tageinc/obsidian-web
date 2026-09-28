import { test, expect } from '@playwright/test';
import axe from 'axe-core';

const sections = [
    { key: 'firmware', title: 'Firmware' },
    { key: 'config', title: 'Configuration' },
];

test.beforeEach(async ({ page, context }) => {
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (request.method() !== 'GET' && /remote-control/.test(url.pathname)) {
            return route.abort();
        }
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
});

function panel(page, title) {
    return page.getByRole('tabpanel', { name: title, exact: true });
}

async function chooseFilter(page, label, value) {
    const input = page.getByRole('combobox', { name: label, exact: true });
    await input.fill(value);
    const listboxId = await input.getAttribute('aria-controls');
    const option = page
        .locator(`[id="${listboxId}"]`)
        .getByRole('option', { name: value, exact: true });
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

for (const { timezoneId, date, clock } of [
    { timezoneId: 'America/Los_Angeles', date: 'Sep 12, 2026', clock: '5:00:00 AM PDT' },
    { timezoneId: 'Pacific/Kiritimati', date: 'Sep 13, 2026', clock: '2:00:00 AM GMT+14' },
]) {
    test.describe(`upload timestamps in ${timezoneId}`, () => {
        test.use({ timezoneId, locale: 'en-US' });

        for (const renderer of ['modern', 'legacy']) {
            test(`${renderer} firmware and configuration uploads use browser local time after reload`, async ({
                page,
            }) => {
                if (renderer === 'legacy') await page.goto('/__browser-fixtures/developer/legacy');
                for (let visit = 0; visit < 2; visit++) {
                    for (const { key, title } of sections) {
                        if (renderer === 'modern') {
                            await page.getByRole('tab', { name: title, exact: true }).click();
                        }
                        const current =
                            renderer === 'modern'
                                ? panel(page, title)
                                : page.locator(`.${key}-updates`);
                        const row = current.locator('tbody tr').first();
                        await expect(row.locator('th, td').first()).toHaveText('v12');
                        const uploaded = row.locator('time');
                        await expect(uploaded).toContainText(date);
                        await expect(uploaded).toContainText(clock);
                        expect(Date.parse(await uploaded.getAttribute('datetime'))).toBe(
                            Date.parse('2026-09-12T12:00:00Z'),
                        );
                    }
                    if (visit === 0) await page.reload();
                }
            });
        }
    });
}

test('release searches include later pages and preserve each software section', async ({
    page,
}) => {
    const firmware = panel(page, 'Firmware');
    await expect(firmware.getByRole('table')).not.toContainText('sunrise calibration');
    await firmware.getByLabel('Search firmware releases', { exact: true }).fill('sunrise');
    await firmware.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page).toHaveURL(/firmware_search=sunrise/);
    await expect(firmware.getByRole('table').locator('tbody tr')).toHaveCount(1);
    await expect(firmware.getByText('Showing 1–1 of 1 releases', { exact: true })).toBeVisible();
    await expect(firmware.getByRole('table')).toContainText(
        'Firmware sunrise calibration fixture.',
    );
    await page.reload();
    await expect(firmware.getByLabel('Search firmware releases', { exact: true })).toHaveValue(
        'sunrise',
    );
    await expect(firmware.getByRole('table')).toContainText(
        'Firmware sunrise calibration fixture.',
    );

    await page.getByRole('tab', { name: 'Configuration', exact: true }).click();
    const config = panel(page, 'Configuration');
    await config.getByLabel('Search configuration releases', { exact: true }).fill('dusk');
    await config.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(page).toHaveURL(/config_search=dusk/);
    expect(new URL(page.url()).searchParams.get('firmware_search')).toBe('sunrise');
    await expect(config.getByRole('table').locator('tbody tr')).toHaveCount(1);
    await expect(config.getByRole('table')).toContainText('Configuration dusk schedule fixture.');
    await config
        .getByLabel('Search configuration releases', { exact: true })
        .fill('no-such-release');
    await config.getByRole('button', { name: 'Search', exact: true }).click();
    await expect(config.getByRole('table')).not.toContainText('schedule fixture');
    await expect(config.getByRole('rowheader')).toHaveCount(0);
    await expect(config.getByText('0 of 0 releases', { exact: true })).toBeVisible();
    await config.getByRole('button', { name: 'Clear filters', exact: true }).click();
    await expect(config.getByLabel('Search configuration releases', { exact: true })).toHaveValue(
        '',
    );
    expect(new URL(page.url()).searchParams.get('firmware_search')).toBe('sunrise');
    await page.getByRole('tab', { name: 'Firmware', exact: true }).click();
    await expect(firmware.getByRole('table')).toContainText(
        'Firmware sunrise calibration fixture.',
    );
    await verifyLayoutAndAccessibility(page);
});

for (const { key, title } of sections) {
    test(`${title} searchable multi-filters combine prefixes, versions and upload dates`, async ({
        page,
    }) => {
        await page.getByRole('tab', { name: title, exact: true }).click();
        const current = panel(page, title);
        const filters = current.getByRole('button', { name: 'Filters', exact: true });
        await filters.focus();
        await page.keyboard.press('Enter');
        await chooseFilter(page, `${title} prefixes`, 'BROWSER-SP1');
        await chooseFilter(page, `${title} prefixes`, 'BROWSER-SP2');
        await chooseFilter(page, `${title} versions`, '3');
        await chooseFilter(page, `${title} versions`, '4');
        await current.getByLabel(`${title} uploaded from`, { exact: true }).fill('2026-09-03');
        await current.getByLabel(`${title} uploaded to`, { exact: true }).fill('2026-09-04');
        await verifyLayoutAndAccessibility(page);
        await current.getByRole('button', { name: 'Apply filters', exact: true }).click();
        await expect(current.getByRole('table').locator('tbody tr')).toHaveCount(2);
        await expect(current.getByRole('rowheader')).toHaveText(['v4', 'v3']);
        await expect(current.getByText('Showing 1–2 of 2 releases', { exact: true })).toBeVisible();
        const query = new URL(page.url()).searchParams;
        expect(query.getAll(`${key}_prefixes[]`).sort()).toEqual(['BROWSER-SP1', 'BROWSER-SP2']);
        expect(query.getAll(`${key}_versions[]`).sort()).toEqual(['3', '4']);
        expect(query.get(`${key}_from`)).toBe('2026-09-03');
        expect(query.get(`${key}_to`)).toBe('2026-09-04');
        await page.reload();
        await expect(current.getByRole('rowheader')).toHaveText(['v4', 'v3']);
        await current.getByRole('button', { name: /^Filters/ }).click();
        await expect(current.getByLabel(`${title} uploaded from`, { exact: true })).toHaveValue(
            '2026-09-03',
        );
        await expect(current.getByLabel(`${title} uploaded to`, { exact: true })).toHaveValue(
            '2026-09-04',
        );
        await current.getByRole('button', { name: /^Filters/ }).click();
        await current.getByRole('button', { name: 'Clear filters', exact: true }).click();
        await expect(current.getByRole('rowheader', { name: 'v12', exact: true })).toBeVisible();
        expect(new URL(page.url()).searchParams.get(`${key}_from`)).toBeNull();
        expect(new URL(page.url()).searchParams.getAll(`${key}_prefixes[]`)).toEqual([]);
        await verifyLayoutAndAccessibility(page);
    });
}

for (const { key, title } of sections) {
    test(`${title} sorting and pagination retain a stable non-expandable table`, async ({
        page,
    }) => {
        await page.getByRole('tab', { name: title, exact: true }).click();
        const releaseName = key === 'firmware' ? 'firmware' : 'configuration';
        const current = panel(page, title);
        await current
            .getByLabel(`Sort ${releaseName} releases`, { exact: true })
            .selectOption({ label: 'Oldest first' });
        await expect(current.getByRole('rowheader').first()).toHaveText('v1');
        await current.getByLabel(`${title} updates per page`, { exact: true }).selectOption('2');
        await expect(current.getByRole('rowheader')).toHaveText(['v1', 'v2']);
        await expect(
            current.getByText('Showing 1–2 of 12 releases', { exact: true }),
        ).toBeVisible();
        const pages = current.getByRole('navigation', { name: `${title} pages`, exact: true });
        await pages.getByRole('link', { name: '2', exact: true }).click();
        await expect(current.getByRole('rowheader')).toHaveText(['v3', 'v4']);
        await page.reload();
        await expect(current.getByRole('rowheader')).toHaveText(['v3', 'v4']);
        await expect(
            current.getByLabel(`Sort ${releaseName} releases`, { exact: true }),
        ).toHaveValue(/.+/);
        const table = current.getByRole('table');
        await expect(table.locator('tbody tr')).toHaveCount(2);
        await expect(table.locator('tr[aria-expanded], details')).toHaveCount(0);
        await expect(
            table.getByRole('button', { name: new RegExp(`^Actions for ${releaseName} `) }),
        ).toHaveCount(2);
        await expect(table.locator('button:not([aria-haspopup="menu"])')).toHaveCount(0);
        await expect(table.locator('tbody tr > :last-child button')).toHaveCount(2);
        await verifyLayoutAndAccessibility(page);
    });
}

test('filter drafts cancel with Escape and apply with Enter from a date field', async ({
    page,
}) => {
    const firmware = panel(page, 'Firmware');
    const filters = firmware.getByRole('button', { name: /^Filters/ });
    await expect(filters).toBeVisible();
    const initialUrl = page.url();
    await filters.click();
    const prefix = page.getByRole('combobox', { name: 'Firmware prefixes', exact: true });
    await prefix.fill('no-such-prefix');
    await expect(firmware.getByText('No results found', { exact: true })).toBeVisible();
    await prefix.press('Enter');
    await expect(filters).toHaveAttribute('aria-expanded', 'true');
    expect(page.url()).toBe(initialUrl);
    await chooseFilter(page, 'Firmware prefixes', 'BROWSER-SP1');
    await verifyLayoutAndAccessibility(page);
    await page.getByRole('combobox', { name: 'Firmware prefixes', exact: true }).press('Escape');
    await expect(filters).toHaveAttribute('aria-expanded', 'false');
    await expect(filters).toBeFocused();
    expect(page.url()).toBe(initialUrl);
    await filters.click();
    await expect(prefix.locator('..').locator('.item')).toHaveCount(0);
    await chooseFilter(page, 'Firmware prefixes', 'BROWSER-SP1');
    await firmware.getByLabel('Firmware uploaded from', { exact: true }).fill('2026-09-03');
    const to = firmware.getByLabel('Firmware uploaded to', { exact: true });
    await to.fill('2026-09-07');
    await to.press('Enter');
    await expect(firmware.getByRole('rowheader')).toHaveText(['v7', 'v5', 'v3']);
    await expect(firmware.getByText('Showing 1–3 of 3 releases', { exact: true })).toBeVisible();
    expect(new URL(page.url()).searchParams.getAll('firmware_prefixes[]')).toEqual(['BROWSER-SP1']);
});
