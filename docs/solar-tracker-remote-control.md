# Solar Tracker Remote Control

The Remote Control card restores the mode switch from commit `95a7375` on
`main`. `solar_tracker_remote_controls.mode` stores `0` for Automatic and `1`
for Remote Control, keyed by the device serial number. Opening the page loads
this persisted state without writing a command; a missing row displays Automatic.

Switching either direction posts the selected mode with motor speed `0` to
`/update-solar-tracker`. While Remote Control is active, Up sends `20`, Stop sends
`0`, and Down sends `-20`. Movement buttons are disabled in Automatic mode and
while a request is pending. The update endpoint also clears the manual speed
when saving Automatic mode. These settings are available through the existing
`/api/remote-control/{serial_no}` firmware polling endpoint.

The card displays success only after the server confirms the save. A failed
save restores the last confirmed mode and shows an error. Saved settings are
commands for the tracker to pick up; they do not confirm physical movement.

Run `npm run test:solar-tracker-remote` for client interaction tests and
`php vendor/phpunit/phpunit/phpunit tests/Feature/SolarTrackerRemoteControlTest.php`
for persisted-mode and endpoint tests.
