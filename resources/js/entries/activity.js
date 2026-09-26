import 'vite/modulepreload-polyfill';
import { createApp } from 'vue';
import ActivityControls from '../features/activity/ActivityControls.vue';
import '../features/activity/legacy-navigation.css';
import { startThemeRuntime } from '../shared/theme';

startThemeRuntime();

for (const root of document.querySelectorAll('[data-app-activity-root]')) {
    createApp(ActivityControls, { csrfToken: root.dataset.csrfToken || '' }).mount(root);
}
