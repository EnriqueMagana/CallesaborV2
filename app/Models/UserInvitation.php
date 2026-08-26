<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class UserInvitation extends Model
{
    protected $fillable = [
        'email',
        'role_id',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_user_id',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(string $token): bool
    {
        return $this->accepted_at === null
            && $this->expires_at->isAfter(now())
            && hash_equals($this->token_hash, self::hashToken($token));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id')->withTrashed();
    }
}
