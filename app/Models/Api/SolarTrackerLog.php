<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolarTrackerLog extends Model
{
    protected $fillable = ['ps1', 'ps2', 'ps_avg', 'pds', 'temp', 'cts', 'motor_speed', 'state', 'serial_no'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;
}
