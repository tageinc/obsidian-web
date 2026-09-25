<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, default: () => ({ prefixes: [], versions: [] }) },
    count: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['apply', 'clear']);
const root = ref(null);
const trigger = ref(null);
const prefixHost = ref(null);
const versionHost = ref(null);
const open = ref(false);
const from = ref('');
const to = ref('');
let prefixes;
let versions;

function makeSelect(host, field, label) {
    const select = document.createElement('select');
    select.multiple = true;
    select.setAttribute('aria-label', label);
    host.append(select);
    const control = new TomSelect(select, {
        options: (props.options[field] || []).map((value) => ({ value, label: value })),
        items: props.filters[field] || [],
        valueField: 'value',
        labelField: 'label',
        searchField: ['label'],
        plugins: ['remove_button'],
        create: false,
        maxItems: 100,
        maxOptions: null,
        placeholder: field === 'prefixes' ? 'All prefixes' : 'All versions',
        openOnFocus: false,
    });
    select.setAttribute('aria-hidden', 'true');
    control.control_input.id = `${props.kind}-filter-${field}`;
    control.control_input.setAttribute('aria-label', label);
    if (props.disabled) control.disable();
    return control;
}
function restore() {
    prefixes?.setValue(props.filters.prefixes || [], true);
    versions?.setValue(props.filters.versions || [], true);
    from.value = props.filters.from || '';
    to.value = props.filters.to || '';
}
function close(focus = false) {
    open.value = false;
    prefixes?.close();
    versions?.close();
    restore();
    if (focus) trigger.value?.focus();
}
async function toggle() {
    if (open.value) return close();
    restore();
    open.value = true;
    await nextTick();
    prefixes?.focus();
}
function escape(event) {
    event.preventDefault();
    event.stopPropagation();
    if (prefixes?.isOpen || versions?.isOpen) {
        prefixes?.close();
        versions?.close();
    } else close(true);
}
function outside(event) {
    if (!root.value?.contains(event.target)) close();
}
function enter(event) {
    if (event.defaultPrevented || event.target.closest('button')) return;
    event.preventDefault();
    // A suggestion refresh may still be pending when Enter reaches a Tom Select input.
    if (event.target.type === 'date') apply();
}
function apply() {
    const form = root.value.closest('form');
    if (!form.reportValidity()) return;
    emit('apply', {
        prefixes: [...prefixes.items],
        versions: [...versions.items],
        from: from.value,
        to: to.value,
    });
    open.value = false;
    prefixes.close();
    versions.close();
    trigger.value?.focus();
}
onMounted(() => {
    prefixes = makeSelect(prefixHost.value, 'prefixes', `${props.title} prefixes`);
    versions = makeSelect(versionHost.value, 'versions', `${props.title} versions`);
    restore();
    document.addEventListener('pointerdown', outside);
});
watch(() => props.filters, restore, { deep: true });
watch(
    () => props.disabled,
    (disabled) => {
        for (const control of [prefixes, versions]) {
            if (disabled) control?.disable();
            else control?.enable();
        }
        if (disabled) close();
    },
);
watch(
    () => props.options,
    () => {
        for (const [field, control] of [
            ['prefixes', prefixes],
            ['versions', versions],
        ]) {
            if (!control) continue;
            control.clear(true);
            control.clearOptions();
            control.addOptions(
                (props.options[field] || []).map((value) => ({ value, label: value })),
            );
        }
        restore();
    },
    { deep: true },
);
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', outside);
    prefixes?.destroy();
    versions?.destroy();
});
</script>

<template>
    <div ref="root" class="release-filters" @keydown.esc.capture="escape">
        <button
            ref="trigger"
            type="button"
            class="btn release-filter-trigger"
            :aria-expanded="open"
            :aria-controls="`${kind}-filter-panel`"
            :disabled="disabled"
            @click="toggle"
        >
            <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                aria-hidden="true"
            >
                <path d="M4 5h16l-6 7v6l-4 2v-8z" />
            </svg>
            Filters <span v-if="count" class="release-filter-count">{{ count }}</span>
            <svg
                width="12"
                height="12"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path :d="open ? 'm3 10 5-5 5 5' : 'm3 6 5 5 5-5'" />
            </svg>
        </button>
        <div
            v-show="open"
            :id="`${kind}-filter-panel`"
            class="release-filter-panel"
            role="group"
            :aria-label="`${title} filters`"
            @keydown.enter="enter"
        >
            <div class="filter-panel-heading">
                <strong>Filter releases</strong
                ><span class="small text-secondary">Combine any filters</span>
            </div>
            <div class="release-filter-grid">
                <div>
                    <label :for="`${kind}-filter-prefixes`" class="form-label">Prefix</label>
                    <div ref="prefixHost" v-once></div>
                </div>
                <div>
                    <label :for="`${kind}-filter-versions`" class="form-label">Version</label>
                    <div ref="versionHost" v-once></div>
                </div>
                <div>
                    <label :for="`${kind}-filter-from`" class="form-label">Uploaded from</label
                    ><input
                        :id="`${kind}-filter-from`"
                        v-model="from"
                        type="date"
                        class="form-control form-control-sm"
                        :aria-label="`${title} uploaded from`"
                        :max="to || undefined"
                        :disabled="disabled || !open"
                    />
                </div>
                <div>
                    <label :for="`${kind}-filter-to`" class="form-label">Uploaded to</label
                    ><input
                        :id="`${kind}-filter-to`"
                        v-model="to"
                        type="date"
                        class="form-control form-control-sm"
                        :aria-label="`${title} uploaded to`"
                        :min="from || undefined"
                        :disabled="disabled || !open"
                    />
                </div>
            </div>
            <div class="filter-panel-actions">
                <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="disabled"
                    @click="apply"
                >
                    Apply filters
                </button>
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    :disabled="disabled"
                    @click="
                        close(true);
                        emit('clear');
                    "
                >
                    Reset filters
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.release-filter-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: 1px solid #dce1e9;
    background: #fff;
    color: #414b5b;
    font-size: 0.875rem;
    min-height: 2.5rem;
    white-space: nowrap;
}
.release-filter-trigger:hover,
.release-filter-trigger[aria-expanded='true'] {
    background: #f0f3fa;
    border-color: #b4bfd3;
}
.release-filter-count {
    display: inline-grid;
    place-items: center;
    border-radius: 1rem;
    min-width: 1.25rem;
    background: #e4ebfb;
    color: #084298;
    font-size: 0.75rem;
}
.release-filter-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    width: min(30rem, 100%);
    padding: 1rem;
    border: 1px solid #dce1e9;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 10px 30px #17233c1f;
    z-index: 20;
}
.filter-panel-heading {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}
.release-filter-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.release-filter-grid > div {
    min-width: 0;
}
.form-label {
    font-size: 0.8125rem;
    font-weight: 600;
}
.filter-panel-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #edf0f4;
}
:deep(.ts-control),
:deep(.ts-dropdown) {
    border-color: #cbd3df;
    background: #fff;
    color: #212b3b;
    border-radius: 0.375rem;
    font-size: 0.8125rem;
}
:deep(.ts-control) {
    min-height: 2.35rem;
    max-height: 8rem;
    overflow-y: auto;
    padding: 0.45rem 0.5rem;
}
:deep(.ts-wrapper.multi .ts-control .item) {
    background: #eef2f8;
    border-radius: 0.2rem;
    color: #24334b;
    overflow-wrap: anywhere;
}
:deep(.ts-control input) {
    color: #212b3b;
    min-width: 3rem;
}
:deep(.ts-control input::placeholder) {
    color: #657184;
    opacity: 1;
}
:deep(.ts-dropdown .active) {
    color: #084298;
    background: #edf2ff;
}
:deep(.ts-dropdown-content) {
    max-height: 12rem;
}
:deep(.ts-wrapper.focus .ts-control) {
    outline: 2px solid #526cbd;
    outline-offset: 2px;
}
@media (max-width: 575.98px) {
    .release-filter-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
