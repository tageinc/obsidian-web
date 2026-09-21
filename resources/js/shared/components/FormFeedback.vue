<script setup>
import { computed, onMounted, ref } from 'vue';

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
    <div v-if="success" class="alert alert-success" role="status">{{ success }}</div>
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
