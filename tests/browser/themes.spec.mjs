import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import axe from 'axe-core';

test.use({ timezoneId: 'America/New_York' });

const manifest = JSON.parse(
    readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'),
);
const chartModule = `/build/${manifest['node_modules/chart.js/auto/auto.js'].file}`;

test.beforeEach(async ({ context }) => {
    await context.route('**/*', async (route) => {
        const incoming = route.request();
        const url = new URL(incoming.url());
        if (/remote-control|update-solar-tracker/.test(url.pathname) && incoming.method() !== 'GET')
            return route.abort();
        if (url.hostname.endsWith('.tile.openstreetmap.org'))
            return route.fulfill({
                contentType: 'image/svg+xml',
                body: '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect width="256" height="256" fill="#eee"/></svg>',
            });
        if (url.href === 'https://cdn.jsdelivr.net/npm/chart.js')
            return route.fulfill({ path: 'node_modules/chart.js/dist/chart.umd.js' });
        if (/bootstrap.*\.css$/.test(url.pathname))
            return route.fulfill({ path: 'node_modules/bootstrap/dist/css/bootstrap.min.css' });
        if (/bootstrap.*\.js$/.test(url.pathname))
            return route.fulfill({
                path: 'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
            });
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        return route.continue();
    });
});

async function login(page, email = 'theme@browser.example.test') {
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('h1')).toBeFocused();
}

async function settingsApi(page, data) {
    return page.evaluate(async (data) => {
        const response = await fetch('/api/user-settings', {
            method: data ? 'PATCH' : 'GET',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: data ? JSON.stringify(data) : undefined,
        });
        if (!response.ok) throw new Error(`Settings fixture request failed (${response.status})`);
        return (await response.json()).data;
    }, data);
}

async function openSettings(page) {
    await page.getByRole('button', { name: 'User Settings', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'User Settings', exact: true });
    await expect(dialog.getByLabel('Theme', { exact: true })).toBeEnabled();
    return dialog;
}

async function saveTheme(page, mode) {
    const dialog = await openSettings(page);
    await dialog.getByLabel('Theme', { exact: true }).selectOption(mode);
    await dialog.getByRole('button', { name: 'Save settings', exact: true }).click();
    await expect(dialog).toContainText('Settings saved.');
    await page.keyboard.press('Escape');
    await expect(dialog).not.toBeVisible();
}

async function expectTheme(page, mode, resolved) {
    await expect(page.locator('html')).toHaveAttribute('data-theme-mode', mode);
    await expect(page.locator('html')).toHaveAttribute('data-theme', resolved);
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', resolved);
}

async function expectContrast(locator, minimum = 4.5) {
    await locator.evaluate(async (element) => {
        // Read the final transition colors, including focus and held active states.
        getComputedStyle(element).color;
        await Promise.all(
            element.getAnimations().map((animation) => animation.finished.catch(() => {})),
        );
    });
    const result = await locator.evaluate((element) => {
        function rgba(value) {
            const parts = value.match(/[\d.]+/g).map(Number);
            return [...parts.slice(0, 3), parts[3] ?? 1];
        }
        function background(node) {
            if (!node) return [255, 255, 255];
            const value = rgba(getComputedStyle(node).backgroundColor);
            const parent = background(node.parentElement);
            return value
                .slice(0, 3)
                .map((channel, index) => channel * value[3] + parent[index] * (1 - value[3]));
        }
        function luminance(color) {
            return color
                .map((channel) => {
                    const value = channel / 255;
                    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                })
                .reduce(
                    (total, value, index) => total + value * [0.2126, 0.7152, 0.0722][index],
                    0,
                );
        }
        const bg = background(element);
        const foreground = rgba(getComputedStyle(element).color);
        const fg = foreground
            .slice(0, 3)
            .map((channel, index) => channel * foreground[3] + bg[index] * (1 - foreground[3]));
        const a = luminance(fg);
        const b = luminance(bg);
        return {
            ratio: (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05),
            background: b,
            foreground: fg,
            backdrop: bg,
        };
    });
    expect(result.ratio, JSON.stringify(result)).toBeGreaterThanOrEqual(minimum);
    return result;
}

async function expectDarkContrast(locator, minimum = 4.5) {
    const result = await expectContrast(locator, minimum);
    expect(result.background, 'Dark surfaces must not retain a white backdrop').toBeLessThan(0.2);
}

async function expectButtonStateContrast(page, button) {
    await expect(button).toBeEnabled();
    await button.scrollIntoViewIfNeeded();
    await page.mouse.move(0, 0);
    await button.evaluate((element) => element.blur());
    await test.step('normal text contrast', () => expectContrast(button));

    await button.hover();
    await expect.poll(() => button.evaluate((element) => element.matches(':hover'))).toBe(true);
    await test.step('hover text contrast', () => expectContrast(button));

    await page.mouse.move(0, 0);
    await button.focus();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Shift+Tab');
    await expect(button).toBeFocused();
    await expect
        .poll(() => button.evaluate((element) => element.matches(':focus-visible')))
        .toBe(true);
    await test.step('keyboard focus text contrast', () => expectContrast(button));

    await page.keyboard.down('Space');
    try {
        await expect
            .poll(() => button.evaluate((element) => element.matches(':active')))
            .toBe(true);
        await test.step('held active text contrast', () => expectContrast(button));
    } finally {
        // Blur before release to inspect native :active without activating the control.
        await button.evaluate((element) => element.blur());
        await page.keyboard.up('Space');
    }
}

async function expectSoftwareIcons(page, dark) {
    const icons = page.locator('.developer-software-icon');
    await expect(icons).toHaveCount(2);
    for (const icon of await icons.all()) {
        await expect
            .poll(() => icon.evaluate((element) => element.complete && element.naturalWidth > 0))
            .toBe(true);
        if (!dark) {
            await expect(icon).toHaveCSS('filter', 'none');
            continue;
        }
        const pixels = await icon.evaluate((element) => {
            const canvas = document.createElement('canvas');
            canvas.width = element.naturalWidth;
            canvas.height = element.naturalHeight;
            const context = canvas.getContext('2d');
            context.filter = getComputedStyle(element).filter;
            context.drawImage(element, 0, 0);
            const data = context.getImageData(0, 0, canvas.width, canvas.height).data;
            let opaque = 0;
            let nonWhite = 0;
            for (let index = 0; index < data.length; index += 4) {
                if (data[index + 3] < 250) continue;
                opaque++;
                if (Math.min(data[index], data[index + 1], data[index + 2]) < 250) nonWhite++;
            }
            return { opaque, nonWhite };
        });
        expect(pixels.opaque).toBeGreaterThan(0);
        expect(pixels.nonWhite).toBe(0);
    }
}

async function chartSnapshot(page, legacy = false) {
    return page.evaluate(
        async ({ moduleUrl, legacy }) => {
            const Chart = legacy ? window.Chart : (await import(moduleUrl)).default;
            const canvas = document.querySelector(
                legacy ? '[data-chart="temperature"]' : '.history-section canvas',
            );
            const chart = canvas && Chart?.getChart(canvas);
            if (!chart) return null;
            return {
                id: chart.id,
                points: chart.data.datasets.map((dataset) => dataset.data),
                min: chart.options.scales.x.min,
                max: chart.options.scales.x.max,
                tickColor: chart.options.scales.x.ticks.color,
                legendColor: chart.options.plugins.legend.labels.color,
                gridColor: chart.options.scales.y.grid.color,
                seriesColor: chart.data.datasets[0].borderColor,
                muted: getComputedStyle(document.documentElement)
                    .getPropertyValue('--obsidian-text-muted')
                    .trim(),
                border: getComputedStyle(document.documentElement)
                    .getPropertyValue('--obsidian-border')
                    .trim(),
            };
        },
        { moduleUrl: chartModule, legacy },
    );
}

async function expectCheckedSwitchContrast(page) {
    const result = await page
        .getByRole('switch', { name: 'Remote control mode', exact: true })
        .evaluate((element) => {
            const original = element.checked;
            // Inspect the checked artwork without dispatching a hardware-control event.
            element.checked = true;
            try {
                const styles = getComputedStyle(element);
                const image = decodeURIComponent(styles.backgroundImage);
                const fill = /fill=['"]([^'"]+)/.exec(image)?.[1];
                const color = document.createElement('span');
                color.style.color = fill || 'transparent';
                element.parentElement.append(color);
                const foreground = getComputedStyle(color).color;
                color.remove();
                const luminance = (value) =>
                    value
                        .match(/[\d.]+/g)
                        .slice(0, 3)
                        .map(Number)
                        .reduce((total, channel, index) => {
                            const normalized = channel / 255;
                            const linear =
                                normalized <= 0.04045
                                    ? normalized / 12.92
                                    : ((normalized + 0.055) / 1.055) ** 2.4;
                            return total + linear * [0.2126, 0.7152, 0.0722][index];
                        }, 0);
                const a = luminance(foreground);
                const b = luminance(styles.backgroundColor);
                return { fill, image, ratio: (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) };
            } finally {
                element.checked = original;
            }
        });
    expect(result.fill, result.image).toBeTruthy();
    expect(result.ratio, result.image).toBeGreaterThanOrEqual(3);
}

test('theme saves persist while cancelled and failed drafts preserve the current appearance and email preference', async ({
    page,
}) => {
    await page.clock.install({ time: new Date('2026-09-26T12:00:00-04:00') });
    await login(page);
    try {
        await settingsApi(page, { theme_mode: 'adaptive', receive_app_activity_emails: false });
        await page.reload();
        await expect(page.locator('h1')).toBeFocused();
        for (const mode of ['dark', 'light', 'adaptive']) {
            await saveTheme(page, mode);
            await expectTheme(page, mode, mode === 'adaptive' ? 'light' : mode);
            await page.reload();
            await expect(page.locator('h1')).toBeFocused();
            await expectTheme(page, mode, mode === 'adaptive' ? 'light' : mode);
            const dialog = await openSettings(page);
            await expect(dialog.getByLabel('Theme', { exact: true })).toHaveValue(mode);
            await expect(
                dialog.getByLabel('Receive app activity emails', { exact: true }),
            ).not.toBeChecked();
            await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        }
        let dialog = await openSettings(page);
        await dialog.getByLabel('Theme', { exact: true }).selectOption('dark');
        await expectTheme(page, 'adaptive', 'light');
        await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
        dialog = await openSettings(page);
        await expect(dialog.getByLabel('Theme', { exact: true })).toHaveValue('adaptive');
        await dialog.getByLabel('Theme', { exact: true }).selectOption('dark');
        let failNext = true;
        await page.route('**/api/user-settings', async (route) => {
            if (route.request().method() === 'PATCH' && failNext) {
                failNext = false;
                return route.fulfill({
                    status: 503,
                    contentType: 'application/json',
                    body: '{"message":"Synthetic save failure"}',
                });
            }
            return route.continue();
        });
        await dialog.getByRole('button', { name: 'Save settings', exact: true }).click();
        await expect(dialog.getByRole('alert')).toBeVisible();
        await expect(dialog.getByLabel('Theme', { exact: true })).toHaveValue('dark');
        await expectTheme(page, 'adaptive', 'light');
        expect((await settingsApi(page)).theme_mode).toBe('adaptive');
        await dialog.getByRole('button', { name: 'Save settings', exact: true }).click();
        await expect(dialog).toContainText('Settings saved.');
        await expectTheme(page, 'dark', 'dark');
        expect((await settingsApi(page)).receive_app_activity_emails).toBe(false);
    } finally {
        await settingsApi(page, { theme_mode: 'adaptive', receive_app_activity_emails: true });
    }
});

test('adaptive appearance follows six oclock boundaries in the local browser timezone', async ({
    page,
}) => {
    await page.clock.install({ time: new Date('2026-09-27T05:59:00-04:00') });
    await login(page);
    await settingsApi(page, { theme_mode: 'adaptive' });
    await page.reload();
    await expect(page.locator('h1')).toBeFocused();
    await expectTheme(page, 'adaptive', 'dark');
    await page.clock.runFor(60000);
    await expectTheme(page, 'adaptive', 'light');
    await page.clock.setSystemTime(new Date('2026-09-27T17:59:00-04:00'));
    await page.evaluate(() => window.dispatchEvent(new Event('pageshow')));
    await expectTheme(page, 'adaptive', 'light');
    await page.clock.runFor(60000);
    await expectTheme(page, 'adaptive', 'dark');
    await saveTheme(page, 'light');
    await expectTheme(page, 'light', 'light');
    await page.clock.runFor(60000);
    await expectTheme(page, 'light', 'light');
    await settingsApi(page, { theme_mode: 'adaptive' });
});

test('dark modern navigation, dialogs, forms, rows and history charts retain contrast and redraw without new telemetry requests', async ({
    page,
}) => {
    await login(page);
    try {
        await settingsApi(page, { theme_mode: 'dark' });
        await page.reload();
        await expect(page.locator('h1')).toBeFocused();
        await expectTheme(page, 'dark', 'dark');
        await expectDarkContrast(page.getByRole('navigation', { name: 'Main navigation' }));
        await expectDarkContrast(page.getByLabel('Search by device name', { exact: true }));
        const rows = page.locator('.device-table-row');
        await expect(rows).toHaveCount(3);
        for (let index = 0; index < 3; index++)
            await expectDarkContrast(rows.nth(index).locator('.device-name'));
        const bell = page.getByRole('button', { name: 'App activity', exact: true });
        await bell.focus();
        await page.keyboard.press('Enter');
        const emptyFeed = page.getByText('You’re all caught up.', { exact: true });
        await expect(emptyFeed).toBeVisible();
        await expectDarkContrast(emptyFeed);
        await page.keyboard.press('Escape');
        await expect(bell).toBeFocused();
        const dialog = await openSettings(page);
        await expectDarkContrast(dialog.getByLabel('Theme', { exact: true }));
        await expectDarkContrast(dialog.locator('#theme-mode-help'));
        await page.keyboard.press('Escape');
        await page.goto('/profile');
        await expect(page.locator('h1')).toBeFocused();
        await expectDarkContrast(page.getByLabel('Name', { exact: true }));
        await page.goto('/dashboard');
        await expect(page.locator('h1')).toBeFocused();
        await page.getByRole('link', { name: 'Theme simulator 1', exact: true }).click();
        await expect(page.locator('h1')).toBeFocused();
        await page.getByRole('tab', { name: 'Control', exact: true }).click();
        await expectCheckedSwitchContrast(page);
        await page.getByRole('tab', { name: 'History', exact: true }).click();
        await page.getByLabel('View', { exact: true }).selectOption('all');
        await expect.poll(() => chartSnapshot(page)).not.toBeNull();
        await expectDarkContrast(page.locator('.history-range-display'));
        await expectDarkContrast(page.getByLabel('Measurement', { exact: true }));
        const dark = await chartSnapshot(page);
        expect(dark.tickColor).toBe(dark.muted);
        expect(dark.legendColor).toBe(dark.muted);
        expect(dark.gridColor).toBe(dark.border);
        expect(dark.seriesColor).toBe('#79adff');
        const telemetryRequests = [];
        page.on('request', (request) => {
            if (/\/telemetry(?:\?|$)/.test(request.url())) telemetryRequests.push(request.url());
        });
        await saveTheme(page, 'light');
        await expect.poll(async () => (await chartSnapshot(page))?.id).not.toBe(dark.id);
        const light = await chartSnapshot(page);
        expect(light.points).toEqual(dark.points);
        expect(light.min).toBe(dark.min);
        expect(light.max).toBe(dark.max);
        expect(light.seriesColor).toBe('#275bb5');
        expect(telemetryRequests).toEqual([]);
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
    } finally {
        await settingsApi(page, { theme_mode: 'adaptive', receive_app_activity_emails: true });
    }
});

test('legacy device surfaces and existing canvas charts follow saved theme changes', async ({
    page,
}) => {
    await login(page);
    try {
        const link = await page
            .getByRole('link', { name: 'Theme simulator 1', exact: true })
            .getAttribute('href');
        const id = new URL(link, page.url()).pathname.split('/').at(-1);
        await settingsApi(page, { theme_mode: 'dark' });
        await page.goto(`/__browser-fixtures/devices/${id}/legacy`);
        await expectTheme(page, 'dark', 'dark');
        await expectDarkContrast(page.locator('#legacy-device-view .card-body').first());
        await expectDarkContrast(
            page.locator('#legacy-device-view .motor-speed-tick-label').first(),
        );
        await expectCheckedSwitchContrast(page);
        await expect.poll(() => chartSnapshot(page, true)).not.toBeNull();
        const dark = await chartSnapshot(page, true);
        expect(dark.tickColor).toBe(dark.muted);
        expect(dark.gridColor).toBe(dark.border);
        await saveTheme(page, 'light');
        await expectTheme(page, 'light', 'light');
        await expect.poll(async () => (await chartSnapshot(page, true))?.id).not.toBe(dark.id);
        expect((await chartSnapshot(page, true)).points).toEqual(dark.points);
        await page.reload();
        await expectTheme(page, 'light', 'light');
        const dialog = await openSettings(page);
        await expect(dialog.getByLabel('Theme', { exact: true })).toHaveValue('light');
        await page.keyboard.press('Escape');
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
    } finally {
        await settingsApi(page, { theme_mode: 'adaptive', receive_app_activity_emails: true });
    }
});

async function expectReleaseActionSurfaces(page, legacy, dark, title) {
    await page.goto(legacy ? '/__browser-fixtures/developer/legacy' : '/developer-workspace');
    if (!legacy) await page.getByRole('tab', { name: title, exact: true }).click();
    const releaseName = title.toLowerCase();
    const actions = page.getByRole('button', {
        name: `Actions for ${releaseName} 12`,
        exact: true,
    });
    await expectButtonStateContrast(page, actions);
    await actions.focus();
    await page.keyboard.press('ArrowDown');
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    const contrast = dark ? expectDarkContrast : expectContrast;
    await contrast(menu.getByRole('menuitem', { name: 'Edit', exact: true }));
    await contrast(menu.getByRole('menuitem', { name: 'Delete', exact: true }));
    await page.keyboard.press('Enter');
    const edit = page.getByRole('dialog', { name: `Edit ${releaseName}`, exact: true });
    await expect(edit).toBeVisible();
    await contrast(edit);
    await contrast(edit.getByRole('spinbutton', { name: `${title} version`, exact: true }));
    await contrast(edit.getByLabel(`${title} description`, { exact: true }));
    await expectButtonStateContrast(
        page,
        edit.getByRole('button', { name: 'Save changes', exact: true }),
    );
    await page.keyboard.press('Escape');
    await expect(actions).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    const confirmation = page.getByRole('dialog', { name: `Delete ${releaseName}`, exact: true });
    await expect(confirmation).toBeVisible();
    await contrast(confirmation);
    await expectButtonStateContrast(
        page,
        confirmation.getByRole('button', { name: `Delete ${releaseName}`, exact: true }),
    );
    await confirmation.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(actions).toBeFocused();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
}

async function expectKeyActionMenuContrast(page, content) {
    const actions = content.locator('button[aria-label^="Actions for API key "]:enabled').first();
    await expectButtonStateContrast(page, actions);
    const name = await actions.getAttribute('aria-label');
    await actions.focus();
    await page.keyboard.press('ArrowDown');
    const menu = page.getByRole('menu', { name, exact: true });
    const revoke = menu.getByRole('menuitem');
    await expect(revoke).toHaveCount(1);
    await expect(revoke).toHaveText('Revoke');
    await expect(revoke).toBeFocused();
    await actions.focus();
    await expect(menu).toBeVisible();
    await expectContrast(revoke);
    await revoke.hover();
    await expect.poll(() => revoke.evaluate((element) => element.matches(':hover'))).toBe(true);
    await expectContrast(revoke);
    await page.mouse.move(0, 0);
    await revoke.focus();
    await page.keyboard.press('ArrowDown');
    await expect
        .poll(() => revoke.evaluate((element) => element.matches(':focus-visible')))
        .toBe(true);
    await expectContrast(revoke);
    await page.keyboard.down('Space');
    try {
        await expect
            .poll(() => revoke.evaluate((element) => element.matches(':active')))
            .toBe(true);
        await expectContrast(revoke);
    } finally {
        await revoke.evaluate((element) => element.blur());
        await page.keyboard.up('Space');
    }
    await expect(menu).toBeHidden();
}

for (const mode of ['light', 'dark', 'adaptive']) {
    for (const legacy of [false, true]) {
        test(`${legacy ? 'legacy' : 'modern'} developer actions retain readable interaction states in ${mode} mode at night`, async ({
            page,
        }) => {
            await page.clock.setFixedTime(new Date('2026-09-27T23:00:00-04:00'));
            await login(page, 'developer@browser.example.test');
            const original = await settingsApi(page);
            const keyMutations = [];
            const releaseMutations = [];
            await page.route(/\/developer-workspace\/(firmware|config)\//, async (route) => {
                if (!['GET', 'HEAD'].includes(route.request().method())) {
                    releaseMutations.push(route.request().method());
                    return route.abort();
                }
                return route.continue();
            });
            await page.route('**/developer-workspace/api-keys**', async (route) => {
                if (!['GET', 'HEAD'].includes(route.request().method())) {
                    keyMutations.push(
                        route.request().method() + ' ' + new URL(route.request().url()).pathname,
                    );
                    return route.abort();
                }
                return route.continue();
            });
            try {
                await settingsApi(page, { theme_mode: mode });
                await page.goto(
                    legacy
                        ? '/developer-workspace/api-keys'
                        : '/developer-workspace?section=api-keys',
                );
                const dark = mode !== 'light';
                await expectTheme(page, mode, dark ? 'dark' : 'light');
                const content = legacy
                    ? page.locator('main')
                    : page.locator('.developer-workspace');
                await expect(content.getByRole('rowheader').first()).toBeVisible();
                if (!legacy) await expectSoftwareIcons(page, dark);

                await expectKeyActionMenuContrast(page, content);
                await expectButtonStateContrast(
                    page,
                    content.getByRole('button', {
                        name: legacy ? 'Create Key' : 'Search',
                        exact: true,
                    }),
                );
                expect(keyMutations).toEqual([]);

                await page.addScriptTag({ content: axe.source });
                const violations = await page.evaluate(
                    async (selector) =>
                        (
                            await axe.run(document.querySelector(selector), {
                                runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] },
                            })
                        ).violations.map(({ id, nodes }) => ({
                            id,
                            nodes: nodes.map(({ target, failureSummary }) => ({
                                target,
                                failureSummary,
                            })),
                        })),
                    legacy ? 'main' : '.developer-workspace',
                );
                expect(violations).toEqual([]);
                for (const title of ['Firmware', 'Configuration']) {
                    await expectReleaseActionSurfaces(page, legacy, dark, title);
                }
                expect(releaseMutations).toEqual([]);
                expect((await settingsApi(page)).receive_app_activity_emails).toBe(
                    original.receive_app_activity_emails,
                );
            } finally {
                await settingsApi(page, { theme_mode: original.theme_mode });
            }
        });
    }
}
