<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FormModal from '../../shared/components/FormModal.vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { requestJson } from '../../shared/api/client.js';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    row: { type: Object, required: true },
    csrfToken: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['changed']);
const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ top: '0px', left: '0px' });
const mode = ref(null);
const pending = ref(false);
const version = ref('');
const description = ref('');
const errors = ref({});
const error = ref(null);
const id = `${props.kind}-actions-${props.row.id}`;
const releaseName = computed(() => props.title.toLowerCase());
const lifetime = new AbortController();
const summaryErrors = computed(() =>
    Object.fromEntries(
        Object.entries(errors.value).map(([field, messages]) => [`${id}-${field}`, messages]),
    ),
);

function closeMenu(restoreFocus = false) {
    open.value = false;
    if (restoreFocus) trigger.value?.focus({ preventScroll: true });
}
function place() {
    if (!open.value || !trigger.value || !menu.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const popup = menu.value.getBoundingClientRect();
    const width = document.documentElement.clientWidth || window.innerWidth;
    position.value = {
        left: `${Math.max(8, Math.min(anchor.right - popup.width, width - popup.width - 8))}px`,
        top: `${
            anchor.bottom + popup.height + 8 <= window.innerHeight
                ? anchor.bottom + 4
                : Math.max(8, anchor.top - popup.height - 4)
        }px`,
    };
}
async function reveal(last = false) {
    if (props.disabled || pending.value) return;
    open.value = true;
    await nextTick();
    place();
    await nextTick();
    if (!open.value) return;
    const items = menu.value?.querySelectorAll('button');
    items?.[last ? items.length - 1 : 0]?.focus();
}
function contains(target) {
    return trigger.value?.contains(target) || menu.value?.contains(target);
}
function dismiss(event) {
    if (!contains(event.target)) closeMenu();
}
function leave(event) {
    if (!contains(event.relatedTarget)) closeMenu();
}
function keydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeMenu(true);
        return;
    }
    if (event.key === 'Tab') {
        closeMenu(true);
        if (event.shiftKey) event.preventDefault();
        return;
    }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const items = [...menu.value.querySelectorAll('button')];
    const index = items.indexOf(event.target);
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? items.length - 1
              : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
    items[next]?.focus();
}
function showDialog(action) {
    if (props.disabled || pending.value) return;
    closeMenu();
    version.value = String(props.row.version);
    description.value = props.row.description || '';
    errors.value = {};
    error.value = null;
    mode.value = action;
}
async function closeDialog() {
    if (pending.value) return;
    mode.value = null;
    await nextTick();
    trigger.value?.focus({ preventScroll: true });
}
async function save(event) {
    if (pending.value || props.disabled || !event.currentTarget.reportValidity()) return;
    pending.value = true;
    errors.value = {};
    error.value = null;
    try {
        const result = await requestJson(
            mode.value === 'edit' ? props.row.updateUrl : props.row.deleteUrl,
            {
                method: mode.value === 'edit' ? 'PATCH' : 'DELETE',
                ...(mode.value === 'edit'
                    ? { data: { version: version.value, description: description.value } }
                    : {}),
                csrfToken: props.csrfToken,
                signal: lifetime.signal,
            },
        );
        mode.value = null;
        emit('changed', result);
    } catch (failure) {
        if (failure.name === 'AbortError') return;
        errors.value = failure.fieldErrors || {};
        error.value = Object.keys(errors.value).length ? null : failure.message;
        await nextTick();
        const feedback = document.getElementById(`${id}-dialog`)?.querySelector('[role="alert"]');
        feedback?.setAttribute('tabindex', '-1');
        feedback?.focus();
    } finally {
        pending.value = false;
    }
}
onMounted(() => {
    document.addEventListener('click', dismiss);
    document.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
});
onBeforeUnmount(() => {
    lifetime.abort();
    document.removeEventListener('click', dismiss);
    document.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
});
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="release-action-trigger"
        :aria-label="`Actions for ${releaseName} ${row.version}`"
        :aria-controls="id"
        :aria-expanded="open"
        aria-haspopup="menu"
        :disabled="disabled || pending"
        @click="open ? closeMenu() : reveal()"
        @keydown.down.prevent="reveal()"
        @keydown.up.prevent="reveal(true)"
        @keydown.esc.prevent="closeMenu(true)"
        @focusout="leave"
    >
        <span aria-hidden="true">⋮</span>
    </button>
    <Teleport to="body">
        <div
            :id="id"
            ref="menu"
            v-show="open"
            role="menu"
            :aria-label="`Actions for ${releaseName} ${row.version}`"
            class="release-action-menu"
            :style="position"
            @keydown="keydown"
            @focusout="leave"
        >
            <button type="button" role="menuitem" tabindex="-1" @click="showDialog('edit')">
                Edit
            </button>
            <button
                type="button"
                role="menuitem"
                tabindex="-1"
                class="release-delete-action"
                @click="showDialog('delete')"
            >
                Delete
            </button>
        </div>
        <FormModal
            v-if="mode"
            :id="`${id}-dialog`"
            class="release-modal"
            :title="`${mode === 'edit' ? 'Edit' : 'Delete'} ${releaseName}`"
            :pending="pending"
            @close="closeDialog"
        >
            <template #default="{ close }">
                <p class="release-record">
                    {{ mode === 'delete' ? `Delete ${releaseName} version` : `${title} version` }}
                    <strong>{{ row.version }}</strong> ({{ row.prefix }}){{
                        mode === 'delete' ? '?' : ''
                    }}
                </p>
                <p v-if="mode === 'delete'">This permanently removes the release and its file.</p>
                <FormFeedback :errors="summaryErrors" :session-error="error" />
                <form :aria-busy="pending" @submit.prevent="save">
                    <template v-if="mode === 'edit'">
                        <FormField
                            :id="`${id}-version`"
                            v-model="version"
                            name="version"
                            :label="`${title} version`"
                            type="number"
                            min="1"
                            step="1"
                            required
                            :disabled="pending"
                            :errors="errors.version"
                        />
                        <div class="mb-3">
                            <label :for="`${id}-description`" class="form-label"
                                >{{ title }} description</label
                            >
                            <textarea
                                :id="`${id}-description`"
                                v-model="description"
                                name="description"
                                class="form-control"
                                :class="{ 'is-invalid': errors.description?.length }"
                                required
                                maxlength="255"
                                rows="3"
                                :disabled="pending"
                                :aria-invalid="errors.description?.length ? 'true' : undefined"
                                :aria-describedby="
                                    errors.description?.length
                                        ? `${id}-description-errors`
                                        : undefined
                                "
                            />
                            <div
                                v-if="errors.description?.length"
                                :id="`${id}-description-errors`"
                                class="invalid-feedback"
                            >
                                <div v-for="message in errors.description" :key="message">
                                    {{ message }}
                                </div>
                            </div>
                        </div>
                    </template>
                    <div class="d-flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            :disabled="pending"
                            @click="close"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="btn"
                            :class="mode === 'delete' ? 'btn-danger' : 'btn-primary'"
                            :disabled="pending"
                        >
                            {{
                                pending
                                    ? 'Saving…'
                                    : mode === 'delete'
                                      ? `Delete ${releaseName}`
                                      : 'Save changes'
                            }}
                        </button>
                    </div>
                </form>
            </template>
        </FormModal>
    </Teleport>
</template>

<style scoped>
.release-action-trigger {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--obsidian-text-muted);
    font-size: 1.5rem;
    line-height: 1;
}
.release-action-trigger:hover,
.release-action-trigger[aria-expanded='true'] {
    background: var(--obsidian-surface-subtle);
    color: var(--obsidian-text);
}
.release-action-trigger:focus-visible,
.release-action-menu button:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong);
    outline-offset: 2px;
}
.release-action-trigger:disabled {
    opacity: 0.5;
}
.release-action-menu {
    position: fixed;
    z-index: 1060;
    width: 11rem;
    max-width: calc(100vw - 16px);
    padding: 0.35rem;
    border: 1px solid var(--obsidian-border);
    border-radius: 8px;
    color: var(--obsidian-text);
    background: var(--obsidian-surface);
    box-shadow: 0 8px 20px rgb(0 0 0 / 12%);
}
.release-action-menu button {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    border-radius: 6px;
    color: inherit;
    background: transparent;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: left;
}
.release-action-menu button:hover,
.release-action-menu button:focus {
    background: var(--obsidian-surface-subtle);
}
.release-action-menu .release-delete-action {
    color: var(--obsidian-danger);
}
.release-record {
    overflow-wrap: anywhere;
}
.release-modal .btn-danger {
    --bs-btn-color: var(--obsidian-accent-ink);
    --bs-btn-bg: var(--obsidian-danger);
    --bs-btn-border-color: var(--obsidian-danger);
    --bs-btn-hover-color: var(--obsidian-accent-ink);
    --bs-btn-hover-bg: var(--obsidian-danger-hover);
    --bs-btn-hover-border-color: var(--obsidian-danger-hover);
    --bs-btn-active-color: var(--obsidian-accent-ink);
    --bs-btn-active-bg: var(--obsidian-danger-hover);
    --bs-btn-active-border-color: var(--obsidian-danger-hover);
    color: var(--obsidian-accent-ink);
    background-color: var(--obsidian-danger);
    border-color: var(--obsidian-danger);
}
.release-modal .btn-danger:not(:disabled):hover,
.release-modal .btn-danger:not(:disabled):focus,
.release-modal .btn-danger:not(:disabled):active {
    color: var(--obsidian-accent-ink);
    background-color: var(--obsidian-danger-hover);
    border-color: var(--obsidian-danger-hover);
}
.release-modal .btn-danger:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong);
    outline-offset: 2px;
}
.release-modal :deep(.form-modal-header .btn-close) {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: none;
    color: var(--obsidian-text);
    filter: none;
    font-size: 1.5rem;
    line-height: 1;
    opacity: 1;
}
.release-modal :deep(.btn-close)::before {
    content: '×';
}
.release-modal :deep(.btn-close:hover) {
    background: var(--obsidian-surface-subtle);
}
.release-modal :deep(.btn-close:focus-visible) {
    outline: 2px solid var(--obsidian-accent-strong);
    outline-offset: 2px;
}
.release-modal :deep(.btn-close:disabled) {
    opacity: 0.5;
}
</style>
