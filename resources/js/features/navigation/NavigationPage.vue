<script setup>
import { ref } from 'vue';
import { useSessionStore } from '../../shared/stores/session';
const props = defineProps({
    name: { type: String, required: true },
    logo: { type: String, required: true },
    user: { type: Object, default: null },
    links: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});
const expanded = ref(false);
const session = useSessionStore();
session.initialize(props.user);
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
                @click="expanded = !expanded"
            >
                <span class="navbar-toggler-icon" />
            </button>
            <div
                id="primary-navigation"
                class="collapse navbar-collapse"
                :class="{ show: expanded }"
            >
                <ul class="navbar-nav me-auto">
                    <template v-if="session.signedIn">
                        <li class="nav-item">
                            <a class="nav-link" :href="links.dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="links.profile">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :href="links.registerDevice">Register my device</a>
                        </li>
                        <li v-if="links.admin" class="nav-item">
                            <a class="nav-link" :href="links.admin">Developer Workspace</a>
                        </li>
                    </template>
                    <template v-else>
                        <li class="nav-item"><a class="nav-link" :href="links.login">Login</a></li>
                        <li class="nav-item">
                            <a class="nav-link" :href="links.register">Register</a>
                        </li>
                    </template>
                </ul>
                <form
                    v-show="session.signedIn"
                    method="POST"
                    :action="links.logout"
                    class="d-flex align-items-center gap-3"
                    @submit="session.clear()"
                >
                    <span>{{ session.name }}</span
                    ><input type="hidden" name="_token" :value="csrfToken" /><button
                        class="btn btn-outline-secondary"
                        type="submit"
                    >
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>
</template>
