const assert = require('assert');
const fs = require('fs');
const path = require('path');
const remote = require('../../public/js/solar-tracker-remote.js');

function element(extra = {}) {
    const attributes = {};
    return {
        textContent: '', checked: false, disabled: false, value: '', attributes,
        setAttribute(name, value) { attributes[name] = value; },
        addEventListener(event, listener) { this[event] = listener; },
        ...extra
    };
}

function fixture(mode = 0, motorSpeed = 0) {
    const toggle = element();
    const label = element();
    const controls = element();
    const feedback = element();
    const slider = element();
    const ownerDocument = { body: {}, activeElement: null };
    ownerDocument.activeElement = ownerDocument.body;
    let disabled = false;
    slider.ownerDocument = ownerDocument;
    slider.isConnected = true;
    slider.focusCalls = 0;
    slider.focus = function () {
        slider.focusCalls++;
        ownerDocument.activeElement = slider;
    };
    Object.defineProperty(slider, 'disabled', {
        get() { return disabled; },
        set(value) {
            disabled = value;
            if (value && ownerDocument.activeElement === slider) ownerDocument.activeElement = ownerDocument.body;
        }
    });
    const speedValue = element();
    const savedSpeed = element();
    const elements = {
        '[data-remote-toggle]': toggle,
        '[data-remote-mode]': label,
        '[data-remote-controls]': controls,
        '[data-remote-feedback]': feedback,
        '[data-remote-speed]': slider,
        '[data-remote-speed-value]': speedValue,
        '[data-remote-saved-speed]': savedSpeed
    };
    const container = element({ querySelector: selector => elements[selector] });
    remote.render(container, {
        mode, motor_speed: motorSpeed, serial_no: 'test-solar-1',
        endpoint: '/device-info/update-remote-control', csrfToken: 'test-csrf'
    });
    return { container, toggle, label, controls, feedback, slider, speedValue, savedSpeed };
}

function success(mode, motorSpeed) {
    return { ok: true, json: async () => ({ success: true, mode, motor_speed: motorSpeed }) };
}

function label(speed) {
    return speed === 0 ? 'Stop (0)' : (speed > 0 ? 'Up (' : 'Down (') + speed + ')';
}

(async function () {
    const originalFetch = globalThis.fetch;
    const requests = [];
    globalThis.fetch = async function (url, request) {
        requests.push({ url, ...request });
        const body = JSON.parse(request.body);
        return success(body.mode, body.motor_speed);
    };

    try {
        const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/device-info.blade.php'), 'utf8');
        const range = blade.match(/<input type="range"[\s\S]*?>/)[0];
        for (const attribute of ['id="remote-motor-speed"', 'min="-100"', 'max="100"', 'step="10"', 'value="0"']) {
            assert.ok(range.includes(attribute), 'Range must include ' + attribute);
        }
        assert.match(blade, /<label[^>]*for="remote-motor-speed">Motor speed<\/label>/);
        assert.doesNotMatch(blade, /data-speed=/);

        const automatic = fixture();
        assert.equal(requests.length, 0, 'Rendering must not write to the remote control table');
        assert.equal(automatic.toggle.checked, false);
        assert.equal(automatic.label.textContent, 'Automatic');
        assert.equal(automatic.controls.attributes['aria-disabled'], 'true');
        assert.equal(automatic.slider.disabled, true);
        assert.equal(automatic.slider.value, '0');
        assert.equal(automatic.slider.attributes['aria-valuetext'], 'Stop (0)');
        automatic.slider.value = '40';
        automatic.slider.input();
        await automatic.slider.change();
        assert.equal(requests.length, 0, 'Manual commands must never activate remote mode implicitly');
        assert.equal(automatic.slider.value, '0');

        const savedRemote = fixture('1', -20);
        assert.equal(requests.length, 0);
        assert.equal(savedRemote.toggle.checked, true);
        assert.equal(savedRemote.label.textContent, 'Remote Control');
        assert.equal(savedRemote.controls.attributes['aria-disabled'], 'false');
        assert.equal(savedRemote.slider.disabled, false);
        assert.equal(savedRemote.slider.value, '-20');
        assert.equal(savedRemote.speedValue.textContent, 'Down (-20)');
        assert.equal(savedRemote.savedSpeed.textContent, 'Down (-20)');
        assert.equal(savedRemote.slider.focusCalls, 0, 'Rendering must not steal focus');
        const offStep = fixture(1, -37);
        assert.equal(offStep.slider.value, '-40');
        assert.equal(offStep.savedSpeed.textContent, 'Down (-37)', 'Preserve the exact saved command when it is between slider steps');
        assert.equal(requests.length, 0, 'Normalizing the thumb position must not send a command');

        automatic.toggle.checked = true;
        await automatic.toggle.change();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/device-info/update-remote-control');
        assert.equal(requests[0].method, 'POST');
        assert.equal(requests[0].credentials, 'same-origin');
        assert.equal(requests[0].headers['X-CSRF-TOKEN'], 'test-csrf');
        assert.equal(requests[0].headers.Accept, 'application/json');
        assert.deepEqual(JSON.parse(requests[0].body), { mode: 1, motor_speed: 0, serial_no: 'test-solar-1' });
        assert.equal(automatic.label.textContent, 'Remote Control');
        assert.equal(automatic.feedback.textContent, 'Remote Control mode saved.');
        assert.equal(automatic.slider.disabled, false);

        for (let speed = -100; speed <= 100; speed += 10) {
            const before = requests.length;
            const confirmedSpeed = automatic.savedSpeed.textContent;
            automatic.slider.value = String(speed);
            automatic.slider.input();
            assert.equal(requests.length, before, 'Dragging only previews the speed');
            assert.equal(automatic.savedSpeed.textContent, confirmedSpeed, 'Preview must not replace the saved value');
            assert.equal(automatic.speedValue.textContent, label(speed));
            assert.equal(automatic.slider.attributes['aria-valuetext'], label(speed));
            await automatic.slider.change();
            assert.equal(requests.length, before + 1, 'Committing a changed speed sends one request');
            assert.deepEqual(JSON.parse(requests.at(-1).body), { mode: 1, motor_speed: speed, serial_no: 'test-solar-1' });
            assert.equal(automatic.savedSpeed.textContent, label(speed));
            assert.equal(automatic.feedback.textContent, 'Motor speed saved: ' + label(speed) + '.');
            await automatic.slider.change();
            assert.equal(requests.length, before + 1, 'An unchanged speed does not send another request');
        }
        for (const value of ['110', '-110', '15', 'not-a-number']) {
            const before = requests.length;
            automatic.slider.value = value;
            await automatic.slider.change();
            assert.equal(requests.length, before, 'Invalid commands must not be submitted');
            assert.equal(automatic.slider.value, '100');
        }

        automatic.toggle.checked = false;
        await automatic.toggle.change();
        assert.deepEqual(JSON.parse(requests.at(-1).body), { mode: 0, motor_speed: 0, serial_no: 'test-solar-1' });
        assert.equal(automatic.label.textContent, 'Automatic');
        assert.equal(automatic.feedback.textContent, 'Automatic mode saved.');
        assert.equal(automatic.slider.disabled, true);
        assert.equal(automatic.slider.value, '0');
        assert.equal(automatic.savedSpeed.textContent, 'Stop (0)');

        // Slow mode and speed saves block both controls and retain the pending command.
        for (const isModeChange of [true, false]) {
            let finishRequest;
            let pendingRequests = 0;
            globalThis.fetch = () => {
                pendingRequests++;
                return new Promise(resolve => { finishRequest = resolve; });
            };
            const slow = fixture(1, -20);
            slow.toggle.checked = false;
            slow.slider.value = '50';
            const saving = isModeChange ? slow.toggle.change() : slow.slider.change();
            assert.equal(slow.toggle.disabled, true);
            assert.equal(slow.slider.disabled, true);
            assert.equal(slow.container.attributes['aria-busy'], 'true');
            assert.equal(slow.feedback.textContent, 'Saving…');
            assert.equal(slow.label.textContent, 'Remote Control', 'Show the confirmed mode until saved');
            assert.equal(slow.savedSpeed.textContent, 'Down (-20)');
            slow.slider.value = '-100';
            slow.slider.input();
            await slow.slider.change();
            slow.toggle.checked = true;
            await slow.toggle.change();
            assert.equal(pendingRequests, 1);
            assert.equal(slow.toggle.checked, !isModeChange);
            assert.equal(slow.slider.value, isModeChange ? '0' : '50');
            finishRequest(success(isModeChange ? 0 : 1, isModeChange ? 0 : 50));
            await saving;
            assert.equal(slow.toggle.disabled, false);
            assert.equal(slow.slider.disabled, isModeChange);
            assert.equal(slow.container.attributes['aria-busy'], 'false');
            assert.equal(slow.savedSpeed.textContent, isModeChange ? 'Stop (0)' : 'Up (50)');
        }

        // Restore focus lost when pending disables the slider, without stealing it after user navigation.
        for (const focusState of ['unchanged', 'moved', 'detached']) {
            let finishRequest;
            globalThis.fetch = () => new Promise(resolve => { finishRequest = resolve; });
            const keyboard = fixture(1, 0);
            keyboard.slider.focus();
            keyboard.slider.focusCalls = 0;
            keyboard.slider.value = '10';
            const saving = keyboard.slider.change();
            assert.equal(keyboard.slider.ownerDocument.activeElement, keyboard.slider.ownerDocument.body);
            const otherControl = {};
            if (focusState === 'moved') keyboard.slider.ownerDocument.activeElement = otherControl;
            if (focusState === 'detached') keyboard.slider.isConnected = false;
            finishRequest(success(1, 10));
            await saving;
            assert.equal(keyboard.slider.focusCalls, focusState === 'unchanged' ? 1 : 0);
            if (focusState === 'unchanged') assert.equal(keyboard.slider.ownerDocument.activeElement, keyboard.slider);
            if (focusState === 'moved') assert.equal(keyboard.slider.ownerDocument.activeElement, otherControl);
        }

        // Every failure restores the confirmed command, including malformed JSON and invalid speeds.
        const failures = [
            async () => ({ ok: false, json: async () => ({ message: 'You cannot control this device.' }) }),
            async () => ({ ok: true, json: async () => ({ success: false, message: 'Save failed.' }) }),
            async () => { throw new TypeError('Failed to fetch'); },
            async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected token'); } }),
            async () => ({ ok: true, json: async () => ({ success: true }) }),
            async () => success(1, 200)
        ];
        for (const failedFetch of failures) {
            globalThis.fetch = failedFetch;
            for (const initialMode of [0, 1]) {
                const failed = fixture(initialMode, -20);
                failed.toggle.checked = !initialMode;
                await failed.toggle.change();
                assert.equal(failed.toggle.checked, initialMode === 1);
                assert.equal(failed.toggle.disabled, false);
                assert.equal(failed.label.textContent, initialMode === 1 ? 'Remote Control' : 'Automatic');
                assert.equal(failed.slider.disabled, initialMode === 0);
                assert.equal(failed.slider.value, '-20');
                assert.equal(failed.savedSpeed.textContent, 'Down (-20)');
                assert.match(failed.feedback.textContent, /cannot|failed|Could not/);
                assert.doesNotMatch(failed.feedback.textContent, /saved[.:]/);
            }
            const failedCommand = fixture(1, -20);
            failedCommand.slider.focus();
            failedCommand.slider.value = '60';
            failedCommand.slider.input();
            assert.equal(failedCommand.speedValue.textContent, 'Up (60)');
            await failedCommand.slider.change();
            assert.equal(failedCommand.slider.value, '-20');
            assert.equal(failedCommand.speedValue.textContent, 'Down (-20)');
            assert.equal(failedCommand.savedSpeed.textContent, 'Down (-20)');
            assert.equal(failedCommand.toggle.checked, true);
            assert.equal(failedCommand.slider.ownerDocument.activeElement, failedCommand.slider, 'Failed saves must allow the keyboard user to retry');
        }
        console.log('Solar tracker remote control tests passed.');
    } finally {
        if (originalFetch === undefined) delete globalThis.fetch;
        else globalThis.fetch = originalFetch;
    }
}()).catch(error => {
    console.error(error);
    process.exitCode = 1;
});
