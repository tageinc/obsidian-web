<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\CustomVerifyEmail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;
use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
	    'phone_number',
        'email',
        'password',
        'address_1',  // New field for address line 1
	    'address_2',  // New field for address line 1
        'city',      // New field for city
        'state',     // New field for state
        'zip_code',   // New field for zip code
        'country',   // New field for country
	'email_verified_at',

    ];

public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail());
    }

    public function licenses()
    {
        $licenses = $this->hasMany(License::class);

        return $licenses;
    }
}
