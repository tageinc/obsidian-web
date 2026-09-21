<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { requestJson } from '../../shared/api/client.js';
import { coordinates, devicePopup, mapDevices, markerIcon } from './deviceMap.js';

const props = defineProps({
    endpoints: { type: Object, required: true },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 10 },
    showAll: { type: Boolean, default: false },
});
const canvas = ref(null);
const loading = ref(true);
const error = ref('');
const tileWarning = ref(false);
const total = ref(0);
const located = ref(0);
let map;
let markers;
let leaflet;
let controller;
let generation = 0;
let destroyed = false;

async function reload() {
    const current = ++generation;
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    loading.value = true;
    error.value = '';
    // Clear stale locations while switching between all/current-page requests.
    markers?.clearLayers();
    try {
        if (!map) {
            const [module] = await Promise.all([
                import('leaflet'),
                import('leaflet/dist/leaflet.css'),
            ]);
            if (destroyed || current !== generation) return;
            leaflet = module;
            map = leaflet.map(canvas.value).setView([33.7263, -117.919], 11);
            const tiles = leaflet
                .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution:
                        'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
                })
                .addTo(map);
            tiles.on('tileerror', () => {
                tileWarning.value = true;
            });
            tiles.on('tileload', () => {
                tileWarning.value = false;
            });
            markers = leaflet.layerGroup().addTo(map);
        }
        const url = new URL(
            props.showAll ? props.endpoints.all : props.endpoints.paginated,
            window.location.origin,
        );
        url.searchParams.set('page', String(props.page));
        url.searchParams.set('show', String(props.perPage));
        const payload = await requestJson(url.pathname + url.search, { signal });
        if (destroyed || current !== generation) return;
        const devices = mapDevices(payload, props.showAll);
        total.value = devices.length;
        located.value = 0;
        for (const device of devices) {
            const point = coordinates(device);
            if (!point) continue;
            leaflet
                .marker(point, {
                    title: String(device.alias ?? 'Device'),
                    alt: String(device.alias ?? 'Device'),
                    keyboard: true,
                    icon: leaflet.divIcon({
                        html: markerIcon(device.state),
                        className: 'device-map-marker',
                        iconSize: [30, 42],
                        iconAnchor: [15, 42],
                    }),
                })
                .addTo(markers)
                .bindPopup(devicePopup(device));
            located.value++;
        }
    } catch (failure) {
        if (destroyed || signal.aborted || current !== generation || failure.name === 'AbortError')
            return;
        error.value = failure.status
            ? failure.message
            : 'Could not load device locations. Try again.';
    } finally {
        if (!destroyed && current === generation) loading.value = false;
    }
}

onMounted(reload);
watch(() => [props.page, props.perPage, props.showAll], reload);
onBeforeUnmount(() => {
    destroyed = true;
    generation++;
    controller?.abort();
    map?.remove();
});
</script>

<template>
    <section aria-label="Device locations" :aria-busy="loading">
        <div v-if="error" class="alert alert-danger" role="alert">
            {{ error }}
            <button type="button" class="btn btn-outline-danger btn-sm ms-2" @click="reload">
                Retry map
            </button>
        </div>
        <p v-else-if="loading" role="status" class="text-muted">Loading device locations…</p>
        <p v-else-if="total === 0" role="status" class="text-muted">
            No devices available for this map.
        </p>
        <p v-else-if="located === 0" role="status" class="text-muted">
            None of these devices have valid coordinates.
        </p>
        <p v-else role="status" class="text-muted small">
            Showing {{ located }} of {{ total }} device locations.
        </p>
        <p v-if="tileWarning" role="status" class="text-muted small">
            The map background is unavailable. Device details remain available in the list.
        </p>
        <div id="map" ref="canvas" class="device-map mb-2" aria-label="Map of device locations" />
    </section>
</template>

<style scoped>
.device-map {
    height: 400px;
    width: 100%;
    max-width: 100%;
}
</style>
