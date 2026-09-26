<script setup>
import { nextTick, ref } from 'vue';
import ActivityFeed from './ActivityFeed.vue';
import UserSettings from './UserSettings.vue';

defineProps({ csrfToken: { type: String, default: '' } });
const settingsOpen = ref(false);
const settingsButton = ref(null);
const feed = ref(null);
function openSettings() {
    feed.value?.close();
    settingsOpen.value = true;
}
async function closeSettings() {
    settingsOpen.value = false;
    await nextTick();
    settingsButton.value?.focus();
}
</script>

<template>
    <div class="activity-controls" role="group" aria-label="Activity and settings">
        <ActivityFeed ref="feed" :csrf-token="csrfToken" />
        <button
            ref="settingsButton"
            class="activity-settings-trigger"
            type="button"
            title="User Settings"
            aria-label="User Settings"
            aria-haspopup="dialog"
            @click="openSettings"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linejoin="round"
                    d="m9.5 3-.5 2a7.4 7.4 0 0 0-1.8 1L5.3 5.5 3 9.5l1.5 1.4a8 8 0 0 0 0 2.2L3 14.5l2.3 4 1.9-.5A7.4 7.4 0 0 0 9 19l.5 2h5l.5-2a7.4 7.4 0 0 0 1.8-1l1.9.5 2.3-4-1.5-1.4a8 8 0 0 0 0-2.2L21 9.5l-2.3-4-1.9.5A7.4 7.4 0 0 0 15 5l-.5-2z"
                />
                <circle
                    cx="12"
                    cy="12"
                    r="3"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                />
            </svg>
        </button>
        <Teleport to="body">
            <UserSettings v-if="settingsOpen" :csrf-token="csrfToken" @close="closeSettings" />
        </Teleport>
    </div>
</template>

<style scoped>
.activity-controls {
    display: flex;
    align-items: center;
    flex: 0 0 auto;
    gap: 0.25rem;
}
.activity-settings-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--obsidian-accent-strong, #1c1c1c);
    cursor: pointer;
}
.activity-settings-trigger:hover {
    background: var(--obsidian-accent-soft, #f0f0f0);
}
.activity-settings-trigger:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong, #1c1c1c);
    outline-offset: 2px;
}
.activity-settings-trigger svg {
    width: 1.25rem;
    height: 1.25rem;
}
</style>
