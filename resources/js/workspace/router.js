import { nextTick, shallowReactive } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import { requestJson, RequestError } from '../shared/api/client.js';

export const workspaceKey = Symbol('obsidian-workspace');

const destinations = [
    {
        path: '/dashboard',
        page: 'dashboard',
        title: 'Dashboard',
        match: /^\/dashboard$/,
        component: () => import('../features/dashboard/DashboardPage.vue'),
    },
    {
        path: '/profile',
        page: 'profile',
        title: 'Profile',
        match: /^\/profile$/,
        component: () => import('../features/profile/ProfilePage.vue'),
    },
    {
        path: '/create-device',
        page: 'create-device',
        title: 'Create Device',
        match: /^\/create-device$/,
        component: () => import('../features/devices/DeviceFormPage.vue'),
    },
    {
        path: '/edit-device/:id([1-9]\\d*)',
        page: 'edit-device',
        title: 'Edit device',
        match: /^\/edit-device\/[1-9]\d*$/,
        component: () => import('../features/devices/DeviceFormPage.vue'),
    },
    {
        path: '/device-info/:id([1-9]\\d*)',
        page: 'device-info',
        title: 'Device info',
        match: /^\/device-info\/[1-9]\d*$/,
        component: () => import('../features/solar-tracker/DeviceInfoPage.vue'),
    },
];

export function workspaceDestination(value, origin = window.location.origin) {
    let url;
    try {
        url = new URL(value, origin);
    } catch {
        return null;
    }
    if (url.origin !== origin || !['http:', 'https:'].includes(url.protocol)) return null;
    const route = destinations.find((destination) => destination.match.test(url.pathname));
    return route
        ? { page: route.page, title: route.title, url: url.pathname + url.search + url.hash }
        : null;
}

export function interceptWorkspaceLinks(router, target = document) {
    const click = (event) => {
        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        )
            return;
        const anchor = event.target?.closest?.('a[href]');
        if (
            !anchor ||
            anchor.hasAttribute('download') ||
            (anchor.target && anchor.target !== '_self') ||
            anchor.relList.contains('external') ||
            anchor.closest('[data-document-navigation], [data-document-action]')
        )
            return;
        const destination = workspaceDestination(anchor.href);
        if (!destination) return;
        const current = router.currentRoute.value.fullPath;
        if (
            destination.url.includes('#') &&
            destination.url.split('#')[0] === current.split('#')[0]
        )
            return;
        event.preventDefault();
        // Router's error handler presents chunk failures; do not leak a rejected click promise.
        void router.push(destination.url).catch(() => {});
    };
    target.addEventListener('click', click);
    return () => target.removeEventListener('click', click);
}

export function createWorkspaceRouter({
    page,
    props,
    url = window.location.pathname + window.location.search + window.location.hash,
    session,
    history = createWebHistory(),
    request = requestJson,
    navigateDocument = (destination) => window.location.assign(destination),
    components = {},
    focusRoot = () => document.querySelector('main'),
    title = document.title,
}) {
    const initial = workspaceDestination(url);
    if (!initial || initial.page !== page)
        throw new Error('The initial page is outside this workspace.');
    const state = shallowReactive({ page, props, url: initial.url, loading: false, error: null });
    let initialPayload = { page, props, url: initial.url };
    let redirectPayload = null;
    let controller = null;
    let revision = 0;
    let disposed = false;
    let pendingUrl = initial.url;

    const router = createRouter({
        history,
        routes: destinations.map((destination) => ({
            path: destination.path,
            name: destination.page,
            component: components[destination.page] || destination.component,
            meta: { page: destination.page, title: destination.title },
        })),
        scrollBehavior: (_to, _from, saved) => saved || { top: 0 },
    });

    function clearPage() {
        state.props = null;
        state.page = null;
    }

    function clearSession() {
        revision++;
        pendingUrl = '';
        controller?.abort();
        initialPayload = null;
        redirectPayload = null;
        clearPage();
        session.clear();
    }

    async function showOutcome(route, outcome) {
        state.url = route.fullPath;
        state.error = outcome.error || null;
        state.page = outcome.payload?.page || null;
        state.props = outcome.payload?.props || null;
        state.loading = false;
        document.title = `${route.meta.title} · ${title}`;
        await nextTick();
        if (disposed || router.currentRoute.value.fullPath !== route.fullPath) return;
        const root = focusRoot();
        const target =
            root?.querySelector('[role="alert"][tabindex="-1"]') ||
            root?.querySelector('h1, [role="heading"]');
        if (target) {
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
        }
    }

    function normalize(payload) {
        const destination = payload && workspaceDestination(payload.url);
        if (
            !destination ||
            destination.page !== payload.page ||
            !payload.props ||
            typeof payload.props !== 'object' ||
            Array.isArray(payload.props)
        ) {
            throw new RequestError('This page could not be loaded. Please try again.');
        }
        return { page: payload.page, props: payload.props, url: destination.url };
    }

    router.beforeEach(async (to) => {
        const destination = workspaceDestination(to.fullPath);
        if (!destination) {
            navigateDocument(to.fullPath);
            return false;
        }
        const currentRevision = ++revision;
        pendingUrl = to.fullPath;
        controller?.abort();
        controller = new AbortController();
        state.loading = true;
        state.error = null;

        try {
            let payload;
            if (initialPayload && initialPayload.url === to.fullPath) {
                payload = initialPayload;
                initialPayload = null;
            } else if (redirectPayload && redirectPayload.url === to.fullPath) {
                payload = redirectPayload;
                redirectPayload = null;
            } else if (state.props && state.url.split('#')[0] === to.fullPath.split('#')[0]) {
                payload = { page: state.page, props: state.props, url: to.fullPath };
            } else {
                clearPage();
                payload = normalize(
                    await request(to.fullPath.split('#')[0], {
                        signal: controller.signal,
                        headers: { 'X-Obsidian-Page': '1' },
                    }),
                );
                if (payload.url === to.fullPath.split('#')[0]) payload.url += to.hash;
            }
            if (disposed || currentRevision !== revision) return false;
            if (payload.url !== to.fullPath) {
                if (payload.url === router.currentRoute.value.fullPath) {
                    await showOutcome(router.currentRoute.value, { payload });
                    return false;
                }
                redirectPayload = payload;
                const redirected = router.resolve(payload.url);
                return {
                    path: redirected.path,
                    query: redirected.query,
                    hash: redirected.hash,
                    force: true,
                    replace: true,
                };
            }
            to.meta.workspaceOutcome = { payload };
            return true;
        } catch (error) {
            if (disposed || currentRevision !== revision || error.name === 'AbortError')
                return false;
            clearPage();
            if (error.status === 409) {
                navigateDocument(to.fullPath);
                return false;
            }
            if ([401, 419].includes(error.status)) session.clear();
            to.meta.workspaceOutcome = {
                error: {
                    status: error.status || 0,
                    message: error.message || 'This page could not be loaded. Please try again.',
                },
            };
            return true;
        }
    });

    router.afterEach(async (to, _from, failure) => {
        if (failure || disposed) return;
        const outcome = to.meta.workspaceOutcome;
        if (!outcome) return;
        delete to.meta.workspaceOutcome;
        await showOutcome(to, outcome);
    });

    router.onError((_error, to) => {
        if (disposed || to.fullPath !== pendingUrl) return;
        revision++;
        controller?.abort();
        clearPage();
        delete to.meta.workspaceOutcome;
        state.url = to.fullPath;
        state.loading = false;
        state.error = {
            status: 0,
            reload: true,
            message: 'This page could not load. Reload the page to try again.',
        };
        document.title = `Page unavailable · ${title}`;
        void nextTick(() => {
            if (disposed || to.fullPath !== pendingUrl) return;
            const heading = focusRoot()?.querySelector('h1');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
            }
        });
    });

    function retry() {
        if (state.error?.reload) {
            navigateDocument(state.url);
            return Promise.resolve();
        }
        const route = router.currentRoute.value;
        return router
            .replace({
                path: route.path,
                query: route.query,
                hash: route.hash,
                force: true,
            })
            .catch(() => {});
    }

    function dispose() {
        disposed = true;
        pendingUrl = '';
        revision++;
        controller?.abort();
        initialPayload = null;
        redirectPayload = null;
        clearPage();
        history.destroy();
    }

    return { router, state, retry, clearSession, dispose };
}
