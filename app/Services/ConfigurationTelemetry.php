<?php

namespace App\Services;

/** Device acknowledgement is independent of publication and of earlier readings. */
class ConfigurationTelemetry
{
    public function fromReading(array $data): array
    {
        $ota = is_array($data['config_ota'] ?? null) ? $data['config_ota'] : [];
        $result = [];
        foreach (['downloaded', 'applied', 'persisted'] as $stage) {
            $result[$stage] = $this->identity($ota[$stage] ?? null);
        }
        foreach (['status', 'error'] as $field) {
            $value = $ota[$field] ?? null;
            $result[$field] = is_string($value) && preg_match('/^[A-Za-z0-9_ .:-]{1,96}$/D', $value) ? $value : null;
        }

        return $result;
    }

    private function identity($value): ?array
    {
        if (!is_array($value) || ($value['schema_version'] ?? null) !== 1) {
            return null;
        }
        $result = [];
        foreach (['release_id', 'prefix', 'version'] as $field) {
            if (!is_string($value[$field] ?? null) || !preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $value[$field])) {
                return null;
            }
            $result[$field] = $value[$field];
        }
        $result['schema_version'] = 1;

        return $result;
    }
}
