<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Historical billing record retained for audit; never used as an access gate. */
class License extends Model
{
    use HasFactory;
    protected $guarded = [];
}
