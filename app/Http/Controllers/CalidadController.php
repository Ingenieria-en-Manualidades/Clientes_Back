<?php

namespace App\Http\Controllers;

use App\Models\Calidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CalidadController extends Controller
{
    public function guardarCalidad(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'checklist_mes' => 'required|string',
                'checklist_calificacion' => 'required|integer',
                'inspeccion_mes' => 'required|string',
                'inspeccion_calificacion' => 'required|integer',
                'tablero_id' => 'required|integer'
            ]);

            // Guardar los datos en la base de datos
            $calidad = new Calidad();
            $calidad->checklist_mes = $validatedData['checklist_mes'];
            $calidad->checklist_calificacion = $validatedData['checklist_calificacion'];
            $calidad->inspeccion_mes = $validatedData['inspeccion_mes'];
            $calidad->inspeccion_calificacion = $validatedData['inspeccion_calificacion'];
            $calidad->tablero_id = $validatedData['tablero_id'];
            $calidad->save();

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
