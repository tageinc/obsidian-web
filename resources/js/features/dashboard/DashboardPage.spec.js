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
vi.mock('./DeviceDetailsModal.vue', () => ({
    default: {
        name: 'DeviceDetailsModal',
        props: ['device', 'mode'],
        emits: ['close', 'edit', 'saved'],
        template:
            '<div data-testid="device-modal"><button @click="$emit(\'close\')">Close device</button></div>',
    },
}));
const mounted = [];
const device = (id, overrides = {}) => ({
    id,
    hardwareName: 'Solar tracker',
    name: `Tracker ${id}`,
    serial: `SERIAL-${id}`,
    sku: `SKU-${id}`,
    address: `${id} Device Street`,
    state: 'active',
    status: 'online',
    lastUpdated: '1 minute ago',
    links: {
        view: `/devices/${id}`,
        edit: `/edit-device/${id}`,
        retire: `/retire-device/${id}`,
    },
    ...overrides,
});
function page(overrides = {}) {
    const wrapper = mount(DashboardPage, {
        attachTo: document.body,
        global: { stubs: { teleport: true } },
        props: {
            csrfToken: 'csrf-test-token',
            devices: [device(1, { name: '<img src=x onerror=alert(1)>' })],
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
            links: { dashboard: '/dashboard', profile: '/profile', create: '/create-device' },
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
    it('shows escaped devices, explicit document actions, query pagination, without the all-map checkbox', async () => {
        const wrapper = page();
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('th[scope="row"]').text()).toContain('<img');
        expect(wrapper.get('a[aria-current="page"]').attributes('href')).toBe(
            '/dashboard?show=20&page=2',
        );
        expect(wrapper.get('form[data-document-action]').attributes('action')).toBe(
            '/retire-device/1',
        );
        expect(wrapper.findAll('thead th').map((cell) => cell.text())).toContain('Actions');
        expect(wrapper.find('#show-all-devices').exists()).toBe(false);
        const map = wrapper.getComponent({ name: 'DeviceMap' });
        expect(map.props()).toMatchObject({ page: 2, perPage: 20 });
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
        expect(wrapper.text()).toContain('No devices created yet.');
        expect(wrapper.get('[role="status"]').text()).toBe('Device removed');
        expect(wrapper.get('[role="alert"]').text()).toBe('Device unavailable');
        const form = wrapper.get('form:not([role="search"])');
        expect(form.attributes()).toMatchObject({ method: 'GET', action: '/dashboard' });
        const submit = vi.spyOn(form.element, 'requestSubmit').mockImplementation(() => {});
        await wrapper.get('#devices-per-page').setValue('30');
        expect(submit).toHaveBeenCalledOnce();
        expect(Object.fromEntries(new FormData(form.element))).toEqual({ show: '30' });
    });

    it('provides an underlined link that includes inactive devices with active devices', () => {
        const wrapper = page();
        const toggle = wrapper.get('.device-inactive-toggle');
        expect(toggle.element.parentElement.classList.contains('device-footer-summary')).toBe(true);
        expect(toggle.text()).toBe('Show inactive');
        expect(toggle.attributes('href')).toContain('show=20');
        expect(toggle.attributes('href')).toContain('state%5B%5D=active');
        expect(toggle.attributes('href')).toContain('state%5B%5D=inactive');

        const allStates = page({ stateFilter: ['active', 'inactive'] });
        expect(allStates.get('.device-inactive-toggle').text()).toBe('Hide inactive');
        expect(allStates.get('.device-inactive-toggle').attributes('href')).toContain(
            'state%5B%5D=active',
        );
    });

    it('opens details from a row and collapses them with the labeled disclosure button', async () => {
        const wrapper = page({ devices: [device(1)] });
        const row = wrapper.get('.device-table-row');
        const toggle = row.get('button[aria-controls="device-details-1"]');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.attributes('aria-label')).toBe('Show details for Tracker 1');
        expect(wrapper.get('#device-details-1').isVisible()).toBe(false);
        await row.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.attributes('aria-label')).toBe('Hide details for Tracker 1');
        const details = wrapper.get('#device-details-1');
        expect(details.isVisible()).toBe(true);
        expect(details.classes()).toContain('device-details-row');
        expect(details.text()).toContain('SERIAL-1');
        expect(details.text()).toContain('SKU-1');
        expect(details.text()).toContain('1 Device Street');
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('#device-details-1').isVisible()).toBe(false);
    });
    it('keeps expanded details independent across multiple device rows', async () => {
        const wrapper = page({ devices: [device(1), device(2)] });
        const first = wrapper.get('button[aria-controls="device-details-1"]');
        const second = wrapper.get('button[aria-controls="device-details-2"]');
        await first.trigger('click');
        await second.trigger('click');
        expect(wrapper.get('#device-details-1').isVisible()).toBe(true);
        expect(wrapper.get('#device-details-2').isVisible()).toBe(true);
        expect(wrapper.get('#device-details-1').text()).toContain('SERIAL-1');
        expect(wrapper.get('#device-details-2').text()).toContain('SERIAL-2');
        await first.trigger('click');
        expect(first.attributes('aria-expanded')).toBe('false');
        expect(second.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('#device-details-1').isVisible()).toBe(false);
        expect(wrapper.get('#device-details-2').isVisible()).toBe(true);
        expect(wrapper.get('#device-details-2').text()).toContain('SERIAL-2');
    });
    it('shows clear fallbacks for missing detail fields while preserving zero-valued text', async () => {
        const wrapper = page({
            devices: [
                device(1, { serial: null, sku: '', address: undefined }),
                device(2, { serial: '0', sku: '0', address: '0' }),
            ],
        });
        await wrapper.get('button[aria-controls="device-details-1"]').trigger('click');
        await wrapper.get('button[aria-controls="device-details-2"]').trigger('click');
        expect(
            wrapper
                .get('#device-details-1')
                .findAll('dt')
                .map((term) => term.text()),
        ).toEqual(['Serial number', 'SKU', 'Location']);
        expect(
            wrapper
                .get('#device-details-1')
                .findAll('dd')
                .map((value) => value.text()),
        ).toEqual(['—', '—', 'No address provided']);
        expect(
            wrapper
                .get('#device-details-2')
                .findAll('dd')
                .map((value) => value.text()),
        ).toEqual(['0', '0', '0']);
    });
    it('does not toggle details when activating the name or action menu links', async () => {
        const wrapper = page({ devices: [device(1)] });
        const row = wrapper.get('.device-table-row');
        const toggle = row.get('button[aria-controls="device-details-1"]');
        const name = row.get('a[href="/devices/1"]');
        name.element.addEventListener('click', (event) => event.preventDefault());
        await name.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        await row.trigger('click');
        await name.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
    });
    it('submits name search from the first page, keeps the applied filter on size changes, and clears it explicitly', async () => {
        const wrapper = page({ search: 'Roof' });
        const searchForm = wrapper.get('form[role="search"]');
        expect(searchForm.attributes()).toMatchObject({ method: 'GET', action: '/dashboard' });
        await wrapper.get('#device-name-search').setValue('New name');
        expect(Object.fromEntries(new FormData(searchForm.element))).toEqual({
            search: 'New name',
            show: '20',
        });
        const sizeForm = wrapper.get('form[action="/dashboard"]:not([role="search"])');
        expect(Object.fromEntries(new FormData(sizeForm.element))).toEqual({
            search: 'Roof',
            show: '20',
        });
        expect(wrapper.get('a[href="/dashboard?show=20"]').text()).toBe('Clear search');
        await wrapper.setProps({ search: 'Different' });
        expect(wrapper.get('#device-name-search').element.value).toBe('Different');
    });
    it('navigates to the view page without dashboard action controls', async () => {
        const wrapper = page({ devices: [device(1)] });
        const name = wrapper.get('a.device-name');
        expect(name.attributes('href')).toBe('/devices/1');
        const event = new MouseEvent('click', { bubbles: true, cancelable: true });
        name.element.dispatchEvent(event);
        await flushPromises();
        expect(event.defaultPrevented).toBe(false);
        expect(wrapper.findComponent({ name: 'DeviceDetailsModal' }).exists()).toBe(false);
    });
    it('distinguishes no name matches from an account with no devices', () => {
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
        expect(wrapper.text()).not.toContain('No devices created yet');
    });
});

it('opens the edit modal from table actions and restores focus on close', async () => {
    const wrapper = page({ devices: [device(1)] });
    const trigger = wrapper.get('.device-actions-cell button');
    await trigger.trigger('click');
    await flushPromises();
    await wrapper.get('a[href="/edit-device/1"]').trigger('click');
    await flushPromises();
    expect(wrapper.getComponent({ name: 'DeviceDetailsModal' }).props('mode')).toBe('edit');
    await wrapper.get('[data-testid="device-modal"] button').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="device-modal"]').exists()).toBe(false);
    expect(document.activeElement).toBe(trigger.element);
});
