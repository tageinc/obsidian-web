<script setup>
import { computed, nextTick, ref } from 'vue';
import softwareIcon from './download.svg';
import toolsIcon from './wrench.svg';
import ExternalApiKeys from './ExternalApiKeys.vue';
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
const softwareOpen = ref(true);
const toolsOpen = ref(true);
const sections = [
    { key: 'firmware', label: 'Firmware' },
    { key: 'config', label: 'Configuration' },
];
const modalFailureKind = computed(
    () =>
        props.initiallyOpenUpload || (Object.keys(props.errors).length ? props.activeUpload : null),
);
const selected = ref(
    ['config', 'api-keys'].includes(modalFailureKind.value || props.activeSection)
        ? modalFailureKind.value || props.activeSection
        : 'firmware',
);
const tabElements = ref({});
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
    <div class="developer-workspace">
        <div class="developer-layout">
            <aside class="developer-sidebar" aria-label="Developer workspace">
                <header class="developer-sidebar-header">
                    <h1>Developer Workspace</h1>
                </header>
                <button
                    type="button"
                    class="developer-software-toggle"
                    :aria-expanded="softwareOpen"
                    aria-controls="developer-software-sections"
                    @click="softwareOpen = !softwareOpen"
                >
                    <img :src="softwareIcon" alt="" class="developer-software-icon" />
                    <span>Software</span>
                    <svg
                        class="developer-software-chevron"
                        :class="{ 'is-open': softwareOpen }"
                        viewBox="0 0 16 16"
                        aria-hidden="true"
                    >
                        <path d="m6 3 5 5-5 5" />
                    </svg>
                </button>
                <div
                    id="developer-software-sections"
                    v-show="softwareOpen"
                    class="developer-sections"
                    role="tablist"
                    aria-label="Release tools"
                    aria-orientation="vertical"
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
                        :tabindex="
                            selected === section.key || (selected === 'api-keys' && index === 0)
                                ? 0
                                : -1
                        "
                        :disabled="pending"
                        @click="selected = section.key"
                        @keydown="navigateSections($event, index)"
                    >
                        {{ section.label }}
                    </button>
                </div>
                <button
                    type="button"
                    class="developer-software-toggle mt-2"
                    :aria-expanded="toolsOpen"
                    aria-controls="developer-tools-sections"
                    @click="toolsOpen = !toolsOpen"
                >
                    <img :src="toolsIcon" alt="" class="developer-software-icon" />
                    <span>Tools</span>
                    <svg
                        class="developer-software-chevron"
                        :class="{ 'is-open': toolsOpen }"
                        viewBox="0 0 16 16"
                        aria-hidden="true"
                    >
                        <path d="m6 3 5 5-5 5" />
                    </svg>
                </button>
                <div
                    id="developer-tools-sections"
                    v-show="toolsOpen"
                    class="developer-sections"
                    role="tablist"
                    aria-label="Developer tools"
                    aria-orientation="vertical"
                >
                    <button
                        id="developer-api-keys-tab"
                        type="button"
                        role="tab"
                        class="developer-section"
                        aria-controls="developer-api-keys-panel"
                        :aria-selected="selected === 'api-keys'"
                        :disabled="pending"
                        @click="selected = 'api-keys'"
                    >
                        External API Keys
                    </button>
                </div>
            </aside>
            <div class="developer-content">
                <FormFeedback
                    :success="success"
                    :session-error="modalFailureKind ? null : sessionError"
                />
                <div
                    v-if="selected === 'api-keys'"
                    id="developer-api-keys-panel"
                    role="tabpanel"
                    aria-labelledby="developer-api-keys-tab"
                    tabindex="0"
                >
                    <ExternalApiKeys :endpoint="links.apiKeys" />
                </div>
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
                        :developer-url="links.developer"
                        :rows="(section.key === 'firmware' ? firmware : config).rows"
                        :pagination="(section.key === 'firmware' ? firmware : config).pagination"
                        :filters="(section.key === 'firmware' ? firmware : config).filters"
                        :filter-options="
                            (section.key === 'firmware' ? firmware : config).filterOptions
                        "
                        :preserved-query="
                            (section.key === 'firmware' ? firmware : config).preservedQuery
                        "
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
.developer-layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 2rem;
    min-height: calc(100vh - 8rem);
    align-items: start;
}
.developer-sidebar {
    background: var(--bs-body-bg);
    min-height: calc(100vh - 8rem);
    padding: 1.5rem 0.75rem;
}
.developer-sidebar-header {
    padding: 0 0.75rem 1.5rem;
}
.developer-sidebar-header h1 {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.developer-software-toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    padding: 0.875rem 0.75rem;
    border: 0;
    background: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
    text-align: left;
    font-size: 0.875rem;
}
.developer-software-toggle:hover {
    background: var(--bs-secondary-bg);
}
.developer-software-icon {
    width: 1rem;
    height: 1rem;
}
.developer-software-chevron {
    width: 0.75rem;
    height: 0.75rem;
    margin-left: auto;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.25;
}
.developer-software-chevron.is-open {
    transform: rotate(90deg);
}
.developer-sections {
    display: grid;
    gap: 0.25rem;
    padding: 0.25rem 0 0.25rem 2rem;
}
.developer-section {
    width: 100%;
    min-width: 0;
    padding: 0.875rem 0.75rem;
    border: 0;
    border-radius: var(--bs-border-radius);
    background: transparent;
    color: var(--bs-body-color);
    text-align: left;
    font-size: 0.875rem;
}
.developer-section:hover {
    background: var(--bs-tertiary-bg);
}
.developer-section[aria-selected='true'] {
    color: var(--bs-link-color);
    background: var(--bs-primary-bg-subtle);
    font-weight: 600;
}
.developer-content {
    min-width: 0;
    width: 100%;
    max-width: 1500px;
    padding-right: 2rem;
}
@media (max-width: 991.98px) {
    .developer-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
    }
    .developer-sidebar {
        min-height: 0;
        padding: 1rem;
    }
    .developer-sidebar-header {
        padding: 0 0 1rem;
    }
    .developer-content {
        padding: 0 1rem;
    }
}
</style>
