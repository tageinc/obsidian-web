import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DeviceForm from './DeviceForm.vue';

const mounted = [];
function form(overrides = {}, slots = {}) {
    const wrapper = mount(DeviceForm, {
        attachTo: document.body,
        props: {
            registering: true,
            csrfToken: 'test-csrf',
            action: '/dataInsert',
            hardwareOptions: [{ id: 1, name: 'Solar tracker' }],
            values: {
                hardware_id: 1,
                alias: 'Roof tracker',
                serial_no: 'TRACK-1',
                sku: 'ST',
                order_no: 'ORD-1',
                address_1: '1 Example Street',
                city: 'Toronto',
                state: 'ON',
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
afterEach(() => mounted.splice(0).forEach((wrapper) => wrapper.unmount()));

describe('reusable device form', () => {
    it('adds the modal marker to the existing native registration contract only when requested', () => {
        const standalone = form();
        expect(standalone.find('[name="_registration_modal"]').exists()).toBe(false);
        standalone.unmount();
        mounted.pop();
        const modal = form({ registrationModal: true });
        expect(modal.get('form').attributes()).toMatchObject({
            method: 'POST',
            action: '/dataInsert',
        });
        expect(Object.fromEntries(new FormData(modal.get('form').element))).toMatchObject({
            _token: 'test-csrf',
            _registration_modal: '1',
            hardware_id: '1',
            serial_no: 'TRACK-1',
            alias: 'Roof tracker',
            latitude: '0',
            longitude: '0',
        });
        expect(modal.find('[name="_method"]').exists()).toBe(false);
        expect(modal.findAll('button[type="submit"]')).toHaveLength(1);
    });

    it('retains validation errors and entered values when mounted in a reopened modal', () => {
        const wrapper = form({
            registrationModal: true,
            errors: { serial_no: ['This serial number is already registered.'] },
        });
        expect(wrapper.get('#serial_no').element.value).toBe('TRACK-1');
        expect(wrapper.get('#serial_no').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#serial_no-errors').text()).toContain(
            'This serial number is already registered.',
        );
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.get('#hardware_id').element.value).toBe('1');
        expect(wrapper.get('#latitude').attributes('required')).toBeDefined();
    });

    it('reports pending synchronously and supplies it to the actions slot until native navigation resumes', async () => {
        const wrapper = form(
            { registrationModal: true },
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
});
