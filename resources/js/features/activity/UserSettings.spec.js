import { afterEach, expect, it, vi } from 'vitest';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import UserSettings from './UserSettings.vue';
import { requestJson } from '../../shared/api/client';

vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
enableAutoUnmount(afterEach);
const settings = (receive = true, theme = 'adaptive') => ({
    data: { receive_app_activity_emails: receive, theme_mode: theme },
});
function modal() {
    return mount(UserSettings, {
        props: { csrfToken: 'synthetic-csrf' },
        attachTo: document.body,
        global: {
            stubs: {
                FormModal: {
                    props: ['title', 'pending'],
                    template: '<div><h2>{{ title }}</h2><slot /></div>',
                },
            },
        },
    });
}
afterEach(() => {
    vi.resetAllMocks();
    document.body.innerHTML = '';
    document.documentElement.removeAttribute('data-theme-mode');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.removeAttribute('data-bs-theme');
    document.documentElement.style.colorScheme = '';
});

it('loads saved preference, saves the unchecked value, and keeps settings open with confirmation', async () => {
    requestJson.mockResolvedValueOnce(settings()).mockResolvedValueOnce(settings(false));
    const wrapper = modal();
    expect(wrapper.get('input').element.checked).toBe(true);
    expect(wrapper.get('input').element.disabled).toBe(true);
    await flushPromises();
    expect(wrapper.get('label[for="receive-app-activity-emails"]').text()).toBe(
        'Receive app activity emails',
    );
    expect(wrapper.get('input').element.disabled).toBe(false);
    await wrapper.get('input').setValue(false);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/user-settings',
        expect.objectContaining({
            method: 'PATCH',
            data: { receive_app_activity_emails: false, theme_mode: 'adaptive' },
            csrfToken: 'synthetic-csrf',
        }),
    );
    expect(wrapper.get('[role="status"]').text()).toBe('Settings saved.');
    expect(wrapper.emitted('close')).toBeUndefined();
});

it('does not allow overwriting a preference that failed to load, and retries loading it', async () => {
    requestJson
        .mockRejectedValueOnce(new Error('unavailable'))
        .mockResolvedValueOnce(settings(false));
    const wrapper = modal();
    await flushPromises();
    expect(wrapper.get('input').element.disabled).toBe(true);
    expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
    expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
    await wrapper.get('[role="alert"] button').trigger('click');
    await flushPromises();
    expect(wrapper.get('input').element.checked).toBe(false);
    expect(wrapper.get('input').element.disabled).toBe(false);
});

it('retains the draft on save failure and cancel closes without saving', async () => {
    requestJson
        .mockResolvedValueOnce(settings())
        .mockRejectedValueOnce(new Error('Settings could not be saved.'));
    const wrapper = modal();
    await flushPromises();
    await wrapper.get('input').setValue(false);
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.get('input').element.checked).toBe(false);
    expect(wrapper.get('[role="alert"]').text()).toBe('Settings could not be saved.');
    const calls = requestJson.mock.calls.length;
    await wrapper.get('.settings-actions button[type="button"]').trigger('click');
    expect(wrapper.emitted('close')).toHaveLength(1);
    expect(requestJson).toHaveBeenCalledTimes(calls);
});

it('loads the saved theme and only applies the server-confirmed mode after saving', async () => {
    document.documentElement.dataset.themeMode = 'light';
    document.documentElement.dataset.theme = 'light';
    requestJson
        .mockResolvedValueOnce(settings(true, 'light'))
        .mockResolvedValueOnce(settings(true, 'dark'));
    const wrapper = modal();
    await flushPromises();
    expect(wrapper.get('label[for="theme-mode"]').text()).toBe('Theme');
    expect(wrapper.findAll('option').map((option) => option.text())).toEqual([
        'Adaptive',
        'Light',
        'Dark',
    ]);
    expect(wrapper.get('select').element.value).toBe('light');
    await wrapper.get('select').setValue('dark');
    expect(document.documentElement.dataset.theme).toBe('light');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/user-settings',
        expect.objectContaining({
            data: { receive_app_activity_emails: true, theme_mode: 'dark' },
        }),
    );
    expect(document.documentElement.dataset.themeMode).toBe('dark');
    expect(document.documentElement.dataset.theme).toBe('dark');
});

it('keeps a failed or cancelled theme draft from changing the active page', async () => {
    document.documentElement.dataset.themeMode = 'light';
    document.documentElement.dataset.theme = 'light';
    requestJson
        .mockResolvedValueOnce(settings(true, 'light'))
        .mockRejectedValueOnce(new Error('Settings could not be saved.'));
    const wrapper = modal();
    await flushPromises();
    await wrapper.get('select').setValue('dark');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.get('select').element.value).toBe('dark');
    expect(document.documentElement.dataset.themeMode).toBe('light');
    expect(document.documentElement.dataset.theme).toBe('light');
    await wrapper.get('.settings-actions button[type="button"]').trigger('click');
    expect(wrapper.emitted('close')).toHaveLength(1);
    expect(document.documentElement.dataset.theme).toBe('light');
});
