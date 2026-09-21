<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import FormField from '../../shared/components/FormField.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import FormModal from '../../shared/components/FormModal.vue';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    action: { type: String, required: true },
    csrfToken: { type: String, required: true },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    adminUrl: { type: String, required: true },
    otherSize: { type: Number, required: true },
    values: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
    sessionError: { type: String, default: null },
    initiallyOpen: { type: Boolean, default: false },
    pending: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
});
defineEmits(['submit']);
const firmware = computed(() => props.kind === 'firmware');
const description = ref(props.values.description ?? '');
const prefix = ref(props.values.prefix ?? '');
const perPage = ref(props.pagination.perPage);
const showUpload = ref(
    props.initiallyOpen || Object.keys(props.errors).length > 0 || Boolean(props.sessionError),
);
const uploadButton = ref(null);
watch(
    () => [props.initiallyOpen, props.errors, props.sessionError],
    ([initiallyOpen, errors, sessionError]) => {
        if (initiallyOpen || Object.keys(errors).length || sessionError) showUpload.value = true;
    },
);
async function closeUpload() {
    showUpload.value = false;
    await nextTick();
    uploadButton.value?.focus({ preventScroll: true });
}
watch(
    () => props.pagination.perPage,
    (value) => {
        perPage.value = value;
    },
);
const summaryErrors = computed(() =>
    Object.fromEntries(
        Object.entries(props.errors).map(([field, messages]) => [
            field === props.kind ? field : `${props.kind}-${field}`,
            messages,
        ]),
    ),
);
function checkFile(event) {
    const input = event.target;
    const maxBytes = (firmware.value ? 10 : 1) * 1024 * 1024;
    input.setCustomValidity(
        input.files?.[0]?.size > maxBytes
            ? `The file must be no larger than ${firmware.value ? '10 MB' : '1 MB'}.`
            : '',
    );
}
</script>

<template>
    <section class="card border-0 shadow-sm" :aria-labelledby="`${kind}-heading`">
        <div class="p-4 pb-3 d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 :id="`${kind}-heading`" class="h4 mb-1">{{ title }}</h2>
                <p class="text-secondary mb-0">
                    {{
                        firmware
                            ? 'Release binaries for your devices.'
                            : 'Versioned settings for your devices.'
                    }}
                </p>
            </div>
            <button
                ref="uploadButton"
                type="button"
                class="btn btn-outline-primary btn-sm"
                aria-haspopup="dialog"
                :aria-controls="`${kind}-upload-dialog`"
                :disabled="pending"
                @click="showUpload = true"
            >
                New upload
            </button>
        </div>
        <div class="p-4 pt-0">
            <FormModal
                v-if="showUpload"
                :id="`${kind}-upload-dialog`"
                :title="firmware ? 'Upload firmware' : 'Upload configuration'"
                :description="
                    firmware
                        ? 'Add a binary release for your devices.'
                        : 'Add a JSON configuration release for your devices.'
                "
                :close-label="firmware ? 'Close firmware upload' : 'Close configuration upload'"
                :pending="pending"
                @close="closeUpload"
            >
                <template #default="{ close }">
                    <FormFeedback :errors="summaryErrors" :session-error="sessionError" />
                    <form
                        :id="`${kind}-upload`"
                        :action="action"
                        method="POST"
                        enctype="multipart/form-data"
                        :aria-busy="pending && active"
                        @submit="$emit('submit', $event)"
                    >
                        <input type="hidden" name="_token" :value="csrfToken" />
                        <input type="hidden" name="_upload_kind" :value="kind" />
                        <div class="mb-3">
                            <label :for="kind" class="form-label">{{
                                firmware ? 'Firmware File (.bin)' : 'Configuration File (.json)'
                            }}</label>
                            <input
                                :id="kind"
                                :name="kind"
                                type="file"
                                :accept="firmware ? '.bin' : '.json'"
                                required
                                class="form-control"
                                :class="{ 'is-invalid': errors[kind]?.length }"
                                :aria-invalid="errors[kind]?.length ? 'true' : undefined"
                                :aria-describedby="`${kind}-help${errors[kind]?.length ? ` ${kind}-errors` : ''}`"
                                @change="checkFile"
                            />
                            <div :id="`${kind}-help`" class="form-text">
                                Maximum {{ firmware ? '10 MB' : '1 MB' }}. Select the file again
                                after a validation error.
                            </div>
                            <div
                                v-if="errors[kind]?.length"
                                :id="`${kind}-errors`"
                                class="invalid-feedback"
                            >
                                <div v-for="message in errors[kind]" :key="message">
                                    {{ message }}
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label :for="`${kind}-description`" class="form-label">{{
                                firmware ? 'Firmware Description' : 'Config Description'
                            }}</label>
                            <textarea
                                :id="`${kind}-description`"
                                v-model="description"
                                name="description"
                                class="form-control"
                                :class="{ 'is-invalid': errors.description?.length }"
                                required
                                maxlength="255"
                                rows="3"
                                :aria-invalid="errors.description?.length ? 'true' : undefined"
                                :aria-describedby="
                                    errors.description?.length
                                        ? `${kind}-description-errors`
                                        : undefined
                                "
                            />
                            <div
                                v-if="errors.description?.length"
                                :id="`${kind}-description-errors`"
                                class="invalid-feedback"
                            >
                                <div v-for="message in errors.description" :key="message">
                                    {{ message }}
                                </div>
                            </div>
                        </div>
                        <FormField
                            :id="`${kind}-prefix`"
                            v-model="prefix"
                            name="prefix"
                            label="Prefix"
                            maxlength="255"
                            required
                            :errors="errors.prefix"
                        />
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                :disabled="pending"
                                @click="close"
                            >
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="pending">
                                {{
                                    pending && active
                                        ? 'Uploading…'
                                        : firmware
                                          ? 'Upload Firmware'
                                          : 'Upload JSON Config'
                                }}
                            </button>
                        </div>
                        <span class="visually-hidden" role="status">{{
                            pending && active ? 'Uploading file. Please wait.' : ''
                        }}</span>
                    </form>
                </template>
            </FormModal>
            <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-3">
                <h3 class="h5 mb-0">Release history</h3>
                <span class="small text-secondary">{{
                    firmware ? 'Firmware versions' : 'Configuration versions'
                }}</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle developer-history">
                    <caption class="visually-hidden">
                        {{
                            title
                        }}
                        release history
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Version</th>
                            <th scope="col">Prefix</th>
                            <th scope="col">Description</th>
                            <th scope="col">Date Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="index">
                            <th scope="row">{{ row.version }}</th>
                            <td>{{ row.prefix }}</td>
                            <td class="developer-description">{{ row.description }}</td>
                            <td class="text-nowrap small">{{ row.createdAt }}</td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="4" class="py-4 text-secondary text-center">
                                {{
                                    firmware
                                        ? 'No firmware updates found.'
                                        : 'No configuration updates found.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <form
                :action="adminUrl"
                method="GET"
                class="d-flex align-items-center flex-wrap gap-2 mt-3 mb-3"
            >
                <input type="hidden" name="section" :value="kind" />
                <input
                    type="hidden"
                    :name="firmware ? 'config_show' : 'firmware_show'"
                    :value="otherSize"
                />
                <label :for="`${kind}-per-page`" class="form-label small mb-0">{{
                    firmware ? 'Firmware updates per page' : 'Configuration updates per page'
                }}</label>
                <select
                    :id="`${kind}-per-page`"
                    v-model="perPage"
                    :name="`${kind}_show`"
                    class="form-select form-select-sm d-inline-block w-auto"
                    :disabled="pending"
                    @change="$event.target.form.requestSubmit()"
                >
                    <option v-for="size in pagination.sizes" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
            </form>
            <nav v-if="pagination.lastPage > 1" :aria-label="`${title} pages`">
                <ul class="pagination flex-wrap mb-0">
                    <li
                        v-for="(link, index) in pagination.links"
                        :key="index"
                        class="page-item"
                        :class="{ active: link.active, disabled: !link.url }"
                    >
                        <a
                            v-if="link.url"
                            :href="link.url"
                            class="page-link"
                            :aria-current="link.active ? 'page' : undefined"
                            >{{ link.label }}</a
                        >
                        <span v-else class="page-link" aria-disabled="true">{{ link.label }}</span>
                    </li>
                </ul>
            </nav>
        </div>
    </section>
</template>

<style scoped>
.developer-history th {
    font-size: 0.875rem;
}
.developer-history thead th {
    color: var(--bs-secondary-color);
    font-weight: 500;
}
.developer-description {
    min-width: 10rem;
    overflow-wrap: anywhere;
}
</style>
