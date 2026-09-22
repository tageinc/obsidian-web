<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import DeviceFields from '../../shared/components/DeviceFields.vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm.js';
import { requestJson } from '../../shared/api/client.js';

const props = defineProps({
    creating: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    action: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    creationModal: { type: Boolean, default: false },
    asyncSubmit: { type: Boolean, default: false },
    fixedIdentity: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['pending-change', 'saved']);
const fields = [
    'name',
    'address_1',
    'address_2',
    'city',
    'address_state',
    'zip_code',
    'country',
    'latitude',
    'longitude',
];
fields.push('serial_no', 'sku', 'order_no');
const form = ref(Object.fromEntries(fields.map((field) => [field, props.values[field] ?? ''])));
const { pending, submit } = useNativeForm();
watch(pending, (value) => emit('pending-change', value), { immediate: true, flush: 'sync' });
const feedback = ref(null);
const submitted = ref(false);
const clientErrors = ref({});
const clientError = ref(null);
const errors = computed(() => (submitted.value ? clientErrors.value : props.errors));
const sessionError = computed(() => (submitted.value ? clientError.value : props.sessionError));
async function save(event) {
    if (!props.asyncSubmit) return submit(event);
    event.preventDefault();
    if (pending.value) return;
    pending.value = true;
    submitted.value = true;
    clientErrors.value = {};
    clientError.value = null;
    try {
        const result = await requestJson(props.action, {
            method: 'PUT',
            data: { ...form.value },
            csrfToken: props.csrfToken,
            headers: { 'X-Obsidian-Modal': '1' },
        });
        emit('saved', result);
    } catch (error) {
        clientErrors.value = error.fieldErrors || {};
        if (!Object.keys(clientErrors.value).length) {
            clientError.value = error.message || 'The device could not be saved. Please try again.';
        }
        await nextTick();
        const alert = feedback.value?.querySelector('[role="alert"]');
        alert?.setAttribute('tabindex', '-1');
        alert?.focus();
    } finally {
        pending.value = false;
    }
}
const identityFields = [
    { name: 'serial_no', label: 'Serial number' },
    { name: 'sku', label: 'SKU' },
    { name: 'order_no', label: 'Order number' },
];
</script>

<template>
    <div ref="feedback">
        <FormFeedback
            :errors="errors"
            :success="submitted ? null : success"
            :session-error="sessionError"
        />
    </div>
    <form id="device-form" method="POST" :action="action" :aria-busy="pending" @submit="save">
        <input type="hidden" name="_token" :value="csrfToken" />
        <input v-if="creationModal" type="hidden" name="_creation_modal" value="1" />
        <input v-if="!creating" type="hidden" name="_method" value="PUT" />
        <fieldset class="mb-3">
            <legend class="h5 mb-3">Device details</legend>
            <div class="row">
                <div v-for="field in identityFields" :key="field.name" class="col-md-6">
                    <FormField
                        v-model="form[field.name]"
                        :name="field.name"
                        :label="field.label"
                        maxlength="255"
                        :required="creating || field.name === 'serial_no'"
                        :errors="errors[field.name]"
                    />
                </div>
            </div>
        </fieldset>
        <DeviceFields v-model="form" :errors="errors" :creating="creating" />
        <div class="d-flex flex-wrap align-items-center gap-2">
            <slot name="actions" :pending="pending" />
            <button type="submit" class="btn btn-primary" :disabled="pending">
                {{ pending ? 'Saving device…' : creating ? 'Create Device' : 'Update device' }}
            </button>
        </div>
        <span class="visually-hidden" role="status">{{
            pending ? 'Saving device. Please wait.' : ''
        }}</span>
    </form>
</template>
