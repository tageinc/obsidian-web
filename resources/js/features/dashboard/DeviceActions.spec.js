import { afterEach, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import DeviceActions from './DeviceActions.vue';
const mounted = [];
function page() {
    const wrapper = mount(DeviceActions, {
        attachTo: document.body, global: { stubs: { teleport: true } },
        props: { csrfToken: 'csrf-test-token', device: {
            id: 1, name: '<img src=x>', serial: 'SERIAL-1',
            links: { edit: '/edit-device/1', retire: '/retire-device/1' },
        } },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => { mounted.splice(0).forEach((wrapper) => wrapper.unmount()); document.body.innerHTML = ''; vi.restoreAllMocks(); });
    it('submits Retire directly from the action menu', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        const form = wrapper.get('#device-actions-1 form');
        expect(form.attributes()).toMatchObject({
            action: '/retire-device/1',
            method: 'POST',
        });
        expect(form.get('input[name="_token"]').element.value).toBe('csrf-test-token');
        expect(form.get('button').text()).toBe('Retire');
        expect(wrapper.find('dialog').exists()).toBe(false);
    });
    it('reveals page actions by keyboard and closes on Escape or an outside click', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const edit = wrapper.get('#device-actions-1 a[href="/edit-device/1"]');
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(edit.element);
        await edit.trigger('keydown', { key: 'ArrowDown' });
        const remove = wrapper.get('#device-actions-1 form button');
        expect(document.activeElement).toBe(remove.element);
        await remove.trigger('keydown', { key: 'Escape' });
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
        await trigger.trigger('click');
        document.body.click();
        await wrapper.vm.$nextTick();
        expect(trigger.attributes('aria-expanded')).toBe('false');
    });
    it('focuses the first action on opening and returns to the trigger when tabbing out of the popup', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        await flushPromises();
        const edit = wrapper.get('#device-actions-1 a[href="/edit-device/1"]');
        expect(document.activeElement).toBe(edit.element);
        await edit.trigger('keydown', { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(trigger.element);
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('keydown', { key: 'ArrowUp' });
        await flushPromises();
        const remove = wrapper.get('#device-actions-1 form button');
        expect(document.activeElement).toBe(remove.element);
        await remove.trigger('keydown', { key: 'Tab' });
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
    });

    it('offers Reactivate for an inactive device', async () => {
        const wrapper = mount(DeviceActions, {
            attachTo: document.body,
            global: { stubs: { teleport: true } },
            props: {
                csrfToken: 'csrf-test-token',
                device: {
                    id: 2, name: 'Inactive tracker', serial: 'SERIAL-2',
                    links: { edit: '/edit-device/2', retire: null, reactivate: '/reactivate-device/2' },
                },
            },
        });
        const trigger = wrapper.get('button[aria-controls="device-actions-2"]');
        await trigger.trigger('click');
        await flushPromises();
        const form = wrapper.get('#device-actions-2 form');
        const reactivate = form.get('button');
        expect(reactivate.text()).toBe('Reactivate');
        expect(form.attributes('action')).toBe('/reactivate-device/2');
        expect(wrapper.find('dialog').exists()).toBe(false);
        wrapper.unmount();
    });
