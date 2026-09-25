<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    // Preserve existing IDs and hashes so issued mobile tokens survive the migration.
    protected $table = 'personal_access_tokens';

    protected $hidden = ['token'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public static function issue(User $user): string
    {
        $secret = bin2hex(random_bytes(32));
        $token = new self;
        $token->forceFill([
            'tokenable_type' => $user->getMorphClass(), 'tokenable_id' => $user->id,
            'name' => 'API Token', 'token' => hash('sha256', $secret), 'abilities' => '["*"]',
        ])->save();

        return $token->id.'|'.$secret;
    }

    public static function resolve(?string $value): ?self
    {
        if (!is_string($value) || $value === '' || strlen($value) > 512) {
            return null;
        }
        if (strpos($value, '|') !== false) {
            [$id, $secret] = explode('|', $value, 2);
            $token = ctype_digit($id) ? self::find($id) : null;
            if (!$token || !hash_equals($token->token, hash('sha256', $secret))) {
                return null;
            }
        } else {
            $token = self::where('token', hash('sha256', $value))->first();
        }

        return $token && !$token->revoked_at && (!$token->expires_at || $token->expires_at->isFuture())
            ? $token : null;
    }

    public function user(): ?User
    {
        return $this->tokenable_type === (new User)->getMorphClass()
            ? User::find($this->tokenable_id) : null;
    }
}
