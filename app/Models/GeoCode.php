<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeoCode extends Model
{
    use HasFactory;

    // Explicitly defining the table name
    protected $table = 'geocode';

    // Defining fillable fields for mass assignment
    protected $fillable = [
        'latitude',
        'longitude',
        'status',
        'serial_no',
        'updated_at',
        'created_at',
    ];
}
