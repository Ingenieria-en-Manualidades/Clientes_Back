<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class accidente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accidentes';
    protected $primaryKey = 'accidentes_id';

    protected $fillable = [
        'tipo_accidente',
        'cantidad',
        'objetivos_id',
    ];

    public function accidentes()
    {
        return $this->hasMany(Accidente::class, 'accidentes_id', 'accidentes_id');
    }

    public function objetivos()
    {
        return $this->belongsTo(Objetivo::class, 'objetivos_id', 'objetivos_id');
    }
}
