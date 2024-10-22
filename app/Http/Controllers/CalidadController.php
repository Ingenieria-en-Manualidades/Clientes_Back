<?php

namespace App\Http\Controllers;

use App\Models\Calidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CalidadController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'checklist' => 'required|integer',
                'inspeccion' => 'required|integer',
                'meta_id' => 'required|integer'
            ]);

            // Guardar los datos en la base de datos
            $calidad = new Calidad();
            $calidad->checklist = $validatedData['checklist'];
            $calidad->inspeccion = $validatedData['inspeccion'];
            $calidad->meta_id = $validatedData['meta_id'];
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
