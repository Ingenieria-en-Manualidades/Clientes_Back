<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'permissions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function user(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permission', 'permission_id', 'user_id');
    }
}
