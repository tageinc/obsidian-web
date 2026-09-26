import { test, expect } from '@playwright/test';
import { datetimeInput } from '../../resources/js/features/solar-tracker/historyRange.js';

test.use({ timezoneId: 'Asia/Tokyo' });

test('viewed device refreshes readings every minute and preserves a chosen history range', async ({
    page,
    context,
    request,
}) => {
    const createdReadings = [];
    const commands = [];
    const refreshRequests = [];
    const telemetryPath = '/devices/1/telemetry';
    const telemetryRoute = (url) => url.pathname === telemetryPath;
    await context.route('**/*', (route) => {
        const incoming = route.request();
        const url = new URL(incoming.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (/remote-control/.test(url.pathname) && incoming.method() !== 'GET') {
            commands.push(url.pathname);
            return route.abort();
        }
        return route.continue();
    });
    page.on('request', (incoming) => {
        if (new URL(incoming.url()).pathname === telemetryPath) refreshRequests.push(incoming);
    });
    const now = Math.floor(Date.now() / 1000) * 1000 + 5000;
    await page.clock.install({ time: now - 1000 });
    await page.clock.pauseAt(now);

    async function addReading(temp, psAverage, pds, extra = {}) {
        const response = await request.post('/__browser-fixtures/telemetry', {
            data: { temp, ps_avg: psAverage, pds, ...extra },
        });
        expect(response.status()).toBe(201);
        const reading = await response.json();
        createdReadings.push(reading.id);
        return reading;
    }

    async function refreshAfter(milliseconds, expectedStatus = 200) {
        const [response] = await Promise.all([
            page.waitForResponse((response) => new URL(response.url()).pathname === telemetryPath),
            page.clock.fastForward(milliseconds),
        ]);
        expect(response.status()).toBe(expectedStatus);
        expect(response.request().method()).toBe('GET');
        return response;
    }

    try {
        await page.goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill('owner@browser.example.test');
        await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Login', exact: true }).click();
        await page.getByRole('link', { name: 'Browser simulator', exact: true }).click();
        const overview = page.getByRole('tabpanel', { name: 'Overview', exact: true });
        const metric = (label) =>
            overview
                .locator('dl > div')
                .filter({ has: page.locator('dt', { hasText: label }) })
                .locator('dd');
        const status = page.locator('.device-refresh-status');
        const versions = overview.locator('.device-metrics .device-software-versions');
        await expect(metric(/^Temperature$/)).toHaveText('68 °F');
        await expect(metric(/^CTS$/)).toHaveText('Closed');
        await expect(metric(/^CTS$/).locator('.cts-badge')).toHaveCSS(
            'background-color',
            'rgb(233, 236, 239)',
        );
        await expect(versions.locator('dd')).toHaveText(['001.020', '0']);
        await expect(versions).toBeVisible();
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);
        await expect(status).toContainText('automatically every minute');
        await page.getByRole('tab', { name: 'History', exact: true }).click();
        const history = page.getByRole('region', { name: 'Reading history', exact: true });
        await expect(history.locator('.plot-heading')).toContainText('°F');
        const ending = history.getByLabel('Ending at', { exact: true });
        const dateValue = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
        await expect(ending).toHaveValue(dateValue(now));
        await expect(history.getByText('4 raw readings', { exact: true })).toBeVisible();

        const first = await addReading(37.75, 123, 9.5, {
            firmware_version: '001.021',
            config_version: '003.004',
            cts: 0,
        });
        await page.clock.fastForward(59999);
        expect(refreshRequests).toHaveLength(0);
        const firstResponse = await refreshAfter(1);
        const snapshot = await firstResponse.json();
        expect(snapshot.latest_reading.id).toBe(first.id);
        expect(snapshot.status['Temperature (°C)']).toBe(37.75);
        expect(snapshot.status['Temperature (°F)']).toBe(99.95);
        expect(snapshot.status['CTS state']).toBe('Open');
        await expect(ending).toHaveValue(dateValue(now + 60000));
        await expect(history.getByText('5 raw readings', { exact: true })).toBeVisible();
        await page.getByRole('tab', { name: 'Overview', exact: true }).click();
        await expect(metric(/^Temperature$/)).toHaveText('99.95 °F');
        await expect(metric(/^CTS$/)).toHaveText('Open');
        await expect(metric(/^CTS$/).locator('.cts-badge')).toHaveCSS(
            'background-color',
            'rgb(25, 135, 84)',
        );
        await expect(metric(/^PS Average$/)).toHaveText('123');
        await expect(metric(/^PDS$/)).toHaveText('9.5');
        await expect(versions.locator('dd')).toHaveText(['001.021', '003.004']);
        await page.getByRole('tab', { name: 'History', exact: true }).click();

        await history.getByLabel('View', { exact: true }).selectOption('custom');
        const from = history.getByLabel('From', { exact: true });
        const to = history.getByLabel('To', { exact: true });
        const customFrom = dateValue(now - 2 * 3600000);
        const customTo = dateValue(now + 10 * 60000);
        await from.fill(customFrom);
        await to.fill(customTo);
        await history.getByLabel('Measurement', { exact: true }).selectOption('pds');
        await expect(history.getByText('3 raw readings', { exact: true })).toBeVisible();
        await addReading(38.25, 124, 10);
        await refreshAfter(60000);
        await expect(history.getByText('4 raw readings', { exact: true })).toBeVisible();
        await expect(from).toHaveValue(customFrom);
        await expect(to).toHaveValue(customTo);
        await expect(history.getByLabel('Measurement', { exact: true })).toHaveValue('pds');

        await page.route(telemetryRoute, (route) =>
            route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }),
        );
        await refreshAfter(60000, 503);
        await expect(status).toContainText('Showing the last available data');
        await expect(history.getByText('4 raw readings', { exact: true })).toBeVisible();
        await page.getByRole('tab', { name: 'Overview', exact: true }).click();
        await expect(metric(/^Temperature$/)).toHaveText('100.85 °F');
        await expect(metric(/^CTS$/)).toHaveText('Closed');
        await expect(versions.locator('dd')).toHaveText(['—', '—']);
        await page.unroute(telemetryRoute);
        await addReading(39.5, 125, 11, { cts: null });
        await refreshAfter(60000);
        await expect(metric(/^Temperature$/)).toHaveText('103.1 °F');
        await expect(metric(/^CTS$/)).toHaveText('—');
        await expect(metric(/^CTS$/).locator('.cts-badge')).toHaveCount(0);
        await expect(status).not.toContainText('Could not refresh');
        await page.getByRole('tab', { name: 'History', exact: true }).click();
        await expect(history.getByText('5 raw readings', { exact: true })).toBeVisible();
        await expect(from).toHaveValue(customFrom);
        await expect(to).toHaveValue(customTo);
        await expect(history.getByLabel('Measurement', { exact: true })).toHaveValue('pds');
        await history.getByRole('button', { name: 'Clear', exact: true }).click();
        await expect(ending).toHaveValue(dateValue(now + 4 * 60000));
        await expect(history.getByText('7 raw readings', { exact: true })).toBeVisible();
        await refreshAfter(60000);
        await expect(ending).toHaveValue(dateValue(now + 5 * 60000));
        await expect(history.getByText('7 raw readings', { exact: true })).toBeVisible();
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        ).toBe(true);

        await page.getByRole('tab', { name: 'Control', exact: true }).click();
        await page.getByRole('tab', { name: 'Overview', exact: true }).click();
        expect(refreshRequests).toHaveLength(5);
        await page.getByRole('link', { name: 'Dashboard', exact: true }).click();
        await expect(page).toHaveURL(/\/dashboard$/);
        await page.clock.fastForward(120000);
        expect(refreshRequests).toHaveLength(5);
        expect(commands).toEqual([]);
    } finally {
        for (const id of createdReadings) {
            const response = await request.delete(`/__browser-fixtures/telemetry/${id}`);
            expect(response.status()).toBe(204);
        }
    }
});
