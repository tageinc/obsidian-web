<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { requestJson } from '../../shared/api/client';

const props = defineProps({ csrfToken: { type: String, default: '' } });
const entries = ref([]);
const unreadCount = ref(0);
const nextCursor = ref(null);
const open = ref(false);
const loading = ref(false);
const loadingMore = ref(false);
const changing = ref(false);
const error = ref('');
const loadError = ref(false);
const moreError = ref(false);
const trigger = ref(null);
const panel = ref(null);
const feedback = ref(null);
const list = ref(null);
const position = ref({ top: '0px', left: '0px' });
const busy = computed(() => loading.value || loadingMore.value || changing.value);
const badge = computed(() => (unreadCount.value > 9 ? '9+' : unreadCount.value));
const controller = new AbortController();
let resizeObserver;

function request(url, options = {}) {
    return requestJson(url, { ...options, csrfToken: props.csrfToken, signal: controller.signal });
}
function place() {
    if (!open.value || !trigger.value || !panel.value) return;
    const anchor = trigger.value.getBoundingClientRect();
    const popup = panel.value.getBoundingClientRect();
    const width = document.documentElement.clientWidth || window.innerWidth;
    const left = Math.max(8, Math.min(anchor.right - popup.width, width - popup.width - 8));
    const top = Math.max(8, Math.min(anchor.bottom + 6, window.innerHeight - popup.height - 8));
    position.value = { top: `${top}px`, left: `${left}px` };
}
function close(restoreFocus = false) {
    open.value = false;
    if (restoreFocus) trigger.value?.focus({ preventScroll: true });
}
defineExpose({ close });
async function reveal() {
    open.value = true;
    const loadingRequest = load();
    await nextTick();
    place();
    panel.value?.focus({ preventScroll: true });
    await loadingRequest;
    await nextTick();
    place();
}
function toggle() {
    if (open.value) close();
    else void reveal();
}
async function load() {
    if (busy.value) return;
    loading.value = true;
    loadError.value = false;
    error.value = '';
    try {
        const result = await request('/api/app-activity');
        entries.value = result.data;
        unreadCount.value = result.unread_count;
        nextCursor.value = result.next_cursor;
        moreError.value = false;
        if (list.value) list.value.scrollTop = 0;
    } catch (exception) {
        if (exception.name !== 'AbortError') loadError.value = true;
    } finally {
        loading.value = false;
        await nextTick();
        place();
    }
}
async function loadMore() {
    if (!nextCursor.value || busy.value) return;
    const restoreFocus = document.activeElement?.classList.contains('activity-feed__more');
    const firstNewIndex = entries.value.length;
    loadingMore.value = true;
    moreError.value = false;
    try {
        const result = await request(
            `/api/app-activity?cursor=${encodeURIComponent(nextCursor.value)}`,
        );
        const existing = new Set(entries.value.map(({ id }) => id));
        entries.value.push(...result.data.filter(({ id }) => !existing.has(id)));
        unreadCount.value = result.unread_count;
        nextCursor.value = result.next_cursor;
    } catch (exception) {
        if (exception.name !== 'AbortError') moreError.value = true;
    } finally {
        loadingMore.value = false;
        await nextTick();
        if (restoreFocus && open.value) {
            const target = moreError.value
                ? panel.value?.querySelector('.activity-feed__more')
                : panel.value?.querySelectorAll('.activity-feed__open')[firstNewIndex];
            (target || panel.value)?.focus();
        }
    }
}
function onScroll(event) {
    const element = event.currentTarget;
    if (!moreError.value && element.scrollHeight - element.scrollTop - element.clientHeight < 80)
        void loadMore();
}
async function showError(message) {
    error.value = message;
    await nextTick();
    if (open.value) feedback.value?.focus();
}
async function openEntry(entry) {
    if (busy.value) return;
    changing.value = true;
    error.value = '';
    try {
        if (!entry.read_at) {
            const result = await request(`/api/app-activity/${encodeURIComponent(entry.id)}/read`, {
                method: 'PATCH',
            });
            const index = entries.value.findIndex(({ id }) => id === entry.id);
            if (index !== -1) entries.value[index] = result.data;
            unreadCount.value = result.unread_count;
        }
        if (entry.url) {
            const destination = new URL(entry.url, window.location.origin);
            if (destination.origin !== window.location.origin)
                throw new Error('invalid destination');
            close();
            window.location.assign(destination.href);
        }
    } catch (exception) {
        if (exception.name !== 'AbortError')
            await showError('Activity could not be opened. Please try again.');
    } finally {
        changing.value = false;
    }
}
async function dismissEntry(entry) {
    if (busy.value) return;
    changing.value = true;
    error.value = '';
    const index = entries.value.findIndex(({ id }) => id === entry.id);
    try {
        const result = await request(`/api/app-activity/${encodeURIComponent(entry.id)}`, {
            method: 'DELETE',
        });
        entries.value = entries.value.filter(({ id }) => id !== entry.id);
        unreadCount.value = result.unread_count;
        changing.value = false;
        await nextTick();
        if (open.value) {
            const remaining = panel.value?.querySelectorAll('.activity-feed__open');
            (remaining?.[Math.min(index, remaining.length - 1)] || panel.value)?.focus();
        }
    } catch (exception) {
        if (exception.name !== 'AbortError')
            await showError('Activity could not be dismissed. Please try again.');
    } finally {
        changing.value = false;
    }
}
async function clearAll() {
    if (busy.value) return;
    changing.value = true;
    error.value = '';
    try {
        const result = await request('/api/app-activity', { method: 'DELETE' });
        entries.value = [];
        unreadCount.value = result.unread_count;
        nextCursor.value = null;
        moreError.value = false;
        await nextTick();
        if (open.value) panel.value?.focus();
    } catch (exception) {
        if (exception.name !== 'AbortError')
            await showError('Activity could not be cleared. Please try again.');
    } finally {
        changing.value = false;
    }
}
function contains(target) {
    return trigger.value?.contains(target) || panel.value?.contains(target);
}
function outside(event) {
    if (!contains(event.target)) close();
}
function leave(event) {
    if (event.relatedTarget && !contains(event.relatedTarget)) close();
}
function keydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close(true);
    } else if (event.key === 'Tab') {
        const controls = [...panel.value.querySelectorAll('button:not(:disabled), [tabindex="0"]')];
        if (event.shiftKey && (event.target === panel.value || event.target === controls[0])) {
            event.preventDefault();
            close(true);
        } else if (!event.shiftKey && event.target === controls.at(-1)) close(true);
    }
}
function timestamp(value) {
    const date = new Date(value);
    if (!value || Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}
onMounted(() => {
    void load();
    document.addEventListener('click', outside);
    document.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    if (typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(place);
        resizeObserver.observe(panel.value);
    }
});
onBeforeUnmount(() => {
    controller.abort();
    resizeObserver?.disconnect();
    document.removeEventListener('click', outside);
    document.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
});
</script>

<template>
    <button
        ref="trigger"
        type="button"
        class="activity-trigger"
        title="App activity"
        aria-label="App activity"
        aria-controls="app-activity-feed"
        :aria-expanded="open"
        @click="toggle"
        @keydown.down.prevent="reveal"
        @keydown.esc.prevent="close(true)"
        @focusout="leave"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path
                fill="currentColor"
                d="M12 2a1.75 1.75 0 0 0-1.75 1.75v.38C7.7 4.82 6 7.2 6 10v4.2l-1.55 1.86A1.1 1.1 0 0 0 5.28 18h13.44a1.1 1.1 0 0 0 .83-1.94L18 14.2V10c0-2.8-1.7-5.18-4.25-5.87v-.38A1.75 1.75 0 0 0 12 2zm-2.2 17.4a2.2 2.2 0 0 0 4.4 0h-4.4z"
            />
        </svg>
        <span
            v-if="unreadCount"
            class="activity-badge"
            :aria-label="`${unreadCount} unread activity items`"
            >{{ badge }}</span
        >
    </button>
    <Teleport to="body">
        <section
            id="app-activity-feed"
            ref="panel"
            v-show="open"
            class="activity-feed"
            aria-label="App activity"
            tabindex="-1"
            :style="position"
            @keydown="keydown"
            @focusout="leave"
        >
            <header class="activity-feed__header">
                <strong>Notifications</strong>
                <div class="activity-feed__header-actions">
                    <button
                        v-if="entries.length"
                        type="button"
                        class="activity-feed__clear"
                        :disabled="busy"
                        @click="clearAll"
                    >
                        Clear all
                    </button>
                    <span v-if="unreadCount" class="activity-feed__count"
                        >{{ unreadCount }} unread</span
                    >
                </div>
            </header>
            <p v-if="error" ref="feedback" class="activity-feed__error" role="alert" tabindex="-1">
                {{ error }}
            </p>
            <p v-if="loadError" class="activity-feed__message" role="alert">
                Activity could not be loaded.
                <button type="button" class="activity-feed__more" :disabled="busy" @click="load">
                    Retry
                </button>
            </p>
            <p v-else-if="loading && !entries.length" class="activity-feed__message" role="status">
                Loading activity…
            </p>
            <p v-else-if="!entries.length" class="activity-feed__message" role="status">
                You’re all caught up.
            </p>
            <div
                v-if="entries.length"
                ref="list"
                class="activity-feed__list"
                tabindex="0"
                aria-label="Activity list"
                @scroll.passive="onScroll"
            >
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    class="activity-feed__item"
                    :class="{ 'is-unread': !entry.read_at }"
                >
                    <button
                        class="activity-feed__open"
                        type="button"
                        :disabled="busy"
                        @click="openEntry(entry)"
                    >
                        <span class="activity-feed__indicator" aria-hidden="true" />
                        <span class="activity-feed__content">
                            <span v-if="!entry.read_at" class="activity-visually-hidden"
                                >Unread:
                            </span>
                            <span class="activity-feed__title">{{ entry.title }}</span>
                            <span v-if="entry.body" class="activity-feed__body">{{
                                entry.body
                            }}</span>
                            <time class="activity-feed__time" :datetime="entry.created_at">{{
                                timestamp(entry.created_at)
                            }}</time>
                        </span>
                    </button>
                    <button
                        class="activity-feed__dismiss"
                        type="button"
                        :disabled="busy"
                        :aria-label="`Dismiss activity: ${entry.title}`"
                        title="Dismiss activity"
                        @click="dismissEntry(entry)"
                    >
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <p v-if="loadingMore" class="activity-feed__message" role="status">
                    Loading more activity…
                </p>
                <p v-else-if="moreError" class="activity-feed__message" role="alert">
                    More activity could not be loaded.
                    <button
                        type="button"
                        class="activity-feed__more"
                        :disabled="busy"
                        @click="loadMore"
                    >
                        Retry
                    </button>
                </p>
                <button
                    v-else-if="nextCursor"
                    class="activity-feed__more"
                    type="button"
                    :disabled="busy"
                    @click="loadMore"
                >
                    Load more
                </button>
            </div>
        </section>
    </Teleport>
</template>

<style scoped>
.activity-trigger {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 2.75rem;
    height: 2.75rem;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--obsidian-accent-strong, #1c1c1c);
    cursor: pointer;
}
.activity-trigger:hover,
.activity-trigger[aria-expanded='true'] {
    background: var(--obsidian-accent-soft, #f0f0f0);
}
.activity-trigger svg {
    width: 1.25rem;
    height: 1.25rem;
}
.activity-badge {
    position: absolute;
    top: 0.15rem;
    right: 0.05rem;
    min-width: 1.125rem;
    height: 1.125rem;
    padding: 0 0.2rem;
    border: 2px solid var(--obsidian-surface, #fff);
    border-radius: 999px;
    background: #b02a37;
    color: #fff;
    font-size: 0.625rem;
    font-weight: 700;
    line-height: 0.875rem;
}
.activity-feed {
    position: fixed;
    z-index: 1060;
    width: min(23rem, calc(100vw - 1rem));
    max-height: calc(100vh - 1rem);
    max-height: calc(100dvh - 1rem);
    padding: 0.5rem 0.65rem;
    border: 1px solid var(--obsidian-border, #e5e7eb);
    border-radius: 8px;
    background: var(--obsidian-surface, #fff);
    color: var(--obsidian-text, #212529);
    box-shadow: 0 8px 24px rgb(0 0 0 / 15%);
    overflow-y: auto;
    font-size: 1rem;
}
.activity-feed__header,
.activity-feed__header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.activity-feed__header {
    justify-content: space-between;
    padding: 0.25rem 0.25rem 0.6rem;
    border-bottom: 1px solid var(--obsidian-border, #e5e7eb);
}
.activity-feed__clear {
    padding: 0.25rem;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: var(--obsidian-text-muted, #5e6570);
    font-size: 0.75rem;
    cursor: pointer;
}
.activity-feed__count,
.activity-feed__time {
    color: var(--obsidian-text-muted, #5e6570);
    font-size: 0.75rem;
}
.activity-feed__message,
.activity-feed__error {
    margin: 0;
    padding: 1rem 0.5rem;
    font-size: 0.875rem;
    text-align: center;
}
.activity-feed__message {
    color: var(--obsidian-text-muted, #5e6570);
}
.activity-feed__error {
    color: var(--obsidian-danger, #b02a37);
}
.activity-feed__list {
    max-height: min(24rem, calc(100vh - 9rem));
    max-height: min(24rem, calc(100dvh - 9rem));
    overflow-y: auto;
    overscroll-behavior: contain;
    margin-top: 0.4rem;
}
.activity-feed__item {
    position: relative;
}
.activity-feed__open {
    display: flex;
    width: 100%;
    gap: 0.625rem;
    padding: 0.7rem 2.75rem 0.7rem 0.4rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--obsidian-text, #212529);
    text-align: left;
    cursor: pointer;
}
.activity-feed__dismiss {
    position: absolute;
    top: 0;
    right: 0;
    width: 2.75rem;
    height: 2.75rem;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--obsidian-text-muted, #5e6570);
    font-size: 1.25rem;
    cursor: pointer;
}
.activity-feed__open:hover,
.activity-feed__dismiss:hover,
.activity-feed__clear:hover {
    background: var(--obsidian-surface-subtle, #f3f4f6);
}
.activity-feed button:disabled {
    cursor: wait;
    opacity: 0.6;
}
.activity-feed__indicator {
    flex: 0 0 auto;
    width: 0.5rem;
    height: 0.5rem;
    margin-top: 0.35rem;
    border-radius: 50%;
    background: transparent;
}
.is-unread .activity-feed__indicator {
    background: #0287ff;
}
.activity-feed__content {
    display: grid;
    min-width: 0;
    gap: 0.15rem;
    overflow-wrap: anywhere;
}
.activity-feed__title {
    font-size: 0.875rem;
    font-weight: 500;
}
.is-unread .activity-feed__title {
    font-weight: 600;
}
.activity-feed__body {
    display: -webkit-box;
    overflow: hidden;
    color: var(--obsidian-text-muted, #5e6570);
    font-size: 0.8125rem;
    line-height: 1.3;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.activity-feed__more {
    display: block;
    margin: 0.5rem auto;
    padding: 0.5rem;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: var(--obsidian-accent-strong, #1c1c1c);
    font-size: 0.875rem;
    text-decoration: underline;
    cursor: pointer;
}
.activity-trigger:focus-visible,
.activity-feed:focus-visible,
.activity-feed button:focus-visible,
.activity-feed__list:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong, #1c1c1c);
    outline-offset: -2px;
}
.activity-visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}
</style>
