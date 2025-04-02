<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cliente extends Model
{
    use HasFactory, SoftDeletes; 

    protected $table = 'CLIENTS.clientes';
    protected $primaryKey = 'id';

    protected $fillable = ['nombre', 'cliente_endpoint_id', 'activo'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function tablero_sae()
    {
        return $this->hasMany(Tablero_Sae::class, 'id', 'id');
    }

    public function meta_unidades()
    {
        return $this->hasMany(MetaUnidades::class, 'clientes_id', 'id');
    }
}
