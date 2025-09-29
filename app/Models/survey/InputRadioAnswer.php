<?php

namespace App\Models\survey;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InputRadioAnswer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'surveys.input_radio_answer';
    protected $primaryKey = 'input_radio_answer_id';

    protected $fillable = [
        'description_option',
        'value_option',
        'observation',
        'survey_has_question_id',
        'username',
        'active',
    ];

    public function survey_has_question()
    {
        return $this->belongsTo(SurveyHasQuestion::class, 'survey_has_question_id', 'survey_has_question_id');
    }
}
