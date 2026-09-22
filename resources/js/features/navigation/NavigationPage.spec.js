import { enableAutoUnmount, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { afterEach, expect, it } from 'vitest';
import NavigationPage from './NavigationPage.vue';
import { useSessionStore } from '../../shared/stores/session';
const links = {
    home: '/',
    dashboard: '/dashboard',
    profile: '/profile',
    createDevice: '/create-device',
    login: '/login',
    register: '/register',
    logout: '/logout',
};
const props = { name: 'Obsidian', logo: '/img/logo.png', links, csrfToken: 'transient' };
enableAutoUnmount(afterEach);
afterEach(() => {
    document.body.innerHTML = '';
});
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
    expect(wrapper.find('a[href="/developer-workspace"]').exists()).toBe(false);
    const form = wrapper.get('form');
    expect(form.attributes('method')).toBe('POST');
    expect(form.attributes('action')).toBe('/logout');
    expect(form.get('input[name="_token"]').element.value).toBe('transient');
    await form.trigger('submit');
    expect(useSessionStore(pinia).signedIn).toBe(false);
    // Native submission must retain its connected form after ephemeral state clears.
    expect(wrapper.find('form').exists()).toBe(true);
});
it('shows the canonical Developer Workspace link when the server grants access', () => {
    const wrapper = mount(NavigationPage, {
        props: {
            ...props,
            user: { name: 'Developer' },
            links: { ...links, admin: '/developer-workspace' },
        },
        global: { plugins: [createPinia()] },
    });
    expect(wrapper.get('a[href="/developer-workspace"]').text()).toBe('Developer Workspace');
    wrapper.unmount();
});
it('keeps app links inside a collapsed account dropdown with keyboard navigation and Escape recovery', async () => {
    const wrapper = mount(NavigationPage, {
        props: { ...props, user: { name: 'Synthetic user' } },
        global: { plugins: [createPinia()] },
        attachTo: document.body,
    });
    const toggle = wrapper.get('[aria-controls="account-navigation"]');
    expect(toggle.text()).toBe('Synthetic user');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(wrapper.get('#account-navigation').isVisible()).toBe(false);
    await toggle.trigger('keydown', { key: 'ArrowDown' });
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(wrapper.get('#account-navigation').isVisible()).toBe(true);
    expect(document.activeElement).toBe(wrapper.get('a[href="/dashboard"]').element);
    await wrapper.get('a[href="/dashboard"]').trigger('keydown', { key: 'ArrowDown' });
    expect(document.activeElement).toBe(wrapper.get('a[href="/profile"]').element);
    await wrapper.get('a[href="/profile"]').trigger('keydown', { key: 'Escape' });
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(toggle.element);
});
it('closes the dropdown on outside click, focus departure, mobile collapse, and link selection', async () => {
    const wrapper = mount(NavigationPage, {
        props: { ...props, user: { name: 'Synthetic user' } },
        global: { plugins: [createPinia()] },
        attachTo: document.body,
    });
    const toggle = wrapper.get('[aria-controls="account-navigation"]');
    const mobile = wrapper.get('[aria-controls="primary-navigation"]');
    await mobile.trigger('click');
    await toggle.trigger('click');
    document.body.click();
    await wrapper.vm.$nextTick();
    expect(toggle.attributes('aria-expanded')).toBe('false');
    await toggle.trigger('click');
    await wrapper.get('.account-dropdown').trigger('focusout', { relatedTarget: document.body });
    expect(toggle.attributes('aria-expanded')).toBe('false');
    await toggle.trigger('click');
    await mobile.trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    await mobile.trigger('click');
    await toggle.trigger('click');
    await wrapper.get('a[href="/profile"]').trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(mobile.attributes('aria-expanded')).toBe('false');
});
