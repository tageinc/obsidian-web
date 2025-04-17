<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVersions extends Model
{
    protected $fillable = ['version', 'prefix', 'file_path', 'description', 'timestamp'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;

}
