import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ProfilePage from './ProfilePage.vue';

const mounted = [];
function page(overrides = {}) {
    const wrapper = mount(ProfilePage, {
        attachTo: document.body,
        props: {
            csrfToken: 'test-csrf',
            action: '/profile',
            links: { dashboard: '/dashboard' },
            values: {
                name: 'Demo user',
                email: 'demo@example.test',
                city: 'Vancouver',
                zip_code: 'V6B 1A1',
            },
            ...overrides,
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => mounted.splice(0).forEach((wrapper) => wrapper.unmount()));

describe('profile workflow', () => {
    it('submits all editable fields in one native CSRF protected PUT form and retains international postal values', async () => {
        const wrapper = page();
        const form = wrapper.get('form');
        expect(form.attributes('action')).toBe('/profile');
        expect(form.attributes('method')).toBe('POST');
        expect(wrapper.findAll('button[type="submit"]')).toHaveLength(1);
        await wrapper.get('#city').setValue('Toronto');
        const submitted = Object.fromEntries(new FormData(form.element));
        expect(submitted).toMatchObject({
            _token: 'test-csrf',
            _method: 'PUT',
            name: 'Demo user',
            city: 'Toronto',
            zip_code: 'V6B 1A1',
            password: '',
            password_confirmation: '',
        });
        expect(Object.keys(submitted)).toEqual(
            expect.arrayContaining(['address_1', 'address_2', 'state', 'country', 'phone_number']),
        );
        expect(wrapper.get('#address_1').attributes('required')).toBeUndefined();
        expect(wrapper.get('nav[aria-label="Breadcrumb"]').text()).toContain('Dashboard');
        expect(wrapper.get('[aria-current="page"]').text()).toBe('Profile');
        expect(wrapper.get('nav a').attributes('href')).toBe('/dashboard');
        expect(wrapper.find('nav button').exists()).toBe(false);
    });

    it('never preloads passwords, tokens, or identity from submitted value objects and keeps state local', async () => {
        const values = Object.freeze({
            name: 'Original',
            email: 'demo@example.test',
            password: 'do-not-render',
            password_confirmation: 'do-not-render',
            api_token: 'do-not-render',
            id: 42,
        });
        const wrapper = page({ values });
        expect(wrapper.html()).not.toContain('do-not-render');
        expect(wrapper.find('[name="id"]').exists()).toBe(false);
        await wrapper.get('#name').setValue('Changed');
        expect(values.name).toBe('Original');
        expect(wrapper.get('#password').element.value).toBe('');
        await wrapper.get('#password').setValue('new-password');
        expect(wrapper.get('#password_confirmation').attributes('required')).toBeDefined();
    });

    it('shows escaped errors with labels, field associations, and a focused summary', () => {
        const wrapper = page({
            errors: { email: ['<script>invalid address</script>'] },
            success: 'Saved',
        });
        expect(wrapper.find('script').exists()).toBe(false);
        const input = wrapper.get('#email');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe('email-errors');
        expect(wrapper.get('label[for="email"]').text()).toBe('Email address');
        expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
        expect(wrapper.get('[role="alert"] a').attributes('href')).toBe('#email');
        expect(wrapper.get('[role="status"]').text()).toBe('Saved');
    });

    it('allows the first native submission, blocks duplicate submissions, and recovers after browser Back', async () => {
        const wrapper = page();
        const form = wrapper.get('form').element;
        const first = new Event('submit', { cancelable: true });
        form.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        const duplicate = new Event('submit', { cancelable: true });
        form.dispatchEvent(duplicate);
        expect(duplicate.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
        expect(wrapper.get('form').attributes('aria-busy')).toBe('true');
        window.dispatchEvent(new Event('pageshow'));
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });
});
