<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetailedAssembly extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'detailed_assembly';

    protected $primaryKey = 'detailed_assembly_id';

    protected $fillable = [
        'year',
        'week_number',
        'week_start_date',
        'week_end_date',
        'notes',
        'username',
    ];

    public function weekly_detailed_assemblies()
    {
        return $this->hasMany(
            WeeklyDetailedAssembly::class,
            'detailed_assembly_id',
            'detailed_assembly_id'
        );
    }
}
