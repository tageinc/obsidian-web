<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps({
    device: { type: Object, required: true },
    showEdit: { type: Boolean, default: true },
    csrfToken: { type: String, required: true },
});
const emit = defineEmits(['edit']);
const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ top: '0px', left: '0px' });
const menuId = `device-actions-${props.device.id}`;
const isReactivation = computed(() => Boolean(props.device.links.reactivate));
const lifecycleAction = computed(() => (isReactivation.value ? 'Reactivate' : 'Retire'));
const lifecycleUrl = computed(() => props.device.links.reactivate || props.device.links.retire);
function close(restoreFocus = false) {
    open.value = false;
    if (restoreFocus) trigger.value?.focus({ preventScroll: true });
}
function place() {
    if (!open.value || !trigger.value || !menu.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const popup = menu.value.getBoundingClientRect();
    const width = document.documentElement.clientWidth || window.innerWidth;
    const left = Math.max(8, Math.min(anchor.right - popup.width, width - popup.width - 8));
    const top =
        anchor.bottom + popup.height + 8 <= window.innerHeight
            ? anchor.bottom + 4
            : Math.max(8, anchor.top - popup.height - 4);
    position.value = { top: `${top}px`, left: `${left}px` };
}
async function reveal(focusLast = null) {
    open.value = true;
    await nextTick();
    place();
    await nextTick();
    if (!open.value || !menu.value) return;
    if (focusLast !== null) {
        const items = menu.value.querySelectorAll('a, button');
        items[focusLast ? items.length - 1 : 0]?.focus();
    }
}
function toggle() {
    if (open.value) close();
    else void reveal(false);
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
        return;
    }
    const items = [...menu.value.querySelectorAll('a, button')];
    const index = items.indexOf(event.target);
    if (event.key === 'Tab') {
        if (event.shiftKey && index === 0) {
            event.preventDefault();
            close(true);
        } else if (!event.shiftKey && index === items.length - 1) {
            // Return to the trigger before the browser advances to its next control.
            close(true);
        }
        return;
    }
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? items.length - 1
              : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
    items[next]?.focus();
}
function edit(event) {
    emit('edit', event, trigger.value);
    close();
}
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
        class="device-action-trigger"
        :aria-label="`Actions for ${device.name}`"
        :aria-controls="menuId"
        :aria-expanded="open"
        @click="toggle"
        @keydown.down.prevent="reveal(false)"
        @keydown.up.prevent="reveal(true)"
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
            role="group"
            :aria-label="`Actions for ${device.name}`"
            class="dropdown-menu show device-action-menu"
            :style="position"
            @keydown="keydown"
            @focusout="leave"
        >
            <a
                v-if="showEdit && device.links.edit"
                class="dropdown-item"
                :href="device.links.edit"
                :aria-label="`Edit ${device.name}`"
                aria-haspopup="dialog"
                @click="edit"
                >Edit</a
            >
            <form v-if="lifecycleUrl" :action="lifecycleUrl" method="POST" data-document-action>
                <input type="hidden" name="_token" :value="csrfToken" />
                <button
                    type="submit"
                    class="dropdown-item"
                    :class="{ 'device-retire': !isReactivation }"
                    :aria-label="`${lifecycleAction} ${device.name}`"
                >
                    {{ lifecycleAction }}
                </button>
            </form>
        </div>
    </Teleport>
</template>

<style scoped>
.device-action-trigger {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6b7280;
    font-size: 1.5rem;
    line-height: 1;
}
.device-action-trigger:hover,
.device-action-trigger[aria-expanded='true'] {
    background: #e5e7eb;
    color: #111827;
}
.device-action-trigger:focus-visible,
.device-action-menu a:focus-visible,
.device-action-menu button:focus-visible {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
.device-action-menu {
    position: fixed;
    z-index: 1060;
    width: 11rem;
    min-width: 0;
    max-width: calc(100vw - 16px);
    padding: 0.35rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 12%);
}
.device-action-menu .dropdown-item {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    font-size: 0.875rem;
    font-weight: 500;
    text-align: left;
}
.device-action-menu form {
    margin: 0;
}
.device-action-menu .dropdown-item:hover,
.device-action-menu .dropdown-item:focus {
    background: #f3f4f6;
}
.device-retire {
    color: #b02a37;
}
</style>
