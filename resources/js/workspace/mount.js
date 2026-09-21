import { createApp } from 'vue';
import App from './App.vue';
import { createWorkspaceRouter, interceptWorkspaceLinks, workspaceKey } from './router.js';
import { useSessionStore } from '../shared/stores/session.js';

export async function mountWorkspace(root, { page, props, pinia }) {
    const session = useSessionStore(pinia);
    const workspace = createWorkspaceRouter({ page, props, session, focusRoot: () => root });
    const app = createApp(App).use(pinia).use(workspace.router).provide(workspaceKey, workspace);
    await workspace.router.isReady();
    app.mount(root);
    const removeLinks = interceptWorkspaceLinks(workspace.router);
    const logout = (event) => {
        const action = event.target?.action;
        if (!action) return;
        const url = new URL(action, window.location.origin);
        if (url.origin === window.location.origin && url.pathname === '/logout') {
            workspace.clearSession();
        }
    };
    document.addEventListener('submit', logout);
    // A restored document may predate a logout/account change. Re-authorize its shell too.
    const restore = (event) => {
        if (event.persisted) window.location.reload();
    };
    window.addEventListener('pageshow', restore);
    return {
        app,
        router: workspace.router,
        dispose() {
            removeLinks();
            document.removeEventListener('submit', logout);
            window.removeEventListener('pageshow', restore);
            workspace.dispose();
            app.unmount();
        },
    };
}
