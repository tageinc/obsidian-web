import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import DeviceForm from './DeviceForm.vue';
import { requestJson } from '../../shared/api/client.js';

vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));

const mounted = [];
function form(overrides = {}, slots = {}) {
    const wrapper = mount(DeviceForm, {
        attachTo: document.body,
        props: {
            creating: true,
            csrfToken: 'test-csrf',
            action: '/create-device',
            values: {
                name: 'Roof tracker',
                serial_no: 'TRACK-1',
                sku: 'ST',
                order_no: 'ORD-1',
                address_1: '1 Example Street',
                city: 'Toronto',
                address_state: 'ON',
                country: 'CA',
                zip_code: 'M5V 1A1',
                latitude: 0,
                longitude: 0,
            },
            ...overrides,
        },
        slots,
    });
    mounted.push(wrapper);
    return wrapper;
}
beforeEach(() => vi.resetAllMocks());
afterEach(() => mounted.splice(0).forEach((wrapper) => wrapper.unmount()));

describe('reusable device form', () => {
    it('adds the modal marker to the existing native creation contract only when requested', () => {
        const standalone = form();
        expect(standalone.find('[name="_creation_modal"]').exists()).toBe(false);
        standalone.unmount();
        mounted.pop();
        const modal = form({ creationModal: true });
        expect(modal.get('form').attributes()).toMatchObject({
            method: 'POST',
            action: '/create-device',
        });
        expect(Object.fromEntries(new FormData(modal.get('form').element))).toMatchObject({
            _token: 'test-csrf',
            _creation_modal: '1',
            serial_no: 'TRACK-1',
            name: 'Roof tracker',
            latitude: '0',
            longitude: '0',
        });
        expect(modal.find('[name="_method"]').exists()).toBe(false);
        expect(modal.get('#serial_no').element.readOnly).toBe(false);
        expect(modal.findAll('button[type="submit"]')).toHaveLength(1);
    });

    it('retains validation errors and entered values when mounted in a reopened modal', () => {
        const wrapper = form({
            creationModal: true,
            errors: { serial_no: ['This serial number is already registered.'] },
        });
        expect(wrapper.get('#serial_no').element.value).toBe('TRACK-1');
        expect(wrapper.get('#serial_no').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#serial_no-errors').text()).toContain(
            'This serial number is already registered.',
        );
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('#hardware_id').exists()).toBe(false);
        expect(wrapper.get('#latitude').attributes('required')).toBeDefined();
    });

    it('reports pending synchronously and supplies it to the actions slot until native navigation resumes', async () => {
        const wrapper = form(
            { creationModal: true },
            {
                actions:
                    '<template #actions="{ pending }"><button type="button" :disabled="pending">Cancel</button></template>',
            },
        );
        expect(wrapper.emitted('pending-change')).toEqual([[false]]);
        const first = new Event('submit', { cancelable: true });
        wrapper.get('form').element.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        expect(wrapper.emitted('pending-change')).toEqual([[false], [true]]);
        const second = new Event('submit', { cancelable: true });
        wrapper.get('form').element.dispatchEvent(second);
        expect(second.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="button"]').element.disabled).toBe(true);
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
        expect(wrapper.findAll('button[type="submit"]')).toHaveLength(1);

        window.dispatchEvent(new Event('pageshow'));
        expect(wrapper.emitted('pending-change')).toEqual([[false], [true], [false]]);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="button"]').element.disabled).toBe(false);
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });

    it('saves modal edits through the existing API without navigation or duplicate requests', async () => {
        let complete;
        requestJson.mockImplementation(
            () =>
                new Promise((resolve) => {
                    complete = resolve;
                }),
        );
        const wrapper = form({ creating: false, asyncSubmit: true, action: '/update-device/1' });
        await wrapper.get('#name').setValue('Updated tracker');
        expect(wrapper.get('#serial_no').element.readOnly).toBe(true);
        await wrapper.get('#sku').setValue('SP2');
        await wrapper.get('#order_no').setValue('ORD-2');
        const first = new Event('submit', { cancelable: true });
        wrapper.get('form').element.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(true);
        wrapper.get('form').element.dispatchEvent(new Event('submit', { cancelable: true }));
        await wrapper.vm.$nextTick();
        expect(requestJson).toHaveBeenCalledTimes(1);
        expect(requestJson).toHaveBeenCalledWith('/update-device/1', {
            method: 'PUT',
            csrfToken: 'test-csrf',
            headers: { 'X-Obsidian-Modal': '1' },
            data: {
                serial_no: 'TRACK-1',
                sku: 'SP2',
                order_no: 'ORD-2',
                name: 'Updated tracker',
                address_1: '1 Example Street',
                address_2: '',
                city: 'Toronto',
                address_state: 'ON',
                country: 'CA',
                zip_code: 'M5V 1A1',
                latitude: 0,
                longitude: 0,
            },
        });
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
        expect(wrapper.emitted('saved')).toBeUndefined();
        const response = { success: true, device: { id: 1, name: 'Updated tracker' } };
        complete(response);
        await flushPromises();
        expect(wrapper.emitted('saved')).toEqual([[response]]);
        expect(wrapper.emitted('pending-change')).toEqual([[false], [true], [false]]);
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });

    it('keeps entered values and focuses server validation errors so the user can correct and retry', async () => {
        requestJson.mockRejectedValueOnce(
            Object.assign(new Error('Validation failed.'), {
                status: 422,
                fieldErrors: { name: ['This name is too long.'] },
            }),
        );
        const wrapper = form({ creating: false, asyncSubmit: true, action: '/update-device/1' });
        await wrapper.get('#name').setValue('Changed name');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('#name').element.value).toBe('Changed name');
        expect(wrapper.get('#name').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#name-errors').text()).toContain('This name is too long.');
        expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
        expect(wrapper.emitted('saved')).toBeUndefined();
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);

        requestJson.mockResolvedValueOnce({ success: true });
        await wrapper.get('#name').setValue('Corrected name');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(requestJson.mock.lastCall[1].data.name).toBe('Corrected name');
        expect(wrapper.emitted('saved')).toEqual([[{ success: true }]]);
    });

    it.each([
        'Your session has expired. Refresh this page and try again.',
        'The request could not be completed. Please try again.',
    ])('shows request failures without losing changes: %s', async (message) => {
        requestJson.mockRejectedValueOnce(new Error(message));
        const wrapper = form({ creating: false, asyncSubmit: true, action: '/update-device/1' });
        await wrapper.get('#city').setValue('Los Angeles');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe(message);
        expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element);
        expect(wrapper.get('#city').element.value).toBe('Los Angeles');
        expect(wrapper.emitted('saved')).toBeUndefined();
        expect(wrapper.emitted('pending-change')).toEqual([[false], [true], [false]]);
    });
});
