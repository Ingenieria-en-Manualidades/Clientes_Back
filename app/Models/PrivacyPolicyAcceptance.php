<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyPolicyAcceptance extends Model
{
    protected $fillable = [
        'user_id', 'policy_version', 'accepted_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected $table = 'surveys.privacy_policy_acceptances';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
