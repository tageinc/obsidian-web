import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import DeviceFormPage from './DeviceFormPage.vue';

const mounted = [];
function page(overrides = {}) {
    const wrapper = mount(DeviceFormPage, {
        attachTo: document.body,
        props: {
            registering: true,
            csrfToken: 'test-csrf',
            action: '/dataInsert',
            links: { dashboard: '/dashboard' },
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
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => mounted.splice(0).forEach((wrapper) => wrapper.unmount()));

describe('device forms', () => {
    it('registers all fields through the existing native POST contract without notification opt-ins', async () => {
        const wrapper = page();
        expect(wrapper.get('form').attributes()).toMatchObject({
            action: '/dataInsert',
            method: 'POST',
        });
        expect(wrapper.findAll('button[type="submit"]')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toBe('Register device');
        expect(wrapper.get('a[href="/dashboard"]').text()).toBe('Back to dashboard');
        expect(wrapper.find('[name="_registration_modal"]').exists()).toBe(false);
        expect(wrapper.find('[name="_method"]').exists()).toBe(false);
        expect(wrapper.find('[name="status_notification"]').exists()).toBe(false);
        expect(wrapper.find('[name="sms_notification"]').exists()).toBe(false);
        await wrapper.get('#alias').setValue('Updated name');
        expect(Object.fromEntries(new FormData(wrapper.get('form').element))).toMatchObject({
            _token: 'test-csrf',
            hardware_id: '1',
            serial_no: 'TRACK-1',
            alias: 'Updated name',
            zip_code: 'M5V 1A1',
            latitude: '0',
            longitude: '0',
        });
        expect(wrapper.get('#address_1').attributes('required')).toBeDefined();
        expect(wrapper.get('#address_2').attributes('required')).toBeUndefined();
        expect(wrapper.get('#latitude').attributes()).toMatchObject({
            min: '-90',
            max: '90',
            step: 'any',
            required: '',
        });
        expect(wrapper.get('#longitude').attributes()).toMatchObject({
            min: '-180',
            max: '180',
            step: 'any',
            required: '',
        });
        expect(wrapper.get('a[target="_blank"]').attributes('rel')).toBe('noopener noreferrer');
    });

    it('edits mutable fields only and displays escaped fixed identity without submitting it', () => {
        const wrapper = page({
            registering: false,
            action: '/edit-device/1',
            fixedIdentity: {
                hardwareName: 'Solar tracker',
                serialNo: '<b>TRACK-1</b>',
                sku: 'ST',
                orderNo: 'ORD-1',
            },
        });
        expect(wrapper.get('form').attributes('action')).toBe('/edit-device/1');
        expect(wrapper.get('[name="_method"]').element.value).toBe('PUT');
        expect(wrapper.get('dl').text()).toContain('<b>TRACK-1</b>');
        expect(wrapper.find('dl b').exists()).toBe(false);
        for (const name of [
            'hardware_id',
            'serial_no',
            'sku',
            'order_no',
            'user_id',
            'status_notification',
        ]) {
            expect(wrapper.find(`[name="${name}"]`).exists()).toBe(false);
        }
        expect(wrapper.get('button[type="submit"]').text()).toBe('Update device');
    });

    it('allows both edit coordinates blank and requires the matching coordinate including when the other is zero', async () => {
        const values = Object.freeze({ alias: 'Original', latitude: '', longitude: '' });
        const wrapper = page({ registering: false, values });
        expect(wrapper.get('#latitude').attributes('required')).toBeUndefined();
        expect(wrapper.get('#longitude').attributes('required')).toBeUndefined();
        await wrapper.get('#latitude').setValue('0');
        expect(wrapper.get('#longitude').attributes('required')).toBeDefined();
        await wrapper.get('#alias').setValue('Changed');
        expect(values.alias).toBe('Original');
        expect(values.latitude).toBe('');
    });

    it('connects server validation to hardware and address fields while retaining supplied input', () => {
        const wrapper = page({
            errors: {
                hardware_id: ['Choose valid hardware.'],
                zip_code: ['Postal code is required.'],
            },
            sessionError: 'Could not save.',
        });
        expect(wrapper.get('#hardware_id').attributes('aria-describedby')).toBe(
            'hardware_id-errors',
        );
        expect(wrapper.get('#zip_code').attributes('aria-describedby')).toBe('zip_code-errors');
        expect(wrapper.get('#zip_code').element.value).toBe('M5V 1A1');
        expect(wrapper.get('#hardware_id').attributes('aria-invalid')).toBe('true');
        expect(wrapper.findAll('[role="alert"]')).toHaveLength(2);
    });

    it('prevents a second submission until navigation and resets pending on pageshow', async () => {
        const wrapper = page();
        const form = wrapper.get('form').element;
        const first = new Event('submit', { cancelable: true });
        form.dispatchEvent(first);
        expect(first.defaultPrevented).toBe(false);
        const second = new Event('submit', { cancelable: true });
        form.dispatchEvent(second);
        expect(second.defaultPrevented).toBe(true);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(true);
        window.dispatchEvent(new Event('pageshow'));
        await wrapper.vm.$nextTick();
        expect(wrapper.get('button[type="submit"]').element.disabled).toBe(false);
    });
});
