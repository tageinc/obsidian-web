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

## Current device behavior

Devices no longer have a hardware assignment. Every registered device uses
`solar_tracker_logs` for telemetry and status. Payloads containing only retired
Energy Monitor fields are rejected as invalid telemetry. The historical Energy
Monitor tables remain untouched; their writers and aggregators remain retired.
