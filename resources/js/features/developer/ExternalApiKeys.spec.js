import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ExternalApiKeys from './ExternalApiKeys.vue';
import { requestJson } from '../../shared/api/client.js';

vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));
const record = {
    id: 1,
    name: 'Integration',
    prefix: 'obs_ext_fixture',
    status: 'Active',
    created_at: '2026-09-24T00:00:00Z',
};
const list = { data: [record], current_page: 1, last_page: 1 };
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
beforeEach(() => vi.resetAllMocks());
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
        await wrapper.get('form').trigger('submit');
        await wrapper.get('form').trigger('submit');
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
