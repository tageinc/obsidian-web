import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import DashboardPage from './DashboardPage.vue';

vi.mock('./DeviceMap.vue', () => ({
    default: {
        name: 'DeviceMap',
        props: ['endpoints', 'page', 'perPage', 'showAll'],
        template: '<div data-testid="map" />',
    },
}));
const mounted = [];
function page(overrides = {}) {
    const wrapper = mount(DashboardPage, {
        attachTo: document.body,
        global: { stubs: { teleport: true } },
        props: {
            devices: [
                {
                    id: 1,
                    hardwareName: 'Solar tracker',
                    alias: '<img src=x onerror=alert(1)>',
                    state: 'online',
                    lastUpdated: '1 minute ago',
                    links: {
                        view: '/device-info/1',
                        edit: '/edit-device/1',
                        remove: '/delete-device/1',
                    },
                },
            ],
            pagination: {
                currentPage: 2,
                perPage: 20,
                lastPage: 3,
                total: 43,
                from: 21,
                to: 40,
                sizes: [10, 20, 30],
                links: [
                    { url: '/dashboard?show=20&page=1', label: 'Previous', active: false },
                    { url: '/dashboard?show=20&page=2', label: '2', active: true },
                    { url: null, label: '…', active: false },
                ],
            },
            mapEndpoints: { all: '/all-devices', paginated: '/paginated-devices' },
            links: { dashboard: '/dashboard', profile: '/profile', register: '/device-register' },
            ...overrides,
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('dashboard workflow', () => {
    it('shows escaped devices, explicit document actions, query pagination, and all-map checkbox', async () => {
        const wrapper = page();
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('th[scope="row"]').text()).toContain('<img');
        expect(wrapper.get('a[aria-current="page"]').attributes('href')).toBe(
            '/dashboard?show=20&page=2',
        );
        expect(wrapper.get('a[data-document-action]').attributes('href')).toBe('/delete-device/1');
        await wrapper.get('#show-all-devices').setValue(true);
        const map = wrapper.getComponent({ name: 'DeviceMap' });
        expect(map.props()).toMatchObject({ showAll: true, page: 2, perPage: 20 });
    });

    it('uses a native GET for page-size changes and displays empty/flash states', async () => {
        const wrapper = page({
            devices: [],
            pagination: {
                currentPage: 1,
                perPage: 20,
                lastPage: 1,
                total: 0,
                sizes: [10, 20, 30],
                links: [],
            },
            success: 'Device removed',
            sessionError: 'Device unavailable',
        });
        expect(wrapper.text()).toContain('No devices registered yet.');
        expect(wrapper.get('[role="status"]').text()).toBe('Device removed');
        expect(wrapper.get('[role="alert"]').text()).toBe('Device unavailable');
        const form = wrapper.get('form:not([role="search"])');
        expect(form.attributes()).toMatchObject({ method: 'GET', action: '/dashboard' });
        const submit = vi.spyOn(form.element, 'requestSubmit').mockImplementation(() => {});
        await wrapper.get('#devices-per-page').setValue('30');
        expect(submit).toHaveBeenCalledOnce();
        expect(Object.fromEntries(new FormData(form.element))).toEqual({ show: '30' });
    });

    it('cancels Delete until confirmation without issuing a request or intercepting confirmed native navigation', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        const remove = wrapper.get('a[data-document-action]').element;
        const canceled = new MouseEvent('click', { cancelable: true });
        remove.dispatchEvent(canceled);
        await wrapper.vm.$nextTick();
        expect(canceled.defaultPrevented).toBe(true);
        expect(confirm).toHaveBeenCalledOnce();
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
        confirm.mockReturnValue(true);
        await trigger.trigger('click');
        const accepted = new MouseEvent('click', { cancelable: true });
        let preventedByComponent;
        remove.addEventListener(
            'click',
            (event) => {
                preventedByComponent = event.defaultPrevented;
                event.preventDefault(); // jsdom cannot navigate; inspect the component before test cancellation.
            },
            { once: true },
        );
        remove.dispatchEvent(accepted);
        expect(preventedByComponent).toBe(false);
    });
    it('reveals row actions by keyboard and closes on Escape or an outside click', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const view = wrapper.get('a[href="/device-info/1"]');
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(view.element);
        await view.trigger('keydown', { key: 'ArrowDown' });
        const edit = wrapper.get('a[href="/edit-device/1"]');
        expect(document.activeElement).toBe(edit.element);
        await edit.trigger('keydown', { key: 'Escape' });
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
        await trigger.trigger('click');
        document.body.click();
        await wrapper.vm.$nextTick();
        expect(trigger.attributes('aria-expanded')).toBe('false');
    });
    it('focuses the first action on opening and returns to the row when tabbing out of the popup', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        await flushPromises();
        const view = wrapper.get('a[href="/device-info/1"]');
        expect(document.activeElement).toBe(view.element);
        await view.trigger('keydown', { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(trigger.element);
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('keydown', { key: 'ArrowUp' });
        await flushPromises();
        const remove = wrapper.get('a[data-document-action]');
        expect(document.activeElement).toBe(remove.element);
        await remove.trigger('keydown', { key: 'Tab' });
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(trigger.element);
    });
    it('submits alias search from the first page, keeps the applied filter on size changes, and clears it explicitly', async () => {
        const wrapper = page({ search: 'Roof' });
        const searchForm = wrapper.get('form[role="search"]');
        expect(searchForm.attributes()).toMatchObject({ method: 'GET', action: '/dashboard' });
        await wrapper.get('#device-alias-search').setValue('New alias');
        expect(Object.fromEntries(new FormData(searchForm.element))).toEqual({
            search: 'New alias',
            show: '20',
        });
        const sizeForm = wrapper.get('form:not([role="search"])');
        expect(Object.fromEntries(new FormData(sizeForm.element))).toEqual({
            search: 'Roof',
            show: '20',
        });
        expect(wrapper.get('a[href="/dashboard?show=20"]').text()).toBe('Clear search');
        await wrapper.setProps({ search: 'Different' });
        expect(wrapper.get('#device-alias-search').element.value).toBe('Different');
    });
    it('distinguishes no alias matches from an account with no devices', () => {
        const wrapper = page({
            search: 'missing',
            devices: [],
            pagination: {
                currentPage: 1,
                perPage: 20,
                lastPage: 1,
                total: 0,
                sizes: [10, 20, 30],
                links: [],
            },
        });
        expect(wrapper.text()).toContain('No devices match “missing”');
        expect(wrapper.text()).not.toContain('No devices registered yet');
    });
});
