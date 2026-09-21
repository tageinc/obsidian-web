<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import UploadSection from './UploadSection.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { useNativeForm } from '../../shared/composables/useNativeForm.js';

const props = defineProps({
    csrfToken: { type: String, required: true },
    links: { type: Object, required: true },
    firmware: { type: Object, required: true },
    config: { type: Object, required: true },
    values: { type: Object, default: () => ({}) },
    activeUpload: { type: String, default: 'firmware' },
    activeSection: { type: String, default: 'firmware' },
    initiallyOpenUpload: { type: String, default: null },
    errors: { type: Object, default: () => ({}) },
    success: { type: String, default: null },
    sessionError: { type: String, default: null },
});
const { pending, submit } = useNativeForm();
const active = ref(props.activeUpload);
const sections = [
    { key: 'firmware', label: 'Firmware', description: 'Device software releases', format: '.bin' },
    {
        key: 'config',
        label: 'Configuration',
        description: 'Device configuration files',
        format: '.json',
    },
];
const modalFailureKind = computed(
    () =>
        props.initiallyOpenUpload || (Object.keys(props.errors).length ? props.activeUpload : null),
);
const selected = ref(
    (modalFailureKind.value || props.activeSection) === 'config' ? 'config' : 'firmware',
);
const tabElements = ref({});
const vertical = ref(false);
let desktopQuery;
function updateOrientation() {
    vertical.value = desktopQuery.matches;
}
onMounted(() => {
    if (!window.matchMedia) return;
    desktopQuery = window.matchMedia('(min-width: 992px)');
    updateOrientation();
    desktopQuery.addEventListener('change', updateOrientation);
});
onBeforeUnmount(() => desktopQuery?.removeEventListener('change', updateOrientation));
async function navigateSections(event, index) {
    const moves = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
    if (!(event.key in moves) && !['Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    if (pending.value) return;
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? sections.length - 1
              : (index + moves[event.key] + sections.length) % sections.length;
    selected.value = sections[next].key;
    await nextTick();
    tabElements.value[selected.value]?.focus();
}
function upload(event, kind) {
    if (!pending.value && !event.defaultPrevented) active.value = kind;
    submit(event);
}
</script>

<template>
    <div class="container developer-workspace">
        <header class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <p class="text-secondary small mb-1">Device software</p>
                <h1 class="h3 mb-2">Developer Workspace</h1>
                <p class="text-secondary mb-0">Manage firmware and configuration releases.</p>
            </div>
            <a :href="links.dashboard" class="btn btn-outline-secondary btn-sm"
                >Back to dashboard</a
            >
        </header>
        <FormFeedback :success="success" :session-error="modalFailureKind ? null : sessionError" />
        <div class="developer-layout">
            <div
                class="developer-sections"
                role="tablist"
                aria-label="Release tools"
                :aria-orientation="vertical ? 'vertical' : 'horizontal'"
            >
                <button
                    v-for="(section, index) in sections"
                    :id="`developer-${section.key}-tab`"
                    :key="section.key"
                    :ref="(element) => (tabElements[section.key] = element)"
                    type="button"
                    role="tab"
                    class="developer-section"
                    :aria-controls="`developer-${section.key}-panel`"
                    :aria-selected="selected === section.key"
                    :tabindex="selected === section.key ? 0 : -1"
                    :disabled="pending"
                    @click="selected = section.key"
                    @keydown="navigateSections($event, index)"
                >
                    <span class="d-flex align-items-center justify-content-between gap-2">
                        <strong>{{ section.label }}</strong>
                        <span class="developer-format">{{ section.format }}</span>
                    </span>
                    <span class="developer-section-description">{{ section.description }}</span>
                </button>
            </div>
            <div class="developer-content">
                <div
                    v-for="section in sections"
                    :id="`developer-${section.key}-panel`"
                    :key="section.key"
                    v-show="selected === section.key"
                    role="tabpanel"
                    :aria-labelledby="`developer-${section.key}-tab`"
                    tabindex="0"
                >
                    <UploadSection
                        :kind="section.key"
                        :title="section.label"
                        :action="
                            section.key === 'firmware' ? links.uploadFirmware : links.uploadConfig
                        "
                        :csrf-token="csrfToken"
                        :admin-url="links.admin"
                        :rows="(section.key === 'firmware' ? firmware : config).rows"
                        :pagination="(section.key === 'firmware' ? firmware : config).pagination"
                        :other-size="
                            (section.key === 'firmware' ? config : firmware).pagination.perPage
                        "
                        :values="activeUpload === section.key ? values : {}"
                        :errors="activeUpload === section.key ? errors : {}"
                        :session-error="modalFailureKind === section.key ? sessionError : null"
                        :initially-open="initiallyOpenUpload === section.key"
                        :pending="pending"
                        :active="active === section.key"
                        @submit="upload($event, section.key)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.developer-workspace > header .text-secondary {
    color: #596579 !important;
}
.developer-layout {
    display: grid;
    gap: 1.5rem;
    align-items: start;
}
.developer-sections {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}
.developer-section {
    width: 100%;
    min-width: 0;
    padding: 1rem;
    border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius-lg);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    text-align: left;
}
.developer-section[aria-selected='true'] {
    border-color: var(--bs-link-color);
    box-shadow: inset 3px 0 var(--bs-link-color);
    background: var(--bs-primary-bg-subtle);
}
.developer-section-description {
    display: block;
    margin-top: 0.35rem;
    color: var(--bs-secondary-color);
    font-size: 0.875rem;
}
.developer-format {
    color: var(--bs-secondary-color);
    font-size: 0.75rem;
}
.developer-content {
    min-width: 0;
}
@media (min-width: 992px) {
    .developer-layout {
        grid-template-columns: 15rem minmax(0, 1fr);
    }
    .developer-sections {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 575.98px) {
    .developer-section {
        padding: 0.75rem;
    }
    .developer-format,
    .developer-section-description {
        display: none;
    }
}
</style>
