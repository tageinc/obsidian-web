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
        const slider = container.querySelector('[data-remote-speed]');
        const speedValue = container.querySelector('[data-remote-speed-value]');
        const savedSpeed = container.querySelector('[data-remote-saved-speed]');
        let confirmed = { mode: Number(options.mode) === 1 ? 1 : 0, motor_speed: Number(options.motor_speed) || 0 };
        let pending = null;
        let preview = sliderSpeed(confirmed.motor_speed);

        function sliderSpeed(speed) {
            return Math.max(-100, Math.min(100, Math.round(speed / 10) * 10));
        }

        function speedLabel(speed) {
            return speed === 0 ? 'Stop (0)' : (speed > 0 ? 'Up (' : 'Down (') + speed + ')';
        }

        function validSpeed(speed) {
            return Number.isFinite(speed) && speed >= -100 && speed <= 100 && speed % 10 === 0;
        }

        function update() {
            const busy = pending !== null;
            const remote = confirmed.mode === 1;
            toggle.checked = (busy ? pending.mode : confirmed.mode) === 1;
            toggle.disabled = busy;
            modeLabel.textContent = remote ? 'Remote Control' : 'Automatic';
            controls.setAttribute('aria-disabled', String(busy || !remote));
            container.setAttribute('aria-busy', String(busy));
            slider.disabled = busy || !remote;
            slider.value = String(preview);
            slider.setAttribute('aria-valuetext', speedLabel(preview));
            speedValue.textContent = speedLabel(preview);
            savedSpeed.textContent = speedLabel(confirmed.motor_speed);
        }

        async function save(mode, motorSpeed, isModeChange) {
            if (pending !== null) {
                update();
                return;
            }
            const restoreSliderFocus = slider.ownerDocument?.activeElement === slider;
            pending = { mode: mode, motor_speed: motorSpeed };
            preview = motorSpeed;
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
                    (result.mode !== 0 && result.mode !== 1) || !Number.isFinite(result.motor_speed) ||
                    result.motor_speed < -100 || result.motor_speed > 100) {
                    throw new Error(result && typeof result.message === 'string' ? result.message : 'Could not save remote control. Try again.');
                }
                confirmed = { mode: result.mode, motor_speed: result.motor_speed };
                feedback.textContent = isModeChange
                    ? (confirmed.mode === 1 ? 'Remote Control mode saved.' : 'Automatic mode saved.')
                    : 'Motor speed saved: ' + speedLabel(confirmed.motor_speed) + '.';
            } catch (error) {
                feedback.textContent = error instanceof TypeError || error instanceof SyntaxError
                    ? 'Could not save remote control. Try again.'
                    : error.message || 'Could not save remote control. Try again.';
            } finally {
                pending = null;
                preview = sliderSpeed(confirmed.motor_speed);
                update();
                if (restoreSliderFocus && slider.isConnected && !slider.disabled &&
                    slider.ownerDocument.activeElement === slider.ownerDocument.body) {
                    slider.focus();
                }
            }
        }

        toggle.addEventListener('change', function () {
            return save(toggle.checked ? 1 : 0, 0, true);
        });
        slider.addEventListener('input', function () {
            const speed = Number(slider.value);
            if (pending === null && confirmed.mode === 1 && validSpeed(speed)) preview = speed;
            update();
        });
        slider.addEventListener('change', function () {
            const speed = Number(slider.value);
            if (pending !== null || confirmed.mode !== 1 || !validSpeed(speed) || speed === confirmed.motor_speed) {
                update();
                return;
            }
            return save(1, speed, false);
        });
        update();
    }

    return { render: render };
}));
