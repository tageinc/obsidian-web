# Obsidian access model

Obsidian access is based on authenticated users and device ownership or administrator authorization. A registered device can use supported device and telemetry APIs without a license, subscription, order, payment, or billing status.

Authorize.Net, checkout, recurring subscription, license assignment, cancellation, and billing update paths have been retired. Historical orders, licenses, subscription identifiers, and payment records remain in their existing database tables for audit and reference; application code does not read them as access gates or mutate them. Any future archival or database cleanup requires a separately approved migration.

Authentication, email verification, ownership checks, and administrator authorization remain active.
