<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolarTrackerRemoteControl extends Model
{
    use HasFactory;

    // Specify the table name
    protected $table = 'solar_tracker_remote_controls';

    // If the table does not have timestamps, disable them
    public $timestamps = false;

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'mode',
        'motor_speed',
        'serial_no',
    ];
}
