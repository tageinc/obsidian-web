<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesSoftwareVersionCache;
use App\Models\Concerns\OrdersReleaseVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmwareVersions extends Model
{
    use InvalidatesSoftwareVersionCache;
    use OrdersReleaseVersions;

    protected $softwareVersionKind = 'firmware';
    protected $fillable = ['version', 'prefix', 'file_path', 'description', 'timestamp'];
    public $timestamps = true;
	protected $dates = ['created_at', 'updated_at'];

    use HasFactory;

}
