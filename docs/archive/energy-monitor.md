# Energy Monitor archive

Energy Monitor is retired from the active Obsidian application path. This is a
source and schema reference archive only; it is not an executable subsystem.

## Retained historical material

The pre-deactivation source is recoverable from commit `63c6d35`:

- `app/Models/Api/EnergyMonitorLog.php`
- `app/Models/Api/EnergyMonitorInfo.php`
- `app/Console/Commands/GenerateDeviceInfo.php`
- Energy Monitor branches formerly in `DeviceInfoController`, `DeviceLogController`,
  `DeviceCommunicationStatus`, `device-info.blade.php`, and `DeviceCommunicationsTest`

Historical `energy_monitor_logs` and Energy Monitor info records are retained in
the deployed database. This change does not delete, rename, migrate, truncate,
or modify those tables or records. The original repository does not include
tracked migrations for those tables, so their production schemas remain an
operator-owned reference and must not be reconstructed from application models.

## Deactivation boundary

Hardware ID `1` (Solar Tracker/Smart Panel) reads and writes only
`solar_tracker_logs`; it does not invoke Energy Monitor writers or aggregators.
Hardware IDs previously used for Energy Monitor no longer receive telemetry,
status evaluation, device-info rendering, graph generation, or scheduled data
aggregation. Requests for those devices return a deliberate archived/unsupported
response where applicable.

Before deploying, inventory devices using retired hardware IDs and inform their
owners that the product UI and ingestion endpoints are being withdrawn. Keep a
database backup under the existing retention policy. Roll back by redeploying the
prior application commit; do not restore or alter historical tables as part of
this code change.
