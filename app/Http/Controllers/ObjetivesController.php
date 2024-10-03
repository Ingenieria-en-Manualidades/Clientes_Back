<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Objetive;
use Illuminate\Validation\ValidationException;

class ObjetivesController extends Controller
{
    public function guardarObjetivos(Request $request)
    {
        // Log::info('Solicitud POST recibida en guardarObjetivos', $request->all());
        
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'calidad' => 'required|string',
                'cumplimiento' => 'required|string',
                'desperdicioME' => 'required|string',
                'desperdicioPP' => 'required|string',
                'eficienciaProductiva' => 'required|string',
                'tablero_id' => 'required|integer'
            ]);

            // Guardar los datos en la base de datos
            $objetivo = new Objetive();
            $objetivo->cumplimiento = $validatedData['cumplimiento'];
            $objetivo->eficiencia_productiva = $validatedData['eficienciaProductiva'];
            $objetivo->calidad = $validatedData['calidad'];
            $objetivo->desperdicio_me = $validatedData['desperdicioME'];
            $objetivo->desperdicio_pp = $validatedData['desperdicioPP'];
            $objetivo->tablero_id = $validatedData['tablero_id'];
            $objetivo->save();
            // Devolver una respuesta exitosa
            return response()->json(['success' => true,'message' => 'Objetivo creado con éxito', 'data' => $request], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de objetivos',
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
