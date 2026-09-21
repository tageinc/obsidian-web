# Solar Tracker Remote Control

The Remote Control card restores the mode switch from commit `95a7375` on
`main`. `solar_tracker_remote_controls.mode` stores `0` for Automatic and `1`
for Remote Control, keyed by the device serial number. Opening the page loads
this persisted state without writing a command; a missing row displays Automatic.

Switching either direction posts the selected mode with motor speed `0` to
`/update-solar-tracker`. While Remote Control is active, the Motor speed slider
selects values from `-100` to `100` in increments of `10`: negative moves Down,
positive moves Up, and `0` means Stop. A missing speed defaults to `0`; opening
the page or device modal loads the saved command without resetting it.

Dragging previews the chosen speed. Releasing the slider (the native `change`
event), or changing it with the keyboard, saves directly; there is no Update
button. Selecting the already-saved speed does not send a duplicate command.
The slider is disabled in Automatic mode and while a request is pending.
Switching either mode still resets the speed to `0`.
The update endpoint also clears the manual speed
when saving Automatic mode. These settings are available through the existing
`/api/remote-control/{serial_no}` firmware polling endpoint.

The card displays success only after the server confirms the save. A failed
save restores the last confirmed mode and speed and shows an error; it does not
retry automatically. The card shows the selected speed separately from the
last saved speed. Older API clients may store values between the slider steps:
these remain valid and are displayed exactly in the saved-speed label, while
the slider thumb starts at the nearest step of `10`. This does not write a new
command until the user commits a slider change. The API continues accepting
numeric values throughout `-100` to `100` for compatibility.

The original `main` implementation in `95a7375` used fixed Up `20`, Stop `0`,
and Down `-20` buttons; its controller already supported the full speed range.
The slider replaces those buttons in both Vue and the legacy Blade fallback.

Saved settings are
commands for the tracker to pick up; they do not confirm physical movement.

Run `npm test -- resources/js/features/solar-tracker/RemoteControl.spec.js`
and `npm run test:solar-tracker-remote` for client interaction tests and
`php vendor/phpunit/phpunit/phpunit tests/Feature/SolarTrackerRemoteControlTest.php`
for persisted-mode and endpoint tests, including all 21 slider speeds. Run
`npm run test:browser -- --grep "remote motor speed|raw chart ranges|device view modal"`
for desktop/mobile interaction and accessibility checks. Browser command
responses are mocked; persistence tests use the isolated test database and
never command a live tracker.
