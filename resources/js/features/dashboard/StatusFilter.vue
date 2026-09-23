<script setup>
import { nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

const props = defineProps({
    value: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    stateValue: { type: Array, default: () => [] },
    stateOptions: { type: Array, default: () => [] },
});
const root = ref(null);
const container = ref(null);
const stateContainer = ref(null);
const trigger = ref(null);
const open = ref(false);
const applied = ref([...props.value]);
const appliedState = ref([...props.stateValue]);
let control;
let stateControl;
const stateOption = (value) => ({
    value,
    label: value.charAt(0).toUpperCase() + value.slice(1),
});
onMounted(() => {
    const select = document.createElement('select');
    select.multiple = true;
    container.value.append(select);
    control = new TomSelect(select, {
        options: props.options.map((value) => ({ value, label: value })),
        items: [...props.value],
        valueField: 'value',
        labelField: 'label',
        searchField: ['label'],
        plugins: ['remove_button'],
        placeholder: 'Status...',
    });
    control.control_input.setAttribute('aria-label', 'Status');
    const stateSelect = document.createElement('select');
    stateSelect.multiple = true;
    stateContainer.value.append(stateSelect);
    stateControl = new TomSelect(stateSelect, {
        options: props.stateOptions.map(stateOption),
        items: [...props.stateValue],
        valueField: 'value',
        labelField: 'label',
        searchField: ['label'],
        plugins: ['remove_button'],
        placeholder: 'State...',
    });
    stateControl.control_input.setAttribute('aria-label', 'State');
    document.addEventListener('pointerdown', outside);
});
function outside(event) {
    if (!root.value?.contains(event.target)) open.value = false;
}
function toggle() {
    open.value = !open.value;
    if (open.value) {
        control.setValue(applied.value, true);
        stateControl.setValue(appliedState.value, true);
    }
}
function close() {
    open.value = false;
    trigger.value?.focus();
}
async function apply(clear = false) {
    applied.value = clear ? [] : [...control.items];
    appliedState.value = clear ? [] : [...stateControl.items];
    if (clear) {
        control.clear(true);
        stateControl.clear(true);
        const search = root.value.closest('form')?.querySelector('[name="search"]');
        if (search) {
            search.value = '';
            search.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
    close();
    await nextTick();
    root.value.closest('form')?.requestSubmit();
}
watch(
    () => props.value,
    (value) => {
        applied.value = [...value];
        control?.setValue(value, true);
    },
);
watch(
    () => props.stateValue,
    (value) => {
        appliedState.value = [...value];
        stateControl?.setValue(value, true);
    },
);
watch(
    () => props.options,
    (options) => {
        if (!control) return;
        control.clear(true);
        control.clearOptions();
        control.addOptions(options.map((value) => ({ value, label: value })));
        control.setValue(props.value, true);
    },
);
watch(
    () => props.stateOptions,
    (options) => {
        if (!stateControl) return;
        stateControl.clear(true);
        stateControl.clearOptions();
        stateControl.addOptions(options.map(stateOption));
        stateControl.setValue(props.stateValue, true);
    },
);
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outside);
    control?.destroy();
    stateControl?.destroy();
});
</script>

<template>
    <div ref="root" class="device-filter" @keydown.esc.prevent.stop="close">
        <input
            v-for="status in applied"
            :key="status"
            type="hidden"
            name="status[]"
            :value="status"
        />
        <input
            v-for="state in appliedState"
            :key="state"
            type="hidden"
            name="state[]"
            :value="state"
        />
        <button
            ref="trigger"
            type="button"
            class="filter-trigger"
            :aria-expanded="open"
            aria-controls="device-filter-panel"
            @click="toggle"
        >
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M1 2h14L10 8v5l-4 2V8z" />
            </svg>
            Filters
            <svg
                width="12"
                height="12"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path :d="open ? 'm3 10 5-5 5 5' : 'm3 6 5 5 5-5'" />
            </svg>
        </button>
        <div
            v-show="open"
            id="device-filter-panel"
            class="filter-panel"
            role="group"
            aria-label="Device filters"
        >
            <label class="fw-bolder" @click="control?.focus()">Status</label>
            <div ref="container" v-once></div>
            <label class="fw-bolder mt-2" @click="stateControl?.focus()">State</label>
            <div ref="stateContainer" v-once></div>
            <div class="mt-2 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary" @click="apply()">Apply</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="apply(true)">
                    Clear
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.device-filter {
    position: relative;
    flex: 0 0 auto;
}
.filter-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem;
    background: none;
    border: none;
    color: #525a62;
    font-weight: 500;
    white-space: nowrap;
}
.filter-panel {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: min(300px, calc(100vw - 2rem));
    padding: 0.5rem 0.65rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 8px 20px rgb(0 0 0 / 12%);
    z-index: 1060;
}
:deep(.ts-control),
:deep(.ts-dropdown) {
    background-color: #fff;
    border-color: #cbd5e1;
    color: #111827;
}
:deep(.ts-control input),
:deep(.ts-control .item),
:deep(.ts-dropdown .option) {
    color: #111827;
}
</style>
