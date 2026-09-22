<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import StatusFilter from './StatusFilter.vue';
import DeviceMap from './DeviceMap.vue';
import DeviceActions from './DeviceActions.vue';
import CreateDeviceModal from './CreateDeviceModal.vue';
import DeviceDetailsModal from './DeviceDetailsModal.vue';
import { statusColor } from './deviceMap.js';
import FormFeedback from '../../shared/components/FormFeedback.vue';

const props = defineProps({
    csrfToken: { type: String, required: true },
    devices: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    mapEndpoints: { type: Object, required: true },
    links: { type: Object, required: true },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    statusFilter: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    creation: { type: Object, default: null },
});
const creatingDevice = ref(props.creation?.initiallyOpen ?? false);
watch(
    () => props.creation,
    (creation) => {
        if (creation?.initiallyOpen) creatingDevice.value = true;
    },
);
const createButton = ref(null);
const selectedDevice = ref(null);
const deviceMode = ref('view');
let deviceTrigger;
function openDevice(event, device, mode, trigger = event.currentTarget) {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
        return;
    event.preventDefault();
    deviceTrigger = trigger;
    deviceMode.value = mode;
    selectedDevice.value = device;
}
async function closeDevice() {
    selectedDevice.value = null;
    await nextTick();
    deviceTrigger?.focus({ preventScroll: true });
}
function deviceSaved() {
    // Reload the current filter/page so the table and map share fresh server data.
    window.location.reload();
}
async function closeCreation() {
    creatingDevice.value = false;
    await nextTick();
    createButton.value?.focus({ preventScroll: true });
}
const perPage = ref(props.pagination.perPage);
const nameSearch = ref(props.search);
const expandedIds = ref([]);
function toggleDetails(id) {
    expandedIds.value = expandedIds.value.includes(id)
        ? expandedIds.value.filter((value) => value !== id)
        : [...expandedIds.value, id];
}
function clickRow(event, id) {
    if (event.target.closest('a, button, input, select, textarea, label')) return;
    toggleDetails(id);
}
watch(
    () => props.devices,
    () => {
        expandedIds.value = expandedIds.value.filter((id) =>
            props.devices.some((device) => device.id === id),
        );
    },
);
watch(
    () => props.search,
    (value) => {
        nameSearch.value = value;
    },
);
const clearSearchUrl = computed(() => {
    const url = new URL(props.links.dashboard, window.location.origin);
    url.searchParams.delete('search');
    props.statusFilter.forEach((status) => url.searchParams.append('status[]', status));
    url.searchParams.delete('page');
    url.searchParams.set('show', String(perPage.value));
    return url.pathname + url.search;
});
watch(
    () => props.pagination.perPage,
    (value) => {
        perPage.value = value;
    },
);
</script>

<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
                    <div>
                        <h1 class="h3 mb-1">Dashboard</h1>
                        <p class="text-muted mb-2">Manage your devices and their locations.</p>
                    </div>
                </div>
                <FormFeedback :success="success" :session-error="sessionError" />
                <DeviceMap
                    :endpoints="mapEndpoints"
                    :page="pagination.currentPage"
                    :per-page="pagination.perPage"
                />
                <section class="device-manager" aria-labelledby="device-manager-heading">
                    <h2 id="device-manager-heading" class="h5 mb-3">Device Manager</h2>
                    <form
                        :action="links.dashboard"
                        method="GET"
                        role="search"
                        class="device-search"
                    >
                        <div class="device-search-field">
                            <label for="device-name-search" class="visually-hidden"
                                >Search by device name</label
                            >
                            <button type="submit" class="device-search-submit" aria-label="Search">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <circle cx="10.5" cy="10.5" r="6.5" />
                                    <path d="m16 16 5 5" />
                                </svg>
                            </button>
                            <input
                                id="device-name-search"
                                v-model="nameSearch"
                                type="search"
                                name="search"
                                maxlength="255"
                                class="device-search-input"
                                placeholder="Search by name…"
                            />
                        </div>
                        <input type="hidden" name="show" :value="perPage" />
                        <a v-if="search" :href="clearSearchUrl" class="device-clear-search"
                            >Clear search</a
                        >
                        <div class="device-create-toolbar">
                            <StatusFilter :value="statusFilter" :options="statusOptions" />
                            <button
                                v-if="creation"
                                ref="createButton"
                                type="button"
                                class="btn btn-primary"
                                aria-haspopup="dialog"
                                @click="creatingDevice = true"
                            >
                                Create +
                            </button>
                            <a v-else class="btn btn-primary" :href="links.create">Create +</a>
                        </div>
                    </form>
                    <p v-if="devices.length === 0" class="device-empty" role="status">
                        <template v-if="pagination.total > 0"
                            >No devices on this page. Choose another page below.</template
                        >
                        <template v-else-if="search"
                            >No devices match “{{ search }}”. Try another name or clear the
                            search.</template
                        >
                        <template v-else
                            >No devices created yet. Use Create + to add your first
                            device.</template
                        >
                    </p>
                    <table v-else class="device-table" role="table">
                        <caption class="visually-hidden">
                            Your devices
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="device-expand-column">
                                    <span class="visually-hidden">Details</span>
                                </th>
                                <th scope="col">Device</th>
                                <th scope="col">Status</th>
                                <th scope="col">Last updated</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="device in devices" :key="device.id">
                                <tr
                                    class="device-table-row"
                                    :class="{ 'is-expanded': expandedIds.includes(device.id) }"
                                    @click="clickRow($event, device.id)"
                                >
                                    <td class="device-expand-cell">
                                        <button
                                            type="button"
                                            class="device-expand-button"
                                            :aria-label="`${expandedIds.includes(device.id) ? 'Hide' : 'Show'} details for ${device.name}`"
                                            :aria-expanded="expandedIds.includes(device.id)"
                                            :aria-controls="`device-details-${device.id}`"
                                            @click="toggleDetails(device.id)"
                                        >
                                            <svg
                                                width="16"
                                                height="16"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path d="m9 5 7 7-7 7" />
                                            </svg>
                                        </button>
                                    </td>
                                    <th scope="row" class="device-name-cell">
                                        <span class="device-field-label" aria-hidden="true"
                                            >Device</span
                                        >
                                        <a
                                            class="device-name"
                                            :href="device.links.view"
                                            aria-haspopup="dialog"
                                            >{{ device.name }}</a
                                        >
                                    </th>
                                    <td>
                                        <span class="device-field-label" aria-hidden="true"
                                            >Status</span
                                        >
                                        <span class="device-status">
                                            <span
                                                class="status-dot"
                                                :style="{
                                                    backgroundColor: statusColor(device.status),
                                                }"
                                                aria-hidden="true"
                                            />{{ device.status }}
                                        </span>
                                    </td>
                                    <td class="device-updated">
                                        <span class="device-field-label" aria-hidden="true"
                                            >Last updated</span
                                        >{{ device.lastUpdated }}
                                    </td>
                                    <td class="device-actions-cell">
                                        <span class="device-field-label" aria-hidden="true"
                                            >Actions</span
                                        >
                                        <DeviceActions
                                            :csrf-token="csrfToken"
                                            :device="device"
                                            @edit="
                                                (event, trigger) =>
                                                    openDevice(event, device, 'edit', trigger)
                                            "
                                        />
                                    </td>
                                </tr>
                                <tr
                                    v-show="expandedIds.includes(device.id)"
                                    :id="`device-details-${device.id}`"
                                    class="device-details-row"
                                >
                                    <td colspan="5">
                                        <dl class="device-details">
                                            <div>
                                                <dt>Serial number</dt>
                                                <dd>{{ device.serial || '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt>SKU</dt>
                                                <dd>{{ device.sku || '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt>Location</dt>
                                                <dd>
                                                    {{ device.address || 'No address provided' }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div class="device-table-footer">
                        <form :action="links.dashboard" method="GET" class="device-page-size">
                            <input v-if="search" type="hidden" name="search" :value="search" />
                            <input
                                v-for="status in statusFilter"
                                :key="status"
                                type="hidden"
                                name="status[]"
                                :value="status"
                            />
                            <label for="devices-per-page"
                                >Show<span class="visually-hidden"> devices per page</span>:</label
                            >
                            <select
                                id="devices-per-page"
                                v-model="perPage"
                                name="show"
                                class="device-size-select"
                                @change="$event.target.form.requestSubmit()"
                            >
                                <option v-for="size in pagination.sizes" :key="size" :value="size">
                                    {{ size }}
                                </option>
                            </select>
                        </form>
                        <nav v-if="pagination.lastPage > 1" aria-label="Device pages">
                            <ul class="device-pagination">
                                <li v-for="(link, index) in pagination.links" :key="index">
                                    <a
                                        v-if="link.url"
                                        :href="link.url"
                                        :aria-current="link.active ? 'page' : undefined"
                                        >{{ link.label }}</a
                                    >
                                    <span v-else aria-disabled="true">{{ link.label }}</span>
                                </li>
                            </ul>
                        </nav>
                        <p class="device-result-count">
                            {{
                                pagination.total
                                    ? `Showing ${pagination.from}–${pagination.to} of ${pagination.total} devices`
                                    : '0 devices'
                            }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
        <CreateDeviceModal
            v-if="creation && creatingDevice"
            :creation="creation"
            @close="closeCreation"
        />
        <DeviceDetailsModal
            v-if="selectedDevice"
            :device="selectedDevice"
            :mode="deviceMode"
            @close="closeDevice"
            @edit="deviceMode = 'edit'"
            @saved="deviceSaved"
        />
    </div>
</template>

<style scoped>
.device-create-toolbar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
    justify-content: flex-end;
    margin-left: auto;
}
.device-manager {
    margin-top: 1rem;
    color: #111827;
}
.device-search {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.device-search-field {
    position: relative;
    flex: 1 1 12rem;
    width: auto;
    min-width: 0;
}
.device-search-input {
    width: 100%;
    min-height: 2.5rem;
    padding: 0.5rem 0.75rem 0.5rem 2.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    color: #111827;
    font: inherit;
    font-size: 0.9rem;
}
.device-search-input::placeholder {
    color: #6b7280;
}
.device-search-submit {
    position: absolute;
    inset: 0 auto 0 0;
    width: 2.5rem;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    color: #6b7280;
    background: transparent;
}
.device-clear-search {
    font-size: 0.875rem;
}
.device-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    font-size: 0.9rem;
}
.device-table thead th {
    padding: 0.75rem 0.5rem;
    background: #f7f9fa;
    color: #6b7280;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.device-table th,
.device-table td {
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: middle;
}
.device-table-row > th,
.device-table-row > td {
    padding: 0.8rem;
}
.device-table-row {
    cursor: pointer;
    transition: background-color 0.15s;
}
.device-table-row:hover,
.device-table-row.is-expanded {
    background: #f3f4f6;
}
.device-expand-column,
.device-expand-cell {
    width: 2.5rem;
}
.device-table-row > .device-expand-cell {
    padding: 0.25rem;
}
.device-expand-button {
    display: grid;
    place-items: center;
    width: 2rem;
    height: 2.5rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
}
.device-expand-button svg {
    transition: transform 0.15s;
}
.device-expand-button[aria-expanded='true'] svg {
    transform: rotate(90deg);
}
.device-expand-button:hover,
.device-search-submit:hover {
    background: #e5e7eb;
    color: #111827;
}
.device-manager :is(a, button, input, select):focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
.device-name {
    color: #111827;
    font-weight: 600;
    text-decoration: none;
    overflow-wrap: anywhere;
}
.device-name:hover {
    color: #0b5ed7;
    text-decoration: underline;
}
.device-updated {
    color: #4b5563;
}
.device-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.status-dot {
    height: 8px;
    width: 8px;
    flex: 0 0 8px;
    border-radius: 50%;
}
.device-actions-cell {
    width: 4rem;
}
.device-field-label {
    display: none;
}
.device-details-row > td {
    padding: 1rem;
    border-left: 3px solid #e5e7eb;
    border-right: 3px solid #e5e7eb;
    background: #f8fafc;
}
.device-details {
    display: grid;
    grid-template-columns: 1fr 1fr 2fr;
    gap: 1rem;
    margin: 0;
}
.device-details > div {
    min-width: 0;
}
.device-details dt {
    margin-bottom: 0.25rem;
    color: #6b7280;
    font-size: 0.75rem;
    font-weight: 600;
}
.device-details dd {
    margin: 0;
    overflow-wrap: anywhere;
}
.device-empty {
    margin: 0;
    padding: 2rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 5px;
    color: #6b7280;
    text-align: center;
}
.device-table-footer {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem 1rem;
    margin-top: 1rem;
    font-size: 0.8rem;
}
.device-page-size {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6b7280;
}
.device-size-select {
    padding: 6px 8px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #111827;
    background: #fff;
}
.device-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0;
    margin: 0;
    list-style: none;
}
.device-pagination :is(a, span) {
    display: block;
    min-width: 34px;
    padding: 6px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #111827;
    background: #fff;
    text-align: center;
    text-decoration: none;
}
.device-pagination a:hover {
    background: #f3f4f6;
}
.device-pagination [aria-current='page'] {
    border-color: #0d6efd;
    background: #0d6efd;
    color: #fff;
}
.device-pagination a[aria-current='page']:hover {
    background: #0b5ed7;
}
.device-pagination [aria-disabled='true'] {
    color: #6b7280;
    background: #f7f9fa;
}
.device-result-count {
    margin: 0 0 0 auto;
    color: #6b7280;
}
@media (max-width: 768px) {
    .device-search-field {
        width: 100%;
    }
    .device-table,
    .device-table tbody {
        display: block;
    }
    .device-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .device-table-row {
        position: relative;
        display: block;
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        border: 1px solid #e5e7eb;
        border-radius: 5px;
        box-shadow: 2px 2px 8px rgb(0 0 0 / 5%);
    }
    .device-table-row > th,
    .device-table-row > td {
        display: block;
        width: auto;
        padding: 0.5rem;
        border: 0;
    }
    .device-table-row > .device-expand-cell {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        padding: 0;
    }
    .device-table-row > .device-name-cell {
        padding-right: 2.5rem;
    }
    .device-field-label {
        display: block;
        margin-bottom: 0.25rem;
        color: #4b5563;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .device-details-row {
        display: block;
        margin: -0.5rem 0 0.5rem;
    }
    .device-details-row > td {
        display: block;
        border-radius: 0 0 5px 5px;
    }
    .device-details {
        grid-template-columns: 1fr;
    }
    .device-pagination :is(a, span) {
        min-width: 30px;
        padding: 6px 8px;
    }
    .device-result-count {
        width: 100%;
        margin: 0;
    }
}
</style>
