<script setup>
import { inject } from 'vue';
import { RouterView } from 'vue-router';
import { workspaceKey } from './router.js';

const { state, retry } = inject(workspaceKey);
</script>

<template>
    <div :aria-busy="state.loading">
        <div v-if="state.loading" class="container py-4" role="status">Loading page…</div>
        <section
            v-else-if="state.error"
            class="container py-4"
            aria-labelledby="workspace-error-heading"
        >
            <h1 id="workspace-error-heading" class="h3">
                {{
                    [401, 419].includes(state.error.status)
                        ? 'Please sign in again'
                        : 'Page unavailable'
                }}
            </h1>
            <p role="alert">{{ state.error.message }}</p>
            <a v-if="[401, 419].includes(state.error.status)" class="btn btn-primary" href="/login"
                >Sign in</a
            >
            <a
                v-else-if="state.error.reload"
                class="btn btn-primary"
                :href="state.url"
                data-document-navigation
                >Reload page</a
            >
            <button v-else class="btn btn-primary" type="button" @click="retry">Try again</button>
        </section>
        <RouterView v-else v-slot="{ Component }">
            <component
                :is="Component"
                v-if="Component && state.props"
                v-bind="state.props"
                :key="state.url.split('#')[0]"
            />
        </RouterView>
    </div>
</template>
