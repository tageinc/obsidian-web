import { test, expect } from '@playwright/test';
import axe from 'axe-core';
import { readFileSync } from 'node:fs';
import { datetimeInput } from '../../resources/js/features/solar-tracker/historyRange.js';

async function expectZeroReference(page) {
    await expect
        .poll(() =>
            page.evaluate(() => {
                const canvas = document.querySelector('.history-section canvas');
                if (!canvas || !canvas.chart) return false;
                const scale = canvas.chart.scales.y;
                if (!scale) return false;
                const pixel = scale.getPixelForValue(0);
                const zeroIdx = scale.ticks.findIndex((t) => t.value === 0);
                if (zeroIdx < 0) return false;
                const grid = scale.options.grid.setContext(scale.getContext(zeroIdx));
                return (
                    scale.min <= 0 &&
                    scale.max >= 0 &&
                    scale.ticks[zeroIdx].label === '0' &&
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--obsidian-border')
                        .trim() === grid.color &&
                    grid.lineWidth === 1 &&
                    pixel >= canvas.chart.chartArea.top &&
                    pixel <= canvas.chart.chartArea.bottom
                );
            }),
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
    await page.route(/chart\.js/i, (route) => {
        if (/chart\.js/.test(route.request().url())) {
            return route.fulfill({
                contentType: 'application/javascript',
                body: readFileSync('node_modules/chart.js/dist/chart.umd.js'),
            });
        }
        return route.continue();
    });
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

// --- Browser regressions for OB-20 empty-ending recovery and arrow visibility ---

test.describe('OB-20 empty-ending recovery and period navigation', () => {
    test.use({ timezoneId: 'America/Los_Angeles' });

    async function navigate(page) {
        await page.clock.setFixedTime(Date.now());
        await page.goto('/login');
        await page.getByLabel('Email address', { exact: true }).fill('owner@browser.example.test');
        await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
        await page.getByRole('button', { name: 'Login', exact: true }).click();
        await page.getByRole('tab', { name: 'History', exact: true }).click();
    }

    async function panelElements(page) {
        const ending = page.locator('#history-ending');
        const view = page.locator('#history-view');
        const metric = page.locator('#history-metric');
        const prevArrow = page.locator('[aria-label="Previous time range"]');
        const nextArrow = page.locator('[aria-label="Next time range"]');
        const rangeError = page.locator('#history-range-error');
        const from = page.locator('#history-from');
        const periodDate = page.locator('#history-period-date');
        return { ending, view, metric, prevArrow, nextArrow, rangeError, from, periodDate };
    }

    test('empty-ending recovery restores valid now-range in day, hour, twelveHours and retains view/metric', async ({
        page,
    }) => {
        const value = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
        await navigate(page);
        const { ending, view, metric, rangeError } = await panelElements(page);

        for (const targetView of ['day', 'hour', 'twelveHours']) {
            await view.selectOption(targetView);
            const savedMetric = metric.element.value;

            // Set a non-default ending.
            await ending.fill(value(Date.now() - 60_000));
            await expect(ending).toHaveValue(value(Date.now() - 60_000));

            // Clear to empty — recovery should restore valid range, clear error, keep view.
            await ending.fill('');
            await page.waitForTimeout(300);
            await expect(rangeError).toBeHidden();
            await expect(view).toHaveValue(targetView);
            await expect(metric).toHaveValue(savedMetric);

            // Ending should now be a populated value matching the current time.
            const endingVal = await ending.inputValue();
            expect(endingVal).not.toBe('');
            expect(new Date(endingVal).getTime()).toBeGreaterThan(Date.now() - 2000);
        }
    });

    test('Previous/Next arrows produce exact elapsed shifts for hour and twelveHours', async ({
        page,
    }) => {
        const value = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
        await navigate(page);
        const { ending, view, prevArrow, nextArrow } = await panelElements(page);

        for (const [targetView, expectedDuration] of [
            ['hour', 3_600_000],
            ['twelveHours', 12 * 3_600_000],
        ]) {
            await view.selectOption(targetView);

            const endingBefore = await ending.inputValue();
            const endMsBefore = new Date(endingBefore).getTime();

            // Click Previous — should shift by exactly one window.
            await prevArrow.click();
            await page.waitForTimeout(200);
            const endingPrev = await ending.inputValue();
            const endMsPrev = new Date(endingPrev).getTime();
            expect(endMsBefore - endMsPrev).toBe(expectedDuration);

            // Click Next — should restore the original ending.
            await nextArrow.click();
            await page.waitForTimeout(200);
            const endingNext = await ending.inputValue();
            expect(endingNext).toBe(endingBefore);
        }
    });

    test('arrows are always visible for all views and report/download parameters are correct', async ({
        page,
    }) => {
        await navigate(page);
        const { view, prevArrow, nextArrow } = await panelElements(page);

        for (const viewVal of ['day', 'week', 'month', 'hour', 'twelveHours']) {
            await view.selectOption(viewVal);
            await expect(prevArrow).toBeVisible();
            await expect(nextArrow).toBeVisible();
        }
    });

    test('empty-ending in custom retains From/To and does not recover; day/week/month arrows shift displayed range', async ({
        page,
    }) => {
        const value = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
        await navigate(page);
        const { ending, view, prevArrow, nextArrow, from, periodDate } = await panelElements(page);

        // Custom: empty ending does not trigger recovery.
        await view.selectOption('custom');
        const savedFrom = await from.inputValue();
        await ending.fill('');
        await page.waitForTimeout(300);
        const actualEnding = await ending.inputValue();
        expect(actualEnding).toBe(savedFrom); // Custom span retains From value.

        // Day view arrows shift displayed range by exactly one window (86400000 ms).
        await view.selectOption('day');
        const dayEndBefore = new Date(await ending.inputValue()).getTime();
        await prevArrow.click();
        await page.waitForTimeout(200);
        const dayEndPrev = new Date(await ending.inputValue()).getTime();
        expect(dayEndBefore - dayEndPrev).toBe(86_400_000);

        // Week view arrows shift displayed range.
        await view.selectOption('week');
        const weekPeriodBefore = await periodDate.inputValue();
        await nextArrow.click();
        await page.waitForTimeout(200);
        const weekPeriodNext = await periodDate.inputValue();
        expect(weekPeriodNext).not.toBe(weekPeriodBefore);
    });
});
