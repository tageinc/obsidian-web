import { test, expect } from '@playwright/test';
import axe from 'axe-core';
import { datetimeInput } from '../../resources/js/features/solar-tracker/historyRange.js';

test.use({ timezoneId: 'Asia/Tokyo' });

test('history datetime ranges navigate, validate and retain state without device commands', async ({
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
    await page.getByRole('tab', { name: 'History', exact: true }).click();
    const panel = page.getByRole('region', { name: 'Reading history', exact: true });
    const from = panel.getByLabel('From', { exact: true });
    const to = panel.getByLabel('To', { exact: true });
    const ending = panel.getByLabel('Ending at', { exact: true });
    const period = panel.locator('#history-period-date');
    const value = (epoch) => datetimeInput(epoch).replace(/:00$/, '');
    await expect(from).toHaveCount(0);
    await expect(ending).toHaveValue(value(now));
    await expect(panel.getByText('4 raw readings', { exact: true })).toBeVisible();
    await expect(panel.getByRole('img')).toBeVisible();
    await panel.getByRole('button', { name: 'Previous time range' }).click();
    await expect(ending).toHaveValue(value(now - 86400000));
    await expect(panel.getByText('2 raw readings', { exact: true })).toBeVisible();
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
        ['ps_avg', 'PS average'],
    ]) {
        await panel.getByLabel('Measurement', { exact: true }).selectOption(metric);
        await expect(panel.getByRole('img')).toHaveAttribute('aria-label', new RegExp(label));
        await expect(panel.getByRole('heading', { name: new RegExp(label) })).toBeVisible();
        await expect(panel.getByText('2 raw readings', { exact: true })).toBeVisible();
    }
    await page.getByRole('tab', { name: 'Overview', exact: true }).click();
    await page.getByRole('tab', { name: 'History', exact: true }).click();
    await expect(from).toHaveValue(value(now - 2 * 3600000));
    await from.fill(datetimeInput(now + 3600000));
    await expect(panel.getByRole('alert')).toContainText('before or equal');
    await expect(panel.getByRole('img')).not.toBeVisible();
    await from.fill('');
    await expect(panel.getByRole('alert')).toContainText('Enter valid');
    await from.fill('2000-01-01T00:00');
    await to.fill('2000-01-02T00:00');
    await expect(panel.getByText('No telemetry was recorded for this time range.')).toBeVisible();
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
