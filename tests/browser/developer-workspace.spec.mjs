import { test, expect } from '@playwright/test';
import axe from 'axe-core';

test('developer creates, uses, and revokes an external key through Tools', async ({
    page,
    context,
}, testInfo) => {
    await context.route('**/*', (route) =>
        new URL(route.request().url()).origin === 'http://127.0.0.1:8127'
            ? route.continue()
            : route.abort(),
    );
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
    const tools = page.getByRole('button', { name: 'Tools', exact: true });
    await tools.click();
    await expect(page.getByRole('tab', { name: 'External API Keys' })).toBeHidden();
    await tools.click();
    await page.getByRole('tab', { name: 'External API Keys' }).click();
    const create = page.getByRole('button', { name: 'Create Key', exact: true });
    await create.click();
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(create).toBeFocused();
    await create.click();
    const keyName = `Browser ${testInfo.project.name} integration`;
    await page.getByLabel('Key name', { exact: true }).fill(keyName);
    await page.getByLabel('Expires on (UTC, optional)').fill('2000-01-01');
    await page.getByRole('button', { name: 'Create key', exact: true }).click();
    await expect(page.getByLabel('Expires on (UTC, optional)')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await page.getByLabel('Expires on (UTC, optional)').fill('');
    await page.getByRole('button', { name: 'Create key', exact: true }).click();
    await expect(page.getByRole('dialog', { name: 'Save your API key' })).toBeVisible();
    const secret = await page.getByLabel('API key', { exact: true }).inputValue();
    expect(/^obs_ext_[a-f0-9]{64}$/.test(secret)).toBe(true);
    await page.getByRole('button', { name: 'Copy key', exact: true }).click();
    await page.getByRole('button', { name: 'Done', exact: true }).click();
    await expect(create).toBeFocused();
    await expect(page.getByLabel('API key', { exact: true })).toHaveCount(0);

    const headers = { Authorization: `Bearer ${secret}`, Accept: 'application/json' };
    const response = await page.request.get('/api/external/v1/devices', { headers });
    expect(response.status()).toBe(200);
    const devices = await response.json();
    expect(devices.data.some((device) => device.name === 'Browser simulator')).toBe(true);
    const noCsrf = await page.request.post('/developer-workspace/api-keys', {
        data: { name: 'Rejected' },
        headers: { Accept: 'application/json' },
    });
    expect(noCsrf.status()).toBe(419);

    await page.reload();
    await page.getByRole('tab', { name: 'External API Keys' }).click();
    const row = page
        .getByRole('row')
        .filter({ has: page.getByRole('rowheader', { name: keyName, exact: true }) });
    await expect(row).toContainText('Active');
    await expect(row).not.toContainText(secret);
    await row.getByRole('button', { name: `Revoke ${keyName}`, exact: true }).click();
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(row.getByRole('button')).toBeFocused();
    await row.getByRole('button').click();
    await page.getByRole('button', { name: 'Revoke key', exact: true }).click();
    await expect(row).toContainText('Revoked');
    expect((await page.request.get('/api/external/v1', { headers })).status()).toBe(401);
    await page.goto('/developer-workspace?section=api-keys');
    await expect(page.getByRole('row').filter({ hasText: keyName })).toContainText('Revoked');
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

    // The native form fallback uses the same session-only management endpoints.
    await page.goto('/developer-workspace/api-keys');
    await page.getByLabel('Key name', { exact: true }).fill(`${keyName} native`);
    await page.getByRole('button', { name: 'Create Key', exact: true }).click();
    const nativeKey = page.getByLabel('Copy this key now. It will not be shown again.');
    await expect(nativeKey).toBeVisible();
    const nativeSecret = await nativeKey.inputValue();
    expect(
        (
            await page.request.get('/api/external/v1', {
                headers: { Authorization: `Bearer ${nativeSecret}` },
            })
        ).status(),
    ).toBe(200);
    await page.goto('/developer-workspace/api-keys');
    await expect(nativeKey).toHaveCount(0);
    await page.getByRole('button', { name: `Revoke ${keyName} native`, exact: true }).click();
    expect(
        (
            await page.request.get('/api/external/v1', {
                headers: { Authorization: `Bearer ${nativeSecret}` },
            })
        ).status(),
    ).toBe(401);
});

test('developer workspace navigation and upload dialogs remain accessible', async ({
    page,
    context,
}) => {
    await context.route('**/*', (route) =>
        new URL(route.request().url()).origin === 'http://127.0.0.1:8127'
            ? route.continue()
            : route.abort(),
    );
    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill('developer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeFocused();
    const navigation = page.getByRole('button', { name: 'Toggle navigation' });
    if (await navigation.isVisible()) await navigation.click();
    await page.getByRole('button', { name: 'Developer browser fixture' }).focus();
    await page.keyboard.press('ArrowDown');
    await page.getByRole('link', { name: 'Developer Workspace', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Developer Workspace' })).toBeVisible();
    const firmware = page.getByRole('tab', { name: /Firmware/ });
    const config = page.getByRole('tab', { name: /Configuration/ });
    const software = page.getByRole('button', { name: 'Software', exact: true });
    await software.focus();
    await page.keyboard.press('Enter');
    await expect(software).toHaveAttribute('aria-expanded', 'false');
    await expect(firmware).toBeHidden();
    await expect(config).toBeHidden();
    await page.keyboard.press('Space');
    await expect(software).toHaveAttribute('aria-expanded', 'true');
    await firmware.focus();
    await page.keyboard.press('End');
    await expect(config).toBeFocused();
    await expect(config).toHaveAttribute('aria-selected', 'true');
    await software.click();
    await software.click();
    await expect(config).toHaveAttribute('aria-selected', 'true');
    for (const kind of ['Configuration', 'Firmware']) {
        await page.getByRole('tab', { name: new RegExp(kind) }).click();
        const upload = page.getByRole('button', { name: 'New upload', exact: true });
        await upload.click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(upload).toBeFocused();
    }
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
});
