import { test, expect } from '@playwright/test';

const originalPassword = 'browser-test-password';
const replacementPassword = 'synthetic-browser-replacement-password';
let physicalRequests;

test.beforeEach(async ({ context }) => {
    physicalRequests = [];
    await context.route('**/*', (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.origin !== 'http://127.0.0.1:8127') return route.abort();
        if (
            request.method() !== 'GET' &&
            /remote-control|update-solar-tracker/.test(url.pathname)
        ) {
            physicalRequests.push(url.pathname);
            return route.abort();
        }
        return route.continue();
    });
});

test.afterEach(() => {
    expect(physicalRequests).toEqual([]);
});

async function failMail(page, email, fail) {
    const response = await page.request.put('/__browser-fixtures/auth-mail', {
        data: { email, fail },
    });
    expect(response.status()).toBe(204);
}

async function mailState(page, email) {
    const response = await page.request.get('/__browser-fixtures/auth-mail', {
        params: { email },
    });
    expect(response.ok()).toBe(true);
    return response.json();
}

async function submit(page, path, name) {
    const [response] = await Promise.all([
        page.waitForResponse(
            (response) =>
                new URL(response.url()).pathname === path && response.request().method() === 'POST',
        ),
        page.waitForEvent('framenavigated', { predicate: (frame) => frame === page.mainFrame() }),
        page.getByRole('button', { name, exact: true }).click(),
    ]);
    expect(response.status()).toBe(302);
    await page.waitForLoadState('domcontentloaded');
}

async function expectAuthPage(page, renderer, title) {
    const mount = page.locator('[data-vue-page="auth"]');
    if (renderer === 'legacy') {
        await expect(mount).toHaveCount(0);
        await expect(page.locator('.card-header', { hasText: title })).toBeVisible();
    } else {
        await expect(mount).toBeVisible();
        await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    }
}

function errorFeedback(page, renderer) {
    return renderer === 'legacy'
        ? page.locator('.alert-danger, .invalid-feedback[role="alert"]')
        : page.locator('[data-vue-page="auth"]').getByRole('alert');
}

function successFeedback(page, renderer) {
    return renderer === 'legacy'
        ? page.locator('.alert-success')
        : page.locator('[data-vue-page="auth"] .alert-success[role="status"]');
}

function resendEmail(page, renderer, location = 'login') {
    if (renderer === 'modern') {
        return page.getByLabel(location === 'login' ? 'Email address to verify' : 'Email address', {
            exact: true,
        });
    }
    return page.getByPlaceholder(
        location === 'login'
            ? 'Enter your email address to resend verification'
            : 'Enter your email address',
        { exact: true },
    );
}

function resendButton(renderer, location = 'login') {
    if (renderer === 'modern') return 'Resend verification email';
    return location === 'login'
        ? 'Click here to resend the verification email.'
        : 'click here to request another';
}

async function login(page, renderer, email, password = originalPassword) {
    await page.goto('/login');
    await expectAuthPage(page, renderer, 'Login');
    await page.getByLabel(/^Email address$/i).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await submit(page, '/login', 'Login');
}

async function logout(page, renderer, purpose, project) {
    const navigation = page.getByRole('button', { name: 'Toggle navigation' });
    if (await navigation.isVisible()) await navigation.click();
    await page
        .getByRole('button', { name: `Authentication ${purpose} ${renderer} ${project} fixture` })
        .click();
    await submit(page, '/logout', 'Logout');
    await expectAuthPage(page, renderer, 'Login');
}

async function expectNoOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(
        true,
    );
}

for (const renderer of ['modern', 'legacy']) {
    test.describe(`${renderer} authentication recovery`, () => {
        test.beforeEach(async ({ context }) => {
            if (renderer === 'legacy') {
                await context.addCookies([
                    {
                        name: 'obsidian_browser_auth_renderer',
                        value: 'legacy',
                        url: 'http://127.0.0.1:8127',
                        sameSite: 'Lax',
                    },
                ]);
            }
        });

        test('password reset recovers from delivery failure and consumes the real rendered mail link once', async ({
            page,
        }, testInfo) => {
            const email = `auth-reset-${renderer}-${testInfo.project.name}@browser.example.test`;
            await page.goto('/login');
            await expectAuthPage(page, renderer, 'Login');
            await page.getByRole('link', { name: 'Forgot Your Password?', exact: true }).click();
            await expect(page).toHaveURL(/\/password\/reset$/);
            await expectAuthPage(page, renderer, 'Reset Password');
            await page.getByLabel(/^Email address$/i).fill(email);
            await failMail(page, email, true);
            try {
                await submit(page, '/password/email', 'Send Password Reset Link');
                await expect(errorFeedback(page, renderer)).toContainText(
                    'We could not send the password reset email. Please try again shortly.',
                );
                await expectAuthPage(page, renderer, 'Reset Password');
                await expect(page.getByLabel(/^Email address$/i)).toHaveValue(email);
                await expectNoOverflow(page);
                expect(await mailState(page, email)).toEqual({
                    delivery: null,
                    verified: true,
                    activity_email_enabled: false,
                    notification_count: 0,
                });
            } finally {
                await failMail(page, email, false);
            }
            await submit(page, '/password/email', 'Send Password Reset Link');
            await expect(successFeedback(page, renderer)).toContainText(
                'We have emailed your password reset link!',
            );
            const delivered = await mailState(page, email);
            expect(delivered.delivery.email).toBe(email);
            expect(new URL(delivered.delivery.url).pathname).toMatch(/^\/password\/reset\/[^/]+$/);
            await page.goto(delivered.delivery.url);
            await expectAuthPage(page, renderer, 'Reset Password');
            await expect(page.getByLabel(/^Email address$/i)).toHaveValue(email);
            await page.getByLabel('Password', { exact: true }).fill(replacementPassword);
            await page.getByLabel(/^Confirm password$/i).fill(replacementPassword);
            await expectNoOverflow(page);
            await submit(page, '/password/reset', 'Reset Password');
            await expect(
                page.getByRole('heading', { name: 'Dashboard', exact: true }),
            ).toBeVisible();
            await logout(page, renderer, 'reset', testInfo.project.name);

            await login(page, renderer, email);
            await expect(errorFeedback(page, renderer)).toContainText(
                'These credentials do not match our records.',
            );
            await login(page, renderer, email, replacementPassword);
            await expect(
                page.getByRole('heading', { name: 'Dashboard', exact: true }),
            ).toBeVisible();
            await logout(page, renderer, 'reset', testInfo.project.name);
            await page.goto(delivered.delivery.url);
            await page
                .getByLabel('Password', { exact: true })
                .fill('reused-token-must-not-change-password');
            await page
                .getByLabel(/^Confirm password$/i)
                .fill('reused-token-must-not-change-password');
            await submit(page, '/password/reset', 'Reset Password');
            await expect(errorFeedback(page, renderer)).toContainText(
                'This password reset token is invalid.',
            );
            await login(page, renderer, email, replacementPassword);
            await expect(
                page.getByRole('heading', { name: 'Dashboard', exact: true }),
            ).toBeVisible();
            expect((await mailState(page, email)).notification_count).toBe(0);
        });

        test('verification resend recovers from delivery failure and rejects a tampered rendered link', async ({
            page,
        }, testInfo) => {
            const email = `auth-verify-${renderer}-${testInfo.project.name}@browser.example.test`;
            await login(page, renderer, email);
            await expect(errorFeedback(page, renderer)).toContainText(
                'You need to verify your email address',
            );
            await resendEmail(page, renderer).fill(email);
            await failMail(page, email, true);
            try {
                await submit(page, '/email/resend', resendButton(renderer));
                await expect(errorFeedback(page, renderer)).toContainText(
                    'Failed to send verification email.',
                );
                await expectAuthPage(page, renderer, 'Login');
                expect(await mailState(page, email)).toEqual({
                    delivery: null,
                    verified: false,
                    activity_email_enabled: false,
                    notification_count: 0,
                });
            } finally {
                await failMail(page, email, false);
            }

            // An unverified login exposes the existing resend form again after feedback.
            await login(page, renderer, email);
            await resendEmail(page, renderer).fill(email);
            await submit(page, '/email/resend', resendButton(renderer));
            await expect(successFeedback(page, renderer)).toContainText(
                `Verification email sent to: ${email}`,
            );
            const delivered = await mailState(page, email);
            expect(delivered.delivery.email).toBe(email);
            const signed = new URL(delivered.delivery.url);
            expect(signed.pathname).toMatch(/^\/email\/verify\/\d+\/[a-f0-9]+$/);
            expect(signed.searchParams.get('expires')).toMatch(/^\d+$/);
            expect(signed.searchParams.get('signature')).toMatch(/^[a-f0-9]+$/);
            const tampered = new URL(signed);
            tampered.searchParams.set('tampered', '1');
            const rejected = await page.goto(tampered.href);
            expect(rejected.status()).toBe(403);
            expect((await mailState(page, email)).verified).toBe(false);
            await page.goto(signed.href);
            await expectAuthPage(page, renderer, 'Login');
            expect((await mailState(page, email)).verified).toBe(true);
            await login(page, renderer, email);
            await expect(
                page.getByRole('heading', { name: 'Dashboard', exact: true }),
            ).toBeVisible();
            await expectNoOverflow(page);
            const verified = await mailState(page, email);
            expect(verified.activity_email_enabled).toBe(false);
            expect(verified.notification_count).toBe(0);
        });

        test('registration keeps an account usable when verification mail fails and resend finishes setup', async ({
            page,
        }, testInfo) => {
            const email = `auth-register-${renderer}-${testInfo.project.name}@browser.example.test`;
            await page.goto('/register');
            await expectAuthPage(page, renderer, 'Register');
            for (const [label, value] of Object.entries({
                Name: `Authentication registration ${renderer} ${testInfo.project.name} fixture`,
                [renderer === 'legacy' ? 'Email Address' : 'Email address']: email,
                [renderer === 'legacy' ? 'Phone Number' : 'Phone number']: '6045550123',
                [renderer === 'legacy' ? 'Address 1' : 'Address line 1']: '10 Test Street',
                City: 'Vancouver',
                [renderer === 'legacy' ? 'State' : 'State / Province']: 'BC',
                [renderer === 'legacy' ? 'Zip Code' : 'ZIP / Postal code']: 'V6B 1A1',
                Country: 'CA',
                Password: originalPassword,
                [renderer === 'legacy' ? 'Confirm Password' : 'Confirm password']: originalPassword,
            })) {
                await page.getByLabel(label, { exact: true }).fill(value);
            }
            await failMail(page, email, true);
            try {
                await submit(page, '/register', 'Register');
                await expect(page).toHaveURL(/\/email\/verify$/);
                await expectAuthPage(page, renderer, 'Verify Your Email Address');
                await expect(errorFeedback(page, renderer)).toContainText(
                    'Your account was created, but we could not send the verification email. Please try sending it again.',
                );
                expect((await mailState(page, email)).delivery).toBeNull();
                expect((await mailState(page, email)).verified).toBe(false);
                await expectNoOverflow(page);
            } finally {
                await failMail(page, email, false);
            }
            await page.goto('/dashboard');
            await expect(page).toHaveURL(/\/email\/verify$/);
            await expectAuthPage(page, renderer, 'Verify Your Email Address');
            const verificationEmail = resendEmail(page, renderer, 'notice');
            if (renderer === 'modern') await expect(verificationEmail).toHaveValue(email);
            else await verificationEmail.fill(email);
            await submit(page, '/email/resend', resendButton(renderer, 'notice'));
            await expect(successFeedback(page, renderer)).toContainText(
                `Verification email sent to: ${email}`,
            );
            const delivered = await mailState(page, email);
            expect(delivered.delivery.email).toBe(email);
            await page.goto(delivered.delivery.url);
            await expect(
                page.getByRole('heading', { name: 'Dashboard', exact: true }),
            ).toBeVisible();
            const verified = await mailState(page, email);
            expect(verified.verified).toBe(true);
            expect(verified.notification_count).toBe(0);
        });
    });
}
