import { test, expect } from '@playwright/test';

test.beforeEach(async ({ context }) => {
    await context.route('**/*', async (route) => {
        const url = new URL(route.request().url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        return route.continue();
    });
});

function channel(value) {
    const normalized = value / 255;
    return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
}

function luminance(color) {
    const [red, green, blue] = color
        .match(/[\d.]+/g)
        .slice(0, 3)
        .map(Number);
    return channel(red) * 0.2126 + channel(green) * 0.7152 + channel(blue) * 0.0722;
}

for (const [name, time] of [
    ['light', '2026-09-28T12:00:00-07:00'],
    ['dark', '2026-09-28T20:00:00-07:00'],
]) {
    test(`legacy mobile Login link has readable ${name} contrast`, async ({ page }) => {
        await page.clock.install({ time: new Date(time) });
        await page.goto('/__browser-fixtures/legacy-login');

        const colors = await page
            .getByRole('link', { name: 'Login', exact: true })
            .evaluate((link) => ({
                foreground: getComputedStyle(link).color,
                background: getComputedStyle(link).backgroundColor,
            }));
        const foreground = luminance(colors.foreground);
        const background = luminance(colors.background);
        const ratio =
            (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);

        expect(ratio).toBeGreaterThanOrEqual(4.5);
    });
}
