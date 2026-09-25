<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FormModal from '../../shared/components/FormModal.vue';
import FormFeedback from '../../shared/components/FormFeedback.vue';
import { requestJson } from '../../shared/api/client.js';

const props = defineProps({ endpoint: { type: String, required: true } });
const rows = ref([]);
const loading = ref(false);
const pending = ref(false);
const error = ref(null);
const message = ref(null);
const modalError = ref(null);
const errors = ref({});
const page = ref(1);
const lastPage = ref(1);
const showCreate = ref(false);
const revokeKey = ref(null);
const name = ref('');
const expiration = ref('');
const secret = ref('');
const copied = ref(false);
const secretInput = ref(null);
const createButton = ref(null);
let returnFocus;
const lifetime = new AbortController();
onBeforeUnmount(() => lifetime.abort());
onMounted(() => load());

async function load(number = 1) {
    loading.value = true;
    error.value = null;
    try {
        const url = new URL(props.endpoint, window.location.origin);
        url.searchParams.set('page', number);
        const result = await requestJson(url.href, { signal: lifetime.signal });
        rows.value = result.data;
        page.value = result.current_page;
        lastPage.value = result.last_page;
    } catch (failure) {
        if (failure.name !== 'AbortError') error.value = failure.message;
    } finally {
        loading.value = false;
    }
}
function openCreate() {
    name.value = '';
    expiration.value = '';
    secret.value = '';
    copied.value = false;
    modalError.value = null;
    errors.value = {};
    showCreate.value = true;
}
async function closeCreate() {
    showCreate.value = false;
    secret.value = '';
    await nextTick();
    createButton.value?.focus();
}
async function create() {
    if (pending.value) return;
    pending.value = true;
    modalError.value = null;
    errors.value = {};
    try {
        const result = await requestJson(props.endpoint, {
            method: 'POST',
            data: { name: name.value, expires_at: expiration.value || null },
            signal: lifetime.signal,
        });
        secret.value = result.plain_text_key;
        message.value = 'API key created.';
        await load();
        await nextTick();
        secretInput.value?.focus();
    } catch (failure) {
        if (failure.name !== 'AbortError') {
            modalError.value = failure.message;
            errors.value = failure.fieldErrors || {};
        }
    } finally {
        pending.value = false;
    }
}
async function copy() {
    try {
        await navigator.clipboard.writeText(secret.value);
        copied.value = true;
    } catch {
        secretInput.value?.focus();
        secretInput.value?.select();
        modalError.value = 'Copy is unavailable. Select and copy the key from the field.';
    }
}
function openRevoke(key, event) {
    returnFocus = event.currentTarget;
    modalError.value = null;
    revokeKey.value = key;
}
async function closeRevoke() {
    revokeKey.value = null;
    await nextTick();
    if (returnFocus?.isConnected && !returnFocus.disabled) returnFocus.focus();
    else createButton.value?.focus();
}
async function revoke() {
    if (pending.value) return;
    pending.value = true;
    modalError.value = null;
    try {
        const result = await requestJson(
            `${props.endpoint.replace(/\/$/, '')}/${revokeKey.value.id}/revoke`,
            {
                method: 'POST',
                signal: lifetime.signal,
            },
        );
        rows.value = rows.value.map((row) => (row.id === result.data.id ? result.data : row));
        message.value = 'API key revoked. Requests using it will now be rejected.';
        await closeRevoke();
    } catch (failure) {
        if (failure.name !== 'AbortError') modalError.value = failure.message;
    } finally {
        pending.value = false;
    }
}
function date(value) {
    return value ? new Date(value).toLocaleString() : 'Never';
}
</script>

<template>
    <section class="card border-0 shadow-sm p-4" aria-labelledby="api-keys-heading">
        <header class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h2 id="api-keys-heading" class="h3 mb-1">External API Keys</h2>
                <p class="text-secondary small mb-0">
                    Connect integrations with full developer access.
                </p>
            </div>
            <button
                ref="createButton"
                type="button"
                class="btn btn-primary btn-sm"
                aria-haspopup="dialog"
                @click="openCreate"
            >
                Create Key
            </button>
        </header>
        <FormFeedback :success="message" :session-error="error" />
        <p class="small text-secondary">
            Use a Bearer key with <code>/api/external/v1</code>. Keys belong to the configured
            developer.
        </p>
        <p v-if="loading" role="status">Loading API keys…</p>
        <button
            v-if="error"
            type="button"
            class="btn btn-outline-secondary btn-sm mb-3"
            :disabled="loading"
            @click="load(page)"
        >
            Retry
        </button>
        <div
            class="table-responsive"
            role="region"
            aria-label="External API keys"
            tabindex="0"
            :aria-busy="loading"
        >
            <table class="table table-hover align-middle small">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Key prefix</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                        <th scope="col">Last used</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="key in rows" :key="key.id">
                        <th scope="row" class="key-name">{{ key.name }}</th>
                        <td>
                            <code>{{ key.prefix }}…</code>
                        </td>
                        <td>{{ key.status }}</td>
                        <td>{{ date(key.created_at) }}</td>
                        <td>{{ date(key.last_used_at) }}</td>
                        <td>{{ date(key.expires_at) }}</td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm"
                                :aria-label="`Revoke ${key.name}`"
                                :disabled="key.status === 'Revoked' || pending"
                                @click="openRevoke(key, $event)"
                            >
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && !error && !rows.length">
                        <td colspan="7" class="py-4 text-center text-secondary">
                            No API keys have been created yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav
            v-if="lastPage > 1"
            class="d-flex align-items-center gap-3 mt-3"
            aria-label="API key pages"
        >
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="page === 1 || loading"
                @click="load(page - 1)"
            >
                Previous
            </button>
            <span class="small">Page {{ page }} of {{ lastPage }}</span>
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="page === lastPage || loading"
                @click="load(page + 1)"
            >
                Next
            </button>
        </nav>
        <FormModal
            v-if="showCreate"
            id="create-api-key-dialog"
            :title="secret ? 'Save your API key' : 'Create API key'"
            close-label="Close API key creation"
            :pending="pending"
            @close="closeCreate"
        >
            <FormFeedback :session-error="modalError" />
            <div v-if="secret">
                <p>Copy this key now. It will not be shown again.</p>
                <label for="new-api-key" class="form-label">API key</label>
                <input
                    id="new-api-key"
                    ref="secretInput"
                    :value="secret"
                    readonly
                    autocomplete="off"
                    spellcheck="false"
                    class="form-control font-monospace mb-3"
                />
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" @click="copy">Copy key</button>
                    <button type="button" class="btn btn-outline-secondary" @click="closeCreate">
                        Done
                    </button>
                </div>
                <p class="small mt-2 mb-0" role="status">{{ copied ? 'Key copied.' : '' }}</p>
            </div>
            <form v-else :aria-busy="pending" @submit.prevent="create">
                <div class="mb-3">
                    <label for="api-key-name" class="form-label">Key name</label>
                    <input
                        id="api-key-name"
                        v-model="name"
                        class="form-control"
                        required
                        maxlength="100"
                        :disabled="pending"
                        :aria-invalid="Boolean(errors.name)"
                        :aria-describedby="errors.name ? 'api-key-name-error' : undefined"
                    />
                    <div v-if="errors.name" id="api-key-name-error" class="text-danger small">
                        {{ errors.name.join(' ') }}
                    </div>
                </div>
                <div class="mb-3">
                    <label for="api-key-expiration" class="form-label"
                        >Expires on (UTC, optional)</label
                    >
                    <input
                        id="api-key-expiration"
                        v-model="expiration"
                        type="date"
                        class="form-control"
                        :disabled="pending"
                        :aria-invalid="Boolean(errors.expires_at)"
                        aria-describedby="api-key-expiration-help"
                    />
                    <div id="api-key-expiration-help" class="form-text">
                        {{
                            errors.expires_at?.join(' ') ||
                            'Expires at 00:00 UTC on this date. Leave blank for no expiration.'
                        }}
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        :disabled="pending"
                        @click="closeCreate"
                    >
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" :disabled="pending">
                        {{ pending ? 'Creating…' : 'Create key' }}
                    </button>
                </div>
            </form>
        </FormModal>
        <FormModal
            v-if="revokeKey"
            id="revoke-api-key-dialog"
            title="Revoke API key"
            close-label="Close API key revocation"
            :pending="pending"
            @close="closeRevoke"
        >
            <FormFeedback :session-error="modalError" />
            <p>
                Revoke <strong>{{ revokeKey.name }}</strong
                >? Integrations using this key will lose access immediately.
            </p>
            <div class="d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="pending"
                    @click="closeRevoke"
                >
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" :disabled="pending" @click="revoke">
                    {{ pending ? 'Revoking…' : 'Revoke key' }}
                </button>
            </div>
        </FormModal>
    </section>
</template>

<style scoped>
th,
td {
    min-width: 5rem;
}
.key-name {
    min-width: 8rem;
    max-width: 18rem;
    overflow-wrap: anywhere;
}
thead th {
    background: var(--bs-tertiary-bg);
    white-space: nowrap;
}
</style>
