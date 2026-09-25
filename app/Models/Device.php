<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $attributes = ['state' => 'active'];

    protected static function booted()
    {
        static::deleting(function () {
            throw new \LogicException('Devices cannot be deleted. Inactivate them instead.');
        });
        static::saving(function (Device $device) {
            if (! in_array($device->state, ['active', 'inactive'], true)) {
                throw new \InvalidArgumentException('Device state must be active or inactive.');
            }
        });
    }


	protected $guarded = [];
	
	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}

}

