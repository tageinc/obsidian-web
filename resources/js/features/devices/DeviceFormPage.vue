<script setup>
import DeviceForm from './DeviceForm.vue';

defineProps({
    registering: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    action: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    links: { type: Object, required: true },
    hardwareOptions: { type: Array, default: () => [] },
    fixedIdentity: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <a :href="links.dashboard" class="btn btn-outline-secondary mb-3"
                    >Back to dashboard</a
                >
                <h1 class="h3 mb-3">{{ registering ? 'Register device' : 'Edit device' }}</h1>
                <p class="text-muted">
                    {{
                        registering
                            ? 'Add your device details and installation address, then save everything together.'
                            : 'Update your device name and installation details, then save everything together.'
                    }}
                </p>
                <div class="card">
                    <div class="card-body">
                        <DeviceForm
                            :registering="registering"
                            :csrf-token="csrfToken"
                            :action="action"
                            :values="values"
                            :errors="errors"
                            :success="success"
                            :session-error="sessionError"
                            :hardware-options="hardwareOptions"
                            :fixed-identity="fixedIdentity"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
