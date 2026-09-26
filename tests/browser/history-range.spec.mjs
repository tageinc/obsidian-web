import { test, expect } from '@playwright/test';
import axe from 'axe-core';
import { readFileSync } from 'node:fs';
import { datetimeInput } from '../../resources/js/features/solar-tracker/historyRange.js';

test.use({ timezoneId: 'Asia/Tokyo' });

const manifest = JSON.parse(
    readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'),
);
const chartModule = `/build/${manifest['node_modules/chart.js/auto/auto.js'].file}`;

async function expectZeroReference(page) {
    await expect
        .poll(() =>
            page.evaluate(async (moduleUrl) => {
                const { default: Chart } = await import(moduleUrl);
                const canvas = document.querySelector('.history-section canvas');
                const chart = canvas && Chart.getChart(canvas);
                if (!chart) return false;
                const scale = chart.scales.y;
                const zero = scale.ticks.find((tick) => tick.value === 0);
                const pixel = scale.getPixelForValue(0);
                return (
                    scale.min <= 0 &&
                    scale.max >= 0 &&
                    String(zero?.label) === '0' &&
                    pixel >= chart.chartArea.top &&
                    pixel <= chart.chartArea.bottom
                );
            }, chartModule),
        )
        .toBe(true);
}

async function expectReportDownload(page, report, start, end) {
    const [response, download] = await Promise.all([
        page.waitForResponse(
            (response) => new URL(response.url()).pathname === '/devices/1/report',
        ),
        page.waitForEvent('download'),
        report.press('Enter'),
    ]);
    const url = new URL(response.url());
    expect(response.request().method()).toBe('GET');
    expect(url.searchParams.get('from')).toBe(new Date(start).toISOString());
    expect(url.searchParams.get('to')).toBe(new Date(end).toISOString());
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['content-disposition']).toContain('attachment');
    expect(download.suggestedFilename()).toMatch(/\.pdf$/);
    const stream = await download.createReadStream();
    const chunks = [];
    for await (const chunk of stream) chunks.push(chunk);
    const pdf = Buffer.concat(chunks);
    expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
    expect(pdf.byteLength).toBeGreaterThan(5000);
    expect(await download.failure()).toBeNull();
}

test('history ranges and PDF reports navigate, validate and retain state without device commands', async ({
    page,
    context,
}) => {
    const commands = [];
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (/remote-control/.test(url.pathname) && request.method() !== 'GET') {
            commands.push(url.pathname);
            return route.abort();
        }
        return route.continue();
    });
    const now = Math.floor(Date.now() / 1000) * 1000 + 5000;
    await page.clock.setFixedTime(now);
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill('owner@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.getByRole('link', { name: 'Browser simulator', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Download history report' })).not.toBeVisible();
    await page.getByRole('tab', { name: 'History', exact: true }).click();
    const panel = page.getByRole('region', { name: 'Reading history', exact: true });
    const from = panel.getByLabel('From', { exact: true });
    const to = panel.getByLabel('To', { exact: true });
    const ending = panel.getByLabel('Ending at', { exact: true });
    const period = panel.locator('#history-period-date');
    const report = panel.getByRole('button', { name: 'Download history report', exact: true });
    const rangeError = panel.locator('#history-range-error');
    const value = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
    await expect(from).toHaveCount(0);
    await expect(ending).toHaveValue(value(now));
    await expect(panel.getByText('4 raw readings', { exact: true })).toBeVisible();
    await expect(panel.getByRole('img')).toBeVisible();
    const cards = panel.locator('.history-surface');
    await expect(cards).toHaveCount(2);
    await expect(cards.first().getByRole('group', { name: 'Graph time range' })).toBeVisible();
    await expect(cards.last().getByRole('img')).toBeVisible();
    const controlsBox = await cards.first().boundingBox();
    const chartBox = await cards.last().boundingBox();
    expect(chartBox.y).toBeGreaterThan(controlsBox.y + controlsBox.height);
    await report.focus();
    await expect(report).toBeFocused();
    await expectReportDownload(page, report, now - 86400000, now);
    await expect(panel.getByRole('status')).toContainText('includes all measurements');
    await panel.getByLabel('Measurement', { exact: true }).selectOption('pds');
    await expectZeroReference(page);
    await panel.getByRole('button', { name: 'Previous time range' }).click();
    await expect(ending).toHaveValue(value(now - 86400000));
    await expect(panel.getByText('2 raw readings', { exact: true })).toBeVisible();
    await expectZeroReference(page);
    await panel.getByRole('button', { name: 'Next time range' }).click();
    await expect(ending).toHaveValue(value(now));
    await panel.getByLabel('View', { exact: true }).selectOption('all');
    await expect(panel.getByText('6 raw readings', { exact: true })).toBeVisible();
    await panel.getByRole('button', { name: 'Clear', exact: true }).click();
    await panel.getByLabel('View', { exact: true }).selectOption('custom');
    await expect(from).toHaveValue(value(now - 86400000));
    await expect(to).toHaveValue(value(now));
    await from.fill(datetimeInput(now - 2 * 3600000));
    await expect(panel.getByLabel('View', { exact: true })).toHaveValue('custom');
    await expect(panel.getByText('2 raw readings', { exact: true })).toBeVisible();
    await expect(panel.getByRole('button', { name: 'Previous time range' })).toBeDisabled();
    await panel.getByLabel('Measurement').selectOption('panels');
    await expect(panel.getByRole('img')).toHaveAttribute('aria-label', /Panel sensors/);
    for (const [metric, label] of [
        ['pds', 'PDS'],
        ['motor', 'Motor speed'],
        ['ps_avg', 'PS average'],
    ]) {
        await panel.getByLabel('Measurement', { exact: true }).selectOption(metric);
        await expect(panel.getByRole('img')).toHaveAttribute('aria-label', new RegExp(label));
        await expect(panel.getByRole('heading', { name: new RegExp(label) })).toBeVisible();
        await expect(panel.getByText('2 raw readings', { exact: true })).toBeVisible();
        if (metric === 'pds' || metric === 'motor') await expectZeroReference(page);
    }
    await page.getByRole('tab', { name: 'Overview', exact: true }).click();
    await expect(report).not.toBeVisible();
    await page.getByRole('tab', { name: 'History', exact: true }).click();
    await expect(from).toHaveValue(value(now - 2 * 3600000));
    const reportRoute = (url) => url.pathname === '/devices/1/report';
    await page.route(reportRoute, (route) =>
        route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }),
    );
    await report.click();
    await expect(
        panel.getByRole('alert').filter({ hasText: 'could not be created' }),
    ).toBeVisible();
    await expect(report).toBeEnabled();
    await page.unroute(reportRoute);
    await expectReportDownload(page, report, now - 2 * 3600000, now);
    await expect(panel.getByRole('alert').filter({ hasText: 'could not be created' })).toHaveCount(
        0,
    );
    await expect(panel.getByLabel('Measurement', { exact: true })).toHaveValue('ps_avg');
    await from.fill(datetimeInput(now + 3600000));
    await expect(rangeError).toContainText('before or equal');
    await expect(report).toBeDisabled();
    await expect(panel.getByRole('img')).not.toBeVisible();
    await from.fill('');
    await expect(rangeError).toContainText('Enter valid');
    await expect(report).toBeDisabled();
    await from.fill('2000-01-01T00:00');
    await to.fill('2000-01-02T00:00');
    await expect(panel.getByText('No telemetry was recorded for this time range.')).toBeVisible();
    await expect(report).toBeEnabled();
    await panel.getByLabel('View', { exact: true }).selectOption('week');
    await expect(from).toHaveCount(0);
    await expect(period).toHaveValue('1999-12-27');
    await panel.getByRole('button', { name: 'Next time range' }).click();
    await expect(period).toHaveValue('2000-01-03');
    await panel.getByLabel('View', { exact: true }).selectOption('month');
    await expect(period).toHaveValue('2000-01-01');
    await period.fill('2000-02-15');
    await expect(period).toHaveValue('2000-02-01');
    await panel.getByRole('button', { name: 'Today', exact: true }).click();
    await expect(panel.getByLabel('View', { exact: true })).toHaveValue('month');
    await expect(period).toHaveValue(`${datetimeInput(now).slice(0, 7)}-01`);
    const reset = panel.getByRole('button', { name: 'Clear', exact: true });
    await reset.focus();
    await page.keyboard.press('Enter');
    await expect(reset).toBeDisabled();
    await expect(ending).toHaveValue(value(now));
    await expect(panel.getByRole('img')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(
        true,
    );
    await page.addScriptTag({ content: axe.source });
    const violations = await page.evaluate(
        async () =>
            (
                await window.axe.run('.history-section', {
                    runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] },
                })
            ).violations,
    );
    expect(violations).toEqual([]);
    await page.reload();
    await page.getByRole('tab', { name: 'History', exact: true }).click();
    await expect(ending).toHaveValue(value(now));
    await expect(from).toHaveCount(0);
    expect(commands).toEqual([]);
});
