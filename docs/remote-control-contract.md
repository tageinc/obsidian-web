# Remote-control response contract

The `GET /api/remote-control/{serial_no}` response is shared by Obsidian Web and
the ESP32 firmware:

```json
{"mode": 1, "motor_speed": -37}
```

- `mode` is an integer `0` or `1`; `0` is automatic and `1` is remote/manual.
- The firmware also accepts JSON booleans for compatibility, but the server
  emits the numeric form above.
- `motor_speed` is numeric and bounded from `-100` through `100` inclusive.
- Invalid or out-of-range firmware values are rejected and result in a safe
  zero-speed command.
