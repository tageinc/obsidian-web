<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $fillable = ['receive_app_activity_emails', 'theme_mode'];

    protected $attributes = ['receive_app_activity_emails' => true, 'theme_mode' => 'adaptive'];

    protected $casts = ['receive_app_activity_emails' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
