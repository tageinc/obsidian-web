# API and integration rules

- Identify consumers before changing routes, parameters, payloads, status codes, authentication, or error behavior. Consumers include browser clients and deployed devices/firmware.
- Preserve established device protocol and endpoint compatibility. Do not rename legacy action routes merely to make them RESTful.
- Prefer resource-oriented routes and normal HTTP semantics for new APIs when compatible with nearby patterns.
- Keep filtering, sorting, pagination, validation, and response envelopes consistent with the endpoint family. Paginate growing collections where the consumer contract permits it.
- Enforce applicable device ownership and developer access on the server. Never broaden authorization to make a UI and API feature match.
- Follow [external-api.md](external-api.md) for required UI/external API business-capability parity. Update affected workflows, shared validation/domain logic, callers, tests, capability discovery, and documentation in the same change set.
- Extend the existing `/api/external/v1` contract using Obsidian's user-backed Developer keys and authorization. Do not import TAGCSOFT API-key roles or require new API operations for strictly presentation-only changes; document applicable exclusions.
- Keep secrets and internal storage paths out of responses and logs. Do not use `APP_KEY` as a client credential.
- Verify success, denied access, invalid input, empty results, and relevant backward compatibility.
- Use synthetic data and isolated tests for remote-control endpoints. Do not send commands to physical devices as a routine test.
