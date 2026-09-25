<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import ReleaseFilters from './ReleaseFilters.vue';

const props = defineProps({
    kind: { type: String, required: true },
    title: { type: String, required: true },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    developerUrl: { type: String, required: true },
    otherSize: { type: Number, required: true },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({ prefixes: [], versions: [] }) },
    preservedQuery: { type: Array, default: () => [] },
    pending: { type: Boolean, default: false },
});
const form = ref(null);
const search = ref('');
const sort = ref('newest');
const perPage = ref(props.pagination.perPage);
const applied = ref({ prefixes: [], versions: [], from: '', to: '' });
const releaseName = computed(() => (props.kind === 'firmware' ? 'firmware' : 'configuration'));
const otherQuery = computed(() => {
    const name = props.kind === 'firmware' ? 'config_show' : 'firmware_show';
    return props.preservedQuery.some((item) => item.name === name)
        ? props.preservedQuery
        : [...props.preservedQuery, { name, value: props.otherSize }];
});
const chips = computed(() => [
    ...(props.filters.search
        ? [
              {
                  field: 'search',
                  value: props.filters.search,
                  label: `Search: ${props.filters.search}`,
              },
          ]
        : []),
    ...applied.value.prefixes.map((value) => ({
        field: 'prefixes',
        value,
        label: `Prefix: ${value}`,
    })),
    ...applied.value.versions.map((value) => ({
        field: 'versions',
        value,
        label: `Version: ${value}`,
    })),
    ...(applied.value.from ? [{ field: 'from', label: `From: ${applied.value.from}` }] : []),
    ...(applied.value.to ? [{ field: 'to', label: `To: ${applied.value.to}` }] : []),
]);
const filterCount = computed(
    () =>
        applied.value.prefixes.length +
        applied.value.versions.length +
        Number(Boolean(applied.value.from)) +
        Number(Boolean(applied.value.to)),
);
const total = computed(() => props.pagination.total ?? props.rows.length);
const resultRange = computed(() => {
    if (!props.rows.length) return `0 of ${total.value} releases`;
    return `Showing ${props.pagination.from ?? 1}–${props.pagination.to ?? props.rows.length} of ${total.value} releases`;
});
watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search || '';
        sort.value = filters.sort || 'newest';
        applied.value = {
            prefixes: [...(filters.prefixes || [])],
            versions: [...(filters.versions || [])],
            from: filters.from || '',
            to: filters.to || '',
        };
    },
    { immediate: true, deep: true },
);
watch(
    () => props.pagination.perPage,
    (size) => {
        perPage.value = size;
    },
);

async function submit() {
    await nextTick();
    form.value?.requestSubmit();
}
function apply(filters) {
    applied.value = filters;
    submit();
}
function clear() {
    search.value = '';
    sort.value = 'newest';
    applied.value = { prefixes: [], versions: [], from: '', to: '' };
    submit();
}
function remove(chip) {
    if (chip.field === 'search') search.value = '';
    else if (Array.isArray(applied.value[chip.field])) {
        applied.value[chip.field] = applied.value[chip.field].filter(
            (value) => value !== chip.value,
        );
    } else applied.value[chip.field] = '';
    submit();
}
</script>

<template>
    <form ref="form" :action="developerUrl" method="GET" class="release-history">
        <input type="hidden" name="section" :value="kind" />
        <input
            v-for="(item, index) in otherQuery"
            :key="index"
            type="hidden"
            :name="item.name"
            :value="item.value"
        />
        <template v-for="field in ['prefixes', 'versions']" :key="field">
            <input
                v-for="value in applied[field]"
                :key="value"
                type="hidden"
                :name="`${kind}_${field}[]`"
                :value="value"
            />
        </template>
        <input v-if="applied.from" type="hidden" :name="`${kind}_from`" :value="applied.from" />
        <input v-if="applied.to" type="hidden" :name="`${kind}_to`" :value="applied.to" />

        <div class="release-history-heading">
            <h3 class="h5 mb-0">Release history</h3>
            <span class="release-total"
                >{{ total }} {{ total === 1 ? 'release' : 'releases' }}</span
            >
        </div>
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
                        :id="`${kind}-search`"
                        v-model="search"
                        type="search"
                        :name="`${kind}_search`"
                        class="form-control"
                        :aria-label="`Search ${releaseName} releases`"
                        placeholder="Search version, prefix or description…"
                        maxlength="255"
                        :disabled="pending"
                    />
                </div>
                <button
                    type="submit"
                    class="btn btn-outline-secondary release-search-button"
                    :disabled="pending"
                >
                    Search
                </button>
            </div>
            <ReleaseFilters
                :kind="kind"
                :title="title"
                :filters="applied"
                :options="filterOptions"
                :count="filterCount"
                :disabled="pending"
                @apply="apply"
                @clear="clear"
            />
            <div class="release-sort">
                <label :for="`${kind}-sort`">Sort by</label>
                <select
                    :id="`${kind}-sort`"
                    v-model="sort"
                    :name="`${kind}_sort`"
                    class="form-select form-select-sm"
                    :aria-label="`Sort ${releaseName} releases`"
                    :disabled="pending"
                    @change="submit"
                >
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="version_desc">Version: high to low</option>
                    <option value="version_asc">Version: low to high</option>
                    <option value="prefix_asc">Prefix: A–Z</option>
                </select>
            </div>
        </div>
        <div
            v-if="chips.length"
            class="release-active-filters"
            :aria-label="`Active ${releaseName} filters`"
        >
            <button
                v-for="chip in chips"
                :key="`${chip.field}-${chip.value || ''}`"
                type="button"
                class="release-filter-chip"
                :aria-label="`Remove ${chip.label}`"
                :disabled="pending"
                @click="remove(chip)"
            >
                <span>{{ chip.label }}</span
                ><span aria-hidden="true">×</span>
            </button>
            <button
                type="button"
                class="btn btn-link btn-sm release-clear"
                :disabled="pending"
                @click="clear"
            >
                Clear filters
            </button>
        </div>

        <div
            class="table-responsive release-table-container"
            tabindex="0"
            role="region"
            :aria-label="`${title} release history`"
        >
            <table class="table align-middle developer-history mb-0">
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
                        <th scope="col">Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="index">
                        <th scope="row">
                            <span class="release-version">{{ row.version }}</span>
                        </th>
                        <td>
                            <span class="release-prefix">{{ row.prefix }}</span>
                        </td>
                        <td class="developer-description">{{ row.description || '—' }}</td>
                        <td class="release-date">
                            <time :datetime="row.createdAt?.replace(' ', 'T')"
                                ><span>{{ row.createdAt?.split(' ')[0] || '—' }}</span
                                ><span class="release-time">{{
                                    row.createdAt?.split(' ')[1]
                                }}</span></time
                            >
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="release-empty">
                            <strong>{{
                                kind === 'firmware'
                                    ? 'No firmware updates found.'
                                    : 'No configuration updates found.'
                            }}</strong>
                            <span>{{
                                chips.length
                                    ? 'Try adjusting your search or filters.'
                                    : 'Uploaded releases will appear here.'
                            }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="release-footer">
            <span class="release-range">{{ resultRange }}</span>
            <div class="release-page-size">
                <label :for="`${kind}-per-page`" class="visually-hidden"
                    >{{ title }} updates per page</label
                >
                <span aria-hidden="true">Rows per page</span>
                <select
                    :id="`${kind}-per-page`"
                    v-model="perPage"
                    :name="`${kind}_show`"
                    class="form-select form-select-sm w-auto"
                    :disabled="pending"
                    @change="submit"
                >
                    <option v-for="size in pagination.sizes" :key="size" :value="size">
                        {{ size }}
                    </option>
                </select>
            </div>
            <nav v-if="pagination.lastPage > 1" :aria-label="`${title} pages`">
                <ul class="pagination pagination-sm flex-wrap mb-0">
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
    </form>
</template>

<style scoped src="./workspace-tables.css"></style>
