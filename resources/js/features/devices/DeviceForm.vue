<script setup>
import { ref, watch } from 'vue';
import DeviceFields from '../../shared/components/DeviceFields.vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm.js';

const props = defineProps({
    registering: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    action: { type: String, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    registrationModal: { type: Boolean, default: false },
    hardwareOptions: { type: Array, default: () => [] },
    fixedIdentity: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['pending-change']);
const fields = [
    'alias',
    'address_1',
    'address_2',
    'city',
    'state',
    'zip_code',
    'country',
    'latitude',
    'longitude',
];
if (props.registering) fields.push('hardware_id', 'serial_no', 'sku', 'order_no');
const form = ref(Object.fromEntries(fields.map((field) => [field, props.values[field] ?? ''])));
const { pending, submit } = useNativeForm();
watch(pending, (value) => emit('pending-change', value), { immediate: true, flush: 'sync' });
const identityFields = [
    { name: 'serial_no', label: 'Serial number' },
    { name: 'sku', label: 'SKU' },
    { name: 'order_no', label: 'Order number' },
];
</script>

<template>
    <FormFeedback :errors="errors" :success="success" :session-error="sessionError" />
    <template v-if="!registering">
        <h2 class="h5 mb-3">Registered device</h2>
        <dl class="row">
            <dt class="col-sm-4">Hardware</dt>
            <dd class="col-sm-8">
                {{ fixedIdentity.hardwareName ?? 'Unknown' }}
            </dd>
            <dt class="col-sm-4">Serial number</dt>
            <dd class="col-sm-8">{{ fixedIdentity.serialNo }}</dd>
            <dt class="col-sm-4">SKU</dt>
            <dd class="col-sm-8">{{ fixedIdentity.sku ?? 'Not provided' }}</dd>
            <dt class="col-sm-4">Order number</dt>
            <dd class="col-sm-8">
                {{ fixedIdentity.orderNo ?? 'Not provided' }}
            </dd>
        </dl>
    </template>
    <form id="device-form" method="POST" :action="action" :aria-busy="pending" @submit="submit">
        <input type="hidden" name="_token" :value="csrfToken" />
        <input v-if="registrationModal" type="hidden" name="_registration_modal" value="1" />
        <input v-if="!registering" type="hidden" name="_method" value="PUT" />
        <fieldset v-if="registering" class="mb-3">
            <legend class="h5 mb-3">Device details</legend>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="hardware_id" class="form-label">Hardware</label>
                    <select
                        id="hardware_id"
                        v-model="form.hardware_id"
                        name="hardware_id"
                        class="form-select"
                        :class="{ 'is-invalid': errors.hardware_id?.length }"
                        required
                        :aria-invalid="errors.hardware_id?.length ? 'true' : undefined"
                        :aria-describedby="
                            errors.hardware_id?.length ? 'hardware_id-errors' : undefined
                        "
                    >
                        <option
                            v-for="hardware in hardwareOptions"
                            :key="hardware.id"
                            :value="hardware.id"
                        >
                            {{ hardware.name }}
                        </option>
                    </select>
                    <div
                        v-if="errors.hardware_id?.length"
                        id="hardware_id-errors"
                        class="invalid-feedback"
                    >
                        <div v-for="message in errors.hardware_id" :key="message">
                            {{ message }}
                        </div>
                    </div>
                </div>
                <div v-for="field in identityFields" :key="field.name" class="col-md-6">
                    <FormField
                        v-model="form[field.name]"
                        :name="field.name"
                        :label="field.label"
                        maxlength="255"
                        required
                        :errors="errors[field.name]"
                    />
                </div>
            </div>
        </fieldset>
        <DeviceFields v-model="form" :errors="errors" :registering="registering" />
        <div class="d-flex flex-wrap align-items-center gap-2">
            <slot name="actions" :pending="pending" />
            <button type="submit" class="btn btn-primary" :disabled="pending">
                {{ pending ? 'Saving device…' : registering ? 'Register device' : 'Update device' }}
            </button>
        </div>
        <span class="visually-hidden" role="status">{{
            pending ? 'Saving device. Please wait.' : ''
        }}</span>
    </form>
</template>
