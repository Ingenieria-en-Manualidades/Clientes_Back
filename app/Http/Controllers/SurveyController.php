<?php

namespace App\Http\Controllers;

use DateTime;
use Illuminate\Http\Request;
use App\Models\survey\Survey;
use App\Models\survey\SimpleAnswer;
use App\Models\survey\BooleanAnswer;
use App\Models\survey\InputRadioAnswer;
use App\Models\survey\SurveyHasQuestion;
use App\Models\survey\Charge;
use App\Models\survey\Clients;
use App\Models\survey\CustomerContact;
use App\Models\survey\CustomerContactHasSurvey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\Mail;
use App\Mail\ThankYouSurveyMail;

class SurveyController extends Controller
{
    /**
     * List all charges from the database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListCharges()
    {
        try {
            $charges = Charge::orderBy('description', 'asc')->get();
            return response()->json(['data' => $charges], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * List all clients from the database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListClients()
    {
        try {
            $clients = Clients::orderBy('name', 'asc')->get();
            return response()->json(['data' => $clients], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function setSaveSurvey(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'start_time' => 'required|Date',
                'fullname' => 'required|string',
                'charge_id' => 'required|integer',
                'clients_id' => 'required|integer',
                'username' => 'required|string',
                'another_charge' => 'nullable|string',
                'answers' => 'required|array',
            ]);

            $user = DB::table('users')->select('users.id')->where('users.name', $validatedData['username'])->whereNull('users.deleted_at')->first();
            if (!$user) {
                return response()->json(['title' => 'Error de usuario.','message' => 'Usuario no encontrado.'], 404);
            }

            DB::transaction(function () use ($validatedData, $user, &$survey) {
                $survey = new Survey();
                $survey->start_time = $validatedData['start_time'];
                $survey->fullname = $validatedData['fullname'];
                $survey->user_id = $user->id;
                $survey->charge_id = $validatedData['charge_id'];
                $survey->clients_id = $validatedData['clients_id'];
                $survey->username = $validatedData['username'];
                $survey->another_charge = $validatedData['another_charge'] ?? null;
                $survey->save();

                foreach ($validatedData['answers'] as $answer) {
                    $surveyHasQuestion = new SurveyHasQuestion();
                    $surveyHasQuestion->survey_id = $survey->survey_id;
                    $surveyHasQuestion->question_id = (int) $answer['question_id'];
                    $surveyHasQuestion->username = $validatedData['username'];
                    $surveyHasQuestion->save();

                    $typeQuestion = $answer['type'];

                    switch ($typeQuestion) {
                        case 'simple_answer':
                            $simpleAnswer = new SimpleAnswer();
                            $simpleAnswer->description = $answer['answer'];
                            $simpleAnswer->survey_has_question_id = $surveyHasQuestion->survey_has_question_id;
                            $simpleAnswer->username = $validatedData['username'];
                            $simpleAnswer->save();
                            break;

                        case 'input_radio_answer':
                            $inputRadioAnswer = new InputRadioAnswer();
                            $inputRadioAnswer->value_option = $answer['answer'];
                            $inputRadioAnswer->observation = $answer['observation'] ?? null;
                            $inputRadioAnswer->survey_has_question_id = $surveyHasQuestion->survey_has_question_id;
                            $inputRadioAnswer->username = $validatedData['username'];
                            $inputRadioAnswer->save();
                            break;

                        case 'boolean_answer':
                            $booleanAnswer = new BooleanAnswer();
                            $booleanAnswer->answer = $answer['answer'];
                            $booleanAnswer->observation = $answer['observation'] ?? null;
                            $booleanAnswer->survey_has_question_id = $surveyHasQuestion->survey_has_question_id;
                            $booleanAnswer->username = $validatedData['username'];
                            $booleanAnswer->save();
                            break;

                        default:
                            return response()->json(['title' => 'Error al enviar.', 'message' => 'Error a la hora de enviar las respuestas.'], 422);
                            break;
                    }
                }
            });

            try {
                $contact = CustomerContact::where('user_id', $user->id)->first();
                $toEmail = $contact->email;
                $toName = $contact->fullname ;

                if($toEmail){
                    Mail::to($toEmail)->queue(new ThankYouSurveyMail($toName));
                }
            } catch (\Throwable $mailEx) {
                Log::warning('Encuesta guardada pero falló envío de gracias', [
                    'user_id' => $user->id,
                    'error'   => $mailEx->getMessage(),
                ]);
            }

            return response()->json(['title' => 'Exito.', 'message' => 'Encuesta enviada.'], 200);
        } catch (ValidationException $e) {
            return response()->json(['title' => 'Error de validación.', 'message' => 'Error en la encuesta enviada.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getInformationUser($username)
    {
        try {
            $user = DB::table('users')->where('users.name', $username)->whereNull('users.deleted_at')->first();
            if (!$user) {
                return response()->json(['title' => 'Error de usuario.', 'message' => 'Usuario no encontrado.'], 404);
            }

            $customerContact = CustomerContact::where('user_id', $user->id)->first();
            if (!$customerContact) {
                return response()->json(['title' => 'Error del contacto.', 'message' => 'Contacto del cliente no encontrado.'], 404);
            }

            $today = new DateTime();
            $existingSurvey = Survey::where('start_time', 'like', $today->format('Y') . '%')->where('user_id', $user->id)->first();

            if ($existingSurvey) {
                $questionsAnsweredByUser = SurveyHasQuestion::where('survey_id', $existingSurvey->survey_id)->get();
                // $questionIds = $questionsAnsweredByUser->pluck('survey_has_question_id');

                $answersSimples = SimpleAnswer::select('simple_answer_id', 'description', 'shq.question_id')
                    ->join('survey_has_question as shq', 'shq.survey_has_question_id', '=', 'simple_answer.survey_has_question_id')
                    ->whereIn('simple_answer.survey_has_question_id', $questionsAnsweredByUser->pluck('survey_has_question_id'))->get();

                $answersBooleans = BooleanAnswer::select('boolean_answer_id', 'answer', 'observation', 'shq.question_id')
                    ->join('survey_has_question as shq', 'shq.survey_has_question_id', '=', 'boolean_answer.survey_has_question_id')
                    ->whereIn('boolean_answer.survey_has_question_id', $questionsAnsweredByUser->pluck('survey_has_question_id'))->get();

                $answersInputRadio = InputRadioAnswer::select('input_radio_answer_id', 'value_option', 'observation', 'shq.question_id')
                    ->join('survey_has_question as shq', 'shq.survey_has_question_id', '=', 'input_radio_answer.survey_has_question_id')
                    ->whereIn('input_radio_answer.survey_has_question_id', $questionsAnsweredByUser->pluck('survey_has_question_id'))->get();

                $surveyComplete = (object) [
                    'survey' => $existingSurvey,
                    'answersSimples' => $answersSimples,
                    'answersBooleans' => $answersBooleans,
                    'answersInputRadio' => $answersInputRadio,
                ];
            }

            return response()->json(['customer' => $customerContact, 'survey' => $surveyComplete ?? null], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }
}
