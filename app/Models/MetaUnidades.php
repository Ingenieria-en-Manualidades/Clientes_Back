<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetaUnidades extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'meta_unidades';
    protected $primaryKey = 'meta_unidades_id';

    protected $fillable = [
        'valor',
        'fecha_meta',
        'actualizaciones',
        'clientes_id',
        'usuario',
        'activo',
    ];

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'clientes_id', 'id');
    }

    public function unidades_diarias()
    {
        return $this->hasMany(UnidadesDiarias::class, 'meta_unidades_id', 'meta_unidades_id');
    }
}
