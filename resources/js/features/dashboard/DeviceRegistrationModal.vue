<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import DeviceForm from '../devices/DeviceForm.vue';

defineProps({ registration: { type: Object, required: true } });
const emit = defineEmits(['close']);
const dialog = ref(null);
const title = ref(null);
const pending = ref(false);
let previousOverflow;

function close() {
    if (!pending.value) dialog.value?.close();
}
function containTab(event) {
    const controls = [
        ...dialog.value.querySelectorAll(
            'a[href], button:not(:disabled), input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
        ),
    ].filter(
        (element) =>
            element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden',
    );
    const first = controls[0];
    const last = controls.at(-1);
    if (!first) {
        event.preventDefault();
        title.value?.focus();
    } else if (
        event.shiftKey &&
        (document.activeElement === first || !controls.includes(document.activeElement))
    ) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
onMounted(async () => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.value.showModal();
    await nextTick();
    const feedback = dialog.value?.querySelector('[role="alert"][tabindex="-1"]');
    (feedback || title.value)?.focus();
});
onBeforeUnmount(() => {
    if (dialog.value?.open) dialog.value.close();
    document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <dialog
        ref="dialog"
        class="device-registration-modal"
        aria-labelledby="register-device-title"
        aria-describedby="register-device-description"
        @cancel.prevent="close"
        @close="emit('close')"
        @keydown.tab="containTab"
    >
        <header class="device-registration-header">
            <div>
                <h2 id="register-device-title" ref="title" class="h4 mb-1" tabindex="-1">
                    Register device
                </h2>
                <p id="register-device-description" class="text-muted mb-0">
                    Add your device details and installation address.
                </p>
            </div>
            <button
                type="button"
                class="btn-close"
                aria-label="Close registration"
                :disabled="pending"
                @click="close"
            />
        </header>
        <div class="device-registration-body">
            <DeviceForm
                :csrf-token="registration.csrfToken"
                :action="registration.action"
                :values="registration.values"
                :hardware-options="registration.hardwareOptions"
                :errors="registration.errors"
                :session-error="registration.sessionError"
                registering
                registration-modal
                @pending-change="pending = $event"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="pending"
                        @click="close"
                    >
                        Cancel
                    </button>
                </template>
            </DeviceForm>
        </div>
    </dialog>
</template>

<style scoped>
.device-registration-modal {
    width: min(54rem, calc(100% - 2rem));
    max-width: none;
    max-height: calc(100vh - 2rem);
    max-height: calc(100dvh - 2rem);
    padding: 0;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    color: #111827;
    background: #fff;
    box-shadow: 0 16px 48px rgb(0 0 0 / 20%);
    overflow: hidden;
}
.device-registration-modal[open] {
    display: flex;
    flex-direction: column;
}
.device-registration-modal::backdrop {
    background: rgb(17 24 39 / 50%);
}
.device-registration-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    flex: 0 0 auto;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}
.device-registration-header .btn-close {
    flex: 0 0 auto;
    padding: 0.75rem;
}
.device-registration-body {
    min-height: 0;
    padding: 1.5rem;
    overflow-y: auto;
    overscroll-behavior: contain;
}
@media (max-width: 575.98px) {
    .device-registration-modal {
        width: calc(100% - 1rem);
        max-height: calc(100vh - 1rem);
        max-height: calc(100dvh - 1rem);
    }
    .device-registration-header,
    .device-registration-body {
        padding: 1rem;
    }
}
</style>
