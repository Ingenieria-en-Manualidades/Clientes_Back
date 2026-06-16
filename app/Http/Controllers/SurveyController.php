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
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
{
    private function tableExists(string $qualifiedTable): bool
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return DB::selectOne('SELECT to_regclass(?) AS table_name', [$qualifiedTable])->table_name !== null;
        }

        if (DB::connection()->getDriverName() === 'sqlite' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)) {
                return false;
            }

            return DB::selectOne("SELECT name FROM {$schema}.sqlite_master WHERE type = 'table' AND name = ?", [$table]) !== null;
        }

        return DB::getSchemaBuilder()->hasTable($qualifiedTable);
    }

    private function firstExistingTable(array $qualifiedTables): ?string
    {
        foreach ($qualifiedTables as $qualifiedTable) {
            if ($this->tableExists($qualifiedTable)) {
                return $qualifiedTable;
            }
        }

        return null;
    }

    private function tableColumns(string $qualifiedTable): array
    {
        if (DB::connection()->getDriverName() === 'pgsql' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);

            return DB::table('information_schema.columns')
                ->where('table_schema', $schema)
                ->where('table_name', $table)
                ->pluck('column_name')
                ->all();
        }

        if (DB::connection()->getDriverName() === 'sqlite' && str_contains($qualifiedTable, '.')) {
            [$schema, $table] = explode('.', $qualifiedTable, 2);
            if (
                !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $schema)
                || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)
            ) {
                return [];
            }

            return collect(DB::select("PRAGMA {$schema}.table_info({$table})"))
                ->pluck('name')
                ->all();
        }

        return DB::getSchemaBuilder()->getColumnListing($qualifiedTable);
    }

    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function surveyClientTable(): ?string
    {
        return $this->firstExistingTable(['surveys.clients']);
    }

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
            $surveyTable = $this->surveyClientTable();
            if (!$surveyTable) {
                return response()->json([
                    'data' => [],
                    'title' => 'Surveys no disponible.',
                    'message' => 'No existe la tabla clients dentro del esquema surveys en esta base de datos.',
                ], 200);
            }

            $columns = $this->tableColumns($surveyTable);
            $nameColumn = $this->firstExistingColumn($columns, ['name', 'nombre']);
            $primaryKey = $this->firstExistingColumn($columns, ['clients_id', 'cliente_id', 'id']);

            $clients = DB::table($surveyTable)
                ->when($nameColumn, fn ($query) => $query->orderBy($nameColumn, 'asc'))
                ->get()
                ->map(function ($client) use ($nameColumn, $primaryKey) {
                    if ($nameColumn && !property_exists($client, 'name')) {
                        $client->name = $client->{$nameColumn};
                    }
                    if ($primaryKey && !property_exists($client, 'clients_id')) {
                        $client->clients_id = $client->{$primaryKey};
                    }

                    return $client;
                });

            return response()->json(['data' => $clients], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => 'Ha ocurrido un fallo con el servidor.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateSurveyClient(int $id, Request $request)
    {
        try {
            $token = $request->header(config('app.type_key_app_clients'));
            $expectedToken = config('app.api_key_app_clients');

            if ($token !== $expectedToken) {
                return response()->json(['title' => 'Token no valido.', 'message' => 'Error en la peticion al enviar el token incorrecto.'], 401);
            }

            $surveyTable = $this->surveyClientTable();
            if (!$surveyTable) {
                return response()->json([
                    'title' => 'Surveys no disponible.',
                    'message' => 'No existe la tabla clients dentro del esquema surveys en esta base de datos.',
                ], 409);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'feed_value' => 'nullable|numeric',
                'cost_center' => 'nullable|string|max:255',
                'overtime' => 'nullable|date_format:H:i:s',
                'city_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['title' => 'Error de validacion.', 'message' => $validator->errors(), 'error' => $validator->errors()], 422);
            }

            $columns = $this->tableColumns($surveyTable);
            $primaryKey = $this->firstExistingColumn($columns, ['clients_id', 'cliente_id', 'id']);
            $nameColumn = $this->firstExistingColumn($columns, ['name', 'nombre']);

            if (!$primaryKey || !$nameColumn) {
                return response()->json([
                    'title' => 'Surveys no compatible.',
                    'message' => "La tabla {$surveyTable} no tiene columnas de id/nombre compatibles.",
                ], 409);
            }

            $data = [$nameColumn => $request->name];
            foreach (['feed_value', 'cost_center', 'overtime', 'city_id'] as $column) {
                if (in_array($column, $columns, true)) {
                    $data[$column] = $request->{$column};
                }
            }
            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = now();
            }

            $clientExists = DB::table($surveyTable)->where($primaryKey, $id)->exists();
            if (!$clientExists) {
                return response()->json(['title' => 'Cliente no encontrado.', 'message' => 'Cliente de encuestas no encontrado.'], 404);
            }
            DB::table($surveyTable)->where($primaryKey, $id)->update($data);

            return response()->json(['title' => 'Exito.', 'message' => 'Cliente de encuestas actualizado exitosamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function getUsersBySurveyClientId(int $id)
    {
        try {
            $surveyTable = $this->surveyClientTable();
            if (!$surveyTable) {
                return response()->json([
                    'title' => 'Surveys no disponible.',
                    'message' => 'No existe la tabla clients dentro del esquema surveys en esta base de datos.',
                ], 409);
            }

            $columns = $this->tableColumns($surveyTable);
            $primaryKey = $this->firstExistingColumn($columns, ['clients_id', 'cliente_id', 'id']);
            if (!$primaryKey) {
                return response()->json([
                    'title' => 'Surveys no compatible.',
                    'message' => "La tabla {$surveyTable} no tiene columna id compatible.",
                ], 409);
            }

            $surveyClient = DB::table($surveyTable)->where($primaryKey, $id)->first();
            if (!$surveyClient) {
                return response()->json(['title' => 'Cliente no encontrado.', 'message' => 'Cliente de encuestas no encontrado.'], 404);
            }
            $hasSurveyContacts = $this->tableExists('surveys.customer_contact');

            $users = DB::table('clientes as c')
                ->join('cliente_user as cu', 'cu.cliente_id', '=', 'c.id')
                ->join('users as u', 'u.id', '=', 'cu.user_id')
                ->leftJoin('public.empleado as e', 'e.empleado_id', '=', 'u.empleado_id');

            if ($hasSurveyContacts) {
                $users->leftJoin('surveys.customer_contact as cc', function ($join) {
                    $join->on('cc.user_id', '=', 'u.id')
                        ->whereNull('cc.deleted_at');
                });
            }

            $selects = [
                    'u.id',
                    'u.name as username',
                    'u.email',
                    'u.activo',
                    'u.deleted_at',
                    DB::raw("TRIM(CONCAT(COALESCE(e.nombre, ''), ' ', COALESCE(e.apellido, ''))) as fullname"),
            ];

            $selects[] = $hasSurveyContacts ? 'cc.cellphone' : DB::raw('NULL as cellphone');
            $selects[] = $hasSurveyContacts ? 'cc.fullname as contact_fullname' : DB::raw('NULL as contact_fullname');

            $users = $users->select($selects)
                ->where('c.cliente_endpoint_id', $surveyClient->{$primaryKey})
                ->whereNull('cu.deleted_at')
                ->orderBy('u.name', 'asc')
                ->get();

            if ($users->isEmpty()) {
                return response()->json(['title' => 'Usuarios no encontrados.', 'message' => 'Este cliente de encuestas no tiene usuarios asociados.'], 404);
            }

            return response()->json(['data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
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

            $validatedData['username'] = mb_strtoupper(trim($validatedData['username']));

            $user = DB::table('users')->select('users.id')->where('users.name', $validatedData['username'])->whereNull('users.deleted_at')->first();
            if (!$user) {
                return response()->json(['title' => 'Error de usuario.','message' => 'Usuario no encontrado.'], 404);
            }

            $customerContact = CustomerContact::where('user_id', $user->id)->first();
            if (!$customerContact) {
                return response()->json(['title' => 'Error del contacto.','message' => 'Contacto del cliente no encontrado.'], 404);
            }

            DB::transaction(function () use ($validatedData, $user, &$survey, $customerContact) {
                $survey = new Survey();
                $survey->start_time = $validatedData['start_time'];
                $survey->fullname = $validatedData['fullname'];
                $survey->user_id = $user->id;
                $survey->charge_id = $validatedData['charge_id'];
                $survey->clients_id = $validatedData['clients_id'];
                $survey->username = $validatedData['username'];
                $survey->another_charge = $validatedData['another_charge'] ?? null;
                $survey->save();

                // Save the traceability of the surveys, making sure to save it in the 'customer_contact_has_survey' table.
                $year = new DateTime($validatedData['start_time']);
                $traceabilitySurvey = CustomerContactHasSurvey::where('year', 'like', $year->format('Y') . '%')->where('customer_contact_id',$customerContact->customer_contact_id)
                ->orderBy('customer_contact_has_survey_id', 'desc')->first();

                $contactHasSurvey = new CustomerContactHasSurvey();
                $contactHasSurvey->customer_contact_id = $customerContact->customer_contact_id;
                $contactHasSurvey->survey_id = $survey->survey_id;
                $contactHasSurvey->year = $year->format('Y-m-d');
                if ($traceabilitySurvey) {
                    $contactHasSurvey->version = $traceabilitySurvey->version + 1;
                }
                $contactHasSurvey->username = $validatedData['username'];
                $contactHasSurvey->save();

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
        } catch (\Throwable $e) {
            return response()->json(['title' => 'Error con el servidor.', 'message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function getInformationUser($username)
    {
        try {
            $username = mb_strtoupper(trim($username));
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
