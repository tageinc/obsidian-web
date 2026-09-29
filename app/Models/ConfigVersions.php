<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesSoftwareVersionCache;
use App\Models\Concerns\OrdersReleaseVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigVersions extends Model
{
    use InvalidatesSoftwareVersionCache;
    use OrdersReleaseVersions;

    protected $softwareVersionKind = 'config';
    protected $fillable = ['version', 'prefix', 'file_path', 'description', 'timestamp', 'device_family', 'schema_version'];
    protected $casts = ['schema_version' => 'integer'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;

}
