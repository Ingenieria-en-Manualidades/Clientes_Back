<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'roles';
    protected $primaryKey = 'id';

    protected $fillable = ['name', 'guard_name'];

    // Relaciones (si las hay)
    public function users()
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }
}
