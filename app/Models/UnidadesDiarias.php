<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadesDiarias extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'CLIENTS.unidades_diarias';
    protected $primaryKey = 'unidades_diarias_id';

    protected $fillable = [
        'valor',
        'fecha_programacion',
        'actualizaciones',
        'meta_unidades_id',
        'usuario',
        'activo',
    ];

    public function meta_unidades()
    {
        return $this->belongsTo(MetaUnidades::class, 'meta_unidades_id', 'meta_unidades_id');
    }
}
