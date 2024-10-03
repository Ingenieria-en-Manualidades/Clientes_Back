<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Objetive extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'objetives';
    protected $primaryKey = 'objetives_id';

    protected $fillable = [
        'cumplimiento',
        'eficiencia_productiva',
        'calidad',
        'desperdicio_me',
        'desperdicio_pp',
        'tablero_id',
    ];

    public function tablero_sae()
    {
        return $this->belongsTo(Tablero_Sae::class, 'tablero_id', 'tablero_id');
    }
}
