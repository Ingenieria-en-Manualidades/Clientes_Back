<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Objetivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ObjetivoController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'planificada' => 'required|integer',
                'modificada' => 'nullable|integer',
                'plan_armado' => 'nullable|integer',
                'calidad' => 'nullable|integer',
                'desperfecto_me' => 'nullable|integer',
                'desperfecto_pp' => 'nullable|integer',
                'tablero_sae_id' => 'required|integer',
            ]);

            // Guardar los datos en la base de datos
            $objetivo = new Objetivo();
            $objetivo->planificada = $validatedData['planificada'] ?? null;
            $objetivo->modificada = $validatedData['modificada'] ?? null;
            $objetivo->plan_armado = $validatedData['plan_armado'] ?? null;
            $objetivo->calidad = $validatedData['calidad'] ?? null;
            $objetivo->desperfecto_me = $validatedData['desperfecto_me'] ?? null;
            $objetivo->desperfecto_pp = $validatedData['desperfecto_pp'] ?? null;
            $objetivo->tablero_sae_id = $validatedData['tablero_sae_id'] ?? null;
            $objetivo->save();

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true,'message' => 'Objetivo creado con éxito.', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de producción.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar los objetivos.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método para actualizar una inserción de 'Producción', debido a que ciertos campos no se llenan en el momento de la inserción sino hasta otro día, se creo este método para termine de llenar los campos faltantes.
     */
    public function update(Request $request) {
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'fecha' => 'required|string',
                'cliente_id' => 'required|integer',
                'planificada' => 'nullable|integer',
                'modificada' => 'nullable|integer',
                'plan_armado' => 'nullable|integer',
                'calidad' => 'nullable|integer',
                'desperfecto_me' => 'nullable|integer',
                'desperfecto_pp' => 'nullable|integer',
            ]);
            
            // Consultar la producción por fecha para tener la de ID de la producción que queremos actualizar 
            $resultadoMySql = DB::table('objetivos')
            ->select('objetivos.objetivos_id')
            ->join('tablero_sae', 'tablero_sae.tablero_sae_id', '=', 'objetivos.tablero_sae_id')
            ->join('clientes', 'clientes.id', '=', 'tablero_sae.cliente_id')
            ->where('objetivos.created_at', 'like', $validatedData['fecha'] . '%')
            ->where('clientes.cliente_endpoint_id', '=', $validatedData['cliente_id'])
            ->get();

            Log::info('Resultados en mysql', $resultadoMySql->toArray());
            //Revisamos si la consultar trajo un resultado
            if ($resultadoMySql->isEmpty()) {
                return response()->json(['message' => 'No existe una producción con esa fecha.','errors' => $request], 404);
            }else {
                // Obtenemos la producción que actualizaremos con el método "save" ya hecho
                $objetivo = Objetivo::findOrFail($resultadoMySql[0]->objetivos_id);
            }

            // En caso de que alguno de estos datos no exista se reemplazara por 'false'
            $planificada = $validatedData['planificada'] ?? false;
            $modificada = $validatedData['modificada'] ?? false;
            $indicadores = $validatedData['plan_armado'] ?? false;

            // Verifica que dato fue el que pidio el usuario para modificar, el primero que no sea 'false' sera el modificado
            if ($planificada) {
                $objetivo->planificada = $planificada;
            }else {
                if ($modificada) {
                    $objetivo->modificada = $modificada;
                }else {
                    if ($indicadores) {
                        $objetivo->plan_armado = $validatedData['plan_armado'];
                        $objetivo->calidad = $validatedData['calidad'];
                        $objetivo->desperfecto_me = $validatedData['desperfecto_me'];
                        $objetivo->desperfecto_pp = $validatedData['desperfecto_pp'];
                    }else {
                        // Retorna en caso de que no haya llegado un dato para modificar y asi no guarda nada
                        return response()->json(['success' => false, 'message' => 'Ningún cambio hecho'], 404);
                    }
                }
            }
            //Guarda los cambios que se hayan hecho en los if's
            $objetivo->save();

            return response()->json(['success' => true, 'message' => 'Actualización hecha con éxito'], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de producción.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al actualizar la producción.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
