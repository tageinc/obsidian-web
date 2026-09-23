import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory } from 'vue-router';
import App from './App.vue';
import {
    createWorkspaceRouter,
    interceptWorkspaceLinks,
    workspaceDestination,
    workspaceKey,
} from './router.js';
import { RequestError } from '../shared/api/client.js';
import FormFeedback from '../shared/components/FormFeedback.vue';

const fixtures = [];
const Page = { props: ['label'], template: '<div><h1>{{ label }}</h1><p>Page content</p></div>' };
const components = Object.fromEntries(
    ['dashboard', 'profile', 'edit-device', 'view-device'].map((page) => [page, Page]),
);
const envelope = (page, url, label = page) => ({ page, url, props: { label } });

async function fixture(request = vi.fn(), overrides = {}) {
    vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
    const session = { clear: vi.fn() };
    const navigateDocument = vi.fn();
    let wrapper;
    const workspace = createWorkspaceRouter({
        page: 'dashboard',
        props: { label: 'Initial dashboard' },
        url: '/dashboard',
        session,
        navigateDocument,
        request,
        history: createMemoryHistory(),
        components,
        title: 'Obsidian',
        focusRoot: () => wrapper?.element,
        ...overrides,
    });
    await workspace.router.push('/dashboard');
    wrapper = mount(App, {
        attachTo: document.body,
        global: {
            plugins: [workspace.router],
            provide: { [workspaceKey]: workspace },
        },
    });
    const result = { ...workspace, wrapper, request, session, navigateDocument };
    fixtures.push(result);
    return result;
}

afterEach(() => {
    fixtures.splice(0).forEach((item) => {
        item.wrapper.unmount();
        item.dispose();
    });
    vi.restoreAllMocks();
});

describe('bounded workspace navigation', () => {
    it('only recognizes the five same-origin workspace URLs and no aliases or destructive/download paths', () => {
        expect(workspaceDestination('/dashboard?show=20&page=2').page).toBe('dashboard');
        expect(workspaceDestination('/edit-device/12')).toBeNull();
        expect(workspaceDestination('/devices/12').page).toBe('view-device');
        for (const path of [
            '/',
            '/device-manager',
            '/delete-device/1',
            '/firmware-file/version/1',
            '/api/device/1/data',
            '/contact-us',
            '/login',
            '/admin-control-center',
            '/profile/extra',
            '/devices/not-an-id',
            'https://elsewhere.example/profile',
            'javascript:alert(1)',
        ]) {
            expect(workspaceDestination(path)).toBeNull();
        }
    });

    it('uses initial authorized props without a request, then negotiates props and focuses the new heading', async () => {
        const request = vi
            .fn()
            .mockResolvedValue(envelope('profile', '/profile?section=contact', 'Profile fixture'));
        const item = await fixture(request);
        expect(request).not.toHaveBeenCalled();
        expect(item.wrapper.text()).toContain('Initial dashboard');
        await item.router.push('/profile?section=contact');
        await flushPromises();
        expect(request).toHaveBeenCalledWith(
            '/profile?section=contact',
            expect.objectContaining({
                headers: { 'X-Obsidian-Page': '1' },
                signal: expect.any(AbortSignal),
            }),
        );
        expect(item.state.props).toEqual({ label: 'Profile fixture' });
        expect(item.router.currentRoute.value.query).toEqual({ section: 'contact' });
        expect(item.router.currentRoute.value.meta.workspaceOutcome).toBeUndefined();
        expect(document.title).toBe('Profile · Obsidian');
        expect(document.activeElement).toBe(item.wrapper.get('h1').element);
    });

    it('preserves the Laravel validation summary focus instead of replacing it with heading focus', async () => {
        const errors = { email: ['The email has already been taken.'] };
        const ErrorPage = {
            components: { FormFeedback },
            props: ['errors'],
            template: '<div><h1>Profile</h1><FormFeedback :errors="errors" /></div>',
        };
        const item = await fixture(
            vi.fn().mockResolvedValue({ page: 'profile', url: '/profile', props: { errors } }),
            { components: { ...components, profile: ErrorPage } },
        );
        await item.router.push('/profile');
        await flushPromises();
        expect(document.activeElement).toBe(
            item.wrapper.get('[role="alert"][tabindex="-1"]').element,
        );
    });

    it('keeps a server-rendered validation summary focused during the initial navigation outcome', async () => {
        vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
        const wrapper = mount(
            {
                components: { FormFeedback },
                template:
                    '<main><h1>Profile</h1><FormFeedback :errors="{ email: [\'The email has already been taken.\'] }" /></main>',
            },
            { attachTo: document.body },
        );
        const workspace = createWorkspaceRouter({
            page: 'profile',
            props: { errors: { email: ['The email has already been taken.'] } },
            url: '/profile',
            session: { clear: vi.fn() },
            history: createMemoryHistory(),
            components,
            focusRoot: () => wrapper.element,
        });
        fixtures.push({ ...workspace, wrapper });
        await workspace.router.push('/profile');
        await flushPromises();
        expect(document.activeElement).toBe(wrapper.get('[role="alert"][tabindex="-1"]').element);
    });

    it('preserves pagination queries through browser back and forward without caching old page data', async () => {
        const request = vi.fn((url) => Promise.resolve(envelope('dashboard', url, url)));
        const item = await fixture(request);
        await item.router.push('/dashboard?show=20&page=2');
        await item.router.push('/dashboard?show=30&page=3');
        item.router.back();
        await flushPromises();
        expect(item.router.currentRoute.value.fullPath).toBe('/dashboard?show=20&page=2');
        expect(request.mock.calls.at(-1)[0]).toBe('/dashboard?show=20&page=2');
        item.router.forward();
        await flushPromises();
        expect(item.router.currentRoute.value.fullPath).toBe('/dashboard?show=30&page=3');
        expect(request.mock.calls.at(-1)[0]).toBe('/dashboard?show=30&page=3');
    });

    it('aborts obsolete reads and ignores late results even when transport cancellation is ignored', async () => {
        let finishSlow;
        const request = vi
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        finishSlow = resolve;
                    }),
            )
            .mockResolvedValueOnce(envelope('view-device', '/devices/1', 'New page'));
        const item = await fixture(request);
        const slow = item.router.push('/profile');
        await flushPromises();
        expect(item.wrapper.text()).toContain('Loading page');
        const signal = request.mock.calls[0][1].signal;
        await item.router.push('/devices/1');
        expect(signal.aborted).toBe(true);
        finishSlow(envelope('profile', '/profile', 'Stale page'));
        await slow;
        await flushPromises();
        expect(item.wrapper.text()).toContain('New page');
        expect(item.wrapper.text()).not.toContain('Stale page');
    });

    it('follows a server redirect back to the current dashboard without a second read or a stuck loader', async () => {
        const request = vi
            .fn()
            .mockResolvedValue(envelope('dashboard', '/dashboard', 'Device not found'));
        const item = await fixture(request);
        await item.router.push('/devices/999999');
        await flushPromises();
        expect(item.router.currentRoute.value.fullPath).toBe('/dashboard');
        expect(item.state.loading).toBe(false);
        expect(item.wrapper.text()).toContain('Device not found');
        expect(request).toHaveBeenCalledTimes(1);
    });

    it.each([401, 419])(
        'clears session and page memory on %s, with a normal sign-in link',
        async (status) => {
            const item = await fixture(
                vi.fn().mockRejectedValue(new RequestError('Please sign in again.', status)),
            );
            await item.router.push('/profile');
            await flushPromises();
            expect(item.session.clear).toHaveBeenCalledOnce();
            expect(item.state.props).toBeNull();
            expect(item.wrapper.text()).not.toContain('Initial dashboard');
            expect(item.wrapper.get('a').attributes('href')).toBe('/login');
            expect(item.wrapper.find('button').exists()).toBe(false);
        },
    );

    it.each([0, 403, 404, 500])(
        'shows a %s error and only retries when explicitly requested',
        async (status) => {
            const request = vi
                .fn()
                .mockRejectedValueOnce(new RequestError('Read failed.', status))
                .mockResolvedValueOnce(envelope('profile', '/profile', 'Recovered'));
            const item = await fixture(request);
            await item.router.push('/profile');
            expect(request).toHaveBeenCalledTimes(1);
            expect(item.state.props).toBeNull();
            expect(item.session.clear).not.toHaveBeenCalled();
            expect(item.wrapper.get('[role="alert"]').text()).toBe('Read failed.');
            await item.wrapper.get('button').trigger('click');
            await flushPromises();
            expect(request).toHaveBeenCalledTimes(2);
            expect(item.wrapper.text()).toContain('Recovered');
        },
    );

    it('uses document navigation after rollout flags change and rejects mismatched DTOs', async () => {
        const item = await fixture(
            vi
                .fn()
                .mockRejectedValueOnce(new RequestError('Document required.', 409))
                .mockResolvedValueOnce(envelope('profile', '/dashboard')),
        );
        await item.router.push('/profile');
        expect(item.navigateDocument).toHaveBeenCalledWith('/profile');
        expect(item.session.clear).not.toHaveBeenCalled();
        await item.router.push('/devices/1');
        expect(item.state.error.message).toContain('could not be loaded');
        expect(item.state.props).toBeNull();
    });

    it('presents lazy chunk failures with a deliberate document reload and clears pending page props', async () => {
        const item = await fixture(vi.fn().mockResolvedValue(envelope('profile', '/profile')), {
            components: {
                ...components,
                profile: () => Promise.reject(new Error('missing chunk')),
            },
        });
        await item.router.push('/profile').catch(() => {});
        await flushPromises();
        expect(item.state.loading).toBe(false);
        expect(item.state.props).toBeNull();
        expect(item.state.error.reload).toBe(true);
        expect(item.wrapper.get('a').attributes('href')).toBe('/profile');
        expect(item.wrapper.get('a').attributes('data-document-navigation')).toBeDefined();
        await item.retry();
        expect(item.navigateDocument).toHaveBeenCalledWith('/profile');
    });

    it('clears page memory and cancels active navigation when logout begins', async () => {
        let finish;
        const item = await fixture(
            vi.fn(
                () =>
                    new Promise((resolve) => {
                        finish = resolve;
                    }),
            ),
        );
        const navigation = item.router.push('/profile');
        await flushPromises();
        const signal = item.request.mock.calls[0][1].signal;
        item.clearSession();
        expect(signal.aborted).toBe(true);
        expect(item.session.clear).toHaveBeenCalledOnce();
        finish(envelope('profile', '/profile', 'Old private data'));
        await navigation;
        expect(item.state.props).toBeNull();
        expect(item.wrapper.text()).not.toContain('Old private data');
    });

    it('ignores a late chunk failure from a superseded navigation', async () => {
        let failChunk;
        const item = await fixture(
            vi.fn((url) =>
                Promise.resolve(
                    envelope(url === '/profile' ? 'profile' : 'view-device', url, 'Current page'),
                ),
            ),
            {
                components: {
                    ...components,
                    profile: () =>
                        new Promise((_resolve, reject) => {
                            failChunk = reject;
                        }),
                },
            },
        );
        const slow = item.router.push('/profile').catch(() => {});
        await flushPromises();
        await item.router.push('/devices/1');
        failChunk(new Error('outdated chunk'));
        await slow;
        expect(item.state.error).toBeNull();
        expect(item.state.page).toBe('view-device');
        expect(item.wrapper.text()).toContain('Current page');
    });
});

describe('workspace link interception', () => {
    it('intercepts safe document/nav links but never forms, modifiers, downloads, destructive URLs or external links', () => {
        let listener;
        const target = {
            addEventListener: vi.fn((_event, callback) => {
                listener = callback;
            }),
            removeEventListener: vi.fn(),
        };
        const router = {
            push: vi.fn().mockResolvedValue(),
            currentRoute: { value: { fullPath: '/profile' } },
        };
        const dispose = interceptWorkspaceLinks(router, target);
        const click = (href, attrs = {}, overrides = {}) => {
            const anchor = document.createElement('a');
            anchor.href = href;
            for (const [name, value] of Object.entries(attrs)) anchor.setAttribute(name, value);
            const event = {
                target: anchor,
                button: 0,
                defaultPrevented: false,
                preventDefault: vi.fn(),
                ...overrides,
            };
            listener(event);
            return event;
        };
        expect(click('/dashboard?show=20&page=2').preventDefault).toHaveBeenCalled();
        expect(router.push).toHaveBeenCalledWith('/dashboard?show=20&page=2');
        router.push.mockClear();
        for (const [href, attrs, overrides] of [
            ['/delete-device/1'],
            ['/firmware-file/version/1'],
            ['/logout'],
            ['https://elsewhere.example/profile'],
            ['/dashboard', { target: '_blank' }],
            ['/dashboard', { download: '' }],
            ['/dashboard', { rel: 'external' }],
            ['/dashboard', { 'data-document-navigation': '' }],
            ['/dashboard', { 'data-document-action': '' }],
            ['/profile#email'],
            ['/dashboard', {}, { ctrlKey: true }],
            ['/dashboard', {}, { shiftKey: true }],
            ['/dashboard', {}, { altKey: true }],
            ['/dashboard', {}, { metaKey: true }],
            ['/dashboard', {}, { button: 1 }],
            ['/dashboard', {}, { defaultPrevented: true }],
        ]) {
            expect(click(href, attrs, overrides).preventDefault).not.toHaveBeenCalled();
        }
        listener({ target: document.createElement('form'), button: 0, preventDefault: vi.fn() });
        expect(router.push).not.toHaveBeenCalled();
        dispose();
        expect(target.removeEventListener).toHaveBeenCalledWith('click', listener);
    });
});
