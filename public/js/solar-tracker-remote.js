(function (root, factory) {
    const api = factory(root);
    if (typeof module === 'object' && module.exports) module.exports = api;
    root.SolarTrackerRemote = api;
}(typeof window !== 'undefined' ? window : globalThis, function (root) {
    function render(container, options) {
        const toggle = container.querySelector('[data-remote-toggle]');
        const modeLabel = container.querySelector('[data-remote-mode]');
        const controls = container.querySelector('[data-remote-controls]');
        const feedback = container.querySelector('[data-remote-feedback]');
        const buttons = Array.from(container.querySelectorAll('[data-speed]'));
        let confirmed = { mode: Number(options.mode) === 1 ? 1 : 0, motor_speed: Number(options.motor_speed) || 0 };
        let pending = null;

        function update() {
            const busy = pending !== null;
            const remote = confirmed.mode === 1;
            toggle.checked = (busy ? pending.mode : confirmed.mode) === 1;
            toggle.disabled = busy;
            modeLabel.textContent = remote ? 'Remote Control' : 'Automatic';
            controls.setAttribute('aria-disabled', String(busy || !remote));
            container.setAttribute('aria-busy', String(busy));
            buttons.forEach(function (button) {
                button.disabled = busy || !remote;
                const selected = remote && Number(button.dataset.speed) === confirmed.motor_speed;
                button.setAttribute('aria-pressed', String(selected));
                button.classList.toggle('active', selected);
            });
        }

        async function save(mode, motorSpeed, isModeChange) {
            if (pending !== null) {
                update();
                return;
            }
            pending = { mode: mode, motor_speed: motorSpeed };
            feedback.textContent = 'Saving…';
            update();
            try {
                const response = await root.fetch(options.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': options.csrfToken
                    },
                    body: JSON.stringify({ mode: mode, motor_speed: motorSpeed, serial_no: options.serial_no })
                });
                const result = await response.json();
                if (!response.ok || !result || result.success !== true ||
                    (result.mode !== 0 && result.mode !== 1) || !Number.isFinite(result.motor_speed)) {
                    throw new Error(result && typeof result.message === 'string' ? result.message : 'Could not save remote control. Try again.');
                }
                confirmed = { mode: result.mode, motor_speed: result.motor_speed };
                feedback.textContent = isModeChange
                    ? (confirmed.mode === 1 ? 'Remote Control mode saved.' : 'Automatic mode saved.')
                    : (confirmed.motor_speed === 0 ? 'Stop command saved.' : (confirmed.motor_speed > 0 ? 'Up command saved.' : 'Down command saved.'));
            } catch (error) {
                feedback.textContent = error instanceof TypeError || error instanceof SyntaxError
                    ? 'Could not save remote control. Try again.'
                    : error.message || 'Could not save remote control. Try again.';
            } finally {
                pending = null;
                update();
            }
        }

        toggle.addEventListener('change', function () {
            return save(toggle.checked ? 1 : 0, 0, true);
        });
        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const speed = Number(button.dataset.speed);
                if (confirmed.mode !== 1 || ![-20, 0, 20].includes(speed)) return;
                return save(1, speed, false);
            });
        });
        update();
    }

    return { render: render };
}));
