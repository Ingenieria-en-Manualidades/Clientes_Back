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
        'checklist',
        'inspeccion',
        'meta_id'
    ];

    public function meta()
    {
        return $this->belongsTo(Meta::class, 'meta_id', 'meta_id');
    }
}
