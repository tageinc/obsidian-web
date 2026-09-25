<script setup>
import { computed } from 'vue';
import WorkspaceFilters from './WorkspaceFilters.vue';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, default: () => ({ prefixes: [], versions: [] }) },
    count: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
});
defineEmits(['apply', 'clear']);
const fields = computed(() => [
    {
        key: 'prefixes',
        label: 'Prefix',
        ariaLabel: `${props.title} prefixes`,
        placeholder: 'All prefixes',
        options: (props.options.prefixes || []).map((value) => ({ value, label: value })),
    },
    {
        key: 'versions',
        label: 'Version',
        ariaLabel: `${props.title} versions`,
        placeholder: 'All versions',
        options: (props.options.versions || []).map((value) => ({ value, label: value })),
    },
]);
</script>

<template>
    <WorkspaceFilters
        :kind="kind"
        :title="title"
        :filters="filters"
        :fields="fields"
        :count="count"
        :disabled="disabled"
        @apply="$emit('apply', $event)"
        @clear="$emit('clear')"
    />
</template>
