<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    apiKey: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    opensDialog: { type: Boolean, default: true },
});
const emit = defineEmits(['revoke']);
const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ top: '0px', left: '0px' });
const disabled = computed(() => props.disabled || props.apiKey.status === 'Revoked');
const menuId = computed(() => `api-key-actions-${props.apiKey.id}`);

function close(restoreFocus = false) {
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
async function reveal() {
    if (disabled.value) return;
    open.value = true;
    await nextTick();
    place();
    await nextTick();
    if (open.value) menu.value?.querySelector('button')?.focus();
}
function contains(target) {
    return trigger.value?.contains(target) || menu.value?.contains(target);
}
function dismiss(event) {
    if (!contains(event.target)) close();
}
function leave(event) {
    if (!contains(event.relatedTarget)) close();
}
function keydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
    } else if (event.key === 'Tab') {
        close(true);
        if (event.shiftKey) event.preventDefault();
    } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        event.preventDefault();
        menu.value?.querySelector('button')?.focus();
    }
}
function revoke() {
    if (disabled.value) return;
    close();
    emit('revoke', trigger.value);
}
watch(disabled, (value) => {
    if (value) close();
});
onMounted(() => {
    document.addEventListener('click', dismiss);
    document.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', dismiss);
    document.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
});
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="api-key-action-trigger"
        :aria-label="`Actions for API key ${apiKey.name}`"
        :aria-controls="menuId"
        :aria-expanded="open"
        aria-haspopup="menu"
        :disabled="disabled"
        @click="open ? close() : reveal()"
        @keydown.down.prevent="reveal"
        @keydown.up.prevent="reveal"
        @keydown.esc.prevent="close(true)"
        @focusout="leave"
    >
        <span aria-hidden="true">⋮</span>
    </button>
    <Teleport to="body">
        <div
            :id="menuId"
            ref="menu"
            v-show="open"
            role="menu"
            :aria-label="`Actions for API key ${apiKey.name}`"
            class="api-key-action-menu"
            :style="position"
            @keydown="keydown"
            @focusout="leave"
        >
            <button
                type="button"
                role="menuitem"
                tabindex="-1"
                :aria-label="`Revoke ${apiKey.name}`"
                :aria-haspopup="opensDialog ? 'dialog' : undefined"
                :disabled="disabled"
                @click="revoke"
            >
                Revoke
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.api-key-action-trigger {
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
.api-key-action-trigger:hover,
.api-key-action-trigger[aria-expanded='true'] {
    background: var(--obsidian-surface-subtle);
    color: var(--obsidian-text);
}
.api-key-action-trigger:focus-visible,
.api-key-action-menu button:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong);
    outline-offset: 2px;
}
.api-key-action-trigger:disabled {
    opacity: 0.5;
}
.api-key-action-menu {
    position: fixed;
    z-index: 1060;
    width: 11rem;
    max-width: calc(100vw - 16px);
    padding: 0.35rem;
    border: 1px solid var(--obsidian-border);
    border-radius: 8px;
    background: var(--obsidian-surface);
    box-shadow: 0 8px 20px rgb(0 0 0 / 12%);
}
.api-key-action-menu button {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    border-radius: 6px;
    color: var(--obsidian-danger);
    background: transparent;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: left;
}
.api-key-action-menu button:hover,
.api-key-action-menu button:focus {
    background: var(--obsidian-surface-subtle);
}
</style>
