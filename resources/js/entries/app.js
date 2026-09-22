import 'vite/modulepreload-polyfill';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../../css/frontend.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';

const modules = import.meta.glob('../features/**/*Page.vue');
const pages = {
    navigation: '../features/navigation/NavigationPage.vue',
    profile: '../features/profile/ProfilePage.vue',
    'create-device-launcher': '../features/dashboard/CreateDeviceLauncherPage.vue',
    'edit-device': '../features/devices/DeviceFormPage.vue',
    dashboard: '../features/dashboard/DashboardPage.vue',
    'view-device': '../features/solar-tracker/ViewDevicePage.vue',
    auth: '../features/auth/AuthPage.vue',
    admin: '../features/admin/AdminPage.vue',
    public: '../features/public/PublicPage.vue',
};
const pinia = createPinia();

if (document.querySelector('[data-status-filter-root]')) {
    import('../features/dashboard/StatusFilter.vue').then(({ default: StatusFilter }) => {
        document.querySelectorAll('[data-status-filter-root]').forEach((root) => {
            createApp(StatusFilter, JSON.parse(root.dataset.props)).mount(root);
        });
    });
}

for (const root of document.querySelectorAll('[data-vue-page]')) {
    const source = document.getElementById(root.dataset.propsId);
    const load = modules[pages[root.dataset.vuePage]];
    Promise.resolve()
        .then(() => {
            if (!source || !load) throw new Error('Unavailable frontend entry');
            return root.dataset.workspace === '1' ? import('../workspace/mount.js') : load();
        })
        .then(async ({ default: component, mountWorkspace }) => {
            const props = JSON.parse(source.textContent);
            if (mountWorkspace) {
                await mountWorkspace(root, { page: root.dataset.vuePage, props, pinia });
            } else {
                createApp(component, props).use(pinia).mount(root);
            }
            source.remove();
        })
        .catch(() => {
            root.replaceChildren();
            const message = document.createElement('p');
            message.setAttribute('role', 'alert');
            message.textContent = 'This page could not load. Please refresh and try again.';
            root.append(message);
        });
}
