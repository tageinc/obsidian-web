<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    closeLabel: { type: String, default: 'Close dialog' },
    pending: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const dialog = ref(null);
const heading = ref(null);
let previousOverflow;

function close() {
    if (!props.pending) dialog.value?.close();
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
        heading.value?.focus();
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
    const feedback =
        dialog.value?.querySelector('[role="alert"][tabindex="-1"]') ||
        dialog.value?.querySelector('[role="alert"]');
    if (feedback) feedback.setAttribute('tabindex', '-1');
    (feedback || heading.value)?.focus();
});
onBeforeUnmount(() => {
    if (dialog.value?.open) dialog.value.close();
    document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <dialog
        :id="id"
        ref="dialog"
        class="form-modal"
        :aria-labelledby="`${id}-title`"
        :aria-describedby="description ? `${id}-description` : undefined"
        @cancel.prevent="close"
        @close="emit('close')"
        @keydown.tab="containTab"
    >
        <header class="form-modal-header">
            <div>
                <h2 :id="`${id}-title`" ref="heading" class="h4 mb-1" tabindex="-1">
                    {{ title }}
                </h2>
                <p v-if="description" :id="`${id}-description`" class="text-muted mb-0">
                    {{ description }}
                </p>
            </div>
            <button
                type="button"
                class="btn-close"
                :aria-label="closeLabel"
                :disabled="pending"
                @click="close"
            />
        </header>
        <div class="form-modal-body">
            <slot :close="close" />
        </div>
    </dialog>
</template>

<style scoped>
.form-modal {
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
.form-modal[open] {
    display: flex;
    flex-direction: column;
}
.form-modal::backdrop {
    background: rgb(17 24 39 / 50%);
}
.form-modal-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    flex: 0 0 auto;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}
.form-modal-header .btn-close {
    flex: 0 0 auto;
    padding: 0.75rem;
}
.form-modal-body {
    min-height: 0;
    padding: 1.5rem;
    overflow-y: auto;
    overscroll-behavior: contain;
}
@media (max-width: 575.98px) {
    .form-modal {
        width: calc(100% - 1rem);
        max-height: calc(100vh - 1rem);
        max-height: calc(100dvh - 1rem);
    }
    .form-modal-header,
    .form-modal-body {
        padding: 1rem;
    }
}
</style>
