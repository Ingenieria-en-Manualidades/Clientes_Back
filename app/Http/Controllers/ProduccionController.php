<?php

namespace App\Http\Controllers;

use App\Models\Produccion;
use Illuminate\Http\Request;

class ProduccionController extends Controller
{
    public function guardarProduccion(Request $request){
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
}
