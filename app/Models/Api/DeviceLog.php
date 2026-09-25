<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    use HasFactory;

    public const TELEMETRY_FIELDS = ['ps1', 'ps2', 'ps_avg', 'pds', 'temp', 'cts', 'motor_speed', 'state'];

    protected $fillable = ['serial_no', 'data', 'ps1', 'ps2', 'ps_avg', 'pds', 'temp', 'cts', 'motor_speed', 'state'];

    protected $casts = ['data' => 'array'];

    protected static function newFactory()
    {
        return \Database\Factories\DeviceLogFactory::new();
    }

    // Keep existing status and graph consumers compatible with JSON storage.
    public function getAttribute($key)
    {
        if (in_array($key, self::TELEMETRY_FIELDS, true)) {
            return ($this->data ?? [])[$key] ?? null;
        }
        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::TELEMETRY_FIELDS, true)) {
            $data = $this->data ?? [];
            $data[$key] = $value;
            return parent::setAttribute('data', $data);
        }
        return parent::setAttribute($key, $value);
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        foreach (self::TELEMETRY_FIELDS as $field) {
            $attributes[$field] = $this->getAttribute($field);
        }
        return $attributes;
    }
}
