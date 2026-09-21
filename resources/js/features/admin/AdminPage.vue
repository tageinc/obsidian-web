<script setup>
import { ref } from 'vue';
import UploadSection from './UploadSection.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm.js';

const props = defineProps({
    csrfToken: { type: String, required: true },
    links: { type: Object, required: true },
    firmware: { type: Object, required: true },
    config: { type: Object, required: true },
    values: { type: Object, default: () => ({}) },
    activeUpload: { type: String, default: 'firmware' },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
});
const { pending, submit } = useNativeForm();
const active = ref(props.activeUpload);
function upload(event, kind) {
    if (!pending.value && !event.defaultPrevented) active.value = kind;
    submit(event);
}
</script>

<template>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h1 class="h3 mb-0">Admin Control Center</h1>
            <a :href="links.dashboard">Back to dashboard</a>
        </div>
        <FormFeedback :success="success" :session-error="sessionError" />
        <UploadSection
            kind="firmware"
            title="Firmware Updates"
            :action="links.uploadFirmware"
            :csrf-token="csrfToken"
            :admin-url="links.admin"
            :rows="firmware.rows"
            :pagination="firmware.pagination"
            :other-size="config.pagination.perPage"
            :values="activeUpload === 'firmware' ? values : {}"
            :errors="activeUpload === 'firmware' ? errors : {}"
            :pending="pending"
            :active="active === 'firmware'"
            @submit="upload($event, 'firmware')"
        />
        <UploadSection
            kind="config"
            title="Config Updates"
            :action="links.uploadConfig"
            :csrf-token="csrfToken"
            :admin-url="links.admin"
            :rows="config.rows"
            :pagination="config.pagination"
            :other-size="firmware.pagination.perPage"
            :values="activeUpload === 'config' ? values : {}"
            :errors="activeUpload === 'config' ? errors : {}"
            :pending="pending"
            :active="active === 'config'"
            @submit="upload($event, 'config')"
        />
    </div>
</template>
