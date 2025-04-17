<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceRegister extends Model
{
    use HasFactory;

	protected $guarded = [];
	
	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}

	public function hardware()
    {
        return $this->belongsTo(Hardware::class, 'hardware_id');
    }

	public function license()
	{
		return $this->hasOne(License::class, 'device_id');
	}
}

