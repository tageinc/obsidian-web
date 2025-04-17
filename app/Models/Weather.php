<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weather extends Model
{
    protected $table = 'weather'; // Set the table name if it's different

    protected $fillable = [
        'id',
        'address1',
        'address2',
        'city',
        'state',
        'country',
        'zipCode',
        'longitude',
        'latitude',
        'wind_speed_now',
        'wind_speed_1D',
    ];
}

