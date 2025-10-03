<?php

namespace App\Models\survey;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.survey';
    protected $primaryKey = 'survey_id';

    protected $fillable = [
        'start_time',
        'fullname',
        'user_id',
        'charge_id',
        'clients_id',
        'username',
        'another_charge',
        'active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function charge()
    {
        return $this->belongsTo(Charge::class, 'charge_id', 'charge_id');
    }

    public function clients()
    {
        return $this->belongsTo(Clients::class, 'clients_id', 'clients_id');
    }

    public function type_operation_has_clients()
    {
        return $this->hasMany(TypeOperationHasClients::class, 'clients_id', 'clients_id');
    }

    public function survey_has_question()
    {
        return $this->hasMany(SurveyHasQuestion::class, 'survey_id', 'survey_id');
    }
}
