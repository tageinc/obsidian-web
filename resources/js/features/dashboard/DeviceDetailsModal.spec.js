import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import DeviceDetailsModal from './DeviceDetailsModal.vue';
import { requestJson } from '../../shared/api/client.js';

vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));
const mounted = [];
const info = { page: 'view-device', props: { device: { name: 'Tracker' } } };
const edit = { page: 'edit-device', props: { values: { name: 'Tracker' } } };
function modal(mode = 'view') {
    const wrapper = mount(DeviceDetailsModal, {
        attachTo: document.body,
        props: {
            mode,
            device: {
                id: 1,
                name: 'Tracker',
                links: { view: '/devices/1', edit: '/edit-device/1' },
            },
        },
        global: {
            stubs: {
                FormModal: {
                    name: 'FormModal',
                    props: ['title', 'pending', 'wide'],
                    template: '<div><h2>{{ title }}</h2><slot :close="() => {}" /></div>',
                },
                DeviceForm: {
                    name: 'DeviceForm',
                    props: { values: Object, asyncSubmit: Boolean },
                    emits: ['saved', 'pending-change'],
                    template: '<div>Device form</div>',
                },
                ViewDevicePage: {
                    name: 'ViewDevicePage',
                    props: { device: Object, embedded: Boolean },
                    emits: ['edit'],
                    template: '<div>Device details</div>',
                },
            },
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
    vi.resetAllMocks();
});

describe('device view and edit modal loading', () => {
    it('loads the authorized view DTO and switches to the existing edit form without stale details', async () => {
        requestJson.mockResolvedValueOnce(info).mockResolvedValueOnce(edit);
        const wrapper = modal();
        expect(wrapper.get('[role="status"]').text()).toBe('Loading device…');
        await flushPromises();
        expect(requestJson).toHaveBeenCalledWith(
            '/devices/1',
            expect.objectContaining({
                headers: { 'X-Obsidian-Modal': '1' },
            }),
        );
        const view = wrapper.getComponent({ name: 'ViewDevicePage' });
        expect(view.props('embedded')).toBe(true);
        view.vm.$emit('edit');
        expect(wrapper.emitted('edit')).toHaveLength(1);
        await wrapper.setProps({ mode: 'edit' });
        await flushPromises();
        expect(wrapper.findComponent({ name: 'ViewDevicePage' }).exists()).toBe(false);
        const form = wrapper.getComponent({ name: 'DeviceForm' });
        expect(form.props()).toMatchObject({ asyncSubmit: true, values: { name: 'Tracker' } });
        form.vm.$emit('pending-change', true);
        await flushPromises();
        expect(wrapper.getComponent({ name: 'FormModal' }).props('pending')).toBe(true);
        form.vm.$emit('saved');
        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    it('presents a focused load error and retries without leaving the modal', async () => {
        requestJson
            .mockRejectedValueOnce(new Error('Device temporarily unavailable.'))
            .mockResolvedValueOnce(info);
        const wrapper = modal();
        await flushPromises();
        const alert = wrapper.get('[role="alert"]');
        expect(alert.text()).toContain('Device temporarily unavailable.');
        expect(document.activeElement).toBe(alert.element);
        await alert.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'ViewDevicePage' }).exists()).toBe(true);
    });

    it('aborts closing or superseded requests and ignores their late responses', async () => {
        let resolveOld;
        requestJson
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveOld = resolve;
                    }),
            )
            .mockResolvedValueOnce(edit);
        const wrapper = modal();
        const originalSignal = requestJson.mock.calls[0][1].signal;
        await wrapper.setProps({ mode: 'edit' });
        await flushPromises();
        expect(originalSignal.aborted).toBe(true);
        resolveOld(info);
        await flushPromises();
        expect(wrapper.getComponent({ name: 'DeviceForm' }).props('values')).toEqual(
            edit.props.values,
        );
        const currentSignal = requestJson.mock.calls[1][1].signal;
        wrapper.unmount();
        mounted.pop();
        expect(currentSignal.aborted).toBe(true);
    });

    it('rejects a response for a different page instead of showing unrelated content', async () => {
        requestJson.mockResolvedValue({ page: 'dashboard', props: {} });
        const wrapper = modal();
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('This device could not be loaded.');
        expect(wrapper.findComponent({ name: 'ViewDevicePage' }).exists()).toBe(false);
    });
});
