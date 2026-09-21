<script setup>
import { ref } from 'vue';
import DeviceForm from '../devices/DeviceForm.vue';
import FormModal from '../../shared/components/FormModal.vue';

defineProps({ registration: { type: Object, required: true } });
const emit = defineEmits(['close']);
const pending = ref(false);
</script>

<template>
    <FormModal
        id="register-device"
        title="Register device"
        description="Add your device details and installation address."
        close-label="Close registration"
        :pending="pending"
        @close="emit('close')"
    >
        <template #default="{ close }">
            <DeviceForm
                :csrf-token="registration.csrfToken"
                :action="registration.action"
                :values="registration.values"
                :hardware-options="registration.hardwareOptions"
                :errors="registration.errors"
                :session-error="registration.sessionError"
                registering
                registration-modal
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
