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
    alias: `Tracker ${id}`,
    serial: `SERIAL-${id}`,
    sku: `SKU-${id}`,
    address: `${id} Device Street`,
    state: 'active',
    status: 'online',
    lastUpdated: '1 minute ago',
    links: {
        view: `/devices/${id}`,
        edit: `/edit-device/${id}`,
        archive: `/archive-device/${id}`,
    },
    ...overrides,
});
function page(overrides = {}) {
    const wrapper = mount(DashboardPage, {
        attachTo: document.body,
        global: { stubs: { teleport: true } },
        props: {
            csrfToken: 'csrf-test-token',
            devices: [device(1, { alias: '<img src=x onerror=alert(1)>' })],
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
        expect(wrapper.get('a[data-document-action]').attributes('href')).toBe('/archive-device/1');
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

    it('requires modal confirmation and restores focus on cancellation', async () => {
        HTMLDialogElement.prototype.showModal = function () {
            this.open = true;
        };
        HTMLDialogElement.prototype.close = function () {
            this.open = false;
            this.dispatchEvent(new Event('close'));
        };
        const confirm = vi.spyOn(window, 'confirm');
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        const remove = wrapper.get('a.device-archive');
        const canceled = new MouseEvent('click', { cancelable: true });
        remove.element.dispatchEvent(canceled);
        await flushPromises();
        expect(canceled.defaultPrevented).toBe(true);
        expect(confirm).not.toHaveBeenCalled();
        const dialog = wrapper.get('dialog');
        expect(dialog.text()).toContain('Archive device?');
        expect(dialog.find('img').exists()).toBe(false);
        expect(dialog.get('form').attributes()).toMatchObject({
            action: '/archive-device/1',
            method: 'POST',
        });
        expect(dialog.get('input[name="_token"]').element.value).toBe('csrf-test-token');
        expect(dialog.get('form').attributes()).toHaveProperty('data-document-action');
        await dialog.get('button.btn-outline-secondary').trigger('click');
        await flushPromises();
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        await trigger.trigger('click');
        await remove.trigger('click');
        await flushPromises();
        await wrapper.get('dialog').trigger('cancel');
        await flushPromises();
        expect(wrapper.find('dialog').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
    });
    it('reveals row actions by keyboard and closes on Escape or an outside click', async () => {
        const wrapper = page();
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await trigger.trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();
        const edit = wrapper.get('#device-actions-1 a[href="/edit-device/1"]');
        expect(trigger.attributes('aria-expanded')).toBe('true');
        expect(document.activeElement).toBe(edit.element);
        await edit.trigger('keydown', { key: 'ArrowDown' });
        const remove = wrapper.get('a[data-document-action]');
        expect(document.activeElement).toBe(remove.element);
        await remove.trigger('keydown', { key: 'Escape' });
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
        const edit = wrapper.get('#device-actions-1 a[href="/edit-device/1"]');
        expect(document.activeElement).toBe(edit.element);
        await edit.trigger('keydown', { key: 'Tab', shiftKey: true });
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
    it('does not toggle details when activating the alias or action menu links', async () => {
        const wrapper = page({ devices: [device(1)] });
        const row = wrapper.get('.device-table-row');
        const toggle = row.get('button[aria-controls="device-details-1"]');
        const alias = row.get('a[href="/devices/1"]');
        alias.element.addEventListener('click', (event) => event.preventDefault());
        await alias.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        await row.get('button[aria-controls="device-actions-1"]').trigger('click');
        await flushPromises();
        expect(toggle.attributes('aria-expanded')).toBe('false');
        const edit = wrapper.get('#device-actions-1 a[href="/edit-device/1"]');
        edit.element.addEventListener('click', (event) => event.preventDefault());
        await edit.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        await row.trigger('click');
        await alias.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
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
    it('opens view from the alias and edit from the simplified menu, restoring the correct trigger', async () => {
        const wrapper = page({ devices: [device(1)] });
        const alias = wrapper.get('a.device-alias');
        await alias.trigger('click');
        let modal = wrapper.getComponent({ name: 'DeviceDetailsModal' });
        expect(modal.props()).toMatchObject({ mode: 'view', device: { id: 1 } });
        await modal.get('button').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(alias.element);
        const trigger = wrapper.get('button[aria-controls="device-actions-1"]');
        await trigger.trigger('click');
        expect(wrapper.find('#device-actions-1 hr').exists()).toBe(false);
        expect(wrapper.find('#device-actions-1 a[href="/devices/1"]').exists()).toBe(false);
        await wrapper.get('#device-actions-1 a[href="/edit-device/1"]').trigger('click');
        modal = wrapper.getComponent({ name: 'DeviceDetailsModal' });
        expect(modal.props('mode')).toBe('edit');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        await modal.get('button').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(trigger.element);
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
        expect(wrapper.text()).not.toContain('No devices created yet');
    });
});
