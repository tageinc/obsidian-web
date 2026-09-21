<script setup>
import HistoryCharts from './HistoryCharts.vue';
import RemoteControl from './RemoteControl.vue';
defineProps({
    device: { type: Object, required: true },
    status: { type: Object, required: true },
    remote: { type: Object, required: true },
    points: { type: Array, default: () => [] },
    links: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});
</script>
<template>
    <div class="container">
        <a :href="links.dashboard" class="btn btn-secondary mb-3">Back</a>
        <h1 class="h3">{{ device.alias || 'Device information' }}</h1>
        <div class="row">
            <div class="col-md-4">
                <section class="card mb-3" aria-labelledby="device-title">
                    <h2 id="device-title" class="card-header h6">Device Info</h2>
                    <dl class="card-body">
                        <template v-for="(value, label) in device.details" :key="label"
                            ><dt>{{ label }}</dt>
                            <dd>{{ value || 'N/A' }}</dd></template
                        >
                    </dl>
                </section>
            </div>
            <div class="col-md-8">
                <section class="card mb-3" aria-labelledby="status-title">
                    <h2 id="status-title" class="card-header h6">Current Status</h2>
                    <dl class="card-body row">
                        <div v-for="(value, label) in status" :key="label" class="col-md-6">
                            <dt>{{ label }}</dt>
                            <dd>{{ value ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </section>
                <RemoteControl
                    :key="device.serial"
                    :mode="remote.mode"
                    :motor-speed="remote.motor_speed"
                    :serial="device.serial"
                    :endpoint="links.remote"
                    :csrf-token="csrfToken"
                />
            </div>
        </div>
        <HistoryCharts :points="points" />
    </div>
</template>
