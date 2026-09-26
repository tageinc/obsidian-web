# External API keys: user and integration guide

Use an external API key to connect a script or service to Obsidian. A key grants
developer access to the endpoints under `/api/external/v1`, including devices
across owners, telemetry, remote controls, firmware, and configuration releases.
All keys have the same supported permissions; there are no per-key scopes or
read-only keys.

This guide describes the current implementation. Replace the example hostname,
device IDs, serial numbers, versions, and local file paths with your own values.
The example hostname `https://obsidian.example.invalid` is a placeholder.

## 1. Create and save a key

1. Sign in to Obsidian as the configured developer and verify that account's
   email address. This is the single developer user identified by
   `DEVELOPER_EMAIL` in the application's `.env` configuration.
2. Open your account menu, select **Developer Workspace**, then open
   **Tools → External API Keys**.
3. Select **Create Key**.
4. Enter a **Key name**, up to 100 characters. Use a name that identifies the
   integration, such as `Warehouse reporting` or `Release uploader`.
5. Optionally choose **Expires on (UTC, optional)**. The date must be after today.
   The key expires at **00:00 UTC at the start of that date**, not at the end.
   Leave it blank for no scheduled expiration.
6. Select **Create Key**, then **Copy key**. Save the complete value in your
   integration's secret store before selecting **Done**.

The complete key starts with `obs_ext_`, followed by 64 hexadecimal characters.
It is displayed **once**. Closing the dialog, leaving the page, or refreshing
loses that display. If copying is unavailable, select and copy the value from
the API key field manually.

The table's **Key prefix** is only an identifier; it cannot authenticate a
request. Obsidian stores a hash of the key, so the complete value cannot be
retrieved later. If you lose it, create a replacement and revoke the lost key.

If Developer Workspace is unavailable, confirm that you are signed in to the
verified account matching the configured developer email. A regular user
cannot create keys. Deployments using the legacy interface can manage keys at
`/developer-workspace/api-keys` after signing in. That page displays ISO timestamps
in UTC and its **Revoke** button applies immediately without a confirmation dialog.

## 2. Authenticate your first request

Use your application's actual HTTPS origin followed by `/api/external/v1`.
The `Authorization` header is required. `Accept: application/json` is recommended
for JSON operations and is included in the examples:

```http
Authorization: Bearer <complete-external-api-key>
Accept: application/json
```

Replace the entire placeholder, including angle brackets. There is one space
between `Bearer` and the key. Do not put the key in a URL or JSON request body.
JSON writes also need `Content-Type: application/json`; file uploads use
multipart form data instead.

An external integration does not need browser cookies, a CSRF token, a Sanctum
handshake, or a call to `/api/login`. Tokens returned by `/api/login` are a
different credential and do not authenticate these external endpoints.

### PowerShell quick start

This prompts for the key without putting its literal value in your command
history. The request only reads the API's supported endpoints.

```powershell
$obsidianUrl = 'https://obsidian.example.invalid'
$obsidianApi = "$obsidianUrl/api/external/v1"
$obsidianSecureKey = Read-Host 'Paste your external API key' -AsSecureString
$obsidianHeaders = @{
    Authorization = 'Bearer ' + [System.Net.NetworkCredential]::new('', $obsidianSecureKey).Password
    Accept = 'application/json'
}

Invoke-RestMethod -Method Get -Uri $obsidianApi -Headers $obsidianHeaders

$devicePage = Invoke-RestMethod -Method Get -Uri "$obsidianApi/devices?per_page=20&page=1" -Headers $obsidianHeaders
$devicePage.data
```

A successful first request returns HTTP **200** with `version: "v1"`,
`access: "developer"`, and an `endpoints` array. The device request returns a
paginated `data` array; an empty array is a successful response when no devices
exist.

### curl quick start

The curl examples in this guide use **Bash** syntax, suitable for Linux, macOS,
Git Bash, or WSL. Run this setup in the same shell before the remaining examples:

```bash
OBSIDIAN_URL='https://obsidian.example.invalid'
OBSIDIAN_API="$OBSIDIAN_URL/api/external/v1"
read -r -s -p 'Paste your external API key: ' OBSIDIAN_API_KEY
printf '\n'

curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API"
```

For a deployed integration, load the key from its secret store or protected
environment configuration instead of an interactive prompt. Keep it out of
source control, browser JavaScript, screenshots, and request logs. Use HTTPS
when sending a real key.

## 3. Supported endpoints

All paths below are relative to `/api/external/v1`. `{id}` is the numeric device
ID returned by the device list. Remote-control requests instead use the device's
`serial_no`. Download paths use a release's `version`, not its database `id`.

| Method | Path | Result |
| --- | --- | --- |
| GET | `/` | API version, access type, and supported endpoint list |
| GET | `/devices` | Paginated device list across owners |
| GET | `/devices/{id}` | Device details in `data` |
| PATCH | `/devices/{id}` | Update supported device fields; returns `values` |
| GET | `/devices/{id}/data` | Recent telemetry in `graph.points` |
| GET | `/devices/{id}/telemetry` | Fresh overview values and recent History readings in one read-only snapshot |
| GET | `/devices/{id}/report` | Download the History PDF for an optional inclusive `from`/`to` range |
| GET | `/remote-control?serial_no=...` | Stored control mode and motor speed |
| POST | `/remote-control` | Save control mode and motor speed |
| GET | `/firmware` | Paginated firmware releases |
| POST | `/firmware` | Upload a firmware file and create a release |
| GET | `/firmware/{version}/download` | Download a firmware file |
| GET | `/configuration` | Paginated configuration releases |
| POST | `/configuration` | Upload a JSON configuration and create a release |
| GET | `/configuration/{version}/download` | Download a configuration file |

External keys cannot manage other keys, register devices, change accounts, or
inactivate/reactivate devices through this endpoint group. They do not work on
the separate legacy/mobile API endpoint group or browser management routes.

## 4. List and read devices

```bash
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/devices?per_page=20&page=1"

# Replace 123 with an id returned in data[].
DEVICE_ID=123
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/devices/$DEVICE_ID"
```

Device lists are ordered by ID ascending and include active and inactive devices
across owners. Each row includes `id`, `serial_no`, `name`, `sku`, `state`,
`address_1`, `address_2`, `city`, `address_state`, `zip_code`, `country`, `latitude`,
`longitude`, `created_at`, and `updated_at`. The single-device response additionally
includes `order_no` and `status`; status is `"no geo data"` if no location status
is available. Nullable fields can contain `null`.

### Pagination

`GET /devices`, `GET /firmware`, and `GET /configuration` accept:

| Parameter | Meaning |
| --- | --- |
| `per_page` | Integer from 1 to 100; default 20 |
| `page` | Page number, starting at 1 |

Read records from `data`. Pagination metadata includes `current_page`,
`last_page`, `per_page`, `total`, `from`, `to`, `next_page_url`, and `prev_page_url`.
Use `current_page < last_page` to request the next page, keeping your chosen
`per_page` value. Do not assume a generated next-page URL retains it.

These external list endpoints currently support pagination only. Developer
Workspace table searches, Prefix/Version filters, date ranges, and sort controls
are browser features; those parameters are not implemented on the external lists.

## 5. Update a device

Send only the fields you want to change. Omitted fields retain their values.
This example changes a device name and location:

```bash
curl --silent --show-error --fail-with-body \
  -X PATCH \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data '{"name":"Roof tracker","latitude":34.0522,"longitude":-118.2437}' \
  "$OBSIDIAN_API/devices/$DEVICE_ID"
```

| Field | Accepted value |
| --- | --- |
| `name` | Nonempty string, maximum 255 characters |
| `sku`, `order_no` | String up to 255 characters, or `null` to clear |
| `address_1`, `address_2`, `city`, `address_state`, `zip_code`, `country` | String up to 255 characters, or `null` to clear |
| `latitude` | Number from -90 to 90, or `null` to clear |
| `longitude` | Number from -180 to 180, or `null` to clear |

`serial_no` is immutable: omit it; if supplied, it must equal the existing value.
Do not send `state` or `status`; changes to them are prohibited. Unrecognized
fields are not saved. Updating coordinates also updates the stored device
location record.

Success returns HTTP **200** with a `values` object containing the current
supported fields, including the unchanged serial number. This response uses
`values`, not the `data` envelope used by the read endpoint.

## 6. Read telemetry

```bash
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/devices/$DEVICE_ID/data"
```

The response is `{"graph": {...}}`. It contains up to the latest **9,000** individual
readings in chronological order, not a paginated or averaged series. Each point
has `id`, `timestamp`, `epoch_ms`, `label`, `temp`, `temp_f`, `ps1`, `ps2`, `pds`, `ps_avg`,
and `motor_speed`.
Missing metric values are `null`; duplicate timestamps can represent distinct
readings. Use `epoch_ms` or the ISO-8601 `timestamp` for processing.

`temp` retains the recorded Celsius value for existing clients. `temp_f` is the
Fahrenheit value used by the device overview and History: `C * 9 / 5 + 32`, rounded
to four decimal places. Numeric strings are accepted; missing, blank, boolean,
and invalid temperatures or non-finite conversion results return `null` for
`temp_f`. A recorded zero Celsius is `32` Fahrenheit. Raw telemetry is not rewritten.

Timestamps and display labels use `America/Los_Angeles` with the applicable UTC
offset. This endpoint does not implement date ranges, custom limits, or
historical paging. Filter the returned points in your integration when needed.
An existing device without readings returns HTTP **200**:

```json
{
  "graph": {
    "timezone": "America/Los_Angeles",
    "date_time_format": "M j, Y, g:i A T",
    "points": []
  }
}
```

### Refresh the overview and History together

`GET /devices/{id}/telemetry` returns the same snapshot used to initialize and
refresh the Vue device page. The browser uses its session-authenticated
`GET /devices/{id}/telemetry` route; integrations use the external API base URL:

```bash
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/devices/$DEVICE_ID/telemetry"
```

The response contains three fields:

- `status`: the latest overview values under `State`, `PS1`, `PS2`, `PS Average`,
  `PDS`, `CTS`, `CTS state`, `Motor Speed`, `Temperature (°C)`, `Temperature (°F)`, `Firmware version`,
  `Config version`, and `Updated`. Missing
  measurements are `null`, including missing values in a recorded reading;
  zero remains a real zero. `Updated` is the recorded time displayed in Pacific
  Time, or `N/A` when no timestamped reading exists.
- `graph`: the same latest 9,000 raw readings and metadata as `/data`, ordered
  by timestamp and then ID. All measurements and duplicate timestamps are
  preserved. There are no date-range parameters or additional history paging.
- `latest_reading`: `{ "id": 123, "timestamp": "2026-09-25T11:00:00-07:00",
  "epoch_ms": 1790359200000 }`, or `null` for a device without readings. When
  several readings share a timestamp, the highest ID is the latest reading.

The Overview card's **Software versions** values come from `firmware_version`
and `config_version` in that same latest device reading. They describe the
device-reported versions, not the newest releases available for download.
Nonempty string identifiers retain their exact text (including leading zeros),
and numeric versions retain their numeric value; `"0"` and `0` are valid
versions. Missing, `null`, blank, or nonscalar values return `null`, as do
booleans. Versions are not carried forward from an older reading when the
latest reading omits them. The card layout is presentation behavior; the data
is available through the existing telemetry operation above.

`Temperature (°F)` uses the same conversion, precision, and null handling as
`graph.points[].temp_f`. `Temperature (°C)` and `CTS` retain their existing raw
contracts. `CTS state` maps the numeric or string value `0` to `Open` and `1` to
`Closed`; missing or unrecognized states return `null`. The overview shows Open
with a green badge and Closed with a light-gray badge. Badge colors are
presentation behavior; the state values are available through telemetry.

Each successful request returns a full current snapshot, even if the latest
timestamp has not changed. This also exposes corrections to existing readings.
Responses are private and non-cacheable. Reads do not modify device telemetry or
stored remote-control commands, and the response does not include control-form
values. Missing devices return **404**. The browser route requires a verified
owner or Developer session; the external route requires an active Developer
external key. Browser timer scheduling, page visibility, and preserving local
filter selections are presentation behavior and need no separate API operation.

### Download the History PDF report

`GET /devices/{id}/report` provides the same report as the printer button in the
device's **History** tab. Success is an **application/pdf** attachment, not JSON.
The PDF includes device name, serial number, SKU, order number, lifecycle state,
address, coordinates, current stored control mode, report creation time, and the latest telemetry snapshot
(including its recorded time). It includes all five History graphs:
temperature, panel sensors (PS1 and PS2), PDS, PS Average, and motor speed, with
raw readings and the same least-squares linear trends. The currently displayed
measurement in the UI does not limit the report.
Temperatures in the latest snapshot and history charts use Fahrenheit with the
same conversion as `temp_f`. The latest CTS value uses Open/Closed text; an
unrecognized state retains its raw value, and missing values are shown as N/A.

| Query parameter | Contract |
| --- | --- |
| `from` | Inclusive starting instant; ISO 8601 with seconds and an explicit `Z` or numeric offset |
| `to` | Inclusive ending instant in the same format, on or after `from` |

Supply both parameters or omit both. Fractional seconds up to millisecond
precision are accepted, matching the graph timestamps. Omitting
both defaults to the last 24 elapsed hours ending at the current server time
(rounded down to the second). Offset-free dates, impossible dates, incomplete
pairs, and reversed ranges return **422** with field errors. Use URL encoding
for numeric offsets so the `+` sign is preserved.

```bash
curl --silent --show-error --fail-with-body \
  --get \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/pdf, application/json' \
  --data-urlencode 'from=2026-09-24T18:00:00.000Z' \
  --data-urlencode 'to=2026-09-25T18:00:00.000Z' \
  --output ./device-history.pdf \
  "$OBSIDIAN_API/devices/$DEVICE_ID/report"
```

Check the HTTP status and curl exit status before opening the file; failures are
JSON errors. The selected interval filters the same latest **9,000** timestamped
readings available to History, inclusively. It does not retrieve an unlimited
archive. Distinct readings at the same timestamp are retained. Missing metric
values remain missing; PS Average is the recorded `ps_avg`, not a recomputation
from PS1 and PS2. A trend needs at least two distinct valid timestamps.

The latest snapshot is independent of the selected interval and may be outside
it. Report values are read from the database when the report is generated, so
new readings or saved device edits can be newer than a previously opened page.
The PDF labels dates in Pacific Time (`America/Los_Angeles`, PST/PDT) and marks
empty ranges or absent readings. Existing devices without telemetry still return
a PDF with empty graphs. Missing devices return **404**.

Reports use the same owner-or-Developer permission as device details: verified
owners and the configured verified Developer can download in the browser, and
active external keys act as that Developer. The response is private and
non-cacheable; no device settings or remote commands are changed. Browser
filter-card layout and tab navigation are presentation-only and do not require
additional API operations.

## 7. Read or change remote controls

Read the stored command using the exact `serial_no` from a device response:

```bash
SERIAL_NO='REPLACE_WITH_DEVICE_SERIAL'
curl --silent --show-error --fail-with-body \
  --get \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  --data-urlencode "serial_no=$SERIAL_NO" \
  "$OBSIDIAN_API/remote-control"
```

HTTP **200** returns `success`, `mode`, and `motor_speed`. An existing device with
no stored control record returns **404** with `"message": "Panel not found"`.
Reading controls does not create a command or move the device.

To change controls, send JSON with all three fields:

| Field | Accepted value |
| --- | --- |
| `serial_no` | Existing device serial string, maximum 255 characters |
| `mode` | Boolean or 0/1: 0 = automatic, 1 = remote/manual |
| `motor_speed` | Number from -100 to 100; required in either mode |

Use whole-number speeds for compatibility with stored commands: negative means
Down, positive means Up, and zero means Stop. Fractional speeds are accepted by
validation but are not guaranteed to retain their precision in storage.

For example, this payload selects remote/manual mode with a zero-speed command:

```json
{
  "serial_no": "REPLACE_WITH_DEVICE_SERIAL",
  "mode": 1,
  "motor_speed": 0
}
```

Save it as `remote-command.json`, replace the serial, and send it only when you
intend to change that device's controls. A control write can affect physical
hardware; use an isolated test device while developing an integration.

```bash
curl --silent --show-error --fail-with-body \
  -X POST \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  --data-binary @remote-command.json \
  "$OBSIDIAN_API/remote-control"
```

Mode `0` always stores `motor_speed: 0`, even if another speed was supplied.
Success returns HTTP **200** with `success: true`, a `message`, and the saved
`mode` and `motor_speed`. This confirms that Obsidian saved the command; it does
not confirm that hardware received or completed it.

## 8. List, upload, and download software releases

### List releases

```bash
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/firmware?per_page=20&page=1"

curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  "$OBSIDIAN_API/configuration?per_page=20&page=1"
```

Both lists use the pagination format described above and return the newest
database ID first. Each record contains `id`, `version`, `prefix`, `description`,
and `created_at`. Internal storage paths are not returned.

### Upload a release

Uploads use `multipart/form-data`. Let curl set the multipart content type and
boundary; do not add a JSON `Content-Type` header.

| Endpoint | Required file field | File size limit | Other required fields |
| --- | --- | --- | --- |
| `POST /firmware` | `firmware` | 10 MiB (10,240 KiB) | `prefix`, `description`: nonempty strings, maximum 255 characters each |
| `POST /configuration` | `config` | 1 MiB (1,024 KiB), JSON file | Same |

Firmware is intended to be a `.bin` file. The current firmware endpoint checks
that it is a file and enforces the size limit; it does not verify the binary's
contents or hardware compatibility. Configuration uploads receive JSON file-type
validation, but no device-specific configuration schema validation. Use a
release suitable for the intended device and prefix.

```bash
curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  -F 'firmware=@./release.bin' \
  --form-string 'prefix=SP1' \
  --form-string 'description=Firmware release for SP1' \
  "$OBSIDIAN_API/firmware"

curl --silent --show-error --fail-with-body \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  -F 'config=@./configuration.json;type=application/json' \
  --form-string 'prefix=SP1' \
  --form-string 'description=Configuration release for SP1' \
  "$OBSIDIAN_API/configuration"
```

Success returns HTTP **201** with `version` and `message`. The server assigns the
version; do not send your own. For example, a firmware upload might return:

```json
{
  "version": "13",
  "message": "Firmware uploaded successfully."
}
```

Configuration uses the message `"Configuration uploaded successfully."`.
Uploading stores a release; it does not itself confirm installation on a device.
After an uncertain timeout, inspect the release list before retrying: a second
successful upload creates another version.

### Download a release

Set `VERSION` to a version returned by that release list or upload response.
Downloads return the actual file, not a JSON envelope.

```bash
VERSION=13
curl --silent --show-error --fail \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  --output ./downloaded-firmware.bin \
  "$OBSIDIAN_API/firmware/$VERSION/download"

# Choose a version that exists in the configuration list separately.
VERSION=7
curl --silent --show-error --fail \
  -H "Authorization: Bearer $OBSIDIAN_API_KEY" \
  -H 'Accept: application/json' \
  --output ./downloaded-configuration.json \
  "$OBSIDIAN_API/configuration/$VERSION/download"
```

Check for a successful HTTP response and curl exit status before using a file.
A missing version or missing stored file returns **404**.

## 9. Monitor, rotate, and revoke keys

Return to **Developer Workspace → Tools → External API Keys** to inspect a key.

Use **Search** to find a name or key prefix across the entire key list. Open
**Filters** to select one or more statuses (**Active**, **Expired**, **Revoked**),
choose **No expiration** or **Has expiration**, and set a creation date range.
Creation date filters include both selected dates and use UTC. Select
**Apply filters** to apply the selections; dismissing the panel discards drafts.
Selections within a filter match any selected value, while different filters
are combined.

Remove an individual filter chip or select **Clear filters** to start again.
Use **Sort by** for creation order, name, expiration, or last use, and **Rows per
page** to choose 10, 20, 50, or 100 results. Keys without an expiration or last use
appear last for the corresponding sort. Applied searches and filters remain in
the page URL when you reload. Creating or revoking a key refreshes the filtered
results; a revoked key disappears when only **Active** keys are selected.

| Column | Meaning |
| --- | --- |
| Name | The integration label supplied at creation |
| Key prefix | A short identifier, not a usable credential |
| Status | Active, Expired, or Revoked |
| Created | When the key was issued |
| Last used | Latest successful key authentication; Never if unused |
| Expires | Expiration instant, or Never if no expiration was set |

The current Developer Workspace table displays timestamps in your browser's
local time; the legacy page displays UTC timestamps. The creation form's
expiration date is defined in UTC. Last used does not prove the requested action
succeeded: authentication can succeed before endpoint validation fails.

**Rotate a key:**

1. Create a replacement key and save its complete value.
2. Update the integration's secret configuration.
3. Verify the new key with the read-only `GET /api/external/v1` request.
4. Select **Revoke** on the old key and confirm **Revoke key** in Developer
   Workspace. The legacy page revokes directly without that confirmation.

Revocation rejects subsequent requests immediately. Revoked keys remain listed
and cannot be reactivated. There is no key-edit or expiration-extension action;
create a replacement if the name or expiration needs to change. If a key is
exposed, revoke it promptly and replace it in the integration.

Every authenticated request also checks that the key's owner still exists, has
a verified email, and matches the current `DEVELOPER_EMAIL`. Removing that
authorization prevents the key from working even if its table status is Active.
Use separate keys for separate integrations so one can be revoked independently.

Key creation and revocation require your developer **browser session and CSRF
protection**. A bearer key cannot create another key or revoke itself. The
browser routes are `GET/POST /developer-workspace/api-keys` and
`POST /developer-workspace/api-keys/{keyId}/revoke`; use the UI for these actions.

## 10. Errors and request limits

| HTTP status | Meaning and next step |
| --- | --- |
| 200 | Successful read, device edit, or control update; inspect the endpoint's response shape |
| 201 | A software release or browser-managed key was created |
| 401 | Missing, incorrect, expired, revoked, or unauthorized-owner external key; check the complete Bearer value and developer authorization |
| 403 | Permission denied, particularly when managing keys without a verified developer browser session |
| 404 | Missing device, release, stored file, or control record; check numeric ID versus serial number versus version |
| 405 | Correct path with the wrong HTTP method; compare against the endpoint table |
| 419 | Browser key-management session/CSRF problem; sign in again and retry through the UI |
| 422 | Invalid parameters or payload; read the `errors` object and correct the listed fields |
| 429 | Rate limit reached; wait for the `Retry-After` interval before trying again |
| 500 | Server or storage failure; retain the operation details without the key and report the issue to the application operator |

The application allows **120 requests per minute per external key**, alongside
the general API limit of **1,000 requests per minute per IP address**. Requests
from several integrations behind the same IP share that general limit. Browser
key creation is separately limited to 20 requests per minute.

Invalid external authentication returns:

```json
{
  "message": "Invalid or inactive external API key."
}
```

Validation errors normally contain `message` and an `errors` object whose values
are arrays of field messages. A missing remote-control record instead returns:

```json
{
  "success": false,
  "message": "Panel not found"
}
```

Error bodies are not identical across all endpoints. Check the HTTP status first,
then inspect `message`, `error`, and `errors` as present. A reverse proxy or upload
size limit can reject a request before the app handles it, so clients should also
handle non-JSON errors. Avoid automatically repeating writes after timeouts.

If a request unexpectedly returns a login page or HTML, verify the origin and
`/api/external/v1` path, the HTTP method, and that your proxy forwards the
`Authorization` header. Do not use `/developer-workspace/api-keys` as the external
API base URL.

## Implementation references

- [External routes](../routes/api.php)
- [Key management](../app/Http/Controllers/ExternalApiKeyController.php)
- [Key lifecycle and validation](../app/Models/ExternalApiKey.php)
- [External authentication](../app/Http/Middleware/AuthenticateExternalApi.php)
- [Lists and API discovery](../app/Http/Controllers/Api/ExternalApiController.php)
- [Device reads, telemetry, and controls](../app/Http/Controllers/ViewDeviceController.php)
- [Live telemetry snapshot](../app/Http/Controllers/DeviceTelemetryController.php)
- [History PDF reports](../app/Http/Controllers/DeviceHistoryReportController.php)
- [Device editing](../app/Http/Controllers/EditDeviceController.php)
- [Software uploads and downloads](../app/Http/Controllers/DeveloperWorkspaceController.php)
- [Access model](access-model.md)
