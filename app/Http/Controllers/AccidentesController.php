<?php

namespace App\Http\Controllers;

use App\Models\Accidente;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class accidentesController extends Controller
{
    public function create(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'tipo_accidente' => 'required|string',
                'cantidad' => 'required|integer',
                'objetivos_id' => 'required|integer',
            ]);

            // Guardar los datos en la base de datos
            $accidente = new Accidente();
            $accidente->tipo_accidente = $validatedData['tipo_accidente'];
            $accidente->cantidad = $validatedData['cantidad'];
            $accidente->objetivos_id = $validatedData['objetivos_id'];
            $accidente->save();

            // Devolver una respuesta exitosa en caso de no fallar
            return response()->json(['success' => true,'message' => 'Accidente creado con éxito', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de accidente',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar el accidente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
