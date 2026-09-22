<script setup>
import DeviceForm from './DeviceForm.vue';

defineProps({
    creating: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    action: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    links: { type: Object, required: true },
    fixedIdentity: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <nav aria-label="Breadcrumb" class="mb-2">
                    <ol class="list-unstyled d-flex flex-wrap small text-muted mb-0">
                        <li>
                            <a :href="links.dashboard" class="text-muted text-nowrap">Dashboard</a
                            ><span class="mx-2" aria-hidden="true">›</span>
                        </li>
                        <li aria-current="page">
                            {{ creating ? 'Create Device' : 'Edit device' }}
                        </li>
                    </ol>
                </nav>
                <h1 class="h3 mb-3">{{ creating ? 'Create Device' : 'Edit device' }}</h1>
                <p class="text-muted">
                    {{
                        creating
                            ? 'Add your device details and installation address, then save everything together.'
                            : 'Update your device name and installation details, then save everything together.'
                    }}
                </p>
                <div class="card">
                    <div class="card-body">
                        <DeviceForm
                            :creating="creating"
                            :csrf-token="csrfToken"
                            :action="action"
                            :values="values"
                            :errors="errors"
                            :success="success"
                            :session-error="sessionError"
                            :fixed-identity="fixedIdentity"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
