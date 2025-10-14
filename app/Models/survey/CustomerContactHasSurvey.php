<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerContactHasSurvey extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.customer_contact_has_survey';
    protected $primaryKey = 'customer_contact_has_survey_id';

    protected $fillable = [
        'customer_contact_id',
        'survey_id',
        'year',
        'version',
        'username',
        'active',
    ];

    public function customer_contact()
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id', 'customer_contact_id');
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }
}
