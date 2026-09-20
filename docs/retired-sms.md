# SMS retirement

SMS notification delivery and Twilio are removed from the active application.
Email device-status notifications remain enabled through `status_notification`.

## Existing settings and data

The legacy `sms_notification` device column and existing user phone data are
retained so this change does not delete unrelated records. They are no longer
read by notification delivery, user interfaces, or application routes.

Use the following deployment-safe sequence after identifying affected devices:

1. Run `php artisan device:deactivate-sms-notifications` to report how many
   legacy opt-ins exist. This is read-only.
2. After a production data-change approval, run
   `php artisan device:deactivate-sms-notifications --apply`. It changes only
   `sms_notification` from enabled to disabled; it does not delete devices,
   users, phone values, or notification history.
3. Verify the count is zero and confirm email status notifications still work.
4. Revoke the retired provider credentials through the separately approved
   secret-rotation procedure. Never place those values in this repository.

Rollback is application-version rollback before the legacy setting deactivation
is applied. Once the explicit deactivation command has run in production, a
separate approved data change is required to re-enable any setting.
