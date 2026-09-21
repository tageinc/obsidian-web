const assert = require('assert');
const remote = require('../../public/js/solar-tracker-remote.js');

function element(extra = {}) {
    const attributes = {};
    const classes = new Set();
    return {
        textContent: '', checked: false, disabled: false, attributes, classes,
        setAttribute(name, value) { attributes[name] = value; },
        addEventListener(event, listener) { this[event] = listener; },
        classList: { toggle(name, selected) { selected ? classes.add(name) : classes.delete(name); } },
        ...extra
    };
}

function fixture(mode = 0, motorSpeed = 0) {
    const toggle = element();
    const label = element();
    const controls = element();
    const feedback = element();
    const buttons = [20, 0, -20].map(speed => element({ dataset: { speed: String(speed) } }));
    const elements = {
        '[data-remote-toggle]': toggle,
        '[data-remote-mode]': label,
        '[data-remote-controls]': controls,
        '[data-remote-feedback]': feedback
    };
    const container = element({
        querySelector: selector => elements[selector],
        querySelectorAll: selector => selector === '[data-speed]' ? buttons : []
    });
    remote.render(container, {
        mode, motor_speed: motorSpeed, serial_no: 'test-solar-1',
        endpoint: '/device-info/update-remote-control', csrfToken: 'test-csrf'
    });
    return { container, toggle, label, controls, feedback, buttons };
}

function success(mode, motorSpeed) {
    return { ok: true, json: async () => ({ success: true, mode, motor_speed: motorSpeed }) };
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
        const automatic = fixture();
        assert.equal(requests.length, 0, 'Rendering must not write to the remote control table');
        assert.equal(automatic.toggle.checked, false);
        assert.equal(automatic.label.textContent, 'Automatic');
        assert.equal(automatic.controls.attributes['aria-disabled'], 'true');
        assert.ok(automatic.buttons.every(button => button.disabled));
        assert.ok(automatic.buttons.every(button => button.attributes['aria-pressed'] === 'false'));
        await automatic.buttons[0].click();
        assert.equal(requests.length, 0, 'Manual commands must never activate remote mode implicitly');

        const savedRemote = fixture('1', -20);
        assert.equal(requests.length, 0);
        assert.equal(savedRemote.toggle.checked, true);
        assert.equal(savedRemote.label.textContent, 'Remote Control');
        assert.equal(savedRemote.controls.attributes['aria-disabled'], 'false');
        assert.ok(savedRemote.buttons.every(button => !button.disabled));
        assert.equal(savedRemote.buttons[2].attributes['aria-pressed'], 'true');
        assert.equal(savedRemote.buttons[2].classes.has('active'), true);

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
        assert.ok(automatic.buttons.every(button => !button.disabled));

        for (const [index, speed, command] of [[0, 20, 'Up'], [1, 0, 'Stop'], [2, -20, 'Down']]) {
            await automatic.buttons[index].click();
            assert.deepEqual(JSON.parse(requests.at(-1).body), { mode: 1, motor_speed: speed, serial_no: 'test-solar-1' });
            assert.equal(automatic.buttons[index].attributes['aria-pressed'], 'true');
            assert.equal(automatic.feedback.textContent, command + ' command saved.');
        }

        automatic.toggle.checked = false;
        await automatic.toggle.change();
        assert.deepEqual(JSON.parse(requests.at(-1).body), { mode: 0, motor_speed: 0, serial_no: 'test-solar-1' });
        assert.equal(automatic.label.textContent, 'Automatic');
        assert.equal(automatic.feedback.textContent, 'Automatic mode saved.');
        assert.ok(automatic.buttons.every(button => button.disabled));

        // A slow mode save blocks both commands and repeat toggle events.
        let finishRequest;
        let pendingRequests = 0;
        globalThis.fetch = () => {
            pendingRequests++;
            return new Promise(resolve => { finishRequest = resolve; });
        };
        savedRemote.toggle.checked = false;
        const saving = savedRemote.toggle.change();
        assert.equal(savedRemote.toggle.disabled, true);
        assert.ok(savedRemote.buttons.every(button => button.disabled));
        assert.equal(savedRemote.container.attributes['aria-busy'], 'true');
        assert.equal(savedRemote.feedback.textContent, 'Saving…');
        assert.equal(savedRemote.label.textContent, 'Remote Control', 'The label must show the confirmed mode until saved');
        await savedRemote.buttons[0].click();
        savedRemote.toggle.checked = true;
        await savedRemote.toggle.change();
        assert.equal(pendingRequests, 1);
        assert.equal(savedRemote.toggle.checked, false, 'Pending toggle state must survive duplicate events');
        finishRequest(success(0, 0));
        await saving;
        assert.equal(savedRemote.toggle.disabled, false);
        assert.equal(savedRemote.label.textContent, 'Automatic');
        assert.equal(savedRemote.container.attributes['aria-busy'], 'false');

        // Every failure keeps the server-confirmed state, including bad JSON.
        const failures = [
            async () => ({ ok: false, json: async () => ({ message: 'You cannot control this device.' }) }),
            async () => ({ ok: true, json: async () => ({ success: false, message: 'Save failed.' }) }),
            async () => { throw new TypeError('Failed to fetch'); },
            async () => ({ ok: true, json: async () => { throw new SyntaxError('Unexpected token'); } }),
            async () => ({ ok: true, json: async () => ({ success: true }) })
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
                assert.ok(failed.buttons.every(button => button.disabled === (initialMode === 0)));
                assert.equal(failed.buttons[2].attributes['aria-pressed'], String(initialMode === 1));
                assert.match(failed.feedback.textContent, /cannot|failed|Could not/);
                assert.doesNotMatch(failed.feedback.textContent, /saved\./);
            }
            const failedCommand = fixture(1, -20);
            await failedCommand.buttons[0].click();
            assert.equal(failedCommand.buttons[2].attributes['aria-pressed'], 'true');
            assert.equal(failedCommand.buttons[0].attributes['aria-pressed'], 'false');
            assert.equal(failedCommand.toggle.checked, true);
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
