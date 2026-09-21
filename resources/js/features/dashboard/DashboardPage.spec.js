import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
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
            success: 'Device removed',
            sessionError: 'Device unavailable',
        });
        expect(wrapper.text()).toContain('No devices registered yet.');
        expect(wrapper.get('[role="status"]').text()).toBe('Device removed');
        expect(wrapper.get('[role="alert"]').text()).toBe('Device unavailable');
        const form = wrapper.get('form');
        expect(form.attributes()).toMatchObject({ method: 'GET', action: '/dashboard' });
        const submit = vi.spyOn(form.element, 'requestSubmit').mockImplementation(() => {});
        await wrapper.get('#devices-per-page').setValue('30');
        expect(submit).toHaveBeenCalledOnce();
        expect(Object.fromEntries(new FormData(form.element))).toEqual({ show: '30' });
    });

    it('cancels Remove until confirmation without issuing a request or intercepting confirmed native navigation', () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = page();
        const remove = wrapper.get('a[data-document-action]').element;
        const canceled = new MouseEvent('click', { cancelable: true });
        remove.dispatchEvent(canceled);
        expect(canceled.defaultPrevented).toBe(true);
        expect(confirm).toHaveBeenCalledOnce();
        confirm.mockReturnValue(true);
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
});
