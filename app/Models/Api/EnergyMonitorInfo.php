<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnergyMonitorInfo extends Model
{
    protected $fillable = ['p_batt', 'e_batt', 'p_sol', 'e_sol', 'p_inv', 'e_inv', 'emissions', 'savings', 'serial_no'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;
}
