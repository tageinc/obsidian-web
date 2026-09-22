<script setup>
import { ref } from 'vue';
import DeviceForm from '../devices/DeviceForm.vue';
import FormModal from '../../shared/components/FormModal.vue';

defineProps({ creation: { type: Object, required: true } });
const emit = defineEmits(['close']);
const pending = ref(false);
</script>

<template>
    <FormModal
        id="create-device"
        title="Create Device"
        description="Add your device details and installation address."
        close-label="Close creation"
        :pending="pending"
        @close="emit('close')"
    >
        <template #default="{ close }">
            <DeviceForm
                :csrf-token="creation.csrfToken"
                :action="creation.action"
                :values="creation.values"
                :errors="creation.errors"
                :session-error="creation.sessionError"
                creating
                creation-modal
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
        </template>
    </FormModal>
</template>
