<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FormModal from '../../shared/components/FormModal.vue';
import { requestJson } from '../../shared/api/client';
import { applyThemeMode, normalizeThemeMode } from '../../shared/theme';

const props = defineProps({ csrfToken: { type: String, default: '' } });
const emit = defineEmits(['close']);
const receiveEmails = ref(true);
const themeMode = ref('adaptive');
const loading = ref(true);
const loaded = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const feedback = ref(null);
const saveButton = ref(null);
const controller = new AbortController();

async function showError(message) {
    error.value = message;
    await nextTick();
    feedback.value?.focus();
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        const result = await requestJson('/api/user-settings', {
            csrfToken: props.csrfToken,
            signal: controller.signal,
        });
        receiveEmails.value = result.data.receive_app_activity_emails;
        themeMode.value = normalizeThemeMode(result.data.theme_mode);
        loaded.value = true;
    } catch (exception) {
        if (exception.name !== 'AbortError')
            await showError('Settings could not be loaded. Please try again.');
    } finally {
        loading.value = false;
    }
}
async function save() {
    if (saving.value || !loaded.value) return;
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const result = await requestJson('/api/user-settings', {
            method: 'PATCH',
            data: { receive_app_activity_emails: receiveEmails.value, theme_mode: themeMode.value },
            csrfToken: props.csrfToken,
            signal: controller.signal,
        });
        receiveEmails.value = result.data.receive_app_activity_emails;
        themeMode.value = normalizeThemeMode(result.data.theme_mode);
        applyThemeMode(themeMode.value);
        success.value = 'Settings saved.';
    } catch (exception) {
        if (exception.name !== 'AbortError')
            await showError(exception.message || 'Settings could not be saved. Please try again.');
    } finally {
        saving.value = false;
        await nextTick();
        if (success.value) saveButton.value?.focus();
    }
}
onMounted(load);
onBeforeUnmount(() => controller.abort());
</script>

<template>
    <FormModal
        id="user-settings-modal"
        title="User Settings"
        close-label="Close User Settings"
        class="user-settings-modal"
        :pending="saving"
        @close="emit('close')"
    >
        <form @submit.prevent="save">
            <p v-if="loading" class="settings-message" role="status">Loading settings…</p>
            <div class="settings-theme">
                <label for="theme-mode">Theme</label>
                <select
                    id="theme-mode"
                    v-model="themeMode"
                    class="form-select"
                    :disabled="loading || !loaded || saving"
                    aria-describedby="theme-mode-help"
                    required
                >
                    <option value="adaptive">Adaptive</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
                <p id="theme-mode-help" class="settings-help">
                    Adaptive uses Light from 6:00 AM–5:59 PM and Dark from 6:00 PM–5:59 AM in your
                    local time.
                </p>
            </div>
            <div class="settings-option">
                <input
                    id="receive-app-activity-emails"
                    v-model="receiveEmails"
                    type="checkbox"
                    :disabled="loading || !loaded || saving"
                    aria-describedby="app-activity-email-help"
                />
                <div>
                    <label for="receive-app-activity-emails">Receive app activity emails</label>
                    <p id="app-activity-email-help" class="settings-help">
                        Receive emails about device status changes. In-app activity stays enabled.
                        Password reset and account verification emails are unaffected.
                    </p>
                </div>
            </div>
            <p v-if="error" ref="feedback" class="settings-error" role="alert" tabindex="-1">
                {{ error }}
                <button
                    v-if="!loaded"
                    type="button"
                    class="settings-retry"
                    :disabled="loading"
                    @click="load"
                >
                    Retry
                </button>
            </p>
            <p v-if="success" class="settings-success" role="status">{{ success }}</p>
            <div class="settings-actions">
                <button
                    class="btn btn-secondary"
                    type="button"
                    :disabled="saving"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    ref="saveButton"
                    class="btn btn-primary"
                    type="submit"
                    :disabled="loading || !loaded || saving"
                >
                    {{ saving ? 'Saving…' : 'Save settings' }}
                </button>
            </div>
        </form>
    </FormModal>
</template>

<style scoped>
.user-settings-modal {
    width: min(32rem, calc(100% - 2rem));
}
.settings-option {
    display: flex;
    align-items: start;
    gap: 0.75rem;
}
.settings-option input {
    flex: 0 0 auto;
    width: 1.1rem;
    height: 1.1rem;
    margin: 0.25rem 0 0;
    accent-color: var(--obsidian-accent, #1c1c1c);
}
.settings-option label,
.settings-theme label {
    font-weight: 500;
    margin-bottom: 0.25rem;
}
.settings-help,
.settings-message {
    color: var(--obsidian-text-muted, #5e6570);
    font-size: 0.875rem;
}
.settings-help {
    margin: 0 0 1rem;
}
.settings-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}
.settings-error {
    color: var(--obsidian-danger, #b02a37);
}
.settings-success {
    color: var(--obsidian-success, #146c43);
}
.settings-retry {
    border: 0;
    background: transparent;
    color: inherit;
    text-decoration: underline;
}
.settings-option input:focus-visible,
.settings-retry:focus-visible {
    outline: 2px solid var(--obsidian-accent-strong, #1c1c1c);
    outline-offset: 3px;
}
</style>
