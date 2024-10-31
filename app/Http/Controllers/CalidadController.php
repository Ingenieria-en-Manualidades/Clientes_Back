<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Calidad;
use App\Models\Tablero_Sae;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CalidadController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'fecha' => 'required|string',
                'cliente_endpoint_id' => 'required|integer',
                'checklist' => 'nullable|integer',
                'inspeccion' => 'nullable|integer',
            ]);

            // El dato "fecha" ingresado lo convertimos a un objeto "Date".
            $date = new DateTime($validatedData['fecha']);

            // Creamos un string en formato "yyyy-mm" con el objeto "Date" creado anteriormente, para las consultas.
            $mesMeta = $date->format('Y') .'-'. $date->format('m');

            // Consultamos a que tablero Sae va a relacionarse el objetivo, buscando por mes actual y por el cliente relacionado al usuario que ordeno la petición.
            $metaID = Tablero_Sae::select('tablero_sae.meta_id')
            ->join('clientes', 'clientes.id', '=','tablero_sae.cliente_id')
            ->where('tablero_sae.fecha','like', $mesMeta . '%')
            ->where('clientes.cliente_endpoint_id','=', $validatedData['cliente_endpoint_id'])
            ->get();

            Log::info("Consulta: ", ['metaID: ' => $metaID[0]->meta_id]);
            // Verificamos que haya una acción en caso de encontrar o no una meta id.
            if ($metaID->isEmpty()) {
                return response()->json(['message' => 'No existe una meta con esa fecha.','errors' => $request], 404);
            } else {
                $calidad = Calidad::select('calidad.*')
                ->where('calidad.meta_id', '=', $metaID[0]->meta_id)
                ->get();
                Log::info("Consulta mySQL: ", ['Calidad: ' => $calidad]);
                if ($calidad->isEmpty()) {
                    // Guardar los datos en la base de datos
                    $newCalidad = new Calidad();
                    $newCalidad->checklist =  $validatedData['checklist'] ?? null;
                    $newCalidad->inspeccion = $validatedData['inspeccion'] ?? null;
                    $newCalidad->meta_id = $metaID[0]->meta_id;
                    $newCalidad->save();
                } else {
                    if ($calidad[0]->checklist === null || $calidad[0]->inspeccion === null) {
                        if ($calidad[0]->checklist === null) {
                            Log::alert("CHECKLIST IS NULL...");
                        } else {
                            Log::alert("INSPECCION IS NULL...");
                        }
                    } else {
                        Log::alert("TU LO QUE QUIERES ES ACTUALIZAR TODO...");
                    }
                    Log::alert("ACTUALIZANDO...");
                }
            }

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true,'message' => 'Calidad creado con éxito', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de calidad',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar los objetivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
