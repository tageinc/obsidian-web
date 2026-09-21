import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AuthPage from './AuthPage.vue';

const mounted = [];
const actions = {
    login: '/login',
    register: '/register',
    verify: '/email/resend',
    'password-email': '/password/email',
    'password-reset': '/password/reset',
    'password-confirm': '/password/confirm',
};
function page(mode = 'login', overrides = {}) {
    const wrapper = mount(AuthPage, {
        attachTo: document.body,
        props: {
            mode,
            action: actions[mode],
            csrfToken: 'test-csrf',
            links: {
                passwordRequest: '/password/reset',
                contact: '/contact-us',
                privacy: '/privacy.pdf',
                verificationResend: '/email/resend',
            },
            values: { email: 'reader@example.test' },
            ...overrides,
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.restoreAllMocks();
});

describe('native authentication workflows', () => {
    it.each(Object.keys(actions))(
        'keeps the %s endpoint and one CSRF-protected native submit',
        (mode) => {
            const wrapper = page(mode, { resetToken: 'reset-token-fixture' });
            const form = wrapper.get('[data-auth-form]');
            expect(form.attributes('method')).toBe('POST');
            expect(form.attributes('action')).toBe(actions[mode]);
            expect(form.findAll('button[type="submit"]')).toHaveLength(1);
            expect(new FormData(form.element).get('_token')).toBe('test-csrf');
            expect(wrapper.find('[name="_method"]').exists()).toBe(false);
            expect(wrapper.find('[name="token"]').exists()).toBe(mode === 'password-reset');
        },
    );

    it('keeps registration contact/address fields and safe old values, with blank passwords', async () => {
        const wrapper = page('register', {
            values: {
                name: 'Demo',
                email: 'reader@example.test',
                city: 'Vancouver',
                zip_code: 'V6B 1A1',
                password: 'never-hydrate',
                password_confirmation: 'never-hydrate',
                api_token: 'never-hydrate',
                id: 12,
            },
        });
        expect(wrapper.html()).not.toContain('never-hydrate');
        const form = wrapper.get('[data-auth-form]');
        const submitted = Object.fromEntries(new FormData(form.element));
        expect(submitted).toMatchObject({
            name: 'Demo',
            city: 'Vancouver',
            zip_code: 'V6B 1A1',
            password: '',
            password_confirmation: '',
        });
        expect(Object.keys(submitted)).toEqual(
            expect.arrayContaining(['phone_number', 'address_1', 'address_2', 'state', 'country']),
        );
        expect(wrapper.find('[name="id"]').exists()).toBe(false);
        expect(wrapper.get('#address_2').attributes('required')).toBeUndefined();
        expect(wrapper.get('#password').attributes('minlength')).toBe('8');
        await wrapper.get('#city').setValue('Toronto');
        expect(new FormData(form.element).get('city')).toBe('Toronto');
    });

    it('retains remember choice, recovery/contact/privacy links and labels', async () => {
        const wrapper = page('login', { values: { email: 'reader@example.test', remember: true } });
        expect(wrapper.get('#remember').element.checked).toBe(true);
        expect(new FormData(wrapper.get('[data-auth-form]').element).get('remember')).toBe('on');
        expect(wrapper.get('label[for="email"]').text()).toBe('Email address');
        expect(wrapper.get('a[href="/password/reset"]').text()).toContain('Forgot');
        expect(wrapper.get('a[href="/contact-us"]').text()).toBe('Contact Us');
        expect(wrapper.get('a[href="/privacy.pdf"]').attributes('rel')).toBe('noopener noreferrer');
        await wrapper.get('#remember').setValue(false);
        expect(new FormData(wrapper.get('[data-auth-form]').element).has('remember')).toBe(false);
    });

    it('preserves a distinct verification resend form on unverified login without duplicate IDs', async () => {
        const wrapper = page('login', {
            showResend: true,
            sessionError: 'You need to verify your email address to access this page.',
        });
        const resend = wrapper.get('[data-auth-resend]');
        expect(resend.attributes('action')).toBe('/email/resend');
        expect(resend.findAll('button[type="submit"]')).toHaveLength(1);
        expect(wrapper.findAll('#email')).toHaveLength(1);
        await wrapper.get('#resend-email').setValue('verify@example.test');
        expect(Object.fromEntries(new FormData(resend.element))).toEqual({
            _token: 'test-csrf',
            email: 'verify@example.test',
        });
        expect(wrapper.get('#email').element.value).toBe('reader@example.test');
    });

    it('shows escaped field feedback and session-expiry status without storing credentials', () => {
        const storage = vi.spyOn(Storage.prototype, 'setItem');
        const wrapper = page('login', {
            errors: { email: ['<script>Invalid address</script>'] },
            message: 'Your session has expired. Please log in again.',
        });
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#email').attributes('aria-describedby')).toBe('email-errors');
        expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
        expect(wrapper.text()).toContain('Your session has expired');
        expect(storage).not.toHaveBeenCalled();
    });

    it('keeps the reset token in the transient hidden form and never fills a password from props', () => {
        const storage = vi.spyOn(Storage.prototype, 'setItem');
        const wrapper = page('password-reset', {
            resetToken: 'reset-token-fixture',
            values: { email: 'reset@example.test', password: 'secret' },
        });
        expect(wrapper.get('[name="token"]').attributes('type')).toBe('hidden');
        expect(new FormData(wrapper.get('[data-auth-form]').element).get('token')).toBe(
            'reset-token-fixture',
        );
        expect(wrapper.get('#password').element.value).toBe('');
        expect(wrapper.get('#password_confirmation').element.value).toBe('');
        expect(wrapper.get('#password').attributes('autocomplete')).toBe('new-password');
        expect(wrapper.text()).not.toContain('reset-token-fixture');
        expect(storage).not.toHaveBeenCalled();
    });

    it('allows the first native submit, blocks repeats and recovers after browser Back', async () => {
        const wrapper = page('login', { showResend: true });
        const form = wrapper.get('[data-auth-form]').element;
        const first = new Event('submit', { cancelable: true });
        form.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        const duplicate = new Event('submit', { cancelable: true });
        wrapper.get('[data-auth-resend]').element.dispatchEvent(duplicate);
        expect(duplicate.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.findAll('button[type="submit"]').every((button) => button.element.disabled),
        ).toBe(true);
        expect(wrapper.get('[data-auth-form]').attributes('aria-busy')).toBe('true');
        window.dispatchEvent(new Event('pageshow'));
        await wrapper.vm.$nextTick();
        expect(
            wrapper.findAll('button[type="submit"]').every((button) => !button.element.disabled),
        ).toBe(true);
    });
});
