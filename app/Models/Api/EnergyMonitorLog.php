<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnergyMonitorLog extends Model
{
    protected $fillable = ['v_batt', 'i_batt', 'v_sol', 'i_sol', 'i_inv', 'temp', 'serial_no'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;
}
