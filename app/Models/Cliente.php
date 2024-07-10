<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cliente extends Model
{
    use HasFactory, SoftDeletes; 

    protected $fillable = ['nombre', 'cliente_endpoint_id'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
