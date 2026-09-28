import { describe, expect, it, vi } from 'vitest';
import { onSessionExpired } from './client';

describe('onSessionExpired', () => {
    it('calls every listener when session expires with the correct status', () => {
        const handler1 = vi.fn();
        const handler2 = vi.fn();
        const unsub1 = onSessionExpired(handler1);
        const unsub2 = onSessionExpired(handler2);

        // Simulate emit by calling the internal dispatch mechanism
        window.dispatchEvent(new CustomEvent('session:expired', { detail: 401 }));

        expect(handler1).toHaveBeenCalledWith(401);
        expect(handler2).toHaveBeenCalledWith(401);

        unsub1();
        window.dispatchEvent(new CustomEvent('session:expired', { detail: 401 }));

        // handler1 should have been unregistered
        expect(handler1).toHaveBeenCalledTimes(1);
        expect(handler2).toHaveBeenCalledTimes(2);

        unsub2();
    });
});
