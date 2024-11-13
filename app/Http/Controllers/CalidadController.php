<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\Calidad;
use App\Models\Tablero_Sae;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CalidadController extends Controller
{
    /**
     * Método de guardado de calidad en la cual buscamos la meta_id a la cual pertenece la calida y 
     * verificamos que no haya valores ya ingresados en la inserción para no tener que guardar.
     */
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
            $consTableroSae = Tablero_Sae::select('tablero_sae.*')
            ->join('clientes', 'clientes.id', '=','tablero_sae.cliente_id')
            ->where('tablero_sae.fecha','like', $mesMeta . '%')
            ->where('clientes.cliente_endpoint_id','=', $validatedData['cliente_endpoint_id'])
            ->get();

            // Verificamos que haya una acción en caso de encontrar o no una meta id.
            if ($consTableroSae->isEmpty()) {
                return response()->json(['message' => 'No existe una meta con esa fecha.','errors' => $request], 404);
            } else {
                // Guardamos los datos entrantes en variables para que en caso de no estar estas serán "null".
                $checklist = $validatedData['checklist'] ?? null;
                $inspeccion = $validatedData['inspeccion'] ?? null;

                // Buscamos una inserción de calidad que tenga la meta id encontrada.
                $calidadConsulta = Calidad::select('calidad.*')
                ->where('calidad.meta_id', '=', $consTableroSae[0]->meta_id)
                ->get();
                
                // En caso de no haber una calidad sin esa meta_id creamos esa calidad.
                if ($calidadConsulta->isEmpty()) {
                    // Guardar los datos en la base de datos
                    $calidad = new Calidad();
                    $calidad->checklist =  $checklist;
                    $calidad->inspeccion = $inspeccion;
                    $calidad->meta_id = $consTableroSae[0]->meta_id;
                    $calidad->save();
                } else {
                    // En caso de haber una calidad con esa meta_id significa que quiere ingresar el "checklist" o la "inspección" que le falta a la calidad

                    // Creamos un objeto "Calidad" para actualizar la inserción.
                    $calidadUpdate = Calidad::findOrFail($calidadConsulta[0]->calidad_id);

                    // Verificamos que dato quiere ingresar el usuario utilizando las variables que podrian ser null en caso de no estar ingresadas.
                    if ($checklist) {

                        // Verificamos que la inserción no tenga valor para poder realizar el guardado.
                        if ($calidadConsulta[0]->checklist === null) {
                            $calidadUpdate->checklist = $checklist;
                            $calidadUpdate->save();
                        } else {
                            // En caso de haber ya haber un valor enviamos un mensaje avisandolo.
                            return response()->json(['message' => 'Ya existe un valor checklist guardada en esta meta.','errors' => $request], 409);
                        }
                    }else {

                        // Verificamos que la inserción no tenga valor para poder realizar el guardado.
                        if ($calidadConsulta[0]->inspeccion === null) {
                            $calidadUpdate->inspeccion = $inspeccion;
                            $calidadUpdate->save();
                        } else {
                            // En caso de haber ya haber un valor enviamos un mensaje avisandolo.
                            return response()->json(['message' => 'Ya existe un valor de inspección guardada en esta meta.','errors' => $request], 409);
                        }
                    }
                }
            }

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true, 'tablero_sae_id' => $consTableroSae[0]->tablero_sae_id], 200);
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
