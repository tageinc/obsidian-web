# Obsidian secret rotation runbook

## Scope and safety

This runbook records the repository-side process only. Do not place values in
this repository, logs, tickets, shell history, screenshots, or chat. A
production rotation requires separate approval that names the target
environment and exact secret classes. Generate each replacement at its issuer
and place it only in the approved runtime secret store/deployment path.

## Inventory and ownership

| Variable(s) | Secret class | Proposed owner | Rotation status |
| --- | --- | --- | --- |
| `APP_KEY` | Laravel application encryption key | Application/platform owner | Local replacement generated; deployed environments pending approval |
| `DB_USERNAME`, `DB_PASSWORD`, `DATABASE_URL` when configured | Database account credential | Database owner | Pending approval and database-owner confirmation |
| `API_LOGIN_ID`, `TRANSACTION_KEY` | Payment gateway credential | Finance/payment owner | Pending approval |
| `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAILGUN_SECRET`, `POSTMARK_TOKEN`, AWS mail variables when configured | Email provider credential | Messaging/email owner | Pending approval |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` when configured | Infrastructure credential | Cloud/infrastructure owner | Pending approval |
| Any device, deployment, cache, storage, or provider variables added outside the current `.env` inventory | Service credential | Respective system owner | Inventory required before rotation |

Values are intentionally absent. Variables listed as "when configured" are
referenced by application configuration but are not present in the current
local `.env` inventory.

## Approved rotation sequence

1. Record the environment, secret class, provider owner, approved secret-store
   reference, request/change identifier, and rollback owner. Do not record a
   value.
2. Generate a replacement at the issuing provider or database. Store it in the
   approved secret-management path with a versioned reference.
3. Update the runtime deployment configuration to reference that secret
   version. Clear/rebuild configuration cache through the approved deployment
   procedure, then restart/roll instances according to the environment plan.
4. Validate application health and the affected integration with a dedicated
   non-destructive health check. Record the result, timestamp, environment,
   secret-store reference, and validator; do not record request headers,
   payloads, URLs with tokens, or credentials.
5. Revoke, disable, or delete the previous provider/database credential only
   after validation. Record the provider's revocation result without values.
6. Keep the old secret-store version only for the approved rollback window.
   The rollback procedure must select a prior secret reference, never paste a
   literal credential. At the end of the window, revoke the old version and
   close the rotation record.

## Validation matrix

| Secret class | Safe validation | Rollback reference |
| --- | --- | --- |
| Application key | Authenticated application health check and encrypted-session/cookie compatibility plan | Prior secret-store version; coordinate key changes because old encrypted data may become unreadable |
| Database | Read-only health query, then a controlled application write in the approved environment | Prior database account/secret reference; do not broaden grants without database-owner review |
| Payment | Provider sandbox or approved non-financial credential test | Prior secret-store version; never execute a charge to test rotation |
| Mail and messaging | Provider credential validation or approved non-delivery test | Prior secret-store version; do not send customer-facing test traffic |
| Weather/API/infrastructure | Provider identity/health endpoint with redacted telemetry | Prior secret-store version |

## Exposure review

`.env` is ignored and has been removed from the Git index while remaining as a
local runtime file. `.env.example` is the committed placeholder configuration.
The repository history contains one prior `.env` revision with secret-bearing
variable names. Treat each value in that historical revision as exposed until
its issuer confirms it has been rotated or revoked.

The accessible repository may contain historical payment logs. They are retained
payment-provider log. Its content matches generic secret-exposure indicators.
Do not copy its contents into a ticket or chat. The payment owner must review
it in the approved incident process and rotate any credential, token, payment
instrument, or personally identifiable data it confirms contains. Retention or
deletion of that log is a separate records/incident decision and is not
performed by this runbook.

Run `powershell -ExecutionPolicy Bypass -File scripts/audit-secret-exposure.ps1`
from the repository root for a value-free working-tree and Git-history scan.
It reports only locations, commits, and variable names. The scan does not
cover remote deployment artifacts, external backups, CI logs, Git forges,
secret stores, or developer machines. Those systems need access from their
owners and documented follow-up.

## Rotation evidence record

| Environment | Secret class | Secret-store reference | Replacement active | Validation | Previous value revoked | Owner/follow-up |
| --- | --- | --- | --- | --- | --- | --- |
| Local | Application key | Local ignored `.env` | Yes | Laravel reads `local` configuration | Not applicable: this is a new local-only configuration | Developer |
| Production and other deployed environments | All classes above | Pending approved location | Pending approval | Pending | Pending | Platform and respective provider owners |
