import { afterEach, expect, it, vi } from 'vitest';
import { DOMWrapper, enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import ActivityFeed from './ActivityFeed.vue';
import { requestJson } from '../../shared/api/client';

vi.mock('../../shared/api/client', () => ({ requestJson: vi.fn() }));
enableAutoUnmount(afterEach);
const entry = {
    id: 'activity-one',
    title: 'Device status changed',
    body: 'Tracker 123 changed from online to offline.',
    url: null,
    created_at: '2026-09-26T12:00:00Z',
    read_at: null,
};
function page(data = [entry], unread = data.length, cursor = null) {
    return { data, unread_count: unread, next_cursor: cursor };
}
async function feed(result = page()) {
    requestJson.mockResolvedValue(result);
    const wrapper = mount(ActivityFeed, {
        props: { csrfToken: 'synthetic-csrf' },
        attachTo: document.body,
    });
    await flushPromises();
    await wrapper.get('.activity-trigger').trigger('click');
    await flushPromises();
    return new DOMWrapper(document.body);
}
afterEach(() => {
    vi.resetAllMocks();
    document.body.innerHTML = '';
});

it('shows unread counts, preserves unread on opening, and restores keyboard focus on Escape', async () => {
    const wrapper = await feed(page([entry], 21));
    expect(wrapper.get('.activity-badge').text()).toBe('9+');
    expect(wrapper.get('.activity-feed__count').text()).toBe('21 unread');
    expect(wrapper.get('.activity-feed__item').classes()).toContain('is-unread');
    expect(requestJson.mock.calls.every(([, options]) => !options.method)).toBe(true);
    expect(document.activeElement).toBe(wrapper.get('#app-activity-feed').element);
    await wrapper.get('#app-activity-feed').trigger('keydown', { key: 'Escape' });
    expect(wrapper.get('.activity-trigger').attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(wrapper.get('.activity-trigger').element);
});

it('persists read state and unread counts, preserving unread and focusing an error on failure', async () => {
    const wrapper = await feed();
    requestJson.mockRejectedValueOnce(new Error('unavailable'));
    await wrapper.get('.activity-feed__open').trigger('click');
    await flushPromises();
    expect(wrapper.get('.activity-feed__item').classes()).toContain('is-unread');
    expect(wrapper.get('.activity-trigger').attributes('aria-expanded')).toBe('true');
    expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
    requestJson.mockResolvedValueOnce({
        data: { ...entry, read_at: '2026-09-26T13:00:00Z' },
        unread_count: 0,
    });
    await wrapper.get('.activity-feed__open').trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/app-activity/activity-one/read',
        expect.objectContaining({ method: 'PATCH', csrfToken: 'synthetic-csrf' }),
    );
    expect(wrapper.get('.activity-feed__item').classes()).not.toContain('is-unread');
    expect(wrapper.find('.activity-badge').exists()).toBe(false);
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
});

it('appends cursor pages without duplicate rows and offers retry after paging failure', async () => {
    const wrapper = await feed(page([entry], 2, 'opaque cursor+/='));
    requestJson.mockRejectedValueOnce(new Error('unavailable'));
    wrapper.get('.activity-feed__more').element.focus();
    await wrapper.get('.activity-feed__more').trigger('click');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toContain('More activity could not be loaded.');
    expect(wrapper.findAll('.activity-feed__item')).toHaveLength(1);
    expect(document.activeElement).toBe(wrapper.get('[role="alert"] button').element);
    requestJson.mockResolvedValueOnce(
        page([entry, { ...entry, id: 'second', title: 'Device recovered' }], 2),
    );
    await wrapper.get('[role="alert"] button').trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/app-activity?cursor=opaque%20cursor%2B%2F%3D',
        expect.any(Object),
    );
    expect(wrapper.findAll('.activity-feed__item')).toHaveLength(2);
    expect(wrapper.find('.activity-feed__more').exists()).toBe(false);
    expect(document.activeElement).toBe(wrapper.findAll('.activity-feed__open')[1].element);
});

it('dismisses one entry and clears all only after successful persistence', async () => {
    const wrapper = await feed(page([entry, { ...entry, id: 'second' }], 2));
    requestJson.mockResolvedValueOnce({ unread_count: 1 });
    await wrapper.findAll('.activity-feed__dismiss')[0].trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/app-activity/activity-one',
        expect.objectContaining({ method: 'DELETE' }),
    );
    expect(wrapper.findAll('.activity-feed__item')).toHaveLength(1);
    expect(document.activeElement).toBe(wrapper.get('.activity-feed__open').element);
    requestJson.mockRejectedValueOnce(new Error('unavailable'));
    await wrapper.get('.activity-feed__clear').trigger('click');
    await flushPromises();
    expect(wrapper.findAll('.activity-feed__item')).toHaveLength(1);
    expect(wrapper.get('[role="alert"]').text()).toContain('Activity could not be cleared.');
    requestJson.mockResolvedValueOnce({ unread_count: 0 });
    await wrapper.get('.activity-feed__clear').trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenLastCalledWith(
        '/api/app-activity',
        expect.objectContaining({ method: 'DELETE' }),
    );
    expect(wrapper.text()).toContain('You’re all caught up.');
    expect(wrapper.find('.activity-badge').exists()).toBe(false);
    expect(document.activeElement).toBe(wrapper.get('#app-activity-feed').element);
});

it('refreshes on reopening and leaves read entries unchanged', async () => {
    const wrapper = await feed(page([{ ...entry, read_at: '2026-09-26T13:00:00Z' }], 0));
    const calls = requestJson.mock.calls.length;
    await wrapper.get('.activity-feed__open').trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenCalledTimes(calls);
    document.body.click();
    await flushPromises();
    expect(wrapper.get('.activity-trigger').attributes('aria-expanded')).toBe('false');
    requestJson.mockResolvedValueOnce(page());
    await wrapper.get('.activity-trigger').trigger('click');
    await flushPromises();
    expect(requestJson).toHaveBeenCalledTimes(calls + 1);
    expect(wrapper.get('.activity-feed__item').classes()).toContain('is-unread');
});
