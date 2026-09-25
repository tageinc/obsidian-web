<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalApiKey extends Model
{
    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function issue(User $user, string $name, ?string $expiresAt): array
    {
        $secret = 'obs_ext_'.bin2hex(random_bytes(32));
        $key = new self;
        $key->forceFill([
            'user_id' => $user->id, 'name' => $name, 'prefix' => substr($secret, 0, 16),
            'token_hash' => hash('sha256', $secret), 'expires_at' => $expiresAt,
        ])->save();

        return [$key, $secret];
    }

    public static function resolve(?string $secret): ?self
    {
        if (!is_string($secret) || !preg_match('/^obs_ext_[a-f0-9]{64}$/D', $secret)) {
            return null;
        }
        $key = self::where('token_hash', hash('sha256', $secret))->first();
        if (!$key || $key->revoked_at || ($key->expires_at && !$key->expires_at->isFuture())) {
            return null;
        }
        $user = $key->user;

        return $user && $user->isDeveloper() && $user->hasVerifiedEmail() ? $key : null;
    }

    public function summary(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'prefix' => $this->prefix,
            'created_at' => $this->created_at->toIso8601String(),
            'last_used_at' => optional($this->last_used_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'status' => $this->revoked_at ? 'Revoked' : (($this->expires_at && !$this->expires_at->isFuture()) ? 'Expired' : 'Active'),
        ];
    }
}
