import 'vite/modulepreload-polyfill';
import { createApp } from 'vue';
import ActivityControls from '../features/activity/ActivityControls.vue';
import ReleaseActions from '../features/developer/ReleaseActions.vue';
import ExternalApiKeyActions from '../features/developer/ExternalApiKeyActions.vue';
import '../features/activity/legacy-navigation.css';
import { startThemeRuntime } from '../shared/theme';

startThemeRuntime();

function mountComponent(component, props, root) {
    createApp(component, props).mount(root);
}

for (const root of document.querySelectorAll('[data-app-activity-root]')) {
    mountComponent(ActivityControls, { csrfToken: root.dataset.csrfToken || '' }, root);
}

for (const root of document.querySelectorAll('[data-release-actions]')) {
    mountComponent(
        ReleaseActions,
        {
            kind: root.dataset.kind,
            title: root.dataset.title,
            row: JSON.parse(root.dataset.props),
            csrfToken: root.dataset.csrfToken || '',
            onChanged: () => window.location.reload(),
        },
        root,
    );
}

for (const root of document.querySelectorAll('[data-api-key-actions]')) {
    const apiKey = JSON.parse(root.dataset.props);
    mountComponent(
        ExternalApiKeyActions,
        {
            apiKey,
            disabled: apiKey.status === 'Revoked',
            opensDialog: false,
            onRevoke: () => root.closest('form').requestSubmit(),
        },
        root,
    );
}
