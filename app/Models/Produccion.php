<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produccion';
    protected $primaryKey = 'produccion_id';

    protected $fillable = [
        'fecha_produccion',
        'planificada',
        'modificada',
        'plan_armado',
        'calidad',
        'desperfecto_me',
        'desperfecto_pp',
        'tablero_id',
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_id', 'tablero_id');
    }
}
