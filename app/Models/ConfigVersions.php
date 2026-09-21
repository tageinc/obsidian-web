<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesSoftwareVersionCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVersions extends Model
{
    use InvalidatesSoftwareVersionCache;

    protected $softwareVersionKind = 'config';
    protected $fillable = ['version', 'prefix', 'file_path', 'description', 'timestamp'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;

}
