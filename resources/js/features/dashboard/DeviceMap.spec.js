import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import DeviceMap from './DeviceMap.vue';
import { requestJson } from '../../shared/api/client.js';
import { coordinates, devicePopup, mapDevices } from './deviceMap.js';

const leaflet = vi.hoisted(() => {
    const map = { setView: vi.fn().mockReturnThis(), remove: vi.fn() };
    const layer = { addTo: vi.fn().mockReturnThis(), clearLayers: vi.fn() };
    const tile = { addTo: vi.fn().mockReturnThis(), on: vi.fn().mockReturnThis() };
    const marker = { addTo: vi.fn().mockReturnThis(), bindPopup: vi.fn().mockReturnThis() };
    return {
        mapObject: map,
        layer,
        tile,
        markerObject: marker,
        map: vi.fn(() => map),
        layerGroup: vi.fn(() => layer),
        tileLayer: vi.fn(() => tile),
        marker: vi.fn(() => marker),
        divIcon: vi.fn((options) => options),
    };
});
vi.mock('leaflet', () => leaflet);
vi.mock('../../shared/api/client.js', () => ({ requestJson: vi.fn() }));
const mounted = [];
function page() {
    const wrapper = mount(DeviceMap, {
        props: {
            endpoints: { all: '/all-devices', paginated: '/paginated-devices' },
            page: 2,
            perPage: 20,
        },
    });
    mounted.push(wrapper);
    return wrapper;
}
beforeEach(() => {
    vi.clearAllMocks();
    requestJson.mockReset();
});
afterEach(async () => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    await vi.dynamicImportSettled();
});

describe('device map data', () => {
    it('accepts real zero coordinates and rejects missing, nonnumeric, boolean, and out-of-range locations', () => {
        expect(coordinates({ latitude: 0, longitude: '0' })).toEqual([0, 0]);
        for (const latitude of [null, '', ' ', true, 'bad', 91, Infinity])
            expect(coordinates({ latitude, longitude: 0 })).toBeNull();
        expect(coordinates({ latitude: 0, longitude: -181 })).toBeNull();
        expect(mapDevices({ data: [] }, false)).toEqual([]);
        expect(mapDevices([], true)).toEqual([]);
        expect(() => mapDevices({}, false)).toThrow('Could not load');
    });

    it('builds popup labels as text nodes instead of interpreting device values as markup', () => {
        const popup = devicePopup({
            hardware: { name: '<script>bad</script>' },
            name: '<img src=x>',
            address_1: '<b>address</b>',
            last_updated: '<svg>',
        });
        expect(popup.querySelector('img,script,b,svg')).toBeNull();
        expect(popup.textContent).not.toContain('Hardware');
        expect(popup.textContent).toContain('<b>address</b>');
    });
});

describe('map lifecycle', () => {
    it('reloads when name-filtered endpoints change and preserves the filter in both map modes', async () => {
        requestJson.mockResolvedValue({ data: [] });
        const wrapper = page();
        await vi.dynamicImportSettled();
        await flushPromises();
        await wrapper.setProps({
            endpoints: {
                all: '/all-devices?search=Roof',
                paginated: '/paginated-devices?search=Roof',
            },
        });
        await flushPromises();
        expect(requestJson).toHaveBeenLastCalledWith(
            '/paginated-devices?search=Roof&page=2&show=20',
            expect.any(Object),
        );
        requestJson.mockResolvedValue([]);
        await wrapper.setProps({ showAll: true });
        await flushPromises();
        expect(requestJson).toHaveBeenLastCalledWith(
            '/all-devices?search=Roof&page=2&show=20',
            expect.any(Object),
        );
    });
    it('loads the current page, switches endpoint shapes, and destroys Leaflet on unmount', async () => {
        requestJson
            .mockResolvedValueOnce({
                data: [
                    { name: 'Zero', latitude: 0, longitude: 0, state: 'active', status: 'online' },
                ],
            })
            .mockResolvedValueOnce([]);
        const wrapper = page();
        await vi.dynamicImportSettled();
        await flushPromises();
        expect(requestJson).toHaveBeenCalledWith(
            '/paginated-devices?page=2&show=20',
            expect.objectContaining({ signal: expect.any(AbortSignal) }),
        );
        expect(leaflet.marker).toHaveBeenCalledWith(
            [0, 0],
            expect.objectContaining({ keyboard: true, title: 'Zero' }),
        );
        expect(leaflet.markerObject.bindPopup.mock.calls[0][0]).toBeInstanceOf(HTMLElement);
        expect(wrapper.text()).not.toContain('Showing 1 of 1 device locations.');
        await wrapper.setProps({ showAll: true });
        await flushPromises();
        expect(requestJson).toHaveBeenLastCalledWith(
            '/all-devices?page=2&show=20',
            expect.any(Object),
        );
        expect(wrapper.text()).toContain('No devices available for this map.');
        wrapper.unmount();
        expect(leaflet.mapObject.remove).toHaveBeenCalledOnce();
    });

    it('displays a safe retryable network error and recovers to the missing-coordinates state', async () => {
        requestJson
            .mockRejectedValueOnce(new Error('sensitive server payload'))
            .mockResolvedValueOnce({ data: [{ latitude: null, longitude: null }] });
        const wrapper = page();
        await vi.dynamicImportSettled();
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Could not load device locations.');
        expect(wrapper.text()).not.toContain('sensitive server payload');
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('None of these devices have valid coordinates.');
    });

    it('aborts obsolete reads and ignores a late response after a newer map selection', async () => {
        let oldResponse;
        requestJson
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        oldResponse = resolve;
                    }),
            )
            .mockResolvedValueOnce([]);
        const wrapper = page();
        await vi.dynamicImportSettled();
        await flushPromises();
        const oldSignal = requestJson.mock.calls[0][1].signal;
        expect(wrapper.text()).toContain('Loading device locations…');
        await wrapper.setProps({ showAll: true });
        await flushPromises();
        expect(oldSignal.aborted).toBe(true);
        oldResponse({ data: [{ name: 'Obsolete', latitude: 1, longitude: 1 }] });
        await flushPromises();
        expect(leaflet.marker).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('No devices available for this map.');
    });
});
