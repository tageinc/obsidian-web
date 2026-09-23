<script setup>
import { nextTick, ref } from 'vue';
import { requestJson } from '../../shared/api/client.js';
const props = defineProps({
    label: { type: String, required: true },
    field: { type: String, required: true },
    value: { type: [String, Number], default: null },
    endpoint: { type: String, required: true },
    csrfToken: { type: String, required: true },
    numeric: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);
const editing = ref(false);
const draft = ref('');
const pending = ref(false);
const error = ref('');
const input = ref(null);
const trigger = ref(null);
async function start() {
    draft.value = props.value ?? '';
    error.value = '';
    editing.value = true;
    await nextTick();
    input.value?.focus();
}
async function cancel() {
    if (pending.value) return;
    editing.value = false;
    error.value = '';
    await nextTick();
    trigger.value?.focus();
}
async function save() {
    if (pending.value) return;
    pending.value = true;
    error.value = '';
    try {
        const result = await requestJson(props.endpoint, {
            method: 'PATCH',
            csrfToken: props.csrfToken,
            data: { [props.field]: draft.value === '' ? null : draft.value },
        });
        emit('saved', result.values);
        pending.value = false;
        await cancel();
    } catch (failure) {
        error.value = failure.fieldErrors?.[props.field]?.[0] || failure.message;
    } finally {
        pending.value = false;
    }
}
</script>
<template>
    <span class="inline-device-field">
        <button
            v-if="!editing"
            ref="trigger"
            type="button"
            class="field-read"
            :aria-label="`Edit ${label}`"
            @click="start"
        >
            {{ value || value === 0 ? value : '—' }}
        </button>
        <form v-else class="field-editor" @submit.prevent="save" @keydown.esc.prevent="cancel">
            <input
                ref="input"
                v-model="draft"
                :aria-label="label"
                :type="numeric ? 'number' : 'text'"
                step="any"
                class="form-control"
                :disabled="pending"
                :aria-invalid="Boolean(error)"
                :aria-describedby="error ? `error-${field}` : undefined"
            />
            <button
                type="submit"
                class="field-action text-success"
                :disabled="pending"
                :aria-label="`Save ${label}`"
            >
                ✓
            </button>
            <button
                type="button"
                class="field-action text-secondary"
                :disabled="pending"
                :aria-label="`Cancel ${label}`"
                @click="cancel"
            >
                ×
            </button>
            <span v-if="pending" role="status">Saving…</span>
            <span
                v-if="error"
                :id="`error-${field}`"
                role="alert"
                class="text-danger field-error"
                >{{ error }}</span
            >
        </form>
    </span>
</template>
<style scoped>
.inline-device-field {
    display: block;
}
.field-read {
    text-align: left;
    font: inherit;
    color: inherit;
    background: none;
    border: 0;
    padding: 0.25rem 0.5rem;
    margin-left: -0.5rem;
    border-radius: 4px;
    cursor: text;
}
.field-read:hover {
    background: #eef0f4;
    box-shadow: inset 2px 0 #cbd5e1;
}
.field-editor {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.25rem;
}
.field-editor input {
    flex: 1;
    min-width: 80px;
    width: 0;
    font: inherit;
}
.field-action {
    background: transparent;
    border: 0;
    padding: 0.25rem 0.5rem;
    font-size: 1.25rem;
}
.field-error {
    flex-basis: 100%;
    font-size: 0.875rem;
}
</style>
