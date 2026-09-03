<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentChangeAudit extends Model
{
    protected $fillable = [
        'changed_by',
        'changed_keys',
        'backup_file',
        'ip_address',
        'user_agent_hash',
    ];

    protected $casts = [
        'changed_keys' => 'array',
    ];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
