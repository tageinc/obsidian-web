import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ExternalApiKeys from './ExternalApiKeys.vue';
import WorkspaceFilters from './WorkspaceFilters.vue';
import { requestJson } from '../../shared/api/client.js';

vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));
const record = {
    id: 1,
    name: 'Integration',
    prefix: 'obs_ext_fixture',
    status: 'Active',
    created_at: '2026-09-24T00:00:00Z',
};
const list = {
    data: [record],
    current_page: 1,
    last_page: 1,
    total: 1,
    from: 1,
    to: 1,
    per_page: 20,
};
let wrapper;
const dialog = {
    props: ['title', 'pending'],
    emits: ['close'],
    template:
        '<div role="dialog"><button class="test-close" :disabled="pending" @click="$emit(\'close\')">Close</button><slot /></div>',
};
async function page() {
    wrapper = mount(ExternalApiKeys, {
        attachTo: document.body,
        props: { endpoint: '/developer-workspace/api-keys' },
        global: { stubs: { FormModal: dialog } },
    });
    await flushPromises();
    return wrapper;
}
beforeEach(() => {
    vi.resetAllMocks();
    window.history.replaceState({}, '', '/developer-workspace');
});
afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = '';
});

describe('external API key errors and pending requests', () => {
    it('retries a failed list request without reporting a misleading empty result', async () => {
        requestJson
            .mockRejectedValueOnce(new Error('Network unavailable'))
            .mockResolvedValueOnce(list);
        await page();
        expect(wrapper.text()).toContain('Network unavailable');
        expect(wrapper.text()).not.toContain('No API keys have been created yet.');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Retry')
            .trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Integration');
        expect(wrapper.text()).not.toContain('Network unavailable');
    });

    it('prevents duplicate creation and retains the name on a server validation error', async () => {
        let reject;
        requestJson.mockResolvedValueOnce(list).mockImplementationOnce(
            () =>
                new Promise((_, fail) => {
                    reject = fail;
                }),
        );
        await page();
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');
        await wrapper.get('#api-key-name').setValue('Keep this draft');
        await wrapper.get('form[aria-busy]').trigger('submit');
        await wrapper.get('form[aria-busy]').trigger('submit');
        expect(requestJson).toHaveBeenCalledTimes(2);
        expect(wrapper.get('.test-close').element.disabled).toBe(true);
        reject(
            Object.assign(new Error('Check the name'), { fieldErrors: { name: ['Invalid name'] } }),
        );
        await flushPromises();
        expect(wrapper.get('#api-key-name').element.value).toBe('Keep this draft');
        expect(wrapper.get('#api-key-name-error').text()).toBe('Invalid name');
        expect(wrapper.get('.test-close').element.disabled).toBe(false);
    });

    it('keeps an active key and retryable dialog when revocation fails', async () => {
        requestJson
            .mockResolvedValueOnce(list)
            .mockRejectedValueOnce(new Error('Please try again'));
        await page();
        await wrapper.get('button[aria-label="Revoke Integration"]').trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Revoke key')
            .trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="dialog"]').text()).toContain('Please try again');
        expect(wrapper.get('tbody').text()).toContain('Active');
        await wrapper.get('.test-close').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(
            wrapper.get('button[aria-label="Revoke Integration"]').element,
        );
    });
});

describe('external API key filtering', () => {
    it('restores filters and paging from the URL, preserves Software queries, and resets the page for a new search', async () => {
        window.history.replaceState(
            {},
            '',
            '/developer-workspace?firmware_search=SP1&keys_search=Integration&keys_statuses[]=Active&keys_expiration[]=never&keys_from=2026-09-01&keys_page=2&keys_per_page=10',
        );
        requestJson.mockResolvedValue({
            ...list,
            current_page: 2,
            last_page: 3,
            total: 21,
            from: 11,
            to: 20,
            per_page: 10,
        });
        await page();
        const params = new URL(requestJson.mock.calls[0][0]).searchParams;
        expect(params.get('page')).toBe('2');
        expect(params.get('search')).toBe('Integration');
        expect(params.getAll('statuses[]')).toEqual(['Active']);
        expect(params.getAll('expiration[]')).toEqual(['never']);
        expect(params.get('from')).toBe('2026-09-01');
        expect(wrapper.text()).toContain('Showing 11–20 of 21 keys');
        expect(new URLSearchParams(window.location.search).get('firmware_search')).toBe('SP1');
        await wrapper.get('#api-keys-search').setValue('  new prefix  ');
        requestJson.mockResolvedValueOnce({ ...list, per_page: 10 });
        await wrapper.get('.api-key-search').trigger('submit');
        await flushPromises();
        const next = new URL(requestJson.mock.lastCall[0]).searchParams;
        expect(next.get('page')).toBe('1');
        expect(next.get('search')).toBe('new prefix');
        expect(next.getAll('statuses[]')).toEqual(['Active']);
        expect(new URLSearchParams(window.location.search).get('section')).toBe('api-keys');
        await wrapper.get('[aria-label="Remove Status: Active"]').trigger('click');
        await flushPromises();
        expect(new URL(requestJson.mock.lastCall[0]).searchParams.getAll('statuses[]')).toEqual([]);
        expect(new URL(requestJson.mock.lastCall[0]).searchParams.getAll('expiration[]')).toEqual([
            'never',
        ]);
    });

    it('ignores stale list responses after a newer filter request completes', async () => {
        let stale;
        requestJson
            .mockResolvedValueOnce(list)
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        stale = resolve;
                    }),
            )
            .mockResolvedValueOnce({ ...list, data: [], total: 0, from: null, to: null });
        await page();
        const panel = wrapper.findComponent(WorkspaceFilters);
        panel.vm.$emit('apply', { statuses: ['Expired'], expiration: [], from: '', to: '' });
        const firstSignal = requestJson.mock.lastCall[1].signal;
        panel.vm.$emit('apply', { statuses: ['Revoked'], expiration: [], from: '', to: '' });
        await flushPromises();
        stale(list);
        await flushPromises();
        expect(firstSignal.aborted).toBe(true);
        expect(wrapper.get('tbody').text()).toContain('No API keys found.');
        expect(wrapper.get('tbody').text()).not.toContain('Integration');
        expect(new URLSearchParams(window.location.search).getAll('keys_statuses[]')).toEqual([
            'Revoked',
        ]);
    });

    it('refreshes the filtered page after revocation and keeps the status filter when the row disappears', async () => {
        window.history.replaceState({}, '', '/developer-workspace?keys_statuses[]=Active');
        requestJson
            .mockResolvedValueOnce(list)
            .mockResolvedValueOnce({ data: { ...record, status: 'Revoked' } })
            .mockResolvedValueOnce({ ...list, data: [], total: 0, from: null, to: null });
        await page();
        await wrapper.get('[aria-label="Revoke Integration"]').trigger('click');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Revoke key')
            .trigger('click');
        await flushPromises();
        expect(new URL(requestJson.mock.lastCall[0]).searchParams.getAll('statuses[]')).toEqual([
            'Active',
        ]);
        expect(wrapper.get('tbody').text()).not.toContain('Integration');
        expect(wrapper.text()).toContain('No API keys found.');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(wrapper.get('button[aria-haspopup="dialog"]').element);
    });

    it('shows recoverable validation errors and clears invalid URL filters', async () => {
        window.history.replaceState(
            {},
            '',
            '/developer-workspace?keys_statuses[]=Unknown&keys_per_page=3',
        );
        requestJson
            .mockRejectedValueOnce(
                Object.assign(new Error('Invalid filters'), {
                    fieldErrors: { statuses: ['Choose a valid status.'] },
                }),
            )
            .mockResolvedValueOnce(list);
        await page();
        expect(wrapper.text()).toContain('Choose a valid status.');
        expect(wrapper.text()).not.toContain('No API keys found.');
        await wrapper.get('.release-clear').trigger('click');
        await flushPromises();
        const params = new URL(requestJson.mock.lastCall[0]).searchParams;
        expect(params.getAll('statuses[]')).toEqual([]);
        expect(params.get('per_page')).toBe('20');
        expect(wrapper.get('tbody').text()).toContain('Integration');
    });
});
