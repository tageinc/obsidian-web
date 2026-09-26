<script setup>
import { computed, defineAsyncComponent, nextTick, ref, watch } from 'vue';
import DeviceActions from '../dashboard/DeviceActions.vue';
import InlineDeviceField from '../devices/InlineDeviceField.vue';
const DeviceDetailsModal = defineAsyncComponent(
    () => import('../dashboard/DeviceDetailsModal.vue'),
);
import HistoryCharts from './HistoryCharts.vue';
import RemoteControl from './RemoteControl.vue';
import { useDeviceTelemetry } from './useDeviceTelemetry';
import { celsiusToFahrenheit, ctsState } from './sensorReadings';
const props = defineProps({
    device: { type: Object, required: true },
    status: { type: Object, required: true },
    remote: { type: Object, required: true },
    points: { type: Array, default: () => [] },
    links: { type: Object, required: true },
    csrfToken: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    editing: { type: Object, default: null },
    embedded: { type: Boolean, default: false },
});
const fieldValues = ref({ ...props.values });
const editingDevice = ref(props.editing?.initiallyOpen ?? false);
watch(
    () => props.values,
    (values) => {
        fieldValues.value = { ...values };
    },
);
const fieldGroups = [
    {
        id: 'identifiers',
        fields: [
            ['serial_no', 'Serial number'],
            ['sku', 'SKU'],
            ['order_no', 'Order number'],
        ],
    },
    {
        id: 'address',
        fields: [
            ['address_1', 'Address line 1'],
            ['address_2', 'Address line 2'],
            ['city', 'City'],
            ['address_state', 'State / province / region'],
            ['zip_code', 'Postal code'],
            ['country', 'Country'],
        ],
    },
    {
        id: 'coordinates',
        fields: [
            ['latitude', 'Latitude'],
            ['longitude', 'Longitude'],
        ],
    },
];
function saved(values) {
    fieldValues.value = { ...fieldValues.value, ...values };
}
function closeEdit() {
    editingDevice.value = false;
}
function modalSaved() {
    window.location.assign(window.location.pathname);
}

const activeSection = ref('overview');
const historyPanel = ref(null);
const mode = ref(props.remote.mode);
const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'history', label: 'History' },
    { id: 'control', label: 'Control' },
];
const {
    status: currentStatus,
    points: currentPoints,
    refreshedAt,
    message: refreshMessage,
} = useDeviceTelemetry({
    url: () => props.links.telemetry,
    deviceKey: () => props.device.serial,
    initialStatus: () => props.status,
    initialPoints: () => props.points,
});
const hasReading = computed(() =>
    Boolean(currentStatus.value.Updated && currentStatus.value.Updated !== 'N/A'),
);
const reading = (key) => (hasReading.value ? (currentStatus.value[key] ?? '—') : '—');
const temperature = computed(() => {
    if (!hasReading.value) return null;
    if ('Temperature (°F)' in currentStatus.value) {
        const value = currentStatus.value['Temperature (°F)'];
        return Number.isFinite(value) ? value : null;
    }
    return celsiusToFahrenheit(currentStatus.value['Temperature (°C)']);
});
const cts = computed(() => (hasReading.value ? ctsState(currentStatus.value.CTS) : null));
const sensors = ['PS1', 'PS2', 'PS Average', 'PDS', 'CTS'];
async function exploreHistory() {
    activeSection.value = 'history';
    await nextTick();
    historyPanel.value?.focus();
}
function navigateSection(event, index) {
    const keys = {
        ArrowRight: (index + 1) % sections.length,
        ArrowLeft: (index + sections.length - 1) % sections.length,
        Home: 0,
        End: sections.length - 1,
    };
    if (!(event.key in keys)) return;
    event.preventDefault();
    const next = keys[event.key];
    activeSection.value = sections[next].id;
    event.currentTarget.parentElement.querySelectorAll('[role="tab"]')[next].focus();
}
</script>
<template>
    <div class="device-page" :class="{ container: !embedded, 'device-page-embedded': embedded }">
        <nav v-if="!embedded" aria-label="Breadcrumb" class="mb-3">
            <ol class="list-unstyled d-flex flex-wrap small text-muted mb-0">
                <li>
                    <a :href="links.dashboard" class="text-muted text-nowrap">Dashboard</a>
                    <span class="mx-2" aria-hidden="true">›</span>
                </li>
                <li aria-current="page">{{ fieldValues.name || device.name || 'View Device' }}</li>
            </ol>
        </nav>
        <header class="device-heading">
            <div v-if="editing" class="device-page-actions">
                <DeviceActions
                    :csrf-token="csrfToken"
                    :device="{
                        id: editing.id,
                        name: fieldValues.name || device.name,
                        serial: fieldValues.serial_no || device.serial,
                        links: {
                            edit: editing.url,
                            retire: links.retire,
                            reactivate: links.reactivate,
                        },
                    }"
                    :show-edit="false"
                />
            </div>
            <div>
                <p class="device-eyebrow">Solar tracker</p>
                <h1 v-if="!embedded">
                    <InlineDeviceField
                        v-if="links.update"
                        label="Device name"
                        field="name"
                        :value="fieldValues.name"
                        :endpoint="links.update"
                        :csrf-token="csrfToken"
                        @saved="saved"
                    />
                    <template v-else>{{ device.name || 'View Device' }}</template>
                </h1>
            </div>
            <section class="device-header-details" aria-label="Device properties">
                <template v-if="links.update">
                    <dl v-for="group in fieldGroups" :key="group.id" class="device-details">
                        <div v-for="[field, label] in group.fields" :key="field">
                            <dt>{{ label }}</dt>
                            <dd>
                                <span v-if="field === 'serial_no'">{{
                                    fieldValues.serial_no || device.serial
                                }}</span
                                ><InlineDeviceField
                                    v-else
                                    :field="field"
                                    :label="label"
                                    :value="fieldValues[field]"
                                    :numeric="['latitude', 'longitude'].includes(field)"
                                    :endpoint="links.update"
                                    :csrf-token="csrfToken"
                                    @saved="saved"
                                />
                            </dd>
                        </div>
                    </dl>
                </template>
                <dl v-else class="device-details">
                    <div>
                        <dt>Serial number</dt>
                        <dd>{{ device.serial }}</dd>
                    </div>
                    <div>
                        <dt>SKU</dt>
                        <dd>{{ device.details.SKU || '—' }}</dd>
                    </div>
                    <div>
                        <dt>Location</dt>
                        <dd>{{ device.details.Address || 'No address added' }}</dd>
                    </div>
                </dl>
            </section>
        </header>
        <DeviceDetailsModal
            v-if="editingDevice && editing"
            mode="edit"
            :device="{ id: editing.id, name: fieldValues.name, links: { edit: editing.url } }"
            @close="closeEdit"
            @saved="modalSaved"
        />
        <div class="device-tabs" role="tablist" aria-label="Device sections">
            <button
                v-for="(section, index) in sections"
                :id="'device-tab-' + section.id"
                :key="section.id"
                type="button"
                role="tab"
                :aria-controls="'device-panel-' + section.id"
                :aria-selected="activeSection === section.id"
                :tabindex="activeSection === section.id ? 0 : -1"
                @click="activeSection = section.id"
                @keydown="navigateSection($event, index)"
            >
                {{ section.label }}
            </button>
        </div>

        <p v-if="refreshMessage" class="device-refresh-status" role="status" aria-live="polite">
            {{ refreshMessage }}
        </p>

        <section
            v-show="activeSection === 'overview'"
            id="device-panel-overview"
            role="tabpanel"
            aria-labelledby="device-tab-overview"
            tabindex="0"
        >
            <div class="section-heading">
                <div>
                    <component :is="embedded ? 'h3' : 'h2'">At a glance</component>
                    <p>The latest reported readings from this device.</p>
                </div>
                <p class="reading-time">
                    {{
                        hasReading
                            ? 'Last reading · ' + currentStatus.Updated
                            : 'No telemetry received yet'
                    }}
                </p>
            </div>
            <dl class="device-metrics">
                <div>
                    <dt>Reported state</dt>
                    <dd class="state-value">{{ reading('State') }}</dd>
                </div>
                <div>
                    <dt>Temperature</dt>
                    <dd>{{ temperature ?? '—' }}<span v-if="temperature !== null"> °F</span></dd>
                </div>
                <div>
                    <dt>Motor speed</dt>
                    <dd>{{ reading('Motor Speed') }}</dd>
                </div>
                <div>
                    <dt>Control mode</dt>
                    <dd class="state-value">{{ mode === 1 ? 'Remote control' : 'Automatic' }}</dd>
                </div>
                <div class="device-software-metric">
                    <dt>Software versions</dt>
                    <dd>
                        <dl class="device-software-versions">
                            <div>
                                <dt>Firmware</dt>
                                <dd>{{ reading('Firmware version') }}</dd>
                            </div>
                            <div>
                                <dt>Configuration</dt>
                                <dd>{{ reading('Config version') }}</dd>
                            </div>
                        </dl>
                    </dd>
                </div>
            </dl>
            <div class="device-overview-grid">
                <section class="device-surface" aria-labelledby="sensors-title">
                    <component :is="embedded ? 'h3' : 'h2'" id="sensors-title">
                        Sensor readings
                    </component>
                    <p class="section-description">Values from the same reported reading.</p>
                    <dl class="device-sensors">
                        <div v-for="sensor in sensors" :key="sensor">
                            <dt>{{ sensor }}</dt>
                            <dd>
                                <span
                                    v-if="sensor === 'CTS' && cts"
                                    class="cts-badge"
                                    :class="cts === 'Open' ? 'cts-badge-open' : 'cts-badge-closed'"
                                    >{{ cts }}</span
                                >
                                <template v-else>{{ reading(sensor) }}</template>
                            </dd>
                        </div>
                    </dl>
                    <button type="button" class="btn btn-link px-0" @click="exploreHistory">
                        Explore history →
                    </button>
                </section>
            </div>
        </section>

        <section
            v-show="activeSection === 'history'"
            id="device-panel-history"
            ref="historyPanel"
            role="tabpanel"
            aria-labelledby="device-tab-history"
            tabindex="0"
        >
            <HistoryCharts
                :points="currentPoints"
                :refreshed-at="refreshedAt"
                :active="activeSection === 'history'"
                :report-url="links.report"
            />
        </section>

        <section
            v-show="activeSection === 'control'"
            id="device-panel-control"
            role="tabpanel"
            aria-labelledby="device-tab-control"
            tabindex="0"
        >
            <div class="section-heading">
                <div>
                    <component :is="embedded ? 'h3' : 'h2'">Device control</component>
                    <p>Choose automatic tracking or send a manual motor command.</p>
                </div>
            </div>
            <div class="device-control-layout">
                <RemoteControl
                    :key="fieldValues.serial_no || device.serial"
                    :mode="remote.mode"
                    :motor-speed="remote.motor_speed"
                    :serial="fieldValues.serial_no || device.serial"
                    :endpoint="links.remote"
                    :csrf-token="csrfToken"
                    @change="mode = $event.mode"
                />
                <aside class="control-guide" aria-labelledby="control-guide-title">
                    <component :is="embedded ? 'h4' : 'h3'" id="control-guide-title">
                        How control works
                    </component>
                    <dl>
                        <dt>Automatic</dt>
                        <dd>
                            The tracker follows its own tracking program. Manual controls are
                            disabled.
                        </dd>
                        <dt>Remote control</dt>
                        <dd>
                            Set the motor speed from -100 to 100 in steps of 10. Release the slider,
                            or use the arrow keys, to save the command automatically. Zero stops the
                            motor.
                        </dd>
                    </dl>
                    <p>
                        Changing modes sets the manual command to Stop. The reported motor speed
                        updates when the device sends a new reading.
                    </p>
                </aside>
            </div>
        </section>
    </div>
</template>

<style scoped>
.device-page {
    --device-muted: #5c6879;
    --device-border: #e0e6ee;
    color: #202e42;
    padding-bottom: 2rem;
}
.device-page-embedded {
    padding-bottom: 0;
}
.device-page-embedded .device-eyebrow {
    margin-bottom: 0.4rem;
}
.device-page-actions {
    position: absolute;
    top: 0;
    right: 0;
}
.device-heading > div:first-of-type + div {
    padding-right: 3rem;
}
.device-heading {
    position: relative;
    display: block;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.75rem;
}
.device-heading h1 {
    font-size: clamp(1.6rem, 3vw, 2rem);
    font-weight: 650;
    letter-spacing: -0.035em;
    margin: 0.3rem 0 0.5rem;
    overflow-wrap: anywhere;
}
.device-eyebrow {
    color: var(--device-muted);
    font-size: 0.75rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 650;
    margin: 0;
}
.device-identity {
    font-size: 0.85rem;
    color: var(--device-muted);
    margin: 0;
}
.device-identity span {
    color: #35445b;
    margin-left: 0.3rem;
    overflow-wrap: anywhere;
}
.device-heading > .btn {
    flex-shrink: 0;
}
.device-tabs {
    display: flex;
    gap: 1.75rem;
    border-bottom: 1px solid var(--device-border);
    margin-bottom: 1.75rem;
}
.device-tabs button {
    appearance: none;
    background: none;
    border: 0;
    border-bottom: 3px solid transparent;
    color: var(--device-muted);
    padding: 0.75rem 0.2rem 1rem;
    font-size: 0.925rem;
    font-weight: 600;
}
.device-tabs button[aria-selected='true'] {
    color: #084298;
    border-bottom-color: #275bb5;
}
.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: start;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-bottom: 1.25rem;
}
.device-refresh-status {
    color: var(--device-muted);
    font-size: 0.8rem;
    margin: -0.75rem 0 1.25rem;
}
.section-heading h2,
.section-heading h3,
.device-surface h2,
.device-surface h3 {
    font-size: 1.05rem;
    font-weight: 650;
    margin: 0 0 0.45rem;
}
.section-heading p,
.section-description {
    font-size: 0.85rem;
    color: var(--device-muted);
    margin: 0;
}
.section-heading .reading-time {
    font-size: 0.75rem;
    padding-top: 0.25rem;
}
.device-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)) minmax(0, 1.35fr);
    margin-bottom: 1.5rem;
    background: #fff;
    border: 1px solid var(--device-border);
    border-radius: 0.8rem;
}
.device-metrics > div {
    padding: 1.4rem;
    min-width: 0;
}
.device-metrics > div + div {
    border-left: 1px solid var(--device-border);
}
.device-metrics dt {
    font-size: 0.8rem;
    color: var(--device-muted);
    font-weight: 500;
    margin-bottom: 0.75rem;
}
.device-metrics dd {
    font-size: 1.75rem;
    font-weight: 600;
    line-height: 1.3;
    margin: 0;
    overflow-wrap: anywhere;
}
.device-metrics dd span {
    font-size: 0.9rem;
    color: var(--device-muted);
    font-weight: 400;
}
.device-metrics .state-value {
    font-size: 1.2rem;
}
.device-software-versions {
    display: grid;
    gap: 0.55rem;
    margin: 0;
}
.device-software-versions > div {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: baseline;
    gap: 0.6rem;
}
.device-software-versions dt {
    font-size: 0.75rem;
    margin: 0;
}
.device-software-versions dd {
    font-size: 0.9rem;
    text-align: right;
}
.device-overview-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.5rem;
}
.device-surface {
    background: #fff;
    border: 1px solid var(--device-border);
    border-radius: 0.8rem;
    padding: 1.5rem;
}
.device-sensors {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 1.5rem 1rem;
    margin: 1.6rem 0 0.75rem;
}
.device-sensors dt,
.device-details dt {
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--device-muted);
    margin-bottom: 0.4rem;
}
.device-sensors dd {
    margin: 0;
    font-weight: 600;
    overflow-wrap: anywhere;
}
.cts-badge {
    display: inline-block;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.25;
}
.cts-badge-open {
    color: #fff;
    background: #198754;
}
.cts-badge-closed {
    color: #495057;
    background: #e9ecef;
}
.device-header-details {
    margin-top: 1.25rem;
}
.device-details {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 1rem 1.5rem;
    margin: 0;
}
.device-details + .device-details {
    margin-top: 1.25rem;
}
.device-details > div {
    min-width: 0;
}
.device-details dd {
    font-size: 0.9rem;
    margin: 0;
    overflow-wrap: anywhere;
}
.device-control-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
    align-items: start;
    gap: 2rem;
}
.control-guide {
    padding: 0.75rem 0;
    color: var(--device-muted);
    font-size: 0.9rem;
}
.control-guide h3,
.control-guide h4 {
    font-size: 0.9rem;
    color: #202e42;
    font-weight: 650;
    margin-bottom: 1.25rem;
}
.control-guide dt {
    color: #35445b;
    margin-bottom: 0.25rem;
}
.control-guide dd {
    margin-bottom: 1.2rem;
    line-height: 1.65;
}
.control-guide p {
    font-size: 0.8rem;
    line-height: 1.65;
}
@media (max-width: 991px) {
    .device-details {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .device-metrics {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .device-metrics > .device-software-metric {
        grid-column: 1 / -1;
        border-left: 0;
        border-top: 1px solid var(--device-border);
    }
    .device-software-versions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .device-software-versions > div {
        grid-template-columns: minmax(0, 1fr);
        gap: 0.4rem;
    }
    .device-software-versions dd {
        text-align: left;
    }
}
@media (max-width: 575px) {
    .device-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 767px) {
    .device-heading {
        align-items: flex-start;
    }
    .device-heading > .btn {
        font-size: 0.8rem;
    }
    .device-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .device-metrics > div {
        padding: 1rem;
    }
    .device-metrics > div:nth-child(3) {
        border-left: 0;
    }
    .device-metrics > div:nth-child(n + 3) {
        border-top: 1px solid var(--device-border);
    }
    .device-overview-grid,
    .device-control-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
    }
    .device-tabs {
        gap: 1.5rem;
    }
    .device-surface {
        padding: 1.2rem;
    }
}
</style>
