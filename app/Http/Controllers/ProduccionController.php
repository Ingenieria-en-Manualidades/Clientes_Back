<?php

namespace App\Http\Controllers;

use App\Models\Produccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProduccionController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'fecha_produccion' => 'required|string',
                'planificada' => 'required|integer',
                'modificada' => 'nullable|integer',
                'plan_armado' => 'nullable|integer',
                'calidad' => 'nullable|integer',
                'desperfecto_me' => 'nullable|integer',
                'desperfecto_pp' => 'nullable|integer',
                'tablero_id' => 'required|integer',
            ]);

            // Guardar los datos en la base de datos
            $produccion = new Produccion();
            $produccion->fecha_produccion = $validatedData['fecha_produccion'];
            $produccion->planificada = $validatedData['planificada'];
            $produccion->modificada = $validatedData['modificada'];
            $produccion->plan_armado = $validatedData['plan_armado'];
            $produccion->calidad = $validatedData['calidad'];
            $produccion->desperfecto_me = $validatedData['desperfecto_me'];
            $produccion->desperfecto_pp = $validatedData['desperfecto_pp'];
            $produccion->tablero_id = $validatedData['tablero_id'];
            $produccion->save();

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true,'message' => 'Producción creado con éxito.', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de producción.',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar la producción.',
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
                'fecha_produccion' => 'required|string',
                'planificada' => 'nullable|integer',
                'modificada' => 'nullable|integer',
                'plan_armado' => 'nullable|integer',
                'calidad' => 'nullable|integer',
                'desperfecto_me' => 'nullable|integer',
                'desperfecto_pp' => 'nullable|integer',
            ]);
            
            // Consultar la producción por fecha para tener la de ID de la producción que queremos actualizar 
            $resultadoMySql = DB::table('produccion')
            ->select('produccion.produccion_id')
            ->where('produccion.fecha_produccion', 'like', $validatedData['fecha_produccion'] . '%')
            ->get();

            //Revisamos si la consultar trajo un resultado
            if ($resultadoMySql->isEmpty()) {
                return response()->json(['message' => 'No existe una producción con esa fecha.','errors' => $request], 404);
            }else {
                // Obtenemos la producción que actualizaremos con el método "save" ya hecho
                $produccion = Produccion::findOrFail($resultadoMySql[0]->produccion_id);
            }

            // En caso de que alguno de estos datos no exista se reemplazara por 'false'
            $planificada = $validatedData['planificada'] ?? false;
            $modificada = $validatedData['modificada'] ?? false;
            $indicadores = $validatedData['plan_armado'] ?? false;

            // Verifica que dato fue el que pidio el usuario para modificar, el primero que no sea 'false' sera el modificado
            if ($planificada) {
                $produccion->planificada = $planificada;
            }else {
                if ($modificada) {
                    $produccion->modificada = $modificada;
                }else {
                    if ($indicadores) {
                        $produccion->plan_armado = $validatedData['plan_armado'];
                        $produccion->calidad = $validatedData['calidad'];
                        $produccion->desperfecto_me = $validatedData['desperfecto_me'];
                        $produccion->desperfecto_pp = $validatedData['desperfecto_pp'];
                    }else {
                        // Retorna en caso de que no haya llegado un dato para modificar y asi no guarda nada
                        return response()->json(['success' => false, 'message' => 'Ningún cambio hecho'], 404);
                    }
                }
            }
            //Guarda los cambios que se hayan hecho en los if's
            $produccion->save();

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
