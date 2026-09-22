<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
    summary: {
        type: String,
        default: 'Please correct the highlighted fields. Your changes have not been saved.',
    },
});
const feedback = ref(null);
const successDismissed = ref(false);
watch(() => props.success, () => { successDismissed.value = false; });
const messages = computed(() =>
    Object.entries(props.errors).flatMap(([field, errors]) =>
        errors.map((message) => ({ field, message })),
    ),
);
onMounted(() => {
    if (messages.value.length) feedback.value?.focus();
});
</script>

<template>
    <div v-if="success && !successDismissed" class="alert alert-success alert-dismissible" role="status">
        {{ success }}
        <button type="button" class="btn-close" aria-label="Dismiss notification" @click="successDismissed = true"></button>
    </div>
    <div v-if="sessionError" class="alert alert-danger" role="alert">{{ sessionError }}</div>
    <div
        v-if="messages.length"
        ref="feedback"
        class="alert alert-danger"
        role="alert"
        tabindex="-1"
    >
        <p class="mb-1">{{ summary }}</p>
        <ul class="mb-0">
            <li v-for="({ field, message }, index) in messages" :key="index">
                <a :href="`#${field}`" class="alert-link">{{ message }}</a>
            </li>
        </ul>
    </div>
</template>
