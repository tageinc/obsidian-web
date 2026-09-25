<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import WorkspaceFilters from './WorkspaceFilters.vue';
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
const total = ref(0);
const range = ref({ from: null, to: null });
const initialQuery = new URLSearchParams(window.location.search);
const filters = ref({
    search: initialQuery.get('keys_search') || '',
    statuses: initialQuery.getAll('keys_statuses[]'),
    expiration: initialQuery.getAll('keys_expiration[]'),
    from: initialQuery.get('keys_from') || '',
    to: initialQuery.get('keys_to') || '',
    sort: initialQuery.get('keys_sort') || 'newest',
});
const search = ref(filters.value.search);
const searchForm = ref(null);
const perPage = ref(Number(initialQuery.get('keys_per_page')) || 20);
const filterFields = [
    {
        key: 'statuses',
        label: 'Status',
        placeholder: 'All statuses',
        options: ['Active', 'Expired', 'Revoked'].map((value) => ({ value, label: value })),
    },
    {
        key: 'expiration',
        label: 'Expiration',
        placeholder: 'Any expiration',
        options: [
            { value: 'never', label: 'No expiration' },
            { value: 'dated', label: 'Has expiration' },
        ],
    },
];
const chips = computed(() => [
    ...(filters.value.search
        ? [{ field: 'search', label: `Search: ${filters.value.search}` }]
        : []),
    ...filters.value.statuses.map((value) => ({
        field: 'statuses',
        value,
        label: `Status: ${value}`,
    })),
    ...filters.value.expiration.map((value) => ({
        field: 'expiration',
        value,
        label: `Expiration: ${value === 'never' ? 'No expiration' : 'Has expiration'}`,
    })),
    ...(filters.value.from ? [{ field: 'from', label: `From: ${filters.value.from}` }] : []),
    ...(filters.value.to ? [{ field: 'to', label: `To: ${filters.value.to}` }] : []),
]);
const filterCount = computed(() => chips.value.filter((chip) => chip.field !== 'search').length);
const resultRange = computed(() =>
    rows.value.length
        ? `Showing ${range.value.from}–${range.value.to} of ${total.value} keys`
        : `0 of ${total.value} keys`,
);
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
let listController;
let listRequest = 0;
onBeforeUnmount(() => {
    lifetime.abort();
    listController?.abort();
    listRequest++;
});
onMounted(() => load(Number(initialQuery.get('keys_page')) || 1));

function query(number) {
    const params = new URLSearchParams({ page: number, per_page: perPage.value });
    for (const [field, value] of Object.entries(filters.value)) {
        if (Array.isArray(value)) value.forEach((item) => params.append(`${field}[]`, item));
        else if (value) params.set(field, value);
    }
    return params;
}
function rememberQuery() {
    const url = new URL(window.location.href);
    for (const key of [...url.searchParams.keys()]) {
        if (key.startsWith('keys_')) url.searchParams.delete(key);
    }
    for (const [key, value] of query(page.value)) url.searchParams.append(`keys_${key}`, value);
    url.searchParams.set('section', 'api-keys');
    window.history.replaceState(window.history.state, '', url);
}

async function load(number = 1, focusTarget = null) {
    listController?.abort();
    listController = new AbortController();
    const request = ++listRequest;
    loading.value = true;
    error.value = null;
    page.value = number;
    try {
        const url = new URL(props.endpoint, window.location.origin);
        url.search = query(number).toString();
        const result = await requestJson(url.href, { signal: listController.signal });
        if (request !== listRequest) return;
        rows.value = result.data;
        page.value = result.current_page;
        lastPage.value = result.last_page;
        total.value = result.total;
        range.value = { from: result.from, to: result.to };
        perPage.value = result.per_page;
        if (result.filters) filters.value = result.filters;
        rememberQuery();
    } catch (failure) {
        if (request !== listRequest || failure.name === 'AbortError') return;
        error.value =
            Object.values(failure.fieldErrors || {})
                .flat()
                .join(' ') || failure.message;
        rows.value = [];
        total.value = 0;
    } finally {
        if (request === listRequest) {
            loading.value = false;
            await nextTick();
            // Disabling a focused control while loading moves browser focus to the body.
            if (
                request === listRequest &&
                focusTarget &&
                document.activeElement === document.body
            ) {
                const target =
                    focusTarget.isConnected && !focusTarget.disabled
                        ? focusTarget
                        : searchForm.value?.querySelector('input[type="search"]');
                if (target && !target.disabled) target.focus();
            }
        }
    }
}
function submit() {
    filters.value.search = search.value.trim();
    load(1, document.activeElement);
}
function apply(value) {
    filters.value = { ...filters.value, ...value, search: search.value.trim() };
    load(1, searchForm.value?.querySelector('.release-filter-trigger'));
}
function clear() {
    search.value = '';
    filters.value = { search: '', statuses: [], expiration: [], from: '', to: '', sort: 'newest' };
    perPage.value = [10, 20, 50, 100].includes(perPage.value) ? perPage.value : 20;
    load(1, searchForm.value?.querySelector('input[type="search"]'));
}
function remove(chip) {
    if (Array.isArray(filters.value[chip.field])) {
        filters.value[chip.field] = filters.value[chip.field].filter(
            (value) => value !== chip.value,
        );
    } else filters.value[chip.field] = '';
    if (chip.field === 'search') search.value = '';
    load(1, searchForm.value?.querySelector('input[type="search"]'));
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
        await requestJson(`${props.endpoint.replace(/\/$/, '')}/${revokeKey.value.id}/revoke`, {
            method: 'POST',
            signal: lifetime.signal,
        });
        message.value = 'API key revoked. Requests using it will now be rejected.';
        await load(page.value);
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
        <form
            ref="searchForm"
            class="api-key-search"
            aria-label="Find API keys"
            @submit.prevent="submit"
        >
            <div class="release-toolbar">
                <div class="release-search-group">
                    <div class="release-search-field">
                        <svg
                            width="17"
                            height="17"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <circle cx="10.5" cy="10.5" r="6.5" />
                            <path d="m16 16 4.5 4.5" />
                        </svg>
                        <input
                            id="api-keys-search"
                            v-model="search"
                            type="search"
                            class="form-control"
                            aria-label="Search API keys"
                            placeholder="Search name or key prefix…"
                            maxlength="255"
                            :disabled="pending || loading"
                        />
                    </div>
                    <button
                        type="submit"
                        class="btn btn-outline-secondary release-search-button"
                        :disabled="pending || loading"
                    >
                        Search
                    </button>
                </div>
                <WorkspaceFilters
                    kind="api-keys"
                    title="API key"
                    heading="Filter API keys"
                    date-label="Created"
                    date-help="Creation dates use UTC."
                    :fields="filterFields"
                    :filters="filters"
                    :count="filterCount"
                    :disabled="pending || loading"
                    @apply="apply"
                    @clear="clear"
                />
                <div class="release-sort">
                    <label for="api-keys-sort">Sort by</label>
                    <select
                        id="api-keys-sort"
                        v-model="filters.sort"
                        class="form-select form-select-sm"
                        aria-label="Sort API keys"
                        :disabled="pending || loading"
                        @change="submit"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="name_asc">Name: A–Z</option>
                        <option value="expires_asc">Expiring first</option>
                        <option value="last_used_desc">Recently used</option>
                    </select>
                </div>
            </div>
            <div
                v-if="chips.length || error"
                class="release-active-filters"
                aria-label="Active API key filters"
            >
                <button
                    v-for="chip in chips"
                    :key="`${chip.field}-${chip.value || ''}`"
                    type="button"
                    class="release-filter-chip"
                    :aria-label="`Remove ${chip.label}`"
                    :disabled="pending || loading"
                    @click="remove(chip)"
                >
                    <span>{{ chip.label }}</span
                    ><span aria-hidden="true">×</span>
                </button>
                <button
                    type="button"
                    class="btn btn-link btn-sm release-clear"
                    :disabled="pending || loading"
                    @click="clear"
                >
                    Clear filters
                </button>
            </div>
        </form>
        <p v-if="loading" role="status" class="small text-secondary">Loading API keys…</p>
        <button
            v-if="error"
            type="button"
            class="btn btn-outline-secondary btn-sm mb-3"
            :disabled="loading"
            @click="load(page, $event.currentTarget)"
        >
            Retry
        </button>
        <div
            class="table-responsive release-table-container"
            role="region"
            aria-label="External API keys"
            tabindex="0"
            :aria-busy="loading"
        >
            <table class="table align-middle developer-history mb-0">
                <caption class="visually-hidden">
                    External API keys
                </caption>
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
                        <td>
                            <span
                                class="key-status"
                                :class="`key-status-${key.status.toLowerCase()}`"
                                >{{ key.status }}</span
                            >
                        </td>
                        <td>{{ date(key.created_at) }}</td>
                        <td>{{ date(key.last_used_at) }}</td>
                        <td>{{ date(key.expires_at) }}</td>
                        <td>
                            <button
                                type="button"
                                class="btn btn-outline-danger btn-sm"
                                :aria-label="`Revoke ${key.name}`"
                                :disabled="key.status === 'Revoked' || pending || loading"
                                @click="openRevoke(key, $event)"
                            >
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && !error && !rows.length">
                        <td colspan="7" class="release-empty">
                            <strong>{{
                                chips.length
                                    ? 'No API keys found.'
                                    : 'No API keys have been created yet.'
                            }}</strong>
                            <span>{{
                                chips.length
                                    ? 'Try adjusting your search or filters.'
                                    : 'Create a key to connect an integration.'
                            }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="release-footer">
            <span class="release-range" role="status">{{
                loading ? 'Loading results…' : error ? 'Results unavailable' : resultRange
            }}</span>
            <div class="release-page-size">
                <label for="api-keys-per-page" class="visually-hidden">API keys per page</label>
                <span aria-hidden="true">Rows per page</span>
                <select
                    id="api-keys-per-page"
                    v-model.number="perPage"
                    class="form-select form-select-sm w-auto"
                    :disabled="pending || loading"
                    @change="submit"
                >
                    <option v-for="size in [10, 20, 50, 100]" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
            </div>
            <nav
                v-if="lastPage > 1 && !error"
                class="d-flex align-items-center gap-2"
                aria-label="API key pages"
            >
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    :disabled="page === 1 || loading || pending"
                    @click="load(page - 1, $event.currentTarget)"
                >
                    Previous
                </button>
                <span class="small">Page {{ page }} of {{ lastPage }}</span>
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    :disabled="page === lastPage || loading || pending"
                    @click="load(page + 1, $event.currentTarget)"
                >
                    Next
                </button>
            </nav>
        </div>
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

<style scoped src="./workspace-tables.css"></style>
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
    white-space: nowrap;
}
.key-status {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: #f0f3f7;
    color: #47566d;
}
.key-status-active {
    background: #e8f5ee;
    color: #216044;
}
.key-status-expired {
    background: #fff3df;
    color: #79500e;
}
.key-status-revoked {
    background: #fcecef;
    color: #913447;
}
</style>
