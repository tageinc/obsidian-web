<script setup>
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    id: { type: String, default: null },
    name: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    errors: { type: Array, default: () => [] },
    help: { type: String, default: '' },
});
defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || props.name);
const descriptions = computed(
    () =>
        [
            props.help ? `${inputId.value}-help` : null,
            props.errors.length ? `${inputId.value}-errors` : null,
        ]
            .filter(Boolean)
            .join(' ') || undefined,
);
</script>

<template>
    <div class="mb-3">
        <label :for="inputId" class="form-label">{{ label }}</label>
        <input
            v-bind="$attrs"
            :id="inputId"
            :name="name"
            :type="type"
            :value="modelValue ?? ''"
            class="form-control"
            :class="{ 'is-invalid': errors.length }"
            :aria-invalid="errors.length ? 'true' : undefined"
            :aria-describedby="descriptions"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <div v-if="help" :id="`${inputId}-help`" class="form-text">{{ help }}</div>
        <div v-if="errors.length" :id="`${inputId}-errors`" class="invalid-feedback">
            <div v-for="(message, index) in errors" :key="index">{{ message }}</div>
        </div>
    </div>
</template>
