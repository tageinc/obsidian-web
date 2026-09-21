# Obsidian access model

Obsidian access is based on authenticated users and device ownership or developer authorization. A registered device can use supported device and telemetry APIs without a license, subscription, order, payment, or billing status.

Authorize.Net, checkout, recurring subscription, license assignment, cancellation, and billing update paths have been retired. Existing deployments retain historical orders, licenses, subscription identifiers, and payment records until a separately approved archival plan is executed. Fresh installations do not create the retired billing, product, software, or connection tables.

Authentication, email verification, ownership checks, and developer authorization remain active.

## Developer Workspace

`GET /developer-workspace` is the canonical firmware/configuration management page.
The old `/admin-control-center` bookmark redirects there with its query parameters.
Both URLs require authentication, a verified email, and the exact nonempty email
configured as `DEVELOPER_EMAIL` (`app.developer_email`). Missing or blank values
deny developer access. `ADMIN_EMAIL` is no longer read and has no fallback role.
Local examples use `andre.troncoso@tezca.net`; setting the policy does not create
an account or change its password. Refresh Laravel's configuration cache after
changing the setting in an environment.

For a whole-image rollback to a release before this rename, restore that
release's `ADMIN_EMAIL` setting to the same intended account and refresh its
configuration cache. A frontend feature-flag rollback keeps `DEVELOPER_EMAIL`
and the canonical route, because the server authorization policy is shared.

The same `User::isDeveloper()` policy permits developer access to device details,
graphs, remote controls, and device edits alongside the owning user. Upload
POST URLs remain `/upload-firmware` and `/upload-config`, with this developer
boundary enforced on the server. Existing browser download authorization and
external firmware/mobile API contracts are unchanged.

For compatibility, internal `AdminControlCenterController`, `AdminPage`, the
`admin-control-center.blade.php` filename, the `admin` frontend page/feature flag,
and `AdminUserSeeder` keep their names. They implement Developer Workspace, not
a separate role. The seeder reads `DEVELOPER_EMAIL` through application config;
the existing `ADMIN_BOOTSTRAP_PASSWORD` and `ADMIN_BOOTSTRAP_NAME` secret inputs
remain supported. It creates a missing configured account once and does not
modify existing credentials.
