import { test, expect } from '@playwright/test';

test('legacy device sensors show Fahrenheit and CTS states without device commands', async ({
    page,
    context,
    request,
}) => {
    const readings = [];
    const commands = [];
    await context.route('**/*', (route) => {
        const incoming = route.request();
        const url = new URL(incoming.url());
        if (url.href === 'https://cdn.jsdelivr.net/npm/chart.js') {
            return route.fulfill({ path: 'node_modules/chart.js/dist/chart.umd.js' });
        }
        if (/bootstrap.*\.css$/.test(url.pathname)) {
            return route.fulfill({ path: 'node_modules/bootstrap/dist/css/bootstrap.min.css' });
        }
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (
            /remote-control|update-solar-tracker/.test(url.pathname) &&
            incoming.method() !== 'GET'
        ) {
            commands.push(url.pathname);
            return route.abort();
        }
        return route.continue();
    });
    try {
        await page.goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill('owner@browser.example.test');
        await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Login', exact: true }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.goto('/__browser-fixtures/devices/1/legacy');
        const sensors = page.locator('#legacy-device-view');
        await expect(sensors.locator('.motor-speed-tick')).toHaveCount(21);
        await expect(sensors.locator('.motor-speed-tick-label')).toHaveText([
            '-100',
            '-80',
            '-60',
            '-40',
            '-20',
            '0',
            '20',
            '40',
            '60',
            '80',
            '100',
        ]);
        await expect(sensors.locator('#solar-tracker-remote')).not.toContainText(/\b(?:Up|Down)\b/);
        await expect(sensors.getByText('Temperature: 68 °F', { exact: true })).toBeVisible();
        await expect(sensors.locator('.cts-badge')).toHaveText('Closed');
        await expect(sensors.locator('.cts-badge')).toHaveCSS(
            'background-color',
            'rgb(233, 236, 239)',
        );
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const chart = window.Chart?.getChart(
                        document.querySelector('[data-chart="temperature"]'),
                    );
                    return chart?.data.datasets[0].label;
                }),
            )
            .toBe('Temperature (°F)');
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const chart = window.Chart?.getChart(
                        document.querySelector('[data-chart="motor"]'),
                    );
                    if (!chart) return false;
                    const scale = chart.scales.y;
                    const zero = scale.ticks.find((tick) => tick.value === 0);
                    const grid =
                        zero &&
                        scale.options.grid.setContext(scale.getContext(scale.ticks.indexOf(zero)));
                    return (
                        scale.min <= 0 &&
                        scale.max >= 0 &&
                        String(zero?.label) === '0' &&
                        grid.color === window.Chart.defaults.borderColor &&
                        grid.lineWidth === 1
                    );
                }),
            )
            .toBe(true);

        const response = await request.post('/__browser-fixtures/telemetry', {
            data: { temp: 0, ps_avg: 123, pds: 9, cts: 0 },
        });
        expect(response.status()).toBe(201);
        readings.push((await response.json()).id);
        await page.reload();
        await expect(sensors.getByText('Temperature: 32 °F', { exact: true })).toBeVisible();
        await expect(sensors.locator('.cts-badge')).toHaveText('Open');
        await expect(sensors.locator('.cts-badge')).toHaveCSS(
            'background-color',
            'rgb(25, 135, 84)',
        );
        await expect
            .poll(() =>
                page.evaluate(() => {
                    const chart = window.Chart?.getChart(
                        document.querySelector('[data-chart="temperature"]'),
                    );
                    return chart?.data.datasets[0].data.at(-1)?.y;
                }),
            )
            .toBe(32);
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
        expect(commands).toEqual([]);
    } finally {
        for (const id of readings) {
            expect((await request.delete(`/__browser-fixtures/telemetry/${id}`)).status()).toBe(
                204,
            );
        }
    }
});
