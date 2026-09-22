<script setup>
import FormField from './FormField.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    creating: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const addressFields = [
    { name: 'address_1', label: 'Address line 1', autocomplete: 'address-line1', column: 'col-12' },
    {
        name: 'address_2',
        label: 'Address line 2 (optional)',
        autocomplete: 'address-line2',
        column: 'col-12',
    },
    { name: 'city', label: 'City', autocomplete: 'address-level2', column: 'col-md-6' },
    {
        name: 'address_state',
        label: 'State / province / region',
        autocomplete: 'address-level1',
        column: 'col-md-6',
    },
    { name: 'zip_code', label: 'Postal code', autocomplete: 'postal-code', column: 'col-md-6' },
    { name: 'country', label: 'Country', autocomplete: 'country-name', column: 'col-md-6' },
];
const coordinates = [
    { name: 'latitude', label: 'Latitude', limit: 90, other: 'longitude' },
    { name: 'longitude', label: 'Longitude', limit: 180, other: 'latitude' },
];
function update(name, value) {
    emit('update:modelValue', { ...props.modelValue, [name]: value });
}
</script>

<template>
    <FormField
        name="alias"
        label="Device name"
        :model-value="modelValue.alias"
        :errors="errors.alias"
        maxlength="255"
        required
        @update:model-value="update('alias', $event)"
    />
    <fieldset class="mb-3">
        <legend class="h5 mb-3">Installation address</legend>
        <div class="row">
            <div v-for="field in addressFields" :key="field.name" :class="field.column">
                <FormField
                    :name="field.name"
                    :label="field.label"
                    :autocomplete="field.autocomplete"
                    :model-value="modelValue[field.name]"
                    :errors="errors[field.name]"
                    maxlength="255"
                    :required="field.name !== 'address_2'"
                    @update:model-value="update(field.name, $event)"
                />
            </div>
        </div>
    </fieldset>
    <fieldset class="mb-3">
        <legend class="h5 mb-2">Device coordinates</legend>
        <p id="coordinates-help" class="text-muted small">
            Enter the installation location in decimal degrees.<template v-if="!creating">
                If coordinates are not available, leave both fields blank.</template
            >
        </p>
        <div class="row">
            <div v-for="field in coordinates" :key="field.name" class="col-md-6">
                <FormField
                    :name="field.name"
                    :label="field.label"
                    type="number"
                    step="any"
                    :min="-field.limit"
                    :max="field.limit"
                    :model-value="modelValue[field.name]"
                    :errors="errors[field.name]"
                    :required="creating || String(modelValue[field.other] ?? '') !== ''"
                    @update:model-value="update(field.name, $event)"
                />
            </div>
        </div>
    </fieldset>
</template>
