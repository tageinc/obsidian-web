# Obsidian access model

Obsidian access is based on authenticated users and device ownership or administrator authorization. A registered device can use supported device and telemetry APIs without a license, subscription, order, payment, or billing status.

Authorize.Net, checkout, recurring subscription, license assignment, cancellation, and billing update paths have been retired. Existing deployments retain historical orders, licenses, subscription identifiers, and payment records until a separately approved archival plan is executed. Fresh installations do not create the retired billing, product, software, or connection tables.

Authentication, email verification, ownership checks, and administrator authorization remain active.
