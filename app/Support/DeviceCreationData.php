<?php

namespace App\Support;

use App\Models\User;

/** Shared defaults and allowlist for the native device creation form. */
class DeviceCreationData
{
    public const DEVICE_FIELDS = ['alias', 'serial_no', 'sku', 'order_no', 'latitude', 'longitude'];
    public const ADDRESS_FIELDS = ['address_1', 'address_2', 'city', 'state', 'zip_code', 'country'];

    public static function values(User $user, bool $retainOldInput = true): array
    {
        $values = [];
        foreach (self::DEVICE_FIELDS as $field) {
            $default = ['latitude' => '34.052235', 'longitude' => '-118.243683'][$field] ?? null;
            $values[$field] = $retainOldInput ? old($field, $default) : $default;
        }
        foreach (self::ADDRESS_FIELDS as $field) {
            $default = $user->getAttribute($field);
            $default = $field === 'country' && !$default ? 'US' : $default;
            $values[$field] = $retainOldInput ? old($field, $default) : $default;
        }

        return $values;
    }

    public static function errors(array $errors): array
    {
        return array_intersect_key($errors, array_flip(array_merge(
            self::DEVICE_FIELDS, self::ADDRESS_FIELDS
        )));
    }
}
