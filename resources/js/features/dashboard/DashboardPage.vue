<script setup>
import { ref, watch } from 'vue';
import DeviceMap from './DeviceMap.vue';
import { statusColor } from './deviceMap.js';
import FormFeedback from '../../shared/components/FormFeedback.vue';

const props = defineProps({
    devices: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    mapEndpoints: { type: Object, required: true },
    links: { type: Object, required: true },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
});
const showAll = ref(false);
const perPage = ref(props.pagination.perPage);
watch(
    () => props.pagination.perPage,
    (value) => {
        perPage.value = value;
    },
);
function confirmRemoval(event) {
    if (!window.confirm('Are you sure you want to delete this device?')) event.preventDefault();
}
</script>

<template>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
            <div>
                <h1 class="h3 mb-1">Dashboard</h1>
                <p class="text-muted mb-2">Manage your devices and their locations.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" :href="links.profile">Edit profile</a>
                <a class="btn btn-primary" :href="links.register">Register device</a>
            </div>
        </div>
        <FormFeedback :success="success" :session-error="sessionError" />
        <div class="row justify-content-center">
            <div class="col-md-10">
                <DeviceMap
                    :endpoints="mapEndpoints"
                    :page="pagination.currentPage"
                    :per-page="pagination.perPage"
                    :show-all="showAll"
                />
                <div class="form-check mb-3">
                    <input
                        id="show-all-devices"
                        v-model="showAll"
                        type="checkbox"
                        class="form-check-input"
                    />
                    <label class="form-check-label" for="show-all-devices"
                        >Show all devices on map</label
                    >
                </div>
                <section class="card" aria-labelledby="device-manager-heading">
                    <div class="card-header">
                        <h2 id="device-manager-heading" class="h5 mb-0">Device Manager</h2>
                    </div>
                    <div class="card-body">
                        <p v-if="devices.length === 0" class="text-muted mb-0">
                            No devices registered yet. Use Register device to add your first device.
                        </p>
                        <div v-else class="table-responsive">
                            <table class="table align-middle">
                                <caption class="visually-hidden">
                                    Your registered devices
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Hardware</th>
                                        <th scope="col">Alias</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="device in devices" :key="device.id">
                                        <td>{{ device.hardwareName }}</td>
                                        <th scope="row">{{ device.alias }}</th>
                                        <td>
                                            <span
                                                class="status-dot"
                                                :style="{
                                                    backgroundColor: statusColor(device.state),
                                                }"
                                                aria-hidden="true"
                                            />{{ device.state }}
                                            <p class="small mb-0">
                                                Last updated at: {{ device.lastUpdated }}
                                            </p>
                                        </td>
                                        <td class="text-nowrap">
                                            <a
                                                :href="device.links.view"
                                                :aria-label="`View ${device.alias}`"
                                                >View</a
                                            >
                                            <span aria-hidden="true"> | </span>
                                            <a
                                                :href="device.links.edit"
                                                :aria-label="`Edit ${device.alias}`"
                                                >Edit</a
                                            >
                                            <span aria-hidden="true"> | </span>
                                            <a
                                                :href="device.links.remove"
                                                class="text-danger"
                                                data-document-action
                                                :aria-label="`Remove ${device.alias}`"
                                                @click="confirmRemoval"
                                                >Remove</a
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4"
                        >
                            <form :action="links.dashboard" method="GET">
                                <label for="devices-per-page" class="form-label me-2"
                                    >Devices per page</label
                                >
                                <select
                                    id="devices-per-page"
                                    v-model="perPage"
                                    name="show"
                                    class="form-select d-inline-block w-auto"
                                    @change="$event.target.form.requestSubmit()"
                                >
                                    <option
                                        v-for="size in pagination.sizes"
                                        :key="size"
                                        :value="size"
                                    >
                                        {{ size }}
                                    </option>
                                </select>
                            </form>
                            <p class="small text-muted mb-0">
                                {{
                                    pagination.total
                                        ? `${pagination.from}–${pagination.to} of ${pagination.total} devices`
                                        : '0 devices'
                                }}
                            </p>
                        </div>
                        <nav v-if="pagination.lastPage > 1" aria-label="Device pages" class="mt-3">
                            <ul class="pagination flex-wrap mb-0">
                                <li
                                    v-for="(link, index) in pagination.links"
                                    :key="index"
                                    class="page-item"
                                    :class="{ active: link.active, disabled: !link.url }"
                                >
                                    <a
                                        v-if="link.url"
                                        :href="link.url"
                                        class="page-link"
                                        :aria-current="link.active ? 'page' : undefined"
                                        >{{ link.label }}</a
                                    >
                                    <span v-else class="page-link" aria-disabled="true">{{
                                        link.label
                                    }}</span>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.status-dot {
    height: 10px;
    width: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}
</style>
