<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scheduled_detail';

    protected $primaryKey = 'scheduled_detail_id';

    protected $fillable = [
        'year',
        'week_number',
        'week_start_date',
        'week_end_date',
        'notes',
        'username',
    ];

    public function weekly_scheduled_details()
    {
        return $this->hasMany(
            WeeklyScheduledDetail::class,
            'scheduled_detail_id',
            'scheduled_detail_id'
        );
    }
}
