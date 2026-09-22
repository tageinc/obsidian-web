import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PublicPage from './PublicPage.vue';

describe('static browser pages', () => {
    it('preserves public contact links with semantic text and no nested document', () => {
        const wrapper = mount(PublicPage, {
            props: {
                mode: 'contact',
                contact: {
                    phoneHref: 'tel:+019494902059',
                    phoneLabel: '(949)-490-2059',
                    email: 'info@tezca.net',
                },
            },
        });
        expect(wrapper.get('h1').text()).toBe('Contact Us');
        expect(wrapper.get('a[href="tel:+019494902059"]').text()).toBe('(949)-490-2059');
        expect(wrapper.get('a[href="mailto:info@tezca.net"]').text()).toBe('info@tezca.net');
        expect(wrapper.find('html, head, body').exists()).toBe(false);
        wrapper.unmount();
    });

    it('preserves creation confirmation and existing device URL links', () => {
        const wrapper = mount(PublicPage, {
            props: {
                mode: 'thank-you',
                links: {
                    createDevice: '/create-device',
                    deviceManager: '/device-manager',
                },
            },
        });
        expect(wrapper.get('h1').text()).toBe('Thank You for Creating Your Device!');
        expect(wrapper.text()).toContain('Your device has been successfully registered.');
        expect(wrapper.get('a[href="/create-device"]').text()).toBe('Create Another Device');
        expect(wrapper.get('a[href="/device-manager"]').text()).toBe('View My Devices');
        expect(wrapper.find('form').exists()).toBe(false);
        wrapper.unmount();
    });
});
