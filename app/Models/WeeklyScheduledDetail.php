<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyScheduledDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'weekly_scheduled_detail';

    protected $primaryKey = 'weekly_scheduled_detail_id';

    protected $fillable = [
        'client_id',
        'weekly_total',
        'notes',
        'sku',
        'product',
        'scheduled_detail_id',
        'username',
    ];

    public function scheduled_detail()
    {
        return $this->belongsTo(
            ScheduledDetail::class,
            'scheduled_detail_id',
            'scheduled_detail_id'
        );
    }
}
