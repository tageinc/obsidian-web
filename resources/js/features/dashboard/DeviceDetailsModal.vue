<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import DeviceForm from '../devices/DeviceForm.vue';
import ViewDevicePage from '../solar-tracker/ViewDevicePage.vue';
import FormModal from '../../shared/components/FormModal.vue';
import { requestJson } from '../../shared/api/client.js';

const props = defineProps({
    device: { type: Object, required: true },
    mode: { type: String, default: 'view' },
});
const emit = defineEmits(['close', 'edit', 'saved']);
const payload = ref(null);
const loading = ref(true);
const error = ref('');
const pending = ref(false);
const content = ref(null);
const editing = computed(() => props.mode === 'edit');
let controller;
let revision = 0;

async function load() {
    const current = ++revision;
    controller?.abort();
    controller = new AbortController();
    const { signal } = controller;
    const url = editing.value ? props.device.links.edit : props.device.links.view;
    const page = editing.value ? 'edit-device' : 'view-device';
    payload.value = null;
    error.value = '';
    loading.value = true;
    try {
        const response = await requestJson(url, {
            signal,
            headers: { 'X-Obsidian-Modal': '1' },
        });
        if (signal.aborted || current !== revision) return;
        if (response?.page !== page || !response.props || typeof response.props !== 'object') {
            throw new Error('This device could not be loaded. Please try again.');
        }
        payload.value = response.props;
    } catch (failure) {
        if (signal.aborted || current !== revision || failure.name === 'AbortError') return;
        if (failure.status === 409) {
            // Preserve the existing document route when this Vue page is disabled.
            window.location.assign(url);
            return;
        }
        error.value = failure.message || 'This device could not be loaded. Please try again.';
    } finally {
        if (!signal.aborted && current === revision) {
            loading.value = false;
            await nextTick();
            content.value?.querySelector('[role="alert"]')?.focus();
        }
    }
}
watch(
    () => [props.device.id, props.mode],
    () => {
        void load();
        void nextTick(() => content.value?.closest('dialog')?.querySelector('h2')?.focus());
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    revision++;
    controller?.abort();
});
</script>

<template>
    <FormModal
        id="device-dialog"
        :title="editing ? 'Edit device' : 'View device'"
        :description="device.alias"
        close-label="Close device"
        :wide="!editing"
        :pending="pending"
        @close="emit('close')"
    >
        <template #default="{ close }">
            <div ref="content" :aria-busy="loading">
                <p v-if="loading" role="status">Loading device…</p>
                <div v-else-if="error" class="alert alert-danger mb-0" role="alert" tabindex="-1">
                    <p>{{ error }}</p>
                    <button type="button" class="btn btn-outline-primary" @click="load">
                        Try again
                    </button>
                </div>
                <DeviceForm
                    v-else-if="payload && editing"
                    v-bind="payload"
                    async-submit
                    @saved="emit('saved')"
                    @pending-change="pending = $event"
                >
                    <template #actions>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="pending"
                            @click="close"
                        >
                            Cancel
                        </button>
                    </template>
                </DeviceForm>
                <ViewDevicePage
                    v-else-if="payload"
                    v-bind="payload"
                    embedded
                    @edit="emit('edit')"
                />
            </div>
        </template>
    </FormModal>
</template>
