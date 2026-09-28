/**
 * Session-expired watcher for native fetch / XHR requests.
 *
 * Installed once per page load by layouts/web.blade.php (or via the layout).
 * Monitors all fetch() calls and dispatches 'session:expired' when 401/419 appears,
 * letting any component that called onSessionExpired() in client.js react uniformly.
 */
(function () {
    'use strict';

    if (typeof window === 'undefined') return;

    const origFetch = window.fetch;
    function emit(status) {
        window.dispatchEvent(new CustomEvent('session:expired', { detail: status }));
    }

    if (typeof origFetch === 'function') {
        window.fetch = function () {
            const args = Array.prototype.slice.call(arguments);
            return origFetch.apply(this, args).then(function (response) {
                if (response.status === 401 || response.status === 419) {
                    emit(response.status);
                }
                return response;
            });
        };
    }

    // Also hook XHR (for libraries that don't use fetch).
    var origXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (_method, _url /* , ... */) {
        var xhr = this;
        var responded = false;
        this.addEventListener('readystatechange', function () {
            if (xhr.readyState === 4 && !responded) {
                responded = true;
                if (xhr.status === 401 || xhr.status === 419) {
                    emit(xhr.status);
                }
            }
        });
        return origXHROpen.apply(this, arguments);
    };
})();
