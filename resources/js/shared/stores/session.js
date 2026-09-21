import { defineStore } from 'pinia';

// Display metadata only. Laravel remains authoritative for every request.
export const useSessionStore = defineStore('session-display', {
    state: () => ({ name: '', signedIn: false, notice: '' }),
    actions: {
        initialize(user) {
            this.name = user?.name || '';
            this.signedIn = Boolean(user);
        },
        clear() {
            this.$reset();
        },
    },
});
