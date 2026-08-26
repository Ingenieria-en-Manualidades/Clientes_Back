<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyDetailedAssembly extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'weekly_detailed_assembly';

    protected $primaryKey = 'weekly_detailed_assembly_id';

    protected $fillable = [
        'client_id',
        'weekly_total',
        'notes',
        'sku',
        'product',
        'detailed_assembly_id',
        'username',
    ];

    public function detailed_assembly()
    {
        return $this->belongsTo(
            DetailedAssembly::class,
            'detailed_assembly_id',
            'detailed_assembly_id'
        );
    }
}
