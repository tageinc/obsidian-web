<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSessionStore } from '../../shared/stores/session';
const props = defineProps({
    name: { type: String, required: true },
    logo: { type: String, required: true },
    user: { type: Object, default: null },
    links: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});
const expanded = ref(false);
const accountOpen = ref(false);
const account = ref(null);
const accountButton = ref(null);
const session = useSessionStore();
session.initialize(props.user);
function closeNavigation() {
    accountOpen.value = false;
    expanded.value = false;
}
function toggleNavigation() {
    expanded.value = !expanded.value;
    if (!expanded.value) accountOpen.value = false;
}
function dismiss(event) {
    if (!account.value?.contains(event.target)) accountOpen.value = false;
}
function leaveAccount(event) {
    if (!account.value?.contains(event.relatedTarget)) accountOpen.value = false;
}
function escapeAccount(event) {
    if (!accountOpen.value) return;
    event.preventDefault();
    event.stopPropagation();
    accountOpen.value = false;
    accountButton.value?.focus();
}
async function focusItem(last = false) {
    accountOpen.value = true;
    await nextTick();
    const items = account.value?.querySelectorAll('.dropdown-item');
    items?.[last ? items.length - 1 : 0]?.focus();
}
function navigateItems(event) {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const items = [...account.value.querySelectorAll('.dropdown-item')];
    const current = items.indexOf(event.target);
    const next =
        event.key === 'Home'
            ? 0
            : event.key === 'End'
              ? items.length - 1
              : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
    items[next]?.focus();
}
onMounted(() => document.addEventListener('click', dismiss));
onBeforeUnmount(() => document.removeEventListener('click', dismiss));
</script>

<template>
    <nav
        class="navbar navbar-expand-md navbar-light bg-white shadow-sm"
        aria-label="Main navigation"
    >
        <div class="container">
            <a class="navbar-brand" :href="links.home"
                ><img :src="logo" alt="" class="obsidian-logo" /> {{ name }}</a
            >
            <button
                class="navbar-toggler"
                type="button"
                aria-controls="primary-navigation"
                :aria-expanded="expanded"
                aria-label="Toggle navigation"
                @click="toggleNavigation"
            >
                <span class="navbar-toggler-icon" />
            </button>
            <div
                id="primary-navigation"
                class="collapse navbar-collapse"
                :class="{ show: expanded }"
            >
                <ul class="navbar-nav ms-auto">
                    <li
                        v-show="session.signedIn"
                        ref="account"
                        class="nav-item dropdown account-dropdown"
                        @focusout="leaveAccount"
                        @keydown.esc="escapeAccount"
                    >
                        <button
                            id="account-navigation-toggle"
                            ref="accountButton"
                            type="button"
                            class="nav-link dropdown-toggle account-toggle"
                            aria-controls="account-navigation"
                            :aria-expanded="accountOpen"
                            @click="accountOpen = !accountOpen"
                            @keydown.down.prevent="focusItem()"
                            @keydown.up.prevent="focusItem(true)"
                        >
                            {{ session.name }}
                        </button>
                        <div
                            id="account-navigation"
                            v-show="accountOpen"
                            class="dropdown-menu dropdown-menu-end show account-menu"
                            aria-labelledby="account-navigation-toggle"
                            @keydown="navigateItems"
                        >
                            <a
                                class="dropdown-item"
                                :href="links.dashboard"
                                @click="closeNavigation"
                                >Dashboard</a
                            >
                            <a class="dropdown-item" :href="links.profile" @click="closeNavigation"
                                >Profile</a
                            >
                            <a
                                v-if="links.developer"
                                class="dropdown-item"
                                :href="links.developer"
                                @click="closeNavigation"
                                >Developer Workspace</a
                            >
                            <hr class="dropdown-divider" />
                            <form method="POST" :action="links.logout" @submit="session.clear()">
                                <input type="hidden" name="_token" :value="csrfToken" />
                                <button class="dropdown-item" type="submit">Logout</button>
                            </form>
                        </div>
                    </li>
                    <template v-if="!session.signedIn">
                        <li class="nav-item"><a class="nav-link" :href="links.login">Login</a></li>
                        <li class="nav-item">
                            <a class="nav-link" :href="links.register">Register</a>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.account-toggle {
    border: 0;
    background: transparent;
    font: inherit;
    text-align: left;
}
.account-menu {
    right: 0;
    left: auto;
    min-width: 14rem;
    max-width: calc(100vw - 2rem);
    padding: 0.5rem 0;
}
.account-menu .dropdown-item {
    padding: 0.6rem 1rem;
    white-space: normal;
}
@media (max-width: 767.98px) {
    .account-toggle {
        width: 100%;
        padding-block: 0.75rem;
    }
    .account-menu {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}
</style>
