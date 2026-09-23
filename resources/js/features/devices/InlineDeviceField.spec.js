import { mount, flushPromises } from '@vue/test-utils';
import { expect, it, vi, beforeEach } from 'vitest';
import InlineDeviceField from './InlineDeviceField.vue';
import { requestJson } from '../../shared/api/client.js';
vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));
beforeEach(() => vi.clearAllMocks());
const props = {
    label: 'SKU',
    field: 'sku',
    value: 'SP1',
    endpoint: '/devices/1',
    csrfToken: 'token',
};
it('cancels changes without saving and saves only the edited field', async () => {
    const wrapper = mount(InlineDeviceField, { props });
    await wrapper.get('button').trigger('click');
    await wrapper.get('input').setValue('SP2');
    await wrapper.get('[aria-label="Cancel SKU"]').trigger('click');
    expect(requestJson).not.toHaveBeenCalled();
    expect(wrapper.text()).toBe('SP1');
    await wrapper.get('button').trigger('click');
    await wrapper.get('input').setValue('SP3');
    requestJson.mockResolvedValue({ values: { sku: 'SP3' } });
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(requestJson).toHaveBeenCalledWith('/devices/1', {
        method: 'PATCH',
        csrfToken: 'token',
        data: { sku: 'SP3' },
    });
    expect(wrapper.emitted('saved')[0]).toEqual([{ sku: 'SP3' }]);
    wrapper.unmount();
});
it('retains the draft and displays field validation failures', async () => {
    requestJson.mockRejectedValue({ fieldErrors: { sku: ['Invalid SKU'] } });
    const wrapper = mount(InlineDeviceField, { props });
    await wrapper.get('button').trigger('click');
    await wrapper.get('input').setValue('bad');
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toBe('Invalid SKU');
    expect(wrapper.get('input').element.value).toBe('bad');
    expect(wrapper.emitted('saved')).toBeUndefined();
    wrapper.unmount();
});
