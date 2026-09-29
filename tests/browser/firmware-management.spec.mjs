import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';
import axe from 'axe-core';

test.beforeEach(async ({ page, context }) => {
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (
            request.method() !== 'GET' &&
            /remote-control|update-solar-tracker/.test(url.pathname)
        ) {
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

async function expectDialogAccessibility(page, dialog) {
    await expect(dialog).toBeVisible();
    const bounds = await dialog.boundingBox();
    const viewport = page.viewportSize();
    expect(bounds).not.toBeNull();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(viewport.width);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(viewport.height);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
    expect(await dialog.evaluate((element) => element.scrollWidth <= element.clientWidth)).toBe(
        true,
    );
    await page.addScriptTag({ content: axe.source });
    const violations = await dialog.evaluate(async (element) =>
        (
            await axe.run(element, {
                runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] },
            })
        ).violations.map(({ id }) => id),
    );
    expect(violations).toEqual([]);
}

async function openAction(page, releaseName, version, action) {
    const trigger = page.getByRole('button', {
        name: `Actions for ${releaseName} ${version}`,
        exact: true,
    });
    await trigger.scrollIntoViewIfNeeded();
    await trigger.focus();
    await page.keyboard.press('ArrowDown');
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    const bounds = await menu.boundingBox();
    const viewport = page.viewportSize();
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(viewport.width);
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(viewport.height);
    await expect(menu.getByRole('menuitem', { name: 'Edit', exact: true })).toBeFocused();
    if (action === 'Delete') await page.keyboard.press('ArrowDown');
    await expect(menu.getByRole('menuitem', { name: action, exact: true })).toBeFocused();
    await page.keyboard.press('Enter');
    return page.getByRole('dialog', { name: `${action} ${releaseName}`, exact: true });
}

const releases = [
    {
        kind: 'firmware',
        title: 'Firmware',
        releaseName: 'firmware',
        uploadButton: 'Upload Firmware',
        filename: 'fixture.bin',
        mimeType: 'application/octet-stream',
        bytes: Buffer.from('Synthetic browser firmware binary.'),
    },
    {
        kind: 'config',
        title: 'Configuration',
        releaseName: 'configuration',
        uploadButton: 'Upload JSON Config',
        filename: 'fixture.json',
        mimeType: 'application/json',
        bytes: readFileSync(new URL('../Fixtures/tracker-configuration.json', import.meta.url)),
    },
];

for (const { kind, title, releaseName, uploadButton, filename, mimeType, bytes } of releases) {
    for (const renderer of ['modern', 'legacy']) {
        test(`${renderer} ${releaseName} versions are chosen, edited and deleted with confirmation`, async ({
            page,
        }, testInfo) => {
            const prefix = `BROWSER-MUTATION-${renderer}-${testInfo.project.name}`;
            const initialVersion = String(
                (renderer === 'modern' ? 4000 : 5000) +
                    (testInfo.project.name === 'desktop' ? 101 : 201),
            );
            const editedVersion = String(Number(initialVersion) + 1);
            const originalDescription = `Synthetic ${releaseName} ${prefix}.`;
            const editedDescription = `Edited ${releaseName} ${prefix}.`;
            const mutations = [];
            const commands = [];
            page.on('request', (request) => {
                const path = new URL(request.url()).pathname;
                if (
                    ['PATCH', 'DELETE'].includes(request.method()) &&
                    path.startsWith(`/developer-workspace/${kind}/`)
                ) {
                    mutations.push({
                        method: request.method(),
                        path,
                        body: request.postDataJSON(),
                    });
                }
                if (
                    request.method() !== 'GET' &&
                    /remote-control|update-solar-tracker/.test(path)
                ) {
                    commands.push(path);
                }
            });

            try {
                if (renderer === 'legacy') await page.goto('/__browser-fixtures/developer/legacy');
                let upload;
                if (renderer === 'modern') {
                    await page.getByRole('tab', { name: title, exact: true }).click();
                    await page.getByRole('button', { name: 'New upload', exact: true }).click();
                    upload = page.getByRole('dialog', {
                        name: `Upload ${releaseName}`,
                        exact: true,
                    });
                } else {
                    upload = page.locator(`.${kind}-section form[action$="/upload-${kind}"]`);
                }
                const version = upload.getByRole('spinbutton', {
                    name: `${title} version`,
                    exact: true,
                });
                await expect(version).toHaveValue('');
                await expect(version).toHaveAttribute('min', '1');
                await expect(version).toHaveAttribute('step', '1');
                await upload.locator(`input[name="${kind}"]`).setInputFiles({
                    name: filename,
                    mimeType,
                    buffer: bytes,
                });
                await upload.locator('[name="description"]').fill(originalDescription);
                await upload.locator('[name="prefix"]').fill(prefix);
                if (renderer === 'modern') await expectDialogAccessibility(page, upload);
                for (const invalid of ['0', '-1', '1.5']) {
                    await version.fill(invalid);
                    await upload.getByRole('button', { name: uploadButton, exact: true }).click();
                    expect(await version.evaluate((element) => element.validity.valid)).toBe(false);
                    await expect(version).toHaveValue(invalid);
                }
                await version.fill(initialVersion);
                await version.press('ArrowUp');
                await expect(version).toHaveValue(editedVersion);
                await version.press('ArrowDown');
                await expect(version).toHaveValue(initialVersion);
                await upload.getByRole('button', { name: uploadButton, exact: true }).click();
                const section =
                    renderer === 'modern'
                        ? page.getByRole('tabpanel', { name: title, exact: true })
                        : page.locator(`.${kind}-section`);
                const row = section.locator('tbody tr').filter({ hasText: prefix });
                await expect(row).toHaveCount(1);
                await expect(row.locator('th, td').first()).toHaveText(`v${initialVersion}`);
                await expect(row).toContainText(originalDescription);
                const uploadedAt = await row.locator('time').getAttribute('datetime');
                await page.reload();
                await expect(row.locator('th, td').first()).toHaveText(`v${initialVersion}`);
                expect(
                    await (
                        await page.request.get(`/${kind}-file/version/${initialVersion}`)
                    ).body(),
                ).toEqual(bytes);

                let edit = await openAction(page, releaseName, initialVersion, 'Edit');
                await expect(
                    edit.getByRole('heading', { name: `Edit ${releaseName}`, exact: true }),
                ).toBeFocused();
                await expect(
                    edit.getByRole('spinbutton', { name: `${title} version`, exact: true }),
                ).toHaveValue(initialVersion);
                await expect(edit.getByLabel(`${title} description`, { exact: true })).toHaveValue(
                    originalDescription,
                );
                await expect(edit.locator('[name="prefix"]')).toHaveCount(0);
                await edit
                    .getByLabel(`${title} description`, { exact: true })
                    .fill('Cancelled edit');
                await expectDialogAccessibility(page, edit);
                await edit.getByRole('button', { name: 'Save changes', exact: true }).focus();
                await page.keyboard.press('Tab');
                await expect(
                    edit.getByRole('button', { name: 'Close dialog', exact: true }),
                ).toBeFocused();
                await page.keyboard.press('Shift+Tab');
                await expect(
                    edit.getByRole('button', { name: 'Save changes', exact: true }),
                ).toBeFocused();
                await page.keyboard.press('Escape');
                await expect(edit).toHaveCount(0);
                await expect(
                    page.getByRole('button', {
                        name: `Actions for ${releaseName} ${initialVersion}`,
                        exact: true,
                    }),
                ).toBeFocused();
                expect(mutations).toEqual([]);
                await expect(row).toContainText(originalDescription);

                edit = await openAction(page, releaseName, initialVersion, 'Edit');
                await edit
                    .getByRole('spinbutton', { name: `${title} version`, exact: true })
                    .fill('12');
                await edit
                    .getByLabel(`${title} description`, { exact: true })
                    .fill(editedDescription);
                const collision = page.waitForResponse(
                    (response) =>
                        response.request().method() === 'PATCH' &&
                        new URL(response.url()).pathname.startsWith(
                            `/developer-workspace/${kind}/`,
                        ),
                );
                await edit.getByRole('button', { name: 'Save changes', exact: true }).click();
                expect((await collision).status()).toBe(422);
                await expect(
                    edit.getByRole('spinbutton', { name: `${title} version`, exact: true }),
                ).toHaveAttribute('aria-invalid', 'true');
                await expect(edit.getByRole('alert')).toBeVisible();
                await expect(row).toContainText(originalDescription);
                await edit
                    .getByRole('spinbutton', { name: `${title} version`, exact: true })
                    .fill(editedVersion);
                await edit.getByRole('button', { name: 'Save changes', exact: true }).click();
                await expect(edit).toHaveCount(0);
                await expect(row.locator('th, td').first()).toHaveText(`v${editedVersion}`);
                await expect(row).toContainText(editedDescription);
                await expect(row.locator('time')).toHaveAttribute('datetime', uploadedAt);
                await page.reload();
                await expect(row).toContainText(editedDescription);
                expect(
                    (await page.request.get(`/${kind}-file/version/${initialVersion}`)).status(),
                ).toBe(404);
                expect(
                    await (await page.request.get(`/${kind}-file/version/${editedVersion}`)).body(),
                ).toEqual(bytes);

                let confirmation = await openAction(page, releaseName, editedVersion, 'Delete');
                await expect(confirmation).toContainText(editedVersion);
                await expect(confirmation).toContainText(prefix);
                await expectDialogAccessibility(page, confirmation);
                await confirmation.getByRole('button', { name: 'Cancel', exact: true }).click();
                await expect(confirmation).toHaveCount(0);
                await expect(
                    page.getByRole('button', {
                        name: `Actions for ${releaseName} ${editedVersion}`,
                        exact: true,
                    }),
                ).toBeFocused();
                expect(mutations.filter(({ method }) => method === 'DELETE')).toEqual([]);
                await page.reload();
                await expect(row).toContainText(editedDescription);

                let failDelete = true;
                await page.route(`**/developer-workspace/${kind}/*`, (route) => {
                    if (route.request().method() === 'DELETE' && failDelete) {
                        failDelete = false;
                        return route.fulfill({
                            status: 500,
                            contentType: 'application/json',
                            body: JSON.stringify({
                                message: 'Synthetic deletion failure. Try again.',
                            }),
                        });
                    }
                    return route.continue();
                });
                confirmation = await openAction(page, releaseName, editedVersion, 'Delete');
                await confirmation
                    .getByRole('button', { name: `Delete ${releaseName}`, exact: true })
                    .click();
                await expect(confirmation.getByRole('alert')).toContainText(
                    'The request could not be completed. Please try again.',
                );
                await expect(row).toHaveCount(1);
                await confirmation
                    .getByRole('button', { name: `Delete ${releaseName}`, exact: true })
                    .click();
                await expect(confirmation).toHaveCount(0);
                await expect(row).toHaveCount(0);
                await page.reload();
                await expect(row).toHaveCount(0);
                expect(
                    (await page.request.get(`/${kind}-file/version/${editedVersion}`)).status(),
                ).toBe(404);
                expect(
                    mutations.filter(({ method }) => method === 'PATCH').map(({ body }) => body),
                ).toEqual([
                    { version: '12', description: editedDescription },
                    { version: editedVersion, description: editedDescription },
                ]);
                expect(commands).toEqual([]);
                expect(
                    await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
                ).toBe(true);
            } finally {
                const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
                const cleanup = await page.request.delete(`/__browser-fixtures/${kind}`, {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                    data: { prefix },
                });
                expect(cleanup.ok()).toBe(true);
            }
        });
    }
}
