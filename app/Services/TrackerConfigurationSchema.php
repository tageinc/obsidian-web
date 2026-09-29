<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Matches smart-panels-esp32 schema 1. Numeric JSON types are intentional. */
class TrackerConfigurationSchema
{
    public const FAMILY = 'smart-panels-esp32';
    public const VERSION = 1;
    public const MAX_BYTES = 4096;
    private const RANGES = [
        'lid_N' => [1, 4096, true], 'hm_N' => [1, 4096, true],
        'lid_th' => [0, 100, false], 'optimize_th' => [0, 100, false],
        'kp' => [-10, 10, false], 'nrml_w' => [-100, 100, false],
        'log_T' => [1, 86400, false], 'update_T' => [5, 86400, false],
        'remote_control_T' => [0.1, 3600, false], 'config_portal_T' => [1, 3600, true],
        'optimize_T' => [0.001, 1440, false], 'cool_off_T' => [0, 1440, false],
    ];

    public function validate(string $bytes): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            $this->fail('Configuration must be no larger than 4096 bytes.');
        }
        try {
            $value = json_decode($bytes, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            $this->fail('Configuration must contain a valid JSON object.');
        }
        if (!$value instanceof \stdClass) {
            $this->fail('Configuration must contain a JSON object.');
        }
        $config = (array) $value;
        if (array_diff(array_keys($config), array_keys(self::RANGES))) {
            $this->fail('Configuration contains unsupported settings for smart-panels-esp32 schema 1.');
        }
        foreach (self::RANGES as $key => [$min, $max, $integer]) {
            if (!array_key_exists($key, $config)) {
                $this->fail('Configuration is missing required setting '.$key.'.');
            }
            $number = $config[$key];
            if ((!is_int($number) && !is_float($number)) || !is_finite((float) $number)
                || $number < $min || $number > $max || ($integer && floor($number) != $number)
) {
                $this->fail('Configuration setting '.$key.' must be '.($integer ? 'an integer' : 'a number')
                    .' in the supported range '.$min.' to '.$max.'.');
            }
        }

        // At this point all values are numbers and all decoded keys are known ASCII
        // names. Each colon is a property separator, including escaped duplicate keys.
        if (substr_count($bytes, ':') !== count($config)) {
            $this->fail('Configuration must not repeat setting names.');
        }

        return $config;
    }

    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['config' => $message]);
    }
}
