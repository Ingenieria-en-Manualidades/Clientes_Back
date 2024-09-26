<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Calidad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'calidad';
    protected $primaryKey = 'calidad_id';

    protected $fillable = [
        'checklist_mes',
        'checklist_calificacion',
        'inspeccion_mes',
        'inspeccion_calificacion',
        'tablero_id'
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_id', 'tablero_id')
    }
}
