import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { expect, it } from 'vitest';
import NavigationPage from './NavigationPage.vue';
import { useSessionStore } from '../../shared/stores/session';
const links = {
    home: '/',
    dashboard: '/dashboard',
    profile: '/profile',
    registerDevice: '/device-register',
    login: '/login',
    register: '/register',
    logout: '/logout',
};
const props = { name: 'Obsidian', logo: '/img/logo.png', links, csrfToken: 'transient' };
it('toggles accessible mobile navigation without requiring Bootstrap or jQuery globals', async () => {
    const wrapper = mount(NavigationPage, { props, global: { plugins: [createPinia()] } });
    const toggle = wrapper.get('[aria-controls="primary-navigation"]');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    await toggle.trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(wrapper.find('a[href="/login"]').exists()).toBe(true);
});
it('uses native POST logout and clears only ephemeral display state', async () => {
    const pinia = createPinia();
    const wrapper = mount(NavigationPage, {
        props: { ...props, user: { name: 'Synthetic user' } },
        global: { plugins: [pinia] },
    });
    expect(wrapper.find('a[href="/admin-control-center"]').exists()).toBe(false);
    const form = wrapper.get('form');
    expect(form.attributes('method')).toBe('POST');
    await form.trigger('submit');
    expect(useSessionStore(pinia).signedIn).toBe(false);
    // Native submission must retain its connected form after ephemeral state clears.
    expect(wrapper.find('form').exists()).toBe(true);
});
